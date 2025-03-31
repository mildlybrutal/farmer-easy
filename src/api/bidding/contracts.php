<?php
session_start();
require_once __DIR__ . '/../../../config/db_connect.php';
require_once __DIR__ . '/../../../utils/auth_utils.php';
require_once __DIR__ . '/../../controllers/BiddingController.php';

// Get the request method and data
$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents("php://input"), true);

// Check if user is authenticated
if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Initialize bidding controller
$biddingController = new BiddingController($pdo);

// Handle different contract endpoints based on HTTP method
switch ($method) {
    case 'GET':
        if (isset($_GET['bid_id'])) {
            generateContract($_GET['bid_id']);
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
 * Generate a contract for an accepted bid
 * 
 * @param int $bidId Bid ID
 * @return void
 */
function generateContract($bidId) {
    global $pdo, $biddingController;
    
    $userId = $_SESSION['user_id'];
    $role = $_SESSION['role'];
    
    try {
        // Check if the bid exists and is accepted
        $stmt = $pdo->prepare("SELECT b.*, p.retailer_id, p.title as project_title 
                              FROM bids b 
                              JOIN projects p ON b.project_id = p.id 
                              WHERE b.id = ?");
        $stmt->execute([$bidId]);
        $bid = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$bid) {
            http_response_code(404);
            echo json_encode(['error' => 'Bid not found']);
            return;
        }
        
        // Check if bid is accepted
        if ($bid['status'] !== 'accepted') {
            http_response_code(422);
            echo json_encode(['error' => 'Contract can only be generated for accepted bids']);
            return;
        }
        
        // Check authorization (only the farmer who placed the bid or the retailer who owns the project can view the contract)
        if (($role === 'farmer' && $bid['farmer_id'] != $userId) || 
            ($role === 'retailer' && $bid['retailer_id'] != $userId)) {
            http_response_code(403);
            echo json_encode(['error' => 'You are not authorized to view this contract']);
            return;
        }
        
        // Generate the contract
        $contract = $biddingController->generateContract($bidId);
        
        if (!$contract) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to generate contract']);
            return;
        }
        
        echo json_encode([
            'success' => true,
            'contract' => $contract
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to generate contract: ' . $e->getMessage()]);
    }
}
