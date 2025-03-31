<?php
/**
 * Bidding Controller
 * 
 * Handles business logic for the bidding system
 */
class BiddingController {
    private $pdo;
    
    /**
     * Constructor
     * 
     * @param PDO $pdo Database connection
     */
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Get all open projects
     * 
     * @param string $status Filter by status (open/closed)
     * @return array List of projects
     */
    public function getOpenProjects($status = 'open') {
        $query = "SELECT p.*, u.name as retailer_name 
                 FROM projects p 
                 JOIN users u ON p.retailer_id = u.id 
                 WHERE p.status = ? 
                 ORDER BY p.created_at DESC";
        
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([$status]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get projects by retailer
     * 
     * @param int $retailerId Retailer ID
     * @return array List of projects
     */
    public function getRetailerProjects($retailerId) {
        $query = "SELECT p.*, 
                 (SELECT COUNT(*) FROM bids WHERE project_id = p.id) as bid_count 
                 FROM projects p 
                 WHERE p.retailer_id = ? 
                 ORDER BY p.created_at DESC";
        
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([$retailerId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get bids by farmer
     * 
     * @param int $farmerId Farmer ID
     * @return array List of bids with project details
     */
    public function getFarmerBids($farmerId) {
        $query = "SELECT b.*, p.title as project_title, p.description as project_description, 
                 u.name as retailer_name 
                 FROM bids b 
                 JOIN projects p ON b.project_id = p.id 
                 JOIN users u ON p.retailer_id = u.id 
                 WHERE b.farmer_id = ? 
                 ORDER BY b.created_at DESC";
        
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([$farmerId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get bids for a project
     * 
     * @param int $projectId Project ID
     * @return array List of bids with farmer details
     */
    public function getProjectBids($projectId) {
        $query = "SELECT b.*, u.name as farmer_name, u.email as farmer_email 
                 FROM bids b 
                 JOIN users u ON b.farmer_id = u.id 
                 WHERE b.project_id = ? 
                 ORDER BY b.created_at DESC";
        
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([$projectId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Create a new project
     * 
     * @param array $data Project data
     * @return int|bool New project ID or false on failure
     */
    public function createProject($data) {
        $stmt = $this->pdo->prepare("INSERT INTO projects (retailer_id, title, description, deadline, status, created_at) 
                                    VALUES (?, ?, ?, ?, 'open', NOW())");
        
        $result = $stmt->execute([
            $data['retailer_id'],
            $data['title'],
            $data['description'],
            isset($data['deadline']) ? $data['deadline'] : null
        ]);
        
        return $result ? $this->pdo->lastInsertId() : false;
    }
    
    /**
     * Create a new bid
     * 
     * @param array $data Bid data
     * @return int|bool New bid ID or false on failure
     */
    public function createBid($data) {
        // Start transaction
        $this->pdo->beginTransaction();
        
        try {
            // Create the bid
            $stmt = $this->pdo->prepare("INSERT INTO bids (project_id, farmer_id, price, terms, status, created_at) 
                                        VALUES (?, ?, ?, ?, 'pending', NOW())");
            
            $stmt->execute([
                $data['project_id'],
                $data['farmer_id'],
                $data['price'],
                isset($data['terms']) ? $data['terms'] : null
            ]);
            
            $bidId = $this->pdo->lastInsertId();
            
            // If initial message is provided, add it
            if (isset($data['message']) && !empty($data['message'])) {
                // Get retailer ID for the project
                $projectStmt = $this->pdo->prepare("SELECT retailer_id FROM projects WHERE id = ?");
                $projectStmt->execute([$data['project_id']]);
                $retailerId = $projectStmt->fetchColumn();
                
                $msgStmt = $this->pdo->prepare("INSERT INTO messages (bid_id, sender_id, receiver_id, message, created_at) 
                                              VALUES (?, ?, ?, ?, NOW())");
                $msgStmt->execute([
                    $bidId,
                    $data['farmer_id'],
                    $retailerId,
                    $data['message']
                ]);
            }
            
            // Commit transaction
            $this->pdo->commit();
            
            return $bidId;
        } catch (PDOException $e) {
            // Rollback transaction on error
            $this->pdo->rollBack();
            return false;
        }
    }
    
    /**
     * Update bid status
     * 
     * @param int $bidId Bid ID
     * @param string $status New status (accepted/rejected)
     * @return bool Success or failure
     */
    public function updateBidStatus($bidId, $status) {
        // Start transaction
        $this->pdo->beginTransaction();
        
        try {
            // Update the bid status
            $stmt = $this->pdo->prepare("UPDATE bids SET status = ? WHERE id = ?");
            $stmt->execute([$status, $bidId]);
            
            // If accepting this bid, reject all other bids for the project and close the project
            if ($status === 'accepted') {
                // Get project ID
                $projectStmt = $this->pdo->prepare("SELECT project_id FROM bids WHERE id = ?");
                $projectStmt->execute([$bidId]);
                $projectId = $projectStmt->fetchColumn();
                
                // Reject other bids
                $rejectStmt = $this->pdo->prepare("UPDATE bids SET status = 'rejected' 
                                                 WHERE project_id = ? AND id != ? AND status = 'pending'");
                $rejectStmt->execute([$projectId, $bidId]);
                
                // Close the project
                $closeStmt = $this->pdo->prepare("UPDATE projects SET status = 'closed' WHERE id = ?");
                $closeStmt->execute([$projectId]);
            }
            
            // Commit transaction
            $this->pdo->commit();
            
            return true;
        } catch (PDOException $e) {
            // Rollback transaction on error
            $this->pdo->rollBack();
            return false;
        }
    }
    
    /**
     * Send a message
     * 
     * @param array $data Message data
     * @return int|bool New message ID or false on failure
     */
    public function sendMessage($data) {
        $stmt = $this->pdo->prepare("INSERT INTO messages (bid_id, sender_id, receiver_id, message, created_at) 
                                    VALUES (?, ?, ?, ?, NOW())");
        
        $result = $stmt->execute([
            $data['bid_id'],
            $data['sender_id'],
            $data['receiver_id'],
            $data['message']
        ]);
        
        return $result ? $this->pdo->lastInsertId() : false;
    }
    
    /**
     * Get messages for a bid
     * 
     * @param int $bidId Bid ID
     * @return array List of messages
     */
    public function getBidMessages($bidId) {
        $query = "SELECT m.*, 
                 u_sender.name as sender_name, u_sender.role as sender_role,
                 u_receiver.name as receiver_name, u_receiver.role as receiver_role
                 FROM messages m 
                 JOIN users u_sender ON m.sender_id = u_sender.id 
                 JOIN users u_receiver ON m.receiver_id = u_receiver.id 
                 WHERE m.bid_id = ? 
                 ORDER BY m.created_at ASC";
        
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([$bidId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Generate contract for accepted bid
     * 
     * @param int $bidId Bid ID
     * @return array Contract data
     */
    public function generateContract($bidId) {
        $query = "SELECT b.*, 
                 p.title as project_title, p.description as project_description, p.deadline,
                 f.name as farmer_name, f.email as farmer_email, f.phone as farmer_phone, f.address as farmer_address,
                 r.name as retailer_name, r.email as retailer_email, r.phone as retailer_phone, r.address as retailer_address
                 FROM bids b 
                 JOIN projects p ON b.project_id = p.id 
                 JOIN users f ON b.farmer_id = f.id 
                 JOIN users r ON p.retailer_id = r.id 
                 WHERE b.id = ? AND b.status = 'accepted'";
        
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([$bidId]);
        $contract = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$contract) {
            return false;
        }
        
        // Add contract generation date
        $contract['contract_date'] = date('Y-m-d');
        $contract['contract_id'] = 'CNT-' . date('Ymd') . '-' . $bidId;
        
        return $contract;
    }
    
    /**
     * Submit a bid
     * 
     * @param array $data Bid data
     * @return int|bool New bid ID or false on failure
     */
    public function submitBid($data) {
        $stmt = $this->pdo->prepare("INSERT INTO bids (project_id, farmer_id, amount, proposal, status, created_at) 
                                    VALUES (?, ?, ?, ?, 'pending', NOW())");
        
        $result = $stmt->execute([
            $data['project_id'],
            $data['farmer_id'],
            $data['amount'],
            $data['proposal']
        ]);
        
        return $result ? $this->pdo->lastInsertId() : false;
    }
    
    /**
     * Update project status
     * 
     * @param int $projectId Project ID
     * @param string $status New status
     * @return bool Success status
     */
    public function updateProjectStatus($projectId, $status) {
        $stmt = $this->pdo->prepare("UPDATE projects SET status = ? WHERE id = ?");
        return $stmt->execute([$status, $projectId]);
    }
    
    /**
     * Accept a bid
     * 
     * @param int $bidId Bid ID
     * @param int $projectId Project ID
     * @return bool Success status
     */
    public function acceptBid($bidId, $projectId) {
        try {
            $this->pdo->beginTransaction();
            
            // Update bid status
            $stmt = $this->pdo->prepare("UPDATE bids SET status = 'accepted' WHERE id = ?");
            $stmt->execute([$bidId]);
            
            // Reject other bids
            $stmt = $this->pdo->prepare("UPDATE bids SET status = 'rejected' WHERE project_id = ? AND id != ?");
            $stmt->execute([$projectId, $bidId]);
            
            // Close the project
            $stmt = $this->pdo->prepare("UPDATE projects SET status = 'closed' WHERE id = ?");
            $stmt->execute([$projectId]);
            
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return false;
        }
    }
}

// Add WebSocket server for real-time updates
require_once 'vendor/autoload.php';
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

class BidWebSocket implements MessageComponentInterface {
    protected $clients;
    
    public function __construct() {
        $this->clients = new \SplObjectStorage;
    }
    
    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
    }
    
    public function onMessage(ConnectionInterface $from, $msg) {
        foreach ($this->clients as $client) {
            if ($from !== $client) {
                $client->send($msg);
            }
        }
    }
    
    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
    }
    
    public function onError(ConnectionInterface $conn, \Exception $e) {
        $conn->close();
    }
}
