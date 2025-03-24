<?php
/**
 * Authentication Middleware
 * 
 * This file contains functions to verify user authentication and authorization
 */

/**
 * Verify if a user is authenticated
 * 
 * @return bool True if authenticated, false otherwise
 */
function isAuthenticated() {
    return isset($_SESSION['user_id']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

/**
 * Get the current authenticated user
 * 
 * @return array|null User data or null if not authenticated
 */
function getCurrentUser() {
    if (!isAuthenticated()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'],
        'name' => $_SESSION['name'] ?? '',
        'email' => $_SESSION['email'] ?? '',
        'role' => $_SESSION['role'] ?? ''
    ];
}

/**
 * Check if the current user has a specific role
 * 
 * @param string|array $roles Role or array of roles to check
 * @return bool True if user has the role, false otherwise
 */
function hasRole($roles) {
    if (!isAuthenticated()) {
        return false;
    }
    
    if (is_string($roles)) {
        return $_SESSION['role'] === $roles;
    }
    
    if (is_array($roles)) {
        return in_array($_SESSION['role'], $roles);
    }
    
    return false;
}

/**
 * Require authentication for a route
 * 
 * @return bool True if authenticated, exits with 401 response otherwise
 */
function requireAuth() {
    if (!isAuthenticated()) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        exit;
    }
    
    return true;
}

/**
 * Require specific role for a route
 * 
 * @param string|array $roles Role or array of roles required
 * @return bool True if user has the required role, exits with 403 response otherwise
 */
function requireRole($roles) {
    requireAuth();
    
    if (!hasRole($roles)) {
        http_response_code(403);
        echo json_encode(['error' => 'Insufficient permissions']);
        exit;
    }
    
    return true;
}

/**
 * Regenerate session ID to prevent session fixation attacks
 * 
 * @return void
 */
function regenerateSession() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        // Save current session data
        $sessionData = $_SESSION;
        
        // Clear and regenerate session ID
        session_unset();
        session_regenerate_id(true);
        
        // Restore session data
        $_SESSION = $sessionData;
    }
}

/**
 * Set session timeout
 * 
 * @param int $timeout Timeout in seconds (default: 3600 = 1 hour)
 * @return void
 */
function checkSessionTimeout($timeout = 3600) {
    if (isAuthenticated()) {
        $currentTime = time();
        $lastActivity = $_SESSION['login_time'] ?? $currentTime;
        
        // Check if session has expired
        if ($currentTime - $lastActivity > $timeout) {
            // Session expired, log out the user
            session_unset();
            session_destroy();
            
            http_response_code(401);
            echo json_encode(['error' => 'Session expired']);
            exit;
        }
        
        // Update last activity time
        $_SESSION['login_time'] = $currentTime;
    }
}
