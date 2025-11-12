<?php
// Authentication API endpoints

function handleAuthRequest($action, $method, $body) {
    switch ($action) {
        case 'login':
            if ($method !== 'POST') {
                Response::error('Method not allowed', 405);
            }
            login($body);
            break;

        case 'register':
            if ($method !== 'POST') {
                Response::error('Method not allowed', 405);
            }
            register($body);
            break;

        case 'logout':
            if ($method !== 'POST') {
                Response::error('Method not allowed', 405);
            }
            logout();
            break;

        case 'me':
            if ($method !== 'GET') {
                Response::error('Method not allowed', 405);
            }
            getCurrentUserInfo();
            break;

        default:
            Response::notFound('Auth endpoint not found');
    }
}

/**
 * Login user
 */
function login($body) {
    // Validate input
    if (empty($body['email']) || empty($body['password'])) {
        Response::validationError(['email' => 'Email and password are required']);
    }

    $email = filter_var($body['email'], FILTER_SANITIZE_EMAIL);
    $password = $body['password'];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        Response::validationError(['email' => 'Invalid email format']);
    }

    try {
        $db = Database::getInstance()->getConnection();

        // Find user
        $stmt = $db->prepare("SELECT user_id, email, password_hash, name, status FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            Response::error('Invalid credentials', 401);
        }

        // Check if account is active
        if ($user['status'] !== 'active') {
            Response::error('Account is not active', 403);
        }

        // Verify password
        if (!password_verify($password, $user['password_hash'])) {
            Response::error('Invalid credentials', 401);
        }

        // Update last login
        $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
        $stmt->execute([$user['user_id']]);

        // Generate JWT token
        $token = JWT::encode([
            'user_id' => $user['user_id'],
            'email' => $user['email']
        ]);

        // Return user data and token
        Response::success('Login successful', [
            'token' => $token,
            'user' => [
                'user_id' => $user['user_id'],
                'email' => $user['email'],
                'name' => $user['name']
            ]
        ]);

    } catch (Exception $e) {
        Response::serverError('Login failed: ' . $e->getMessage());
    }
}

/**
 * Register new user
 */
function register($body) {
    // Validate input
    $errors = [];

    if (empty($body['email'])) {
        $errors['email'] = 'Email is required';
    } else {
        $email = filter_var($body['email'], FILTER_SANITIZE_EMAIL);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email format';
        }
    }

    if (empty($body['password'])) {
        $errors['password'] = 'Password is required';
    } else if (strlen($body['password']) < 6) {
        $errors['password'] = 'Password must be at least 6 characters';
    }

    if (!empty($errors)) {
        Response::validationError($errors);
    }

    $email = filter_var($body['email'], FILTER_SANITIZE_EMAIL);
    $password = $body['password'];
    $name = $body['name'] ?? null;

    try {
        $db = Database::getInstance()->getConnection();

        // Check if email already exists
        $stmt = $db->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->execute([$email]);

        if ($stmt->fetch()) {
            Response::error('Email already registered', 409);
        }

        // Hash password
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        // Insert user
        $stmt = $db->prepare("INSERT INTO users (email, password_hash, name) VALUES (?, ?, ?)");
        $stmt->execute([$email, $passwordHash, $name]);

        $userId = $db->lastInsertId();

        // Generate JWT token
        $token = JWT::encode([
            'user_id' => $userId,
            'email' => $email
        ]);

        // Return user data and token
        Response::success('Registration successful', [
            'token' => $token,
            'user' => [
                'user_id' => $userId,
                'email' => $email,
                'name' => $name
            ]
        ], 201);

    } catch (Exception $e) {
        Response::serverError('Registration failed: ' . $e->getMessage());
    }
}

/**
 * Logout (client-side token removal, just return success)
 */
function logout() {
    Response::success('Logout successful');
}

/**
 * Get current user info
 */
function getCurrentUserInfo() {
    $auth = authenticateUser();
    $userId = $auth['user_id'];

    try {
        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare("
            SELECT user_id, email, name, created_at, last_login
            FROM users
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user) {
            Response::notFound('User not found');
        }

        Response::success('User info retrieved', $user);

    } catch (Exception $e) {
        Response::serverError('Failed to get user info');
    }
}
