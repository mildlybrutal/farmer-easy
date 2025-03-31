<?php
/**
 * Router for PHP's built-in web server
 * 
 * This file handles routing requests to the appropriate handlers
 * when using PHP's built-in web server (php -S localhost:8000)
 */

// Get the requested URI
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Check if the request is for a static file
if (preg_match('/\.(?:png|jpg|jpeg|gif|css|js|ico)$/', $uri)) {
    return false; // Let the built-in server handle static files
}

// Route API requests
if (strpos($uri, '/api/auth') === 0) {
    require __DIR__ . '/src/api/auth/auth.php';
} elseif (strpos($uri, '/api/bidding/projects') === 0) {
    require __DIR__ . '/src/api/bidding/projects.php';
} elseif (strpos($uri, '/api/bidding/bids') === 0) {
    require __DIR__ . '/src/api/bidding/bids.php';
} elseif (strpos($uri, '/api/bidding/messages') === 0) {
    require __DIR__ . '/src/api/bidding/messages.php';
} elseif (strpos($uri, '/api/bidding/contracts') === 0) {
    require __DIR__ . '/src/api/bidding/contracts.php';
} else {
    // Default to index.php for all other requests
    require __DIR__ . '/src/index.php';
}
