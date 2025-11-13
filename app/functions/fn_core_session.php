<?php
// Core Session & Authentication Functions

/**
 * Initialize session with defaults
 * Permission Levels:
 * 0 = Guest (not logged in)
 * 1 = Registered (needs company)
 * 2 = Company Created (needs subscription)
 * 3 = Paid Subscriber (full access)
 * 10 = Super Admin
 */
function fn_core_session_initialise_session() {
    if (!isset($_SESSION['user_type'])) {
        $_SESSION['user_type'] = 0; // Guest
    }

    // Session timeout (1 hour)
    if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 3600)) {
        session_unset();
        session_destroy();
        $_SESSION['user_type'] = 0;
    }
    $_SESSION['LAST_ACTIVITY'] = time();
}

/**
 * Log user in
 */
function fn_core_session_log_me_in($password, $password_hash_row, $email) {
    if (password_verify($password, $password_hash_row)) {
        // Get full user data
        $user = fn_core_session_get_user_data($email);

        if (!$user) {
            return false;
        }

        // Regenerate session ID for security
        session_regenerate_id(true);

        // Set session variables
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['company_id'] = $user['company_id'];
        $_SESSION['user_type'] = fn_core_session_calculate_permission_level($user);

        // Update last login
        $query = "UPDATE users SET last_login = NOW() WHERE user_id = ?";
        fn_core_edit_row_no_redirect($query, [$user['user_id']]);

        // Redirect to dashboard
        header("Location: /dash");
        exit;
    }

    return false;
}

/**
 * Calculate user permission level
 */
function fn_core_session_calculate_permission_level($user) {
    // Super admin
    if ($user['user_group'] == 10) {
        return 10;
    }

    // Has paid subscription
    if ($user['company_id'] && fn_core_session_has_active_subscription($user['company_id'])) {
        return 3;
    }

    // Has company but no subscription
    if ($user['company_id']) {
        return 2;
    }

    // Just registered
    if ($user['status'] === 'active') {
        return 1;
    }

    // Guest
    return 0;
}

/**
 * Check if company has active subscription
 */
function fn_core_session_has_active_subscription($company_id) {
    $query = "
        SELECT COUNT(*) as count
        FROM team_subscriptions ts
        JOIN teams t ON ts.team_id = t.team_id
        WHERE t.company_id = ?
        AND ts.status IN ('active', 'trialing')
    ";
    $result = fn_core_database_row($query, [$company_id]);
    return $result['count'] > 0;
}

/**
 * Get user data by email
 */
function fn_core_session_get_user_data($email) {
    $query = "
        SELECT
            u.*,
            t.team_id,
            t.stripe_customer_id
        FROM users u
        LEFT JOIN teams t ON u.user_id = t.owner_user_id
        WHERE u.email = ?
    ";
    return fn_core_database_row($query, [$email]);
}

/**
 * Get user data by ID
 */
function fn_core_session_get_user_by_id($user_id) {
    $query = "SELECT * FROM users WHERE user_id = ?";
    return fn_core_database_row($query, [$user_id]);
}

/**
 * Get all users for a company
 */
function fn_core_session_all_users_company_id($company_id, $offset = 0, $limit = 10) {
    $query = "
        SELECT * FROM users
        WHERE company_id = ?
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
    ";
    return fn_core_database_rows($query, [$company_id, $limit, $offset]);
}

/**
 * Get all users (super admin only)
 */
function fn_core_session_all_users($offset = 0, $limit = 10) {
    $query = "
        SELECT u.*, t.name as company_name
        FROM users u
        LEFT JOIN teams t ON u.company_id = t.team_id
        ORDER BY u.created_at DESC
        LIMIT ? OFFSET ?
    ";
    return fn_core_database_rows($query, [$limit, $offset]);
}

/**
 * Create new user
 */
function fn_core_user_create($email, $password, $name = null, $company_id = null) {
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    $query = "
        INSERT INTO users (email, password_hash, name, company_id, status, created_at)
        VALUES (?, ?, ?, ?, 'active', NOW())
    ";

    return fn_core_insert_row_no_redirect($query, [
        $email,
        $password_hash,
        $name,
        $company_id
    ]);
}

/**
 * Update user
 */
function fn_core_user_update($user_id, $data) {
    $updates = [];
    $params = [];

    if (isset($data['name'])) {
        $updates[] = "name = ?";
        $params[] = $data['name'];
    }

    if (isset($data['email'])) {
        $updates[] = "email = ?";
        $params[] = $data['email'];
    }

    if (isset($data['password'])) {
        $updates[] = "password_hash = ?";
        $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
    }

    if (isset($data['avatar_url'])) {
        $updates[] = "avatar_url = ?";
        $params[] = $data['avatar_url'];
    }

    if (empty($updates)) {
        return false;
    }

    $params[] = $user_id;
    $query = "UPDATE users SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE user_id = ?";

    return fn_core_edit_row_no_redirect($query, $params);
}

