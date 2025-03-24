<?php
/**
 * Password Reset Functionality
 * 
 * Handles password reset requests and token validation
 */

session_start();
require_once __DIR__ . '/../../../config/db_connect.php';
require_once __DIR__ . '/../../../utils/auth_utils.php';

// Get the request method and data
$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];
$data = json_decode(file_get_contents("php://input"), true);

// Set content type to JSON
header('Content-Type: application/json');

// Handle different password reset endpoints
if ($method === 'POST') {
    // Request password reset
    if (strpos($uri, '/api/auth/password/request') !== false) {
        requestPasswordReset($data);
    } 
    // Verify reset token
    elseif (strpos($uri, '/api/auth/password/verify') !== false) {
        verifyResetToken($data);
    } 
    // Reset password
    elseif (strpos($uri, '/api/auth/password/reset') !== false) {
        resetPassword($data);
    } 
    else {
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found']);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}

/**
 * Request a password reset
 * 
 * @param array $data Request data containing email
 * @return void
 */
function requestPasswordReset($data) {
    global $pdo;
    
    // Validate input
    if (!isset($data['email'])) {
        http_response_code(422);
        echo json_encode(['error' => 'Email is required']);
        return;
    }
    
    $email = sanitizeInput($data['email']);
    
    // Validate email format
    if (!validateEmail($email)) {
        http_response_code(422);
        echo json_encode(['error' => 'Invalid email format']);
        return;
    }
    
    // Check if email exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        // Don't reveal that the email doesn't exist for security reasons
        echo json_encode([
            'success' => true,
            'message' => 'If your email exists in our system, you will receive a password reset link'
        ]);
        return;
    }
    
    // Generate token
    $token = generateToken();
    $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour expiration
    
    // Save token to database
    $stmt = $pdo->prepare("INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
    try {
        $stmt->execute([$user['id'], $token, $expiresAt]);
        
        // In a production environment, you would send an email with the reset link
        // For now, we'll just return the token in the response
        echo json_encode([
            'success' => true,
            'message' => 'If your email exists in our system, you will receive a password reset link',
            'debug_token' => $token // Remove this in production
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create reset token: ' . $e->getMessage()]);
    }
}

/**
 * Verify a password reset token
 * 
 * @param array $data Request data containing token
 * @return void
 */
function verifyResetToken($data) {
    global $pdo;
    
    // Validate input
    if (!isset($data['token'])) {
        http_response_code(422);
        echo json_encode(['error' => 'Token is required']);
        return;
    }
    
    $token = sanitizeInput($data['token']);
    
    // Check if token exists and is valid
    $stmt = $pdo->prepare("
        SELECT t.id, t.user_id, t.expires_at, t.used, u.email 
        FROM password_reset_tokens t
        JOIN users u ON t.user_id = u.id
        WHERE t.token = ?
    ");
    $stmt->execute([$token]);
    $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$tokenData) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid token']);
        return;
    }
    
    // Check if token is expired
    if (strtotime($tokenData['expires_at']) < time()) {
        http_response_code(400);
        echo json_encode(['error' => 'Token has expired']);
        return;
    }
    
    // Check if token has been used
    if ($tokenData['used']) {
        http_response_code(400);
        echo json_encode(['error' => 'Token has already been used']);
        return;
    }
    
    // Token is valid
    echo json_encode([
        'success' => true,
        'message' => 'Token is valid',
        'email' => $tokenData['email']
    ]);
}

/**
 * Reset password using a token
 * 
 * @param array $data Request data containing token and new password
 * @return void
 */
function resetPassword($data) {
    global $pdo;
    
    // Validate input
    if (!isset($data['token']) || !isset($data['password']) || !isset($data['confirm_password'])) {
        http_response_code(422);
        echo json_encode(['error' => 'Token, password, and confirm_password are required']);
        return;
    }
    
    $token = sanitizeInput($data['token']);
    $password = $data['password'];
    $confirmPassword = $data['confirm_password'];
    
    // Check if passwords match
    if ($password !== $confirmPassword) {
        http_response_code(422);
        echo json_encode(['error' => 'Passwords do not match']);
        return;
    }
    
    // Check password strength
    $passwordCheck = checkPasswordStrength($password);
    if (!$passwordCheck['valid']) {
        http_response_code(422);
        echo json_encode(['error' => $passwordCheck['message']]);
        return;
    }
    
    // Check if token exists and is valid
    $stmt = $pdo->prepare("
        SELECT t.id, t.user_id, t.expires_at, t.used
        FROM password_reset_tokens t
        WHERE t.token = ?
    ");
    $stmt->execute([$token]);
    $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$tokenData) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid token']);
        return;
    }
    
    // Check if token is expired
    if (strtotime($tokenData['expires_at']) < time()) {
        http_response_code(400);
        echo json_encode(['error' => 'Token has expired']);
        return;
    }
    
    // Check if token has been used
    if ($tokenData['used']) {
        http_response_code(400);
        echo json_encode(['error' => 'Token has already been used']);
        return;
    }
    
    // Update password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
    
    try {
        $pdo->beginTransaction();
        
        // Update password
        $stmt->execute([$hashedPassword, $tokenData['user_id']]);
        
        // Mark token as used
        $stmt = $pdo->prepare("UPDATE password_reset_tokens SET used = 1 WHERE id = ?");
        $stmt->execute([$tokenData['id']]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Password has been reset successfully'
        ]);
    } catch (PDOException $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to reset password: ' . $e->getMessage()]);
    }
}
