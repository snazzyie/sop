<?php
// OAuth Authentication endpoints (Google, GitHub, etc.)

require_once '../config/oauth.php';

function handleOAuthRequest($provider, $action, $method, $body) {
    if ($provider === 'google') {
        handleGoogleOAuth($action, $method);
    } elseif ($provider === 'github') {
        handleGitHubOAuth($action, $method);
    } else {
        Response::error('Unsupported OAuth provider', 400);
    }
}

/**
 * Handle Google OAuth flow
 */
function handleGoogleOAuth($action, $method) {
    switch ($action) {
        case 'login':
            if ($method === 'GET') {
                initiateGoogleOAuth();
            } else {
                Response::error('Method not allowed', 405);
            }
            break;

        case 'callback':
            if ($method === 'GET') {
                handleGoogleCallback();
            } else {
                Response::error('Method not allowed', 405);
            }
            break;

        default:
            Response::notFound('OAuth endpoint not found');
    }
}

/**
 * Initiate Google OAuth login
 */
function initiateGoogleOAuth() {
    $authUrl = GoogleOAuth::getAuthorizationUrl();

    // Redirect to Google
    header('Location: ' . $authUrl);
    exit;
}

/**
 * Handle Google OAuth callback
 */
function handleGoogleCallback() {
    $code = $_GET['code'] ?? null;
    $state = $_GET['state'] ?? null;
    $error = $_GET['error'] ?? null;

    // Handle errors
    if ($error) {
        redirectWithError('OAuth error: ' . $error);
        return;
    }

    if (!$code || !$state) {
        redirectWithError('Missing code or state');
        return;
    }

    // Verify state
    if (!GoogleOAuth::verifyState($state)) {
        redirectWithError('Invalid state parameter');
        return;
    }

    try {
        // Exchange code for tokens
        $tokenData = GoogleOAuth::exchangeCodeForToken($code);
        $accessToken = $tokenData['access_token'];
        $refreshToken = $tokenData['refresh_token'] ?? null;

        // Get user info
        $userInfo = GoogleOAuth::getUserInfo($accessToken);

        $googleId = $userInfo['id'];
        $email = $userInfo['email'];
        $name = $userInfo['name'];
        $avatar = $userInfo['picture'] ?? null;

        $db = Database::getInstance()->getConnection();

        // Check if OAuth connection exists
        $stmt = $db->prepare("
            SELECT u.*, op.oauth_id
            FROM oauth_providers op
            JOIN users u ON op.user_id = u.user_id
            WHERE op.provider = 'google' AND op.provider_user_id = ?
        ");
        $stmt->execute([$googleId]);
        $existingUser = $stmt->fetch();

        if ($existingUser) {
            // User exists - update tokens and log them in
            $userId = $existingUser['user_id'];

            $stmt = $db->prepare("
                UPDATE oauth_providers
                SET access_token = ?, refresh_token = ?, provider_email = ?, provider_name = ?, provider_avatar = ?
                WHERE oauth_id = ?
            ");
            $stmt->execute([$accessToken, $refreshToken, $email, $name, $avatar, $existingUser['oauth_id']]);

        } else {
            // Check if user with this email already exists
            $stmt = $db->prepare("SELECT user_id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $emailUser = $stmt->fetch();

            if ($emailUser) {
                // Link OAuth to existing user
                $userId = $emailUser['user_id'];

                $stmt = $db->prepare("
                    INSERT INTO oauth_providers (user_id, provider, provider_user_id, provider_email, provider_name, provider_avatar, access_token, refresh_token)
                    VALUES (?, 'google', ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$userId, $googleId, $email, $name, $avatar, $accessToken, $refreshToken]);

                // Update user avatar if not set
                $stmt = $db->prepare("UPDATE users SET avatar_url = ? WHERE user_id = ? AND avatar_url IS NULL");
                $stmt->execute([$avatar, $userId]);

            } else {
                // Create new user
                $stmt = $db->prepare("
                    INSERT INTO users (email, password_hash, name, oauth_provider, oauth_id, avatar_url, status)
                    VALUES (?, NULL, ?, 'google', ?, ?, 'active')
                ");
                $stmt->execute([$email, $name, $googleId, $avatar]);
                $userId = $db->lastInsertId();

                // Create OAuth provider link
                $stmt = $db->prepare("
                    INSERT INTO oauth_providers (user_id, provider, provider_user_id, provider_email, provider_name, provider_avatar, access_token, refresh_token)
                    VALUES (?, 'google', ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$userId, $googleId, $email, $name, $avatar, $accessToken, $refreshToken]);

                // Create default team for new user
                $stmt = $db->prepare("INSERT INTO teams (name, owner_user_id) VALUES (?, ?)");
                $stmt->execute([$name . "'s Team", $userId]);
                $teamId = $db->lastInsertId();

                // Assign free plan
                $stmt = $db->prepare("
                    INSERT INTO team_subscriptions (team_id, plan_id, status)
                    SELECT ?, plan_id, 'active'
                    FROM subscription_plans
                    WHERE plan_slug = 'free'
                    LIMIT 1
                ");
                $stmt->execute([$teamId]);
            }
        }

        // Update last login
        $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
        $stmt->execute([$userId]);

        // Generate JWT token
        $token = JWT::encode([
            'user_id' => $userId,
            'email' => $email
        ]);

        // Get user data
        $stmt = $db->prepare("SELECT user_id, email, name, avatar_url FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        // Redirect to frontend with token
        redirectWithSuccess($token, $user);

    } catch (Exception $e) {
        error_log('Google OAuth error: ' . $e->getMessage());
        redirectWithError('OAuth failed: ' . $e->getMessage());
    }
}

/**
 * Redirect to frontend with success
 */
function redirectWithSuccess($token, $user) {
    $redirectUrl = BASE_URL . '/pages/oauth-callback.html';
    $params = http_build_query([
        'success' => 'true',
        'token' => $token,
        'user' => json_encode($user)
    ]);

    header('Location: ' . $redirectUrl . '?' . $params);
    exit;
}

/**
 * Redirect to frontend with error
 */
function redirectWithError($message) {
    $redirectUrl = BASE_URL . '/pages/login.html';
    $params = http_build_query([
        'error' => $message
    ]);

    header('Location: ' . $redirectUrl . '?' . $params);
    exit;
}

/**
 * Handle GitHub OAuth flow (similar pattern)
 */
function handleGitHubOAuth($action, $method) {
    // Similar implementation to Google OAuth
    // Left as exercise or for future implementation
    Response::error('GitHub OAuth not yet implemented', 501);
}
