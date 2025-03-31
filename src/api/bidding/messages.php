<?php
session_start();
require_once __DIR__ . '/../../../config/db_connect.php';
require_once __DIR__ . '/../../../utils/auth_utils.php';
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../../controllers/MessagingController.php';

// Get the request method and data
$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents("php://input"), true);

// Check if user is authenticated
if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

$user = getCurrentUser();

// Handle different message endpoints based on HTTP method
switch ($method) {
    case 'GET':
        if (isset($_GET['project_id'])) {
            getMessagesByProject($_GET['project_id']);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Project ID is required']);
        }
        break;
    case 'POST':
        if (empty($data['receiver_id']) || empty($data['project_id']) || empty($data['content'])) {
            http_response_code(422);
            echo json_encode(['error' => 'Missing required fields']);
            exit;
        }
        
        $messageData = [
            'sender_id' => $user['id'],
            'receiver_id' => $data['receiver_id'],
            'project_id' => $data['project_id'],
            'content' => $data['content']
        ];
        
        $controller = new MessagingController($pdo);
        $result = $controller->sendMessage($messageData);
        
        if ($result) {
            echo json_encode(['message' => 'Message sent', 'id' => $result]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to send message']);
        }
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}

/**
 * Get all messages for a specific project
 * 
 * @param int $projectId Project ID
 * @return void
 */
function getMessagesByProject($projectId) {
    global $pdo;
    
    $userId = $_SESSION['user_id'];
    $role = $_SESSION['role'];
    
    try {
        // First check if the project exists and user is authorized to view messages
        $projectStmt = $pdo->prepare("SELECT p.*, u.retailer_id 
                                     FROM projects p 
                                     JOIN users u ON p.retailer_id = u.id 
                                     WHERE p.id = ?");
        $projectStmt->execute([$projectId]);
        $project = $projectStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$project) {
            http_response_code(404);
            echo json_encode(['error' => 'Project not found']);
            return;
        }
        
        // Check authorization (only the farmer who placed the bid or the retailer who owns the project can view messages)
        if (($role === 'farmer' && $project['farmer_id'] != $userId) || 
            ($role === 'retailer' && $project['retailer_id'] != $userId)) {
            http_response_code(403);
            echo json_encode(['error' => 'You are not authorized to view these messages']);
            return;
        }
        
        // Get messages
        $msgStmt = $pdo->prepare("SELECT m.*, 
                                 CASE WHEN m.sender_id = ? THEN 'self' ELSE 'other' END as sender_type,
                                 u.name as sender_name, u.role as sender_role 
                                 FROM messages m 
                                 JOIN users u ON m.sender_id = u.id 
                                 WHERE m.project_id = ? 
                                 ORDER BY m.created_at ASC");
        $msgStmt->execute([$userId, $projectId]);
        $messages = $msgStmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'project_id' => $projectId,
            'messages' => $messages
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to retrieve messages: ' . $e->getMessage()]);
    }
}
