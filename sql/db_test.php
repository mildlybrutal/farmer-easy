<?php
$pdo = require 'config/db_connect.php';

try {
    // Test connection and query
    $stmt = $pdo->query("SELECT * FROM users LIMIT 1");
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        echo "Database connected! Sample user: " . json_encode($user);
    } else {
        echo "Database connected, but no users found.";
    }
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}