<?php
// Share Link Management Functions

/**
 * Generate unique share token
 */
function fn_shares_generate_token() {
    return bin2hex(random_bytes(16)); // 32 character token
}

/**
 * Create share link for SOP
 */
function fn_shares_create($sop_id, $created_by, $options = []) {
    // Verify SOP exists and user has access
    $company_id = $_SESSION['company_id'] ?? null;
    $sop = fn_sops_get_by_id($sop_id, $company_id);

    if (!$sop) {
        return ['error' => 'SOP not found'];
    }

    // Generate unique token
    $token = fn_shares_generate_token();

    // Set defaults
    $password = $options['password'] ?? null;
    $password_hash = $password ? password_hash($password, PASSWORD_DEFAULT) : null;
    $expires_at = $options['expires_at'] ?? null;
    $max_views = $options['max_views'] ?? null;
    $allow_comments = $options['allow_comments'] ?? false;

    $query = "
        INSERT INTO sop_shares (
            sop_id,
            share_token,
            password_hash,
            created_by,
            expires_at,
            max_views,
            view_count,
            allow_comments,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, 0, ?, NOW())
    ";

    $share_id = fn_core_insert_row_no_redirect($query, [
        $sop_id,
        $token,
        $password_hash,
        $created_by,
        $expires_at,
        $max_views,
        $allow_comments
    ]);

    return [
        'share_id' => $share_id,
        'share_token' => $token,
        'share_url' => fn_shares_get_url($token)
    ];
}

/**
 * Get share link URL
 */
function fn_shares_get_url($token) {
    $config = require BASE_PATH . 'config.php';
    $base_url = $config['app_url'] ?? 'http://localhost';

    return rtrim($base_url, '/') . '/share/' . $token;
}

/**
 * Get share by token
 */
function fn_shares_get_by_token($token) {
    $query = "
        SELECT
            ss.*,
            s.title as sop_title,
            s.description as sop_description,
            s.status as sop_status,
            u.name as creator_name
        FROM sop_shares ss
        JOIN sops s ON ss.sop_id = s.sop_id
        LEFT JOIN users u ON ss.created_by = u.user_id
        WHERE ss.share_token = ?
    ";

    return fn_core_database_row($query, [$token]);
}

/**
 * Get all shares for a SOP
 */
function fn_shares_get_for_sop($sop_id, $company_id = null) {
    // Verify access
    $sop = fn_sops_get_by_id($sop_id, $company_id);

    if (!$sop) {
        return [];
    }

    $query = "
        SELECT
            ss.*,
            u.name as creator_name
        FROM sop_shares ss
        LEFT JOIN users u ON ss.created_by = u.user_id
        WHERE ss.sop_id = ?
        ORDER BY ss.created_at DESC
    ";

    return fn_core_database_rows($query, [$sop_id]);
}

/**
 * Verify share access
 */
function fn_shares_verify_access($token, $password = null) {
    $share = fn_shares_get_by_token($token);

    if (!$share) {
        return ['error' => 'Share link not found'];
    }

    // Check if expired
    if ($share['expires_at'] && strtotime($share['expires_at']) < time()) {
        return ['error' => 'Share link has expired'];
    }

    // Check max views
    if ($share['max_views'] && $share['view_count'] >= $share['max_views']) {
        return ['error' => 'Share link has reached maximum views'];
    }

    // Check password
    if ($share['password_hash']) {
        if (!$password) {
            return ['error' => 'Password required', 'requires_password' => true];
        }

        if (!password_verify($password, $share['password_hash'])) {
            return ['error' => 'Incorrect password'];
        }
    }

    // Check if SOP is published or accessible
    if ($share['sop_status'] !== 'published') {
        return ['error' => 'SOP is not available'];
    }

    return ['success' => true, 'share' => $share];
}

/**
 * Increment share view count
 */
function fn_shares_increment_views($share_id) {
    $query = "UPDATE sop_shares SET view_count = view_count + 1, last_viewed_at = NOW() WHERE share_id = ?";
    fn_core_edit_row_no_redirect($query, [$share_id]);
}

/**
 * Get SOP content via share link
 */
function fn_shares_get_sop_content($token, $password = null) {
    // Verify access
    $access = fn_shares_verify_access($token, $password);

    if (isset($access['error'])) {
        return $access;
    }

    $share = $access['share'];

    // Increment view count
    fn_shares_increment_views($share['share_id']);

    // Get full SOP with steps
    $sop = fn_sops_get_with_steps($share['sop_id']);

    return [
        'success' => true,
        'sop' => $sop,
        'share' => [
            'allow_comments' => $share['allow_comments'],
            'creator_name' => $share['creator_name']
        ]
    ];
}

/**
 * Delete share link
 */
function fn_shares_delete($share_id, $company_id = null) {
    // Get share to verify ownership
    $query = "
        SELECT ss.*, s.company_id
        FROM sop_shares ss
        JOIN sops s ON ss.sop_id = s.sop_id
        WHERE ss.share_id = ?
    ";

    $share = fn_core_database_row($query, [$share_id]);

    if (!$share) {
        return false;
    }

    // Check security
    if ($company_id !== null && !fn_check_security($share['created_by'], $share['company_id'])) {
        return false;
    }

    $query = "DELETE FROM sop_shares WHERE share_id = ?";
    return fn_core_edit_row_no_redirect($query, [$share_id]);
}

/**
 * Update share link
 */
function fn_shares_update($share_id, $data, $company_id = null) {
    // Get share to verify ownership
    $query = "
        SELECT ss.*, s.company_id
        FROM sop_shares ss
        JOIN sops s ON ss.sop_id = s.sop_id
        WHERE ss.share_id = ?
    ";

    $share = fn_core_database_row($query, [$share_id]);

    if (!$share) {
        return false;
    }

    // Check security
    if ($company_id !== null && !fn_check_security($share['created_by'], $share['company_id'])) {
        return false;
    }

    $updates = [];
    $params = [];

    if (isset($data['expires_at'])) {
        $updates[] = "expires_at = ?";
        $params[] = $data['expires_at'];
    }

    if (isset($data['max_views'])) {
        $updates[] = "max_views = ?";
        $params[] = $data['max_views'];
    }

    if (isset($data['allow_comments'])) {
        $updates[] = "allow_comments = ?";
        $params[] = $data['allow_comments'];
    }

    if (isset($data['password'])) {
        $updates[] = "password_hash = ?";
        $params[] = $data['password'] ? password_hash($data['password'], PASSWORD_DEFAULT) : null;
    }

    if (empty($updates)) {
        return false;
    }

    $params[] = $share_id;
    $query = "UPDATE sop_shares SET " . implode(', ', $updates) . " WHERE share_id = ?";

    return fn_core_edit_row_no_redirect($query, $params);
}

/**
 * Get share statistics
 */
function fn_shares_get_stats($company_id) {
    $query = "
        SELECT
            COUNT(*) as total_shares,
            SUM(view_count) as total_views,
            COUNT(CASE WHEN expires_at > NOW() OR expires_at IS NULL THEN 1 END) as active_shares
        FROM sop_shares ss
        JOIN sops s ON ss.sop_id = s.sop_id
        WHERE s.company_id = ?
    ";

    return fn_core_database_row($query, [$company_id]);
}
