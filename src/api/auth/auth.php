<?php
    session_start();
    require_once __DIR__ . '/../../../config/db_connect.php';
    require_once __DIR__ . '/../../../config/auth_config.php';

    // Get the request method and data
    $method = $_SERVER['REQUEST_METHOD'];
    $uri = $_SERVER['REQUEST_URI'];
    $data = json_decode(file_get_contents("php://input"), true);

    // Handle different authentication endpoints
    if ($method === 'POST') {
        // Login endpoint
        if (strpos($uri, '/api/auth/login') !== false) {
            handleLogin($data);
        } 
        // Register endpoint
        elseif (strpos($uri, '/api/auth/register') !== false) {
            handleRegister($data);
        } 
        // Logout endpoint
        elseif (strpos($uri, '/api/auth/logout') !== false) {
            handleLogout();
        } 
        else {
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
        }
    } elseif ($method === 'GET') {
        // Check session status endpoint
        if (strpos($uri, '/api/auth/status') !== false) {
            checkAuthStatus();
        } 
        // Get user profile endpoint
        elseif (strpos($uri, '/api/auth/user') !== false) {
            getUserProfile();
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
     * Handle user login
     * 
     * @param array $data Request data containing email, password, and role
     * @return void
     */
    function handleLogin($data) {
        global $pdo;
        
        if (empty($data['email']) || empty($data['password']) || empty($data['role'])) {
            http_response_code(422);
            echo json_encode(['error' => 'Email, password and role are required']);
            exit;
        }
        
        // Validate role
        $config = require __DIR__ . '/../../../config/auth_config.php';
        if (!in_array($data['role'], $config['allowed_roles'])) {
            http_response_code(422);
            echo json_encode(['error' => 'Invalid role']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = ? AND active = 1");
        $stmt->execute([$data['email'], $data['role']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user || !password_verify($data['password'], $user['password'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid credentials']);
            exit;
        }

        // Generate CSRF token
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_expiry'] = time() + $config['csrf_token_expiry'];
        
        // Set session data
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['login_time'] = time();

        echo json_encode([
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role']
            ],
            'csrf_token' => $_SESSION['csrf_token']
        ]);
    }

    /**
     * Handle user registration
     * 
     * @param array $data Request data containing user information
     * @return void
     */
    function handleRegister($data) {
        global $pdo;
        
        if (empty($data['name']) || empty($data['email']) || empty($data['password']) || empty($data['role'])) {
            http_response_code(422);
            echo json_encode(['error' => 'Name, email, password, and role are required']);
            exit;
        }
        
        if (!in_array($data['role'], ['farmer', 'retailer', 'consumer'])) {
            http_response_code(422);
            echo json_encode(['error' => 'Invalid role. Must be farmer, retailer, or consumer']);
            exit;
        }
        
        // Validate email format
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            http_response_code(422);
            echo json_encode(['error' => 'Invalid email format']);
            exit;
        }
        
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$data['email']]);
        if ($stmt->fetchColumn() > 0) {
            http_response_code(409);
            echo json_encode(['error' => 'Email already registered']);
            exit;
        }
        
        // Hash password and insert user
        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, created_at) VALUES (?, ?, ?, ?, NOW())");
        
        try {
            $stmt->execute([$data['name'], $data['email'], $hashedPassword, $data['role']]);
            $userId = $pdo->lastInsertId();
            
            // Create a session for the newly registered user
            session_start();
            $_SESSION['user_id'] = $userId;
            $_SESSION['role'] = $data['role'];
            
            echo json_encode([
                'success' => true,
                'message' => 'Registration successful',
                'user' => [
                    'id' => $userId,
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'role' => $data['role']
                ]
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Registration failed: ' . $e->getMessage()]);
        }
    }

    /**
     * Handle user logout
     * 
     * @return void
     */
    function handleLogout() {
        session_destroy();
        echo json_encode(['message' => 'Logged out successfully']);
    }

    /**
     * Check authentication status
     * 
     * @return void
     */
    function checkAuthStatus() {
        if (isset($_SESSION['user_id']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
            echo json_encode([
                'authenticated' => true,
                'user' => [
                    'id' => $_SESSION['user_id'],
                    'name' => $_SESSION['name'],
                    'email' => $_SESSION['email'],
                    'role' => $_SESSION['role']
                ]
            ]);
        } else {
            echo json_encode([
                'authenticated' => false
            ]);
        }
    }

    /**
     * Get user profile
     * 
     * @return void
     */
    function getUserProfile() {
        global $pdo;
        
        // Check if user is logged in
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Not authenticated']);
            return;
        }
        
        // Get user from database
        $stmt = $pdo->prepare("SELECT id, name, email, role, created_at, last_login FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            echo json_encode([
                'success' => true,
                'user' => $user
            ]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
        }
    }