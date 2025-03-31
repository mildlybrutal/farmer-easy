<?php
/**
 * Authentication Utilities
 * 
 * Helper functions for authentication and security
 */

/**
 * Generate a secure random token
 * 
 * @param int $length Length of the token
 * @return string Random token
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Validate email format
 * 
 * @param string $email Email to validate
 * @return bool True if valid, false otherwise
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Check password strength
 * 
 * @param string $password Password to check
 * @return array Result with status and message
 */
function checkPasswordStrength($password) {
    $result = [
        'valid' => true,
        'message' => 'Password is strong'
    ];
    
    // Check length
    if (strlen($password) < 8) {
        $result['valid'] = false;
        $result['message'] = 'Password must be at least 8 characters long';
        return $result;
    }
    
    // Check for at least one uppercase letter
    if (!preg_match('/[A-Z]/', $password)) {
        $result['valid'] = false;
        $result['message'] = 'Password must contain at least one uppercase letter';
        return $result;
    }
    
    // Check for at least one lowercase letter
    if (!preg_match('/[a-z]/', $password)) {
        $result['valid'] = false;
        $result['message'] = 'Password must contain at least one lowercase letter';
        return $result;
    }
    
    // Check for at least one number
    if (!preg_match('/[0-9]/', $password)) {
        $result['valid'] = false;
        $result['message'] = 'Password must contain at least one number';
        return $result;
    }
    
    return $result;
}

/**
 * Sanitize input data
 * 
 * @param string $data Data to sanitize
 * @return string Sanitized data
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Check if a request is AJAX
 * 
 * @return bool True if AJAX request, false otherwise
 */
function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Get client IP address
 * 
 * @return string Client IP address
 */
function getClientIP() {
    $ip = '';
    
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    
    return $ip;
}

/**
 * Log authentication attempt
 * 
 * @param PDO $pdo Database connection
 * @param string $email Email used in attempt
 * @param bool $success Whether the attempt was successful
 * @return void
 */
function logAuthAttempt($pdo, $email, $success) {
    $ip = getClientIP();
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $stmt = $pdo->prepare("INSERT INTO auth_logs (email, ip_address, user_agent, success, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$email, $ip, $userAgent, $success ? 1 : 0]);
}

/**
 * Check for too many failed login attempts
 * 
 * @param PDO $pdo Database connection
 * @param string $email Email to check
 * @param int $maxAttempts Maximum number of attempts allowed
 * @param int $timeWindow Time window in seconds
 * @return bool True if too many attempts, false otherwise
 */
function checkFailedAttempts($pdo, $email, $maxAttempts = 5, $timeWindow = 900) {
    $ip = getClientIP();
    $timeLimit = date('Y-m-d H:i:s', time() - $timeWindow);
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM auth_logs WHERE (email = ? OR ip_address = ?) AND success = 0 AND created_at > ?");
    $stmt->execute([$email, $ip, $timeLimit]);
    
    return $stmt->fetchColumn() >= $maxAttempts;
}

/**
 * Check if user is authenticated
 * 
 * @return bool True if authenticated, false otherwise
 */
function isAuthenticated() {
    return isset($_SESSION['user_id']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

/**
 * Check if user has specific role
 * 
 * @param string|array $roles Role or array of roles to check
 * @return bool True if user has the role, false otherwise
 */
function hasRole($roles) {
    if (!isAuthenticated()) {
        return false;
    }
    
    if (is_array($roles)) {
        return in_array($_SESSION['role'], $roles);
    }
    
    return $_SESSION['role'] === $roles;
}
