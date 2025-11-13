<?php
// OAuth Integration Functions

/**
 * Generate OAuth state token for CSRF protection
 */
function fn_oauth_generate_state() {
    $state = bin2hex(random_bytes(16));
    $_SESSION['oauth_state'] = $state;
    return $state;
}

/**
 * Verify OAuth state token
 */
function fn_oauth_verify_state($state) {
    if (!isset($_SESSION['oauth_state'])) {
        return false;
    }

    $valid = hash_equals($_SESSION['oauth_state'], $state);

    // Clear state after verification
    unset($_SESSION['oauth_state']);

    return $valid;
}

/**
 * Get Google OAuth authorization URL
 */
function fn_oauth_google_get_auth_url() {
    $config = require BASE_PATH . 'config.php';

    $client_id = $config['google_oauth']['client_id'] ?? null;
    $redirect_uri = $config['google_oauth']['redirect_uri'] ?? null;

    if (!$client_id || !$redirect_uri) {
        throw new Exception('Google OAuth not configured');
    }

    $state = fn_oauth_generate_state();

    $params = [
        'client_id' => $client_id,
        'redirect_uri' => $redirect_uri,
        'response_type' => 'code',
        'scope' => 'openid email profile',
        'state' => $state,
        'access_type' => 'offline',
        'prompt' => 'consent'
    ];

    return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
}

/**
 * Exchange authorization code for access token
 */
function fn_oauth_google_exchange_code($code) {
    $config = require BASE_PATH . 'config.php';

    $client_id = $config['google_oauth']['client_id'] ?? null;
    $client_secret = $config['google_oauth']['client_secret'] ?? null;
    $redirect_uri = $config['google_oauth']['redirect_uri'] ?? null;

    if (!$client_id || !$client_secret || !$redirect_uri) {
        throw new Exception('Google OAuth not configured');
    }

    $ch = curl_init('https://oauth2.googleapis.com/token');

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_POSTFIELDS => http_build_query([
            'code' => $code,
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'redirect_uri' => $redirect_uri,
            'grant_type' => 'authorization_code'
        ])
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    if ($http_code !== 200) {
        fn_log('Google OAuth token exchange failed', ['response' => $response]);
        return false;
    }

    return json_decode($response, true);
}

/**
 * Get user info from Google
 */
function fn_oauth_google_get_user_info($access_token) {
    $ch = curl_init('https://www.googleapis.com/oauth2/v2/userinfo');

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $access_token]
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    if ($http_code !== 200) {
        fn_log('Google OAuth user info failed', ['response' => $response]);
        return false;
    }

    return json_decode($response, true);
}

/**
 * Handle Google OAuth callback
 */
function fn_oauth_google_callback($code, $state) {
    // Verify state
    if (!fn_oauth_verify_state($state)) {
        return ['error' => 'Invalid state parameter'];
    }

    // Exchange code for token
    $token_data = fn_oauth_google_exchange_code($code);

    if (!$token_data || !isset($token_data['access_token'])) {
        return ['error' => 'Failed to exchange authorization code'];
    }

    // Get user info
    $user_info = fn_oauth_google_get_user_info($token_data['access_token']);

    if (!$user_info || !isset($user_info['email'])) {
        return ['error' => 'Failed to get user information'];
    }

    // Create or login user
    $user_id = fn_core_session_oauth_login(
        'google',
        $user_info['id'],
        $user_info['email'],
        $user_info['name'] ?? null,
        $user_info['picture'] ?? null
    );

    if (!$user_id) {
        return ['error' => 'Failed to create or login user'];
    }

    return ['success' => true, 'user_id' => $user_id];
}

/**
 * Get OAuth provider info for user
 */
function fn_oauth_get_providers($user_id) {
    $query = "
        SELECT
            provider,
            provider_email,
            provider_name,
            created_at
        FROM oauth_providers
        WHERE user_id = ?
        ORDER BY created_at DESC
    ";

    return fn_core_database_rows($query, [$user_id]);
}

/**
 * Unlink OAuth provider
 */
function fn_oauth_unlink_provider($user_id, $provider) {
    // Check if user has password (can't unlink if no other login method)
    $user = fn_core_session_get_user_by_id($user_id);

    if (!$user['password_hash']) {
        // Check if there are other OAuth providers
        $providers = fn_oauth_get_providers($user_id);

        if (count($providers) <= 1) {
            return ['error' => 'Cannot unlink last authentication method'];
        }
    }

    $query = "DELETE FROM oauth_providers WHERE user_id = ? AND provider = ?";
    fn_core_edit_row_no_redirect($query, [$user_id, $provider]);

    return ['success' => true];
}

/**
 * Link OAuth provider to existing account
 */
function fn_oauth_link_provider($user_id, $provider, $provider_user_id, $email, $name, $avatar = null) {
    // Check if provider is already linked to another account
    $query = "SELECT user_id FROM oauth_providers WHERE provider = ? AND provider_user_id = ?";
    $existing = fn_core_database_row($query, [$provider, $provider_user_id]);

    if ($existing && $existing['user_id'] != $user_id) {
        return ['error' => 'This account is already linked to another user'];
    }

    // Link provider
    $query = "
        INSERT INTO oauth_providers (
            user_id,
            provider,
            provider_user_id,
            provider_email,
            provider_name,
            provider_avatar,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            provider_email = VALUES(provider_email),
            provider_name = VALUES(provider_name),
            provider_avatar = VALUES(provider_avatar)
    ";

    fn_core_insert_row_no_redirect($query, [
        $user_id,
        $provider,
        $provider_user_id,
        $email,
        $name,
        $avatar
    ]);

    return ['success' => true];
}

/**
 * Check if user has OAuth provider linked
 */
function fn_oauth_has_provider($user_id, $provider) {
    $query = "SELECT COUNT(*) as count FROM oauth_providers WHERE user_id = ? AND provider = ?";
    $result = fn_core_database_row($query, [$user_id, $provider]);

    return ($result['count'] ?? 0) > 0;
}
