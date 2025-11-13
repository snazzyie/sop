<?php
// API Key Management Functions

/**
 * Generate new API key
 */
function fn_api_keys_generate() {
    return 'sk_' . bin2hex(random_bytes(24)); // 48 character key
}

/**
 * Generate API secret
 */
function fn_api_keys_generate_secret() {
    return bin2hex(random_bytes(32)); // 64 character secret
}

/**
 * Hash API secret for storage
 */
function fn_api_keys_hash_secret($secret) {
    return hash('sha256', $secret);
}

/**
 * Create new API key
 */
function fn_api_keys_create($company_id, $name, $permissions = []) {
    // Check if team can create API keys
    if (!fn_subscriptions_can_create_api_key($company_id)) {
        return ['error' => 'API access not available on your plan'];
    }

    // Check limit
    $current_count = fn_api_keys_count($company_id);
    $subscription = fn_subscriptions_get_team($company_id);
    $max_keys = $subscription['limits']['max_api_keys'] ?? 5;

    if ($max_keys !== -1 && $current_count >= $max_keys) {
        return ['error' => 'API key limit reached'];
    }

    // Generate key and secret
    $api_key = fn_api_keys_generate();
    $secret = fn_api_keys_generate_secret();
    $secret_hash = fn_api_keys_hash_secret($secret);

    // Store in database
    $query = "
        INSERT INTO api_keys (
            team_id,
            api_key,
            api_secret_hash,
            name,
            permissions,
            status,
            created_at
        ) VALUES (?, ?, ?, ?, ?, 'active', NOW())
    ";

    $key_id = fn_core_insert_row_no_redirect($query, [
        $company_id,
        $api_key,
        $secret_hash,
        $name,
        json_encode($permissions)
    ]);

    // Return key and secret (secret is only shown once)
    return [
        'api_key_id' => $key_id,
        'api_key' => $api_key,
        'api_secret' => $secret,
        'warning' => 'Save this secret securely. It will not be shown again.'
    ];
}

/**
 * Get all API keys for a team
 */
function fn_api_keys_get_all($company_id) {
    $query = "
        SELECT
            api_key_id,
            api_key,
            name,
            permissions,
            status,
            last_used_at,
            created_at,
            expires_at
        FROM api_keys
        WHERE team_id = ?
        ORDER BY created_at DESC
    ";

    $keys = fn_core_database_rows($query, [$company_id]);

    foreach ($keys as &$key) {
        $key['permissions'] = json_decode($key['permissions'], true);
        $key['api_key_masked'] = fn_api_keys_mask_key($key['api_key']);
    }

    return $keys;
}

/**
 * Get API key by ID
 */
function fn_api_keys_get_by_id($key_id, $company_id = null) {
    $query = "SELECT * FROM api_keys WHERE api_key_id = ?";
    $params = [$key_id];

    if ($company_id !== null && $_SESSION['user_type'] != 10) {
        $query .= " AND team_id = ?";
        $params[] = $company_id;
    }

    $key = fn_core_database_row($query, $params);

    if ($key) {
        $key['permissions'] = json_decode($key['permissions'], true);
    }

    return $key;
}

/**
 * Authenticate API key and secret
 */
function fn_api_keys_authenticate($api_key, $api_secret) {
    // Get key from database
    $query = "SELECT * FROM api_keys WHERE api_key = ? AND status = 'active'";
    $key = fn_core_database_row($query, [$api_key]);

    if (!$key) {
        return false;
    }

    // Check expiration
    if ($key['expires_at'] && strtotime($key['expires_at']) < time()) {
        return false;
    }

    // Verify secret
    $secret_hash = fn_api_keys_hash_secret($api_secret);

    if (!hash_equals($key['api_secret_hash'], $secret_hash)) {
        return false;
    }

    // Update last used timestamp
    fn_api_keys_update_last_used($key['api_key_id']);

    return [
        'api_key_id' => $key['api_key_id'],
        'team_id' => $key['team_id'],
        'permissions' => json_decode($key['permissions'], true),
        'name' => $key['name']
    ];
}

/**
 * Update last used timestamp
 */
