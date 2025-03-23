<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost:3000');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, X-Session-ID');

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];
$pdo = require 'config/db_connect.php';

if ($uri === '/api/login' && $method === 'POST') {
    require 'api/controllers/auth.php';
} elseif ($uri === '/api/logout' && $method === 'POST') {
    require 'api/controllers/auth.php';
} elseif ($uri === '/api/users' && $method === 'POST') {
    require 'api/controllers/users.php';
} elseif ($uri === '/api/products' && $method === 'GET') {
    require 'api/controllers/products.php';
} elseif (preg_match('/^\/api\//', $uri)) {
    $user = require 'api/controllers/auth_helper.php';
    if ($user) {
        if ($uri === '/api/users/me' && in_array($method, ['GET', 'PUT'])) { // User profile
            require 'api/controllers/users.php';
        } elseif ($uri === '/api/products' && $method === 'POST' && $user['role'] === 'farmer') {
            require 'api/controllers/products.php';
        } elseif ($uri === '/api/orders' && $method === 'POST' && $user['role'] === 'public') {
            require 'api/controllers/orders.php';
        } elseif ($uri === '/api/projects' && $method === 'POST' && $user['role'] === 'retailer') {
            require 'api/controllers/projects.php';
        } elseif ($uri === '/api/bids' && $method === 'POST' && $user['role'] === 'farmer') {
            require 'api/controllers/bids.php';
        } elseif ($uri === '/api/messages' && $method === 'POST') {
            require 'api/controllers/messages.php';
        } else {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden']);
        }
    }
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Not found']);
}