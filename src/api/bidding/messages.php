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

// Handle different message endpoints based on HTTP method
switch ($method) {
    case 'GET':
        if (isset($_GET['bid_id'])) {
            getMessagesByBid($_GET['bid_id']);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Bid ID is required']);
        }
        break;
    case 'POST':
        sendMessage($data);
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}

/**
 * Get all messages for a specific bid
 * 
 * @param int $bidId Bid ID
 * @return void
 */
function getMessagesByBid($bidId) {
    global $pdo;
    
    $userId = $_SESSION['user_id'];
    $role = $_SESSION['role'];
    
    try {
        // First check if the bid exists and user is authorized to view messages
        $bidStmt = $pdo->prepare("SELECT b.*, p.retailer_id 
                                 FROM bids b 
                                 JOIN projects p ON b.project_id = p.id 
                                 WHERE b.id = ?");
        $bidStmt->execute([$bidId]);
        $bid = $bidStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$bid) {
            http_response_code(404);
            echo json_encode(['error' => 'Bid not found']);
            return;
        }
        
        // Check authorization (only the farmer who placed the bid or the retailer who owns the project can view messages)
        if (($role === 'farmer' && $bid['farmer_id'] != $userId) || 
            ($role === 'retailer' && $bid['retailer_id'] != $userId)) {
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
                                 WHERE m.bid_id = ? 
                                 ORDER BY m.created_at ASC");
        $msgStmt->execute([$userId, $bidId]);
        $messages = $msgStmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'bid_id' => $bidId,
            'messages' => $messages
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to retrieve messages: ' . $e->getMessage()]);
    }
}

/**
 * Send a new message
 * 
 * @param array $data Message data
 * @return void
 */
function sendMessage($data) {
    global $pdo;
    
    $userId = $_SESSION['user_id'];
    $role = $_SESSION['role'];
    
    // Validate required fields
    if (!isset($data['bid_id']) || !isset($data['message']) || empty($data['message'])) {
        http_response_code(422);
        echo json_encode(['error' => 'Bid ID and message content are required']);
        return;
    }
    
    try {
        // Check if the bid exists and user is authorized to send messages
        $bidStmt = $pdo->prepare("SELECT b.*, p.retailer_id 
                                 FROM bids b 
                                 JOIN projects p ON b.project_id = p.id 
                                 WHERE b.id = ?");
        $bidStmt->execute([$data['bid_id']]);
        $bid = $bidStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$bid) {
            http_response_code(404);
            echo json_encode(['error' => 'Bid not found']);
            return;
        }
        
        // Check authorization and determine receiver
        if ($role === 'farmer') {
            if ($bid['farmer_id'] != $userId) {
                http_response_code(403);
                echo json_encode(['error' => 'You are not authorized to send messages for this bid']);
                return;
            }
            $receiverId = $bid['retailer_id'];
        } elseif ($role === 'retailer') {
            if ($bid['retailer_id'] != $userId) {
                http_response_code(403);
                echo json_encode(['error' => 'You are not authorized to send messages for this bid']);
                return;
            }
            $receiverId = $bid['farmer_id'];
        } else {
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized access']);
            return;
        }
        
        // Insert the message
        $msgStmt = $pdo->prepare("INSERT INTO messages (bid_id, sender_id, receiver_id, message, created_at) 
                                 VALUES (?, ?, ?, ?, NOW())");
        $msgStmt->execute([
            $data['bid_id'],
            $userId,
            $receiverId,
            $data['message']
        ]);
        
        $messageId = $pdo->lastInsertId();
        
        // Get the newly created message with sender info
        $getStmt = $pdo->prepare("SELECT m.*, u.name as sender_name, u.role as sender_role 
                                 FROM messages m 
                                 JOIN users u ON m.sender_id = u.id 
                                 WHERE m.id = ?");
        $getStmt->execute([$messageId]);
        $message = $getStmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'message' => 'Message sent successfully',
            'message_data' => $message
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to send message: ' . $e->getMessage()]);
    }
}