function fn_api_keys_update_last_used($key_id) {
    $query = "UPDATE api_keys SET last_used_at = NOW() WHERE api_key_id = ?";
    fn_core_edit_row_no_redirect($query, [$key_id]);
}

/**
 * Revoke API key
 */
function fn_api_keys_revoke($key_id, $company_id = null) {
    $key = fn_api_keys_get_by_id($key_id, $company_id);

    if (!$key) {
        return false;
    }

    $query = "UPDATE api_keys SET status = 'revoked', updated_at = NOW() WHERE api_key_id = ?";
    return fn_core_edit_row_no_redirect($query, [$key_id]);
}

/**
 * Delete API key
 */
function fn_api_keys_delete($key_id, $company_id = null) {
    $key = fn_api_keys_get_by_id($key_id, $company_id);

    if (!$key) {
        return false;
    }

    $query = "DELETE FROM api_keys WHERE api_key_id = ?";
    return fn_core_edit_row_no_redirect($query, [$key_id]);
}

/**
 * Update API key
 */
function fn_api_keys_update($key_id, $data, $company_id = null) {
    $key = fn_api_keys_get_by_id($key_id, $company_id);

    if (!$key) {
        return false;
    }

    $updates = [];
    $params = [];

    if (isset($data['name'])) {
        $updates[] = "name = ?";
        $params[] = $data['name'];
    }

    if (isset($data['permissions'])) {
        $updates[] = "permissions = ?";
        $params[] = json_encode($data['permissions']);
    }

    if (isset($data['expires_at'])) {
        $updates[] = "expires_at = ?";
        $params[] = $data['expires_at'];
    }

    if (empty($updates)) {
        return false;
    }

    $updates[] = "updated_at = NOW()";
    $params[] = $key_id;

    $query = "UPDATE api_keys SET " . implode(', ', $updates) . " WHERE api_key_id = ?";

    return fn_core_edit_row_no_redirect($query, $params);
}

/**
 * Check if API key has permission
 */
function fn_api_keys_has_permission($key_data, $permission) {
    $permissions = $key_data['permissions'] ?? [];

    // Check for wildcard
    if (in_array('*', $permissions)) {
        return true;
    }

    // Check for specific permission
    return in_array($permission, $permissions);
}

/**
 * Count API keys for a team
 */
function fn_api_keys_count($company_id) {
    $query = "SELECT COUNT(*) as count FROM api_keys WHERE team_id = ?";
    $result = fn_core_database_row($query, [$company_id]);

    return $result['count'] ?? 0;
}

/**
 * Mask API key for display
 */
function fn_api_keys_mask_key($api_key) {
    if (strlen($api_key) <= 8) {
        return str_repeat('*', strlen($api_key));
    }

    $prefix = substr($api_key, 0, 8);
    $suffix = substr($api_key, -4);

    return $prefix . str_repeat('*', strlen($api_key) - 12) . $suffix;
}

/**
 * Rate limit check for API key
 */
function fn_api_keys_check_rate_limit($key_id, $max_requests = 100, $window_seconds = 60) {
    $cache_key = "api_rate_limit_$key_id";

    // Get current count from session/cache
    if (!isset($_SESSION[$cache_key])) {
        $_SESSION[$cache_key] = [
            'count' => 0,
            'window_start' => time()
        ];
    }

    $rate_data = $_SESSION[$cache_key];

    // Check if window has expired
    if (time() - $rate_data['window_start'] > $window_seconds) {
        $_SESSION[$cache_key] = [
            'count' => 1,
            'window_start' => time()
        ];
        return true;
    }

    // Check if limit exceeded
    if ($rate_data['count'] >= $max_requests) {
        return false;
    }

    // Increment count
    $_SESSION[$cache_key]['count']++;

    return true;
}

/**
 * Get API usage statistics
 */
function fn_api_keys_get_usage($company_id, $days = 30) {
    $query = "
        SELECT
            DATE(tracked_at) as date,
            COUNT(*) as requests
        FROM usage_tracking
        WHERE team_id = ?
        AND metric_name = 'api_requests'
        AND tracked_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY DATE(tracked_at)
        ORDER BY date DESC
    ";

    return fn_core_database_rows($query, [$company_id, $days]);
}