/**
 * Reset password - generate recovery code
 */
function fn_core_session_reset_password($email) {
    // Check if user exists
    $user = fn_core_session_get_user_data($email);

    if (!$user) {
        return false;
    }

    // Generate recovery code
    $recovery_code = bin2hex(random_bytes(25)); // 50 characters

    // Save to database
    $query = "UPDATE users SET email_activation_code = ?, updated_at = NOW() WHERE email = ?";
    fn_core_edit_row_no_redirect($query, [$recovery_code, $email]);

    // TODO: Send email with recovery link
    // fn_core_email_send_password_reset($email, $recovery_code);

    return $recovery_code;
}

/**
 * Logout user
 */
function fn_core_session_logout() {
    session_unset();
    session_destroy();
    header("Location: /login");
    exit;
}

/**
 * Check security - verify user can access resource
 * Returns true if user owns resource OR is super admin
 */
function fn_check_security($user_id, $company_id) {
    // Super admin can access everything
    if ($_SESSION['user_type'] == 10) {
        return true;
    }

    // Check if user belongs to company
    if ($_SESSION['user_id'] == $user_id || $_SESSION['company_id'] == $company_id) {
        return true;
    }

    return false;
}

/**
 * Require specific permission level
 */
function fn_require_permission($required_level) {
    if ($_SESSION['user_type'] < $required_level) {
        header("Location: /403");
        exit;
    }
}

/**
 * Check if user is logged in
 */
function fn_is_logged_in() {
    return isset($_SESSION['user_id']) && $_SESSION['user_type'] > 0;
}

/**
 * OAuth - Create or link user account
 */
function fn_core_session_oauth_login($provider, $provider_user_id, $email, $name, $avatar = null) {
    // Check if OAuth connection exists
    $query = "
        SELECT u.*
        FROM oauth_providers op
        JOIN users u ON op.user_id = u.user_id
        WHERE op.provider = ? AND op.provider_user_id = ?
    ";
    $user = fn_core_database_row($query, [$provider, $provider_user_id]);

    if ($user) {
        // Existing OAuth user - log them in
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['company_id'] = $user['company_id'];
        $_SESSION['user_type'] = fn_core_session_calculate_permission_level($user);

        return $user['user_id'];
    }

    // Check if email exists
    $user = fn_core_session_get_user_data($email);

    if ($user) {
        // Link OAuth to existing user
        $query = "
            INSERT INTO oauth_providers (user_id, provider, provider_user_id, provider_email, provider_name, provider_avatar, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ";
        fn_core_insert_row_no_redirect($query, [$user['user_id'], $provider, $provider_user_id, $email, $name, $avatar]);

        // Log them in
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['company_id'] = $user['company_id'];
        $_SESSION['user_type'] = fn_core_session_calculate_permission_level($user);

        return $user['user_id'];
    }

    // Create new user
    $user_id = fn_core_user_create($email, null, $name, null);

    // Create OAuth link
    $query = "
        INSERT INTO oauth_providers (user_id, provider, provider_user_id, provider_email, provider_name, provider_avatar, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ";
    fn_core_insert_row_no_redirect($query, [$user_id, $provider, $provider_user_id, $email, $name, $avatar]);

    // Update user to mark as OAuth user
    $query = "UPDATE users SET oauth_provider = ?, oauth_id = ?, avatar_url = ? WHERE user_id = ?";
    fn_core_edit_row_no_redirect($query, [$provider, $provider_user_id, $avatar, $user_id]);

    // Create default team
    $query = "INSERT INTO teams (name, owner_user_id, created_at) VALUES (?, ?, NOW())";
    $team_id = fn_core_insert_row_no_redirect($query, [$name . "'s Team", $user_id]);

    // Link user to team
    $query = "UPDATE users SET company_id = ? WHERE user_id = ?";
    fn_core_edit_row_no_redirect($query, [$team_id, $user_id]);

    // Assign free plan
    $query = "
        INSERT INTO team_subscriptions (team_id, plan_id, status, created_at)
        SELECT ?, plan_id, 'active', NOW()
        FROM subscription_plans
        WHERE plan_slug = 'free'
        LIMIT 1
    ";
    fn_core_insert_row_no_redirect($query, [$team_id]);

    // Log them in
    $user = fn_core_session_get_user_by_id($user_id);
    $_SESSION['user_id'] = $user_id;
    $_SESSION['email'] = $email;
    $_SESSION['name'] = $name;
    $_SESSION['company_id'] = $team_id;
    $_SESSION['user_type'] = fn_core_session_calculate_permission_level($user);

    return $user_id;
}
