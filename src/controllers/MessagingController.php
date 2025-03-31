<?php
class MessagingController {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Send a message
     * 
     * @param array $data Message data
     * @return int|bool New message ID or false on failure
     */
    public function sendMessage($data) {
        $stmt = $this->pdo->prepare("INSERT INTO messages (sender_id, receiver_id, project_id, content, created_at) 
                                    VALUES (?, ?, ?, ?, NOW())");
        
        $result = $stmt->execute([
            $data['sender_id'],
            $data['receiver_id'],
            $data['project_id'],
            $data['content']
        ]);
        
        return $result ? $this->pdo->lastInsertId() : false;
    }
    
    /**
     * Get conversation history
     * 
     * @param int $projectId Project ID
     * @return array List of messages
     */
    public function getConversation($projectId) {
        $query = "SELECT m.*, 
                 sender.name as sender_name,
                 receiver.name as receiver_name
                 FROM messages m 
                 JOIN users sender ON m.sender_id = sender.id
                 JOIN users receiver ON m.receiver_id = receiver.id
                 WHERE m.project_id = ?
                 ORDER BY m.created_at ASC";
        
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([$projectId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} 