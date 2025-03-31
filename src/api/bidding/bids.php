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

// Handle different bid endpoints based on HTTP method
switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            getBidById($_GET['id']);
        } elseif (isset($_GET['project_id'])) {
            getBidsByProject($_GET['project_id']);
        } else {
            getUserBids();
        }
        break;
    case 'POST':
        createBid($data);
        break;
    case 'PUT':
        if (isset($_GET['id'])) {
            updateBid($_GET['id'], $data);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Bid ID is required']);
        }
        break;
    case 'DELETE':
        if (isset($_GET['id'])) {
            deleteBid($_GET['id']);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Bid ID is required']);
        }
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}

/**
 * Get all bids for the current user
 * 
 * @return void
 */
function getUserBids() {
    global $pdo;
    
    $userId = $_SESSION['user_id'];
    $role = $_SESSION['role'];
    
    try {
        if ($role === 'farmer') {
            // Farmers see their own bids
            $stmt = $pdo->prepare("SELECT b.*, p.title as project_title, u.name as retailer_name 
                                  FROM bids b 
                                  JOIN projects p ON b.project_id = p.id 
                                  JOIN users u ON p.retailer_id = u.id 
                                  WHERE b.farmer_id = ? 
                                  ORDER BY b.created_at DESC");
            $stmt->execute([$userId]);
        } elseif ($role === 'retailer') {
            // Retailers see bids on their projects
            $stmt = $pdo->prepare("SELECT b.*, p.title as project_title, u.name as farmer_name 
                                  FROM bids b 
                                  JOIN projects p ON b.project_id = p.id 
                                  JOIN users u ON b.farmer_id = u.id 
                                  WHERE p.retailer_id = ? 
                                  ORDER BY b.created_at DESC");
            $stmt->execute([$userId]);
        } else {
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized access']);
            return;
        }
        
        $bids = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'bids' => $bids
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to retrieve bids: ' . $e->getMessage()]);
    }
}

/**
 * Get bids for a specific project
 * 
 * @param int $projectId Project ID
 * @return void
 */
function getBidsByProject($projectId) {
    global $pdo;
    
    $userId = $_SESSION['user_id'];
    $role = $_SESSION['role'];
    
    try {
        // First check if the project exists
        $projectStmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
        $projectStmt->execute([$projectId]);
        $project = $projectStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$project) {
            http_response_code(404);
            echo json_encode(['error' => 'Project not found']);
            return;
        }
        
        // Check authorization
        if ($role === 'retailer' && $project['retailer_id'] != $userId) {
            http_response_code(403);
            echo json_encode(['error' => 'You are not authorized to view bids for this project']);
            return;
        }
        
        // Get bids
        $query = "SELECT b.*, u.name as farmer_name, u.email as farmer_email 
                 FROM bids b 
                 JOIN users u ON b.farmer_id = u.id 
                 WHERE b.project_id = ?";
        
        // For farmers, only show their own bids
        if ($role === 'farmer') {
            $query .= " AND b.farmer_id = ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$projectId, $userId]);
        } else {
            $stmt = $pdo->prepare($query);
            $stmt->execute([$projectId]);
        }
        
        $bids = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'project' => $project,
            'bids' => $bids
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to retrieve bids: ' . $e->getMessage()]);
    }
}

/**
 * Get a specific bid by ID
 * 
 * @param int $id Bid ID
 * @return void
 */
function getBidById($id) {
    global $pdo;
    
    $userId = $_SESSION['user_id'];
    $role = $_SESSION['role'];
    
    try {
        // Get bid with related information
        $stmt = $pdo->prepare("SELECT b.*, p.title as project_title, p.description as project_description, 
                              p.retailer_id, f.name as farmer_name, f.email as farmer_email, 
                              r.name as retailer_name, r.email as retailer_email 
                              FROM bids b 
                              JOIN projects p ON b.project_id = p.id 
                              JOIN users f ON b.farmer_id = f.id 
                              JOIN users r ON p.retailer_id = r.id 
                              WHERE b.id = ?");
        $stmt->execute([$id]);
        $bid = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$bid) {
            http_response_code(404);
            echo json_encode(['error' => 'Bid not found']);
            return;
        }
        
        // Check authorization (only the farmer who placed the bid or the retailer who owns the project can view it)
        if (($role === 'farmer' && $bid['farmer_id'] != $userId) || 
            ($role === 'retailer' && $bid['retailer_id'] != $userId)) {
            http_response_code(403);
            echo json_encode(['error' => 'You are not authorized to view this bid']);
            return;
        }
        
        // Get messages related to this bid
        $msgStmt = $pdo->prepare("SELECT m.*, 
                                 CASE WHEN m.sender_id = ? THEN 'self' ELSE 'other' END as sender_type,
                                 u.name as sender_name 
                                 FROM messages m 
                                 JOIN users u ON m.sender_id = u.id 
                                 WHERE m.bid_id = ? 
                                 ORDER BY m.created_at ASC");
        $msgStmt->execute([$userId, $id]);
        $messages = $msgStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $bid['messages'] = $messages;
        
        echo json_encode([
            'success' => true,
            'bid' => $bid
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to retrieve bid: ' . $e->getMessage()]);
    }
}

/**
 * Create a new bid
 * 
 * @param array $data Bid data
 * @return void
 */
function createBid($data) {
    global $pdo;
    
    $userId = $_SESSION['user_id'];
    $role = $_SESSION['role'];
    
    // Only farmers can create bids
    if ($role !== 'farmer') {
        http_response_code(403);
        echo json_encode(['error' => 'Only farmers can create bids']);
        return;
    }
    
    // Validate required fields
    if (!isset($data['project_id']) || !isset($data['amount']) || !isset($data['proposal'])) {
        http_response_code(422);
        echo json_encode(['error' => 'Project ID, amount, and proposal are required']);
        return;
    }
    
    try {
        // Check if project exists and is open
        $projectStmt = $pdo->prepare("SELECT * FROM projects WHERE id = ? AND status = 'open'");
        $projectStmt->execute([$data['project_id']]);
        $project = $projectStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$project) {
            http_response_code(404);
            echo json_encode(['error' => 'Project not found or closed for bidding']);
            return;
        }
        
        // Check if farmer already has a bid on this project
        $existingBidStmt = $pdo->prepare("SELECT * FROM bids WHERE project_id = ? AND farmer_id = ?");
        $existingBidStmt->execute([$data['project_id'], $userId]);
        if ($existingBidStmt->fetch()) {
            http_response_code(409);
            echo json_encode(['error' => 'You already have a bid on this project. Please update your existing bid.']);
            return;
        }
        
        // Create the bid
        $stmt = $pdo->prepare("INSERT INTO bids (project_id, farmer_id, amount, proposal, status, created_at) 
                              VALUES (?, ?, ?, ?, 'pending', NOW())");
        $stmt->execute([
            $data['project_id'],
            $userId,
            $data['amount'],
            $data['proposal']
        ]);
        
        $bidId = $pdo->lastInsertId();
        
        // If initial message is provided, add it
        if (isset($data['message']) && !empty($data['message'])) {
            $msgStmt = $pdo->prepare("INSERT INTO messages (bid_id, sender_id, receiver_id, message, created_at) 
                                     VALUES (?, ?, ?, ?, NOW())");
            $msgStmt->execute([
                $bidId,
                $userId,
                $project['retailer_id'],
                $data['message']
            ]);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Bid created successfully',
            'bid_id' => $bidId
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create bid: ' . $e->getMessage()]);
    }
}

/**
 * Update an existing bid
 * 
 * @param int $id Bid ID
 * @param array $data Updated bid data
 * @return void
 */
function updateBid($id, $data) {
    global $pdo;
    
    $userId = $_SESSION['user_id'];
    $role = $_SESSION['role'];
    
    try {
        // Get the bid
        $stmt = $pdo->prepare("SELECT b.*, p.retailer_id FROM bids b JOIN projects p ON b.project_id = p.id WHERE b.id = ?");
        $stmt->execute([$id]);
        $bid = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$bid) {
            http_response_code(404);
            echo json_encode(['error' => 'Bid not found']);
            return;
        }
        
        // Check authorization and update logic based on role
        if ($role === 'farmer') {
            // Farmers can update their own bids if they're still pending
            if ($bid['farmer_id'] != $userId) {
                http_response_code(403);
                echo json_encode(['error' => 'You are not authorized to update this bid']);
                return;
            }
            
            if ($bid['status'] !== 'pending') {
                http_response_code(422);
                echo json_encode(['error' => 'Cannot update bid that has been accepted or rejected']);
                return;
            }
            
            // Build update query for farmer (can update price and terms)
            $updateFields = [];
            $params = [];
            
            if (isset($data['amount'])) {
                $updateFields[] = "amount = ?";
                $params[] = $data['amount'];
            }
            
            if (isset($data['proposal'])) {
                $updateFields[] = "proposal = ?";
                $params[] = $data['proposal'];
            }
            
            if (empty($updateFields)) {
                http_response_code(422);
                echo json_encode(['error' => 'No fields to update']);
                return;
            }
            
            $query = "UPDATE bids SET " . implode(", ", $updateFields) . " WHERE id = ?";
            $params[] = $id;
            
            $updateStmt = $pdo->prepare($query);
            $updateStmt->execute($params);
            
        } elseif ($role === 'retailer') {
            // Retailers can only update the status (accept/reject)
            if ($bid['retailer_id'] != $userId) {
                http_response_code(403);
                echo json_encode(['error' => 'You are not authorized to update this bid']);
                return;
            }
            
            if (!isset($data['status']) || !in_array($data['status'], ['accepted', 'rejected'])) {
                http_response_code(422);
                echo json_encode(['error' => 'Valid status (accepted/rejected) is required']);
                return;
            }
            
            // Update the bid status
            $updateStmt = $pdo->prepare("UPDATE bids SET status = ? WHERE id = ?");
            $updateStmt->execute([$data['status'], $id]);
            
            // If accepting this bid, reject all other bids for the project
            if ($data['status'] === 'accepted') {
                $rejectStmt = $pdo->prepare("UPDATE bids SET status = 'rejected' 
                                           WHERE project_id = ? AND id != ? AND status = 'pending'");
                $rejectStmt->execute([$bid['project_id'], $id]);
                
                // Close the project for further bidding
                $closeStmt = $pdo->prepare("UPDATE projects SET status = 'closed' WHERE id = ?");
                $closeStmt->execute([$bid['project_id']]);
            }
        } else {
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized access']);
            return;
        }
        
        // Add a message if provided
        if (isset($data['message']) && !empty($data['message'])) {
            $receiverId = ($role === 'farmer') ? $bid['retailer_id'] : $bid['farmer_id'];
            
            $msgStmt = $pdo->prepare("INSERT INTO messages (bid_id, sender_id, receiver_id, message, created_at) 
                                     VALUES (?, ?, ?, ?, NOW())");
            $msgStmt->execute([
                $id,
                $userId,
                $receiverId,
                $data['message']
            ]);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Bid updated successfully'
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update bid: ' . $e->getMessage()]);
    }
}

/**
 * Delete a bid
 * 
 * @param int $id Bid ID
 * @return void
 */
function deleteBid($id) {
    global $pdo;
    
    $userId = $_SESSION['user_id'];
    $role = $_SESSION['role'];
    
    try {
        // Get the bid
        $stmt = $pdo->prepare("SELECT * FROM bids WHERE id = ?");
        $stmt->execute([$id]);
        $bid = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$bid) {
            http_response_code(404);
            echo json_encode(['error' => 'Bid not found']);
            return;
        }
        
        // Only the farmer who created the bid can delete it, and only if it's still pending
        if ($role !== 'farmer' || $bid['farmer_id'] != $userId) {
            http_response_code(403);
            echo json_encode(['error' => 'You are not authorized to delete this bid']);
            return;
        }
        
        if ($bid['status'] !== 'pending') {
            http_response_code(422);
            echo json_encode(['error' => 'Cannot delete bid that has been accepted or rejected']);
            return;
        }
        
        // Start transaction
        $pdo->beginTransaction();
        
        // Delete associated messages first
        $msgStmt = $pdo->prepare("DELETE FROM messages WHERE bid_id = ?");
        $msgStmt->execute([$id]);
        
        // Delete the bid
        $bidStmt = $pdo->prepare("DELETE FROM bids WHERE id = ?");
        $bidStmt->execute([$id]);
        
        // Commit transaction
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Bid deleted successfully'
        ]);
    } catch (PDOException $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete bid: ' . $e->getMessage()]);
    }
}
