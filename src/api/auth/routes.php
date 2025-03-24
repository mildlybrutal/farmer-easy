<?php
/**
 * Authentication Routes
 * 
 * This file handles routing for authentication-related endpoints
 */

// Include required files
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/middleware.php';

// Get request method and URI
$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

// Set content type to JSON
header('Content-Type: application/json');

// Route authentication requests
if (strpos($uri, '/api/auth') === 0) {
    // Check session timeout for authenticated routes
    if (isAuthenticated()) {
        checkSessionTimeout();
    }
    
    // Handle password reset routes
    if (strpos($uri, '/api/auth/password') !== false) {
        require_once __DIR__ . '/password_reset.php';
        exit;
    }
    
    // Handle different authentication endpoints
    if ($method === 'POST') {
        if (strpos($uri, '/api/auth/login') !== false) {
            // Login endpoint - handled in auth.php
        } 
        elseif (strpos($uri, '/api/auth/register') !== false) {
            // Register endpoint - handled in auth.php
        } 
        elseif (strpos($uri, '/api/auth/logout') !== false) {
            // Logout endpoint - handled in auth.php
        } 
        else {
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
            exit;
        }
    } 
    elseif ($method === 'GET') {
        if (strpos($uri, '/api/auth/status') !== false) {
            // Status endpoint - handled in auth.php
        } 
        elseif (strpos($uri, '/api/auth/user') !== false) {
            // User profile endpoint - handled in auth.php
        } 
        else {
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
            exit;
        }
    } 
    else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }
}
