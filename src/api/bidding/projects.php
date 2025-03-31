<?php
session_start();
require_once __DIR__ . '/../../../config/db_connect.php';
require_once __DIR__ . '/../../../utils/auth_utils.php';

// Get the request method and data
$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents("php://input"), true);

// Check if user is authenticated
if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Handle different project endpoints based on HTTP method
switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            getProjectById($_GET['id']);
        } else {
            getProjects();
        }
        break;
    case 'POST':
        createProject($data);
        break;
    case 'PUT':
        if (isset($_GET['id'])) {
            updateProject($_GET['id'], $data);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Project ID is required']);
        }
        break;
    case 'DELETE':
        if (isset($_GET['id'])) {
            deleteProject($_GET['id']);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Project ID is required']);
        }
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}

/**
 * Get all projects or filter by retailer/status
 * 
 * @return void
 */
function getProjects() {
    global $pdo;
    
    $userId = $_SESSION['user_id'];
    $role = $_SESSION['role'];
    $query = "SELECT p.*, u.name as retailer_name FROM projects p 
              JOIN users u ON p.retailer_id = u.id";
    $params = [];
    
    // Apply filters
    $whereClause = [];
    
    // Filter by status if provided
    if (isset($_GET['status']) && in_array($_GET['status'], ['open', 'closed'])) {
        $whereClause[] = "p.status = ?";
        $params[] = $_GET['status'];
    }
    
    // For retailers, only show their own projects
    if ($role === 'retailer') {
        $whereClause[] = "p.retailer_id = ?";
        $params[] = $userId;
    }
    
    // Add WHERE clause if any filters were applied
    if (!empty($whereClause)) {
        $query .= " WHERE " . implode(" AND ", $whereClause);
    }
    
    // Add ordering
    $query .= " ORDER BY p.created_at DESC";
    
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // For each project, get the number of bids
        foreach ($projects as &$project) {
            $bidStmt = $pdo->prepare("SELECT COUNT(*) FROM bids WHERE project_id = ?");
            $bidStmt->execute([$project['id']]);
            $project['bid_count'] = $bidStmt->fetchColumn();
        }
        
        echo json_encode([
            'success' => true,
            'projects' => $projects
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to retrieve projects: ' . $e->getMessage()]);
    }
}

/**
 * Get a specific project by ID
 * 
 * @param int $id Project ID
 * @return void
 */
function getProjectById($id) {
    global $pdo;
    
    $userId = $_SESSION['user_id'];
    $role = $_SESSION['role'];
    
    try {
        // Get project details
        $stmt = $pdo->prepare("SELECT p.*, u.name as retailer_name, u.email as retailer_email 
                              FROM projects p 
                              JOIN users u ON p.retailer_id = u.id 
                              WHERE p.id = ?");
        $stmt->execute([$id]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$project) {
            http_response_code(404);
            echo json_encode(['error' => 'Project not found']);
            return;
        }
        
        // Check if user is authorized to view this project
        if ($role === 'retailer' && $project['retailer_id'] != $userId) {
            http_response_code(403);
            echo json_encode(['error' => 'You are not authorized to view this project']);
            return;
        }
        
        // Get bids for this project
        $bidsQuery = "SELECT b.*, u.name as farmer_name, u.email as farmer_email 
                     FROM bids b 
                     JOIN users u ON b.farmer_id = u.id 
                     WHERE b.project_id = ?";
        
        // For farmers, only show their own bids
        if ($role === 'farmer') {
            $bidsQuery .= " AND b.farmer_id = ?";
            $bidStmt = $pdo->prepare($bidsQuery);
            $bidStmt->execute([$id, $userId]);
        } else {
            $bidStmt = $pdo->prepare($bidsQuery);
            $bidStmt->execute([$id]);
        }
        
        $bids = $bidStmt->fetchAll(PDO::FETCH_ASSOC);
        $project['bids'] = $bids;
        
        echo json_encode([
            'success' => true,
            'project' => $project
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to retrieve project: ' . $e->getMessage()]);
    }
}

/**
 * Create a new project
 * 
 * @param array $data Project data
 * @return void
 */
function createProject($data) {
    global $pdo;
    
    $userId = $_SESSION['user_id'];
    $role = $_SESSION['role'];
    
    // Only retailers can create projects
    if ($role !== 'retailer') {
        http_response_code(403);
        echo json_encode(['error' => 'Only retailers can create projects']);
        return;
    }
    
    // Validate required fields
    if (!isset($data['title']) || !isset($data['description'])) {
        http_response_code(422);
        echo json_encode(['error' => 'Title and description are required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO projects (retailer_id, title, description, deadline, status, created_at) 
                              VALUES (?, ?, ?, ?, 'open', NOW())");
        $stmt->execute([
            $userId,
            $data['title'],
            $data['description'],
            isset($data['deadline']) ? $data['deadline'] : null
        ]);
        
        $projectId = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'message' => 'Project created successfully',
            'project_id' => $projectId
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create project: ' . $e->getMessage()]);
    }
}

/**
 * Update an existing project
 * 
 * @param int $id Project ID
 * @param array $data Updated project data
 * @return void
 */
function updateProject($id, $data) {
    global $pdo;
    
    $userId = $_SESSION['user_id'];
    $role = $_SESSION['role'];
    
    // Check if project exists and belongs to the user
    $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
    $stmt->execute([$id]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$project) {
        http_response_code(404);
        echo json_encode(['error' => 'Project not found']);
        return;
    }
    
    // Only the project owner (retailer) can update it
    if ($role !== 'retailer' || $project['retailer_id'] != $userId) {
        http_response_code(403);
        echo json_encode(['error' => 'You are not authorized to update this project']);
        return;
    }
    
    // Build update query based on provided fields
    $updateFields = [];
    $params = [];
    
    if (isset($data['title'])) {
        $updateFields[] = "title = ?";
        $params[] = $data['title'];
    }
    
    if (isset($data['description'])) {
        $updateFields[] = "description = ?";
        $params[] = $data['description'];
    }
    
    if (isset($data['deadline'])) {
        $updateFields[] = "deadline = ?";
        $params[] = $data['deadline'];
    }
    
    if (isset($data['status']) && in_array($data['status'], ['open', 'closed'])) {
        $updateFields[] = "status = ?";
        $params[] = $data['status'];
    }
    
    if (empty($updateFields)) {
        http_response_code(422);
        echo json_encode(['error' => 'No fields to update']);
        return;
    }
    
    try {
        $query = "UPDATE projects SET " . implode(", ", $updateFields) . " WHERE id = ?";
        $params[] = $id;
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        
        echo json_encode([
            'success' => true,
            'message' => 'Project updated successfully'
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update project: ' . $e->getMessage()]);
    }
}

/**
 * Delete a project
 * 
 * @param int $id Project ID
 * @return void
 */
function deleteProject($id) {
    global $pdo;
    
    $userId = $_SESSION['user_id'];
    $role = $_SESSION['role'];
    
    // Check if project exists and belongs to the user
    $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
    $stmt->execute([$id]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$project) {
        http_response_code(404);
        echo json_encode(['error' => 'Project not found']);
        return;
    }
    
    // Only the project owner (retailer) can delete it
    if ($role !== 'retailer' || $project['retailer_id'] != $userId) {
        http_response_code(403);
        echo json_encode(['error' => 'You are not authorized to delete this project']);
        return;
    }
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Delete associated bids first (to maintain referential integrity)
        $stmt = $pdo->prepare("DELETE FROM bids WHERE project_id = ?");
        $stmt->execute([$id]);
        
        // Delete associated messages
        $stmt = $pdo->prepare("DELETE FROM messages WHERE bid_id IN (SELECT id FROM bids WHERE project_id = ?)");
        $stmt->execute([$id]);
        
        // Delete the project
        $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ?");
        $stmt->execute([$id]);
        
        // Commit transaction
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Project deleted successfully'
        ]);
    } catch (PDOException $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete project: ' . $e->getMessage()]);
    }
}
