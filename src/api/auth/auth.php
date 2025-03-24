<?php
    session_start();
    require_once __DIR__ . '/../../../config/db_connect.php';

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
     * @param array $data Request data containing email and password
     * @return void
     */
    function handleLogin($data) {
        global $pdo;
        
        // Validate input
        if (!isset($data['email']) || !isset($data['password'])) {
            http_response_code(422);
            echo json_encode(['error' => 'Email and password are required']);
            return;
        }
        
        $email = $data['email'];
        $password = $data['password'];
        
        // Query the database
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Verify credentials
        if ($user && password_verify($password, $user['password'])) {
            // Create session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['logged_in'] = true;
            $_SESSION['login_time'] = time();
            
            // Update last login timestamp
            $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $updateStmt->execute([$user['id']]);
            
            // Return success response
            echo json_encode([
                'success' => true,
                'message' => 'Login successful',
                'user' => [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'role' => $user['role']
                ],
                'session_id' => session_id()
            ]);
        } else {
            // Return error for invalid credentials
            http_response_code(401);
            echo json_encode(['error' => 'Invalid email or password']);
        }
    }

    /**
     * Handle user registration
     * 
     * @param array $data Request data containing user information
     * @return void
     */
    function handleRegister($data) {
        global $pdo;
        
        // Validate input
        if (!isset($data['name']) || !isset($data['email']) || !isset($data['password']) || !isset($data['role'])) {
            http_response_code(422);
            echo json_encode(['error' => 'Name, email, password, and role are required']);
            return;
        }
        
        $name = $data['name'];
        $email = $data['email'];
        $password = $data['password'];
        $role = $data['role'];
        
        // Validate role
        if (!in_array($role, ['farmer', 'retailer', 'public'])) {
            http_response_code(422);
            echo json_encode(['error' => 'Invalid role. Must be farmer, retailer, or public']);
            return;
        }
        
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            http_response_code(409);
            echo json_encode(['error' => 'Email already registered']);
            return;
        }
        
        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert new user
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, created_at) VALUES (?, ?, ?, ?, NOW())");
        try {
            $stmt->execute([$name, $email, $hashedPassword, $role]);
            $userId = $pdo->lastInsertId();
            
            // Create session for auto-login
            $_SESSION['user_id'] = $userId;
            $_SESSION['role'] = $role;
            $_SESSION['email'] = $email;
            $_SESSION['name'] = $name;
            $_SESSION['logged_in'] = true;
            $_SESSION['login_time'] = time();
            
            // Return success response
            echo json_encode([
                'success' => true,
                'message' => 'Registration successful',
                'user' => [
                    'id' => $userId,
                    'name' => $name,
                    'email' => $email,
                    'role' => $role
                ],
                'session_id' => session_id()
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
        // Destroy the session
        session_unset();
        session_destroy();
        
        // Return success response
        echo json_encode([
            'success' => true,
            'message' => 'Logout successful'
        ]);
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