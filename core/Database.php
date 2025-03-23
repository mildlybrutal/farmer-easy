<?php
class Database {
    private $conn;

    public function __construct() {
        require_once 'config/database.php';
        global $conn;
        $this->conn = $conn;
    }

    public function query($sql, $params = []) {
        $stmt = $this->conn->prepare($sql);
        if ($params) {
            $types = str_repeat('s', count($params));
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        return $stmt;
    }

    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function fetch($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->get_result()->fetch_assoc();
    }
}
?>