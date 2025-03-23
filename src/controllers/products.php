<?php
$pdo = require '../../config/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->query("SELECT * FROM products");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = require 'auth_helper.php';
    $data = json_decode(file_get_contents("php://input"), true);
    if (empty($data['name']) || !is_numeric($data['price']) || !isset($data['stock'])) {
        http_response_code(422);
        echo json_encode(['error' => 'Invalid input']);
        exit;
    }
    $stmt = $pdo->prepare("INSERT INTO products (farmer_id, name, price, stock) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user['id'], $data['name'], $data['price'], $data['stock']]);
    echo json_encode(['message' => 'Product created']);
}