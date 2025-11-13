<?php
// Recording Session Management Functions

/**
 * Get all recording sessions for a company
 */
function fn_sessions_get_all($company_id, $offset = 0, $limit = 20, $filters = []) {
    $where_clauses = ["company_id = ?"];
    $params = [$company_id];

    if (!empty($filters['status'])) {
        $where_clauses[] = "status = ?";
        $params[] = $filters['status'];
    }

    if (!empty($filters['user_id'])) {
        $where_clauses[] = "user_id = ?";
        $params[] = $filters['user_id'];
    }

    $where_sql = implode(' AND ', $where_clauses);

    $query = "
        SELECT
            rs.*,
            u.name as user_name,
            u.email as user_email,
            s.title as sop_title
        FROM recording_sessions rs
        LEFT JOIN users u ON rs.user_id = u.user_id
        LEFT JOIN sops s ON rs.sop_id = s.sop_id
        WHERE $where_sql
        ORDER BY rs.created_at DESC
        LIMIT ? OFFSET ?
    ";

    $params[] = $limit;
    $params[] = $offset;

    return fn_core_database_rows($query, $params);
}

/**
 * Get single recording session by ID
 */
function fn_sessions_get_by_id($session_id, $company_id = null) {
    $query = "
        SELECT
            rs.*,
            u.name as user_name,
            u.email as user_email,
            s.title as sop_title
        FROM recording_sessions rs
        LEFT JOIN users u ON rs.user_id = u.user_id
        LEFT JOIN sops s ON rs.sop_id = s.sop_id
        WHERE rs.session_id = ?
    ";

    $params = [$session_id];

    if ($company_id !== null && $_SESSION['user_type'] != 10) {
        $query .= " AND rs.company_id = ?";
        $params[] = $company_id;
    }

    return fn_core_database_row($query, $params);
}

/**
 * Create new recording session
 */
function fn_sessions_create($company_id, $user_id, $data = []) {
    $query = "
        INSERT INTO recording_sessions (
            company_id,
            user_id,
            status,
            start_url,
            created_at,
            updated_at
        ) VALUES (?, ?, 'recording', ?, NOW(), NOW())
    ";

    $session_id = fn_core_insert_row_no_redirect($query, [
        $company_id,
        $user_id,
        $data['start_url'] ?? null
    ]);

    return ['session_id' => $session_id];
}

/**
 * Update recording session
 */
function fn_sessions_update($session_id, $data, $company_id = null) {
    // Verify ownership
    $session = fn_sessions_get_by_id($session_id, $company_id);
    if (!$session) {
        return false;
    }

    $updates = [];
    $params = [];

    if (isset($data['status'])) {
        $updates[] = "status = ?";
        $params[] = $data['status'];
    }

    if (isset($data['sop_id'])) {
        $updates[] = "sop_id = ?";
        $params[] = $data['sop_id'];
    }

    if (isset($data['end_url'])) {
        $updates[] = "end_url = ?";
        $params[] = $data['end_url'];
    }

    if (isset($data['total_steps'])) {
        $updates[] = "total_steps = ?";
        $params[] = $data['total_steps'];
    }

    if (isset($data['duration'])) {
        $updates[] = "duration = ?";
        $params[] = $data['duration'];
    }

    if (empty($updates)) {
        return false;
    }

    $updates[] = "updated_at = NOW()";
    $params[] = $session_id;

    $query = "UPDATE recording_sessions SET " . implode(', ', $updates) . " WHERE session_id = ?";

    return fn_core_edit_row_no_redirect($query, $params);
}

/**
 * Stop recording session
 */
function fn_sessions_stop($session_id, $company_id = null) {
    return fn_sessions_update($session_id, ['status' => 'completed'], $company_id);
}

/**
 * Pause recording session
 */
function fn_sessions_pause($session_id, $company_id = null) {
    return fn_sessions_update($session_id, ['status' => 'paused'], $company_id);
}

/**
 * Resume recording session
 */
function fn_sessions_resume($session_id, $company_id = null) {
    return fn_sessions_update($session_id, ['status' => 'recording'], $company_id);
}

/**
 * Delete recording session
 */
function fn_sessions_delete($session_id, $company_id = null) {
    // Verify ownership
    $session = fn_sessions_get_by_id($session_id, $company_id);
    if (!$session) {
        return false;
    }

    $query = "DELETE FROM recording_sessions WHERE session_id = ?";
    return fn_core_edit_row_no_redirect($query, [$session_id]);
}

/**
 * Convert session to SOP
 */
function fn_sessions_convert_to_sop($session_id, $title, $description, $company_id, $user_id) {
    // Get session
    $session = fn_sessions_get_by_id($session_id, $company_id);
    if (!$session || $session['status'] !== 'completed') {
        return ['error' => 'Session not found or not completed'];
    }

    // Create SOP
    $sop_result = fn_sops_create([
        'title' => $title,
        'description' => $description
    ], $company_id, $user_id);

    if (isset($sop_result['error'])) {
        return $sop_result;
    }

    $sop_id = $sop_result['sop_id'];

    // Update session with SOP ID
    fn_sessions_update($session_id, ['sop_id' => $sop_id], $company_id);

    return ['sop_id' => $sop_id, 'session_id' => $session_id];
}

/**
 * Add action to recording session
 */
function fn_sessions_add_action($session_id, $action_data) {
    $query = "
        INSERT INTO session_actions (
            session_id,
            action_type,
            element_selector,
            element_text,
            input_value,
            url,
            screenshot_url,
            timestamp,
            metadata,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ";

    return fn_core_insert_row_no_redirect($query, [
        $session_id,
        $action_data['action_type'] ?? 'unknown',
        $action_data['element_selector'] ?? null,
        $action_data['element_text'] ?? null,
        $action_data['input_value'] ?? null,
        $action_data['url'] ?? null,
        $action_data['screenshot_url'] ?? null,
        $action_data['timestamp'] ?? time(),
        isset($action_data['metadata']) ? json_encode($action_data['metadata']) : null
    ]);
}

/**
 * Get all actions for a session
 */
function fn_sessions_get_actions($session_id) {
    $query = "
        SELECT *
        FROM session_actions
        WHERE session_id = ?
        ORDER BY timestamp ASC
    ";

    return fn_core_database_rows($query, [$session_id]);
}

/**
 * Get active session for user
 */
function fn_sessions_get_active($user_id, $company_id) {
    $query = "
        SELECT *
        FROM recording_sessions
        WHERE user_id = ?
        AND company_id = ?
        AND status = 'recording'
        ORDER BY created_at DESC
        LIMIT 1
    ";

    return fn_core_database_row($query, [$user_id, $company_id]);
}

/**
 * Count sessions for a company
 */
function fn_sessions_count($company_id, $filters = []) {
    $where_clauses = ["company_id = ?"];
    $params = [$company_id];

    if (!empty($filters['status'])) {
        $where_clauses[] = "status = ?";
        $params[] = $filters['status'];
    }

    $where_sql = implode(' AND ', $where_clauses);
    $query = "SELECT COUNT(*) as count FROM recording_sessions WHERE $where_sql";

    $result = fn_core_database_row($query, $params);
    return $result['count'] ?? 0;
}

/**
 * Get session statistics
 */
function fn_sessions_get_stats($company_id) {
    return [
        'total' => fn_sessions_count($company_id),
        'recording' => fn_sessions_count($company_id, ['status' => 'recording']),
        'completed' => fn_sessions_count($company_id, ['status' => 'completed']),
        'paused' => fn_sessions_count($company_id, ['status' => 'paused']),
    ];
}
