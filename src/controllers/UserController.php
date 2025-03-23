<?php
$pdo = require '../../config/db_connect.php';

$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

if ($method === 'POST' && $uri === '/api/users') {
    $data = json_decode(file_get_contents("php://input"), true);
    $name = $data['name'] ?? '';
    $email = $data['email'] ?? '';
    $password = $data['password'] ?? '';
    $role = $data['role'] ?? '';

    if (empty($name) || empty($email) || empty($password) || !in_array($role, ['farmer', 'retailer', 'public'])) {
        http_response_code(422);
        echo json_encode(['error' => 'Invalid input: name, email, password, and valid role required']);
        exit;
    }
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetchColumn() > 0) {
        http_response_code(409);
        echo json_encode(['error' => 'Email already registered']);
        exit;
    }

    
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
    try {
        $stmt->execute([$name, $email, $hashedPassword, $role]);
        echo json_encode(['message' => 'User registered', 'id' => $pdo->lastInsertId()]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Registration failed: ' . $e->getMessage()]);
    }
}

elseif ($method === 'GET' && $uri === '/api/users/me') {
    $user = require 'authHelper.php'; 
    echo json_encode([
        'id' => $user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role'],
        'created_at' => $user['created_at']
    ]);
}

elseif ($method === 'PUT' && $uri === '/api/users/me') {
    $user = require 'authHelper.php';
    $data = json_decode(file_get_contents("php://input"), true);
    $name = $data['name'] ?? $user['name'];
    $email = $data['email'] ?? $user['email'];
    $password = $data['password'] ?? null;

    if (empty($name) || empty($email)) {
        http_response_code(422);
        echo json_encode(['error' => 'Name and email cannot be empty']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
    $stmt->execute([$email, $user['id']]);
    if ($stmt->fetchColumn() > 0) {
        http_response_code(409);
        echo json_encode(['error' => 'Email already in use by another user']);
        exit;
    }

    $query = "UPDATE users SET name = ?, email = ?" . ($password ? ", password = ?" : "") . " WHERE id = ?";
    $params = [$name, $email];
    if ($password) {
        $params[] = password_hash($password, PASSWORD_DEFAULT);
    }
    $params[] = $user['id'];

    $stmt = $pdo->prepare($query);
    try {
        $stmt->execute($params);
        echo json_encode(['message' => 'Profile updated']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Update failed: ' . $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}