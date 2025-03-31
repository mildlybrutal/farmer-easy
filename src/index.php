<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost:3000');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, X-Session-ID');
header('Access-Control-Allow-Credentials: true');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];
$pdo = require __DIR__ . '/../config/db_connect.php';

// Handle authentication routes
if (strpos($uri, '/api/auth') === 0) {
    require __DIR__ . '/api/auth/routes.php';
    exit;
}

// Handle bidding system routes
if (strpos($uri, '/api/bidding/projects') === 0) {
    require __DIR__ . '/api/bidding/projects.php';
    exit;
} elseif (strpos($uri, '/api/bidding/bids') === 0) {
    require __DIR__ . '/api/bidding/bids.php';
    exit;
} elseif (strpos($uri, '/api/bidding/messages') === 0) {
    require __DIR__ . '/api/bidding/messages.php';
    exit;
} elseif (strpos($uri, '/api/bidding/contracts') === 0) {
    require __DIR__ . '/api/bidding/contracts.php';
    exit;
}

// Legacy routes - will be migrated to new structure
if ($uri === '/api/login' && $method === 'POST') {
    require __DIR__ . '/controllers/auth.php';
    exit;
} elseif ($uri === '/api/logout' && $method === 'POST') {
    require __DIR__ . '/controllers/auth.php';
    exit;
} elseif ($uri === '/api/users' && $method === 'POST') {
    require __DIR__ . '/controllers/UserController.php';
    exit;
} elseif ($uri === '/api/products' && $method === 'GET') {
    require __DIR__ . '/controllers/products.php';
    exit;
} 

// Protected routes requiring authentication
elseif (preg_match('/^\/api\//', $uri)) {
    // Include authentication middleware
    require_once __DIR__ . '/api/auth/middleware.php';
    
    // Check if user is authenticated
    if (isAuthenticated()) {
        $user = getCurrentUser();
        
        if ($uri === '/api/users/me' && in_array($method, ['GET', 'PUT'])) {
            require __DIR__ . '/controllers/UserController.php';
            exit;
        } elseif ($uri === '/api/products' && $method === 'POST' && hasRole('farmer')) {
            require __DIR__ . '/controllers/products.php';
            exit;
        } elseif ($uri === '/api/orders' && $method === 'POST' && hasRole('public')) {
            require __DIR__ . '/controllers/orders.php';
            exit;
        } elseif ($uri === '/api/projects' && $method === 'POST' && hasRole('retailer')) {
            require __DIR__ . '/controllers/projects.php';
            exit;
        } elseif ($uri === '/api/bids' && $method === 'POST' && hasRole('farmer')) {
            require __DIR__ . '/controllers/bids.php';
            exit;
        } elseif ($uri === '/api/messages' && $method === 'POST') {
            require __DIR__ . '/controllers/messages.php';
            exit;
        } else {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden']);
            exit;
        }
    } else {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        exit;
    }
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Not found']);
    exit;
}