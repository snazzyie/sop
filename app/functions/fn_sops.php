<?php
// SOP Management Functions

/**
 * Get all SOPs for a company
 */
function fn_sops_get_all($company_id, $offset = 0, $limit = 20, $filters = []) {
    $where_clauses = ["company_id = ?"];
    $params = [$company_id];

    // Apply filters
    if (!empty($filters['status'])) {
        $where_clauses[] = "status = ?";
        $params[] = $filters['status'];
    }

    if (!empty($filters['search'])) {
        $where_clauses[] = "(title LIKE ? OR description LIKE ?)";
        $search_term = '%' . $filters['search'] . '%';
        $params[] = $search_term;
        $params[] = $search_term;
    }

    if (!empty($filters['created_by'])) {
        $where_clauses[] = "created_by = ?";
        $params[] = $filters['created_by'];
    }

    $where_sql = implode(' AND ', $where_clauses);

    $query = "
        SELECT
            s.*,
            u.name as creator_name,
            u.email as creator_email,
            (SELECT COUNT(*) FROM sop_steps WHERE sop_id = s.sop_id) as step_count,
            (SELECT COUNT(*) FROM sop_shares WHERE sop_id = s.sop_id) as share_count
        FROM sops s
        LEFT JOIN users u ON s.created_by = u.user_id
        WHERE $where_sql
        ORDER BY s.updated_at DESC
        LIMIT ? OFFSET ?
    ";

    $params[] = $limit;
    $params[] = $offset;

    return fn_core_database_rows($query, $params);
}

/**
 * Get single SOP by ID
 */
function fn_sops_get_by_id($sop_id, $company_id = null) {
    $query = "
        SELECT
            s.*,
            u.name as creator_name,
            u.email as creator_email
        FROM sops s
        LEFT JOIN users u ON s.created_by = u.user_id
        WHERE s.sop_id = ?
    ";

    $params = [$sop_id];

    // Add company filter for non-admin users
    if ($company_id !== null && $_SESSION['user_type'] != 10) {
        $query .= " AND s.company_id = ?";
        $params[] = $company_id;
    }

    return fn_core_database_row($query, $params);
}

/**
 * Get SOP with all steps
 */
function fn_sops_get_with_steps($sop_id, $company_id = null) {
    $sop = fn_sops_get_by_id($sop_id, $company_id);

    if (!$sop) {
        return null;
    }

    // Get steps
    $sop['steps'] = fn_sops_get_steps($sop_id);

    return $sop;
}

/**
 * Get all steps for a SOP
 */
function fn_sops_get_steps($sop_id) {
    $query = "
        SELECT *
        FROM sop_steps
        WHERE sop_id = ?
        ORDER BY step_order ASC
    ";

    return fn_core_database_rows($query, [$sop_id]);
}

/**
 * Create new SOP
 */
function fn_sops_create($data, $company_id, $user_id) {
    // Check subscription limits
    if (!fn_subscriptions_can_create_sop($company_id)) {
        return ['error' => 'SOP limit reached. Please upgrade your plan.'];
    }

    $query = "
        INSERT INTO sops (
            title,
            description,
            company_id,
            created_by,
            status,
            created_at,
            updated_at
        ) VALUES (?, ?, ?, ?, 'draft', NOW(), NOW())
    ";

    $sop_id = fn_core_insert_row_no_redirect($query, [
        $data['title'] ?? 'Untitled SOP',
        $data['description'] ?? '',
        $company_id,
        $user_id
    ]);

    // Track usage
    fn_subscriptions_track_usage($company_id, 'sops_created');

    return ['sop_id' => $sop_id];
}

/**
 * Update SOP
 */
function fn_sops_update($sop_id, $data, $company_id = null) {
    // Verify ownership
    $sop = fn_sops_get_by_id($sop_id, $company_id);
    if (!$sop) {
        return false;
    }

    $updates = [];
    $params = [];

    if (isset($data['title'])) {
        $updates[] = "title = ?";
        $params[] = $data['title'];
    }

    if (isset($data['description'])) {
        $updates[] = "description = ?";
        $params[] = $data['description'];
    }

    if (isset($data['status'])) {
        $updates[] = "status = ?";
        $params[] = $data['status'];
    }

    if (isset($data['tags'])) {
        $updates[] = "tags = ?";
        $params[] = json_encode($data['tags']);
    }

    if (empty($updates)) {
        return false;
    }

    $updates[] = "updated_at = NOW()";
    $params[] = $sop_id;

    $query = "UPDATE sops SET " . implode(', ', $updates) . " WHERE sop_id = ?";

    return fn_core_edit_row_no_redirect($query, $params);
}

/**
 * Delete SOP
 */
function fn_sops_delete($sop_id, $company_id = null) {
    // Verify ownership
    $sop = fn_sops_get_by_id($sop_id, $company_id);
    if (!$sop) {
        return false;
    }

    // Delete steps first
    $query = "DELETE FROM sop_steps WHERE sop_id = ?";
    fn_core_edit_row_no_redirect($query, [$sop_id]);

    // Delete shares
    $query = "DELETE FROM sop_shares WHERE sop_id = ?";
    fn_core_edit_row_no_redirect($query, [$sop_id]);

    // Delete exports
    $query = "DELETE FROM sop_exports WHERE sop_id = ?";
    fn_core_edit_row_no_redirect($query, [$sop_id]);

    // Delete SOP
    $query = "DELETE FROM sops WHERE sop_id = ?";
    return fn_core_edit_row_no_redirect($query, [$sop_id]);
}

/**
 * Publish SOP
 */
function fn_sops_publish($sop_id, $company_id = null) {
    return fn_sops_update($sop_id, ['status' => 'published'], $company_id);
}

/**
 * Unpublish SOP
 */
function fn_sops_unpublish($sop_id, $company_id = null) {
    return fn_sops_update($sop_id, ['status' => 'draft'], $company_id);
}

/**
 * Duplicate SOP
 */
function fn_sops_duplicate($sop_id, $company_id, $user_id) {
    // Get original SOP
    $original = fn_sops_get_with_steps($sop_id, $company_id);

    if (!$original) {
        return false;
    }

    // Check limits
    if (!fn_subscriptions_can_create_sop($company_id)) {
        return ['error' => 'SOP limit reached. Please upgrade your plan.'];
    }

    // Create new SOP
    $new_sop = fn_sops_create([
        'title' => $original['title'] . ' (Copy)',
        'description' => $original['description']
    ], $company_id, $user_id);

    if (isset($new_sop['error'])) {
        return $new_sop;
    }

    $new_sop_id = $new_sop['sop_id'];

    // Copy steps
    foreach ($original['steps'] as $step) {
        fn_sops_add_step($new_sop_id, [
            'step_order' => $step['step_order'],
            'action_type' => $step['action_type'],
            'description' => $step['description'],
            'element_selector' => $step['element_selector'],
            'element_text' => $step['element_text'],
            'screenshot_url' => $step['screenshot_url'],
            'url' => $step['url']
        ]);
    }

    return ['sop_id' => $new_sop_id];
}

/**
 * Add step to SOP
 */
function fn_sops_add_step($sop_id, $data) {
    $query = "
        INSERT INTO sop_steps (
            sop_id,
            step_order,
            action_type,
            description,
            element_selector,
            element_text,
            screenshot_url,
            url,
            metadata,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ";

    return fn_core_insert_row_no_redirect($query, [
        $sop_id,
        $data['step_order'] ?? 1,
        $data['action_type'] ?? 'navigate',
        $data['description'] ?? '',
        $data['element_selector'] ?? null,
        $data['element_text'] ?? null,
        $data['screenshot_url'] ?? null,
        $data['url'] ?? null,
        isset($data['metadata']) ? json_encode($data['metadata']) : null
    ]);
}

/**
 * Update step
 */
function fn_sops_update_step($step_id, $data) {
    $updates = [];
    $params = [];

    if (isset($data['step_order'])) {
        $updates[] = "step_order = ?";
        $params[] = $data['step_order'];
    }

    if (isset($data['description'])) {
        $updates[] = "description = ?";
        $params[] = $data['description'];
    }

    if (isset($data['screenshot_url'])) {
        $updates[] = "screenshot_url = ?";
        $params[] = $data['screenshot_url'];
    }

    if (empty($updates)) {
        return false;
    }

    $params[] = $step_id;
    $query = "UPDATE sop_steps SET " . implode(', ', $updates) . " WHERE step_id = ?";

    return fn_core_edit_row_no_redirect($query, $params);
}

/**
 * Delete step
 */
function fn_sops_delete_step($step_id) {
    $query = "DELETE FROM sop_steps WHERE step_id = ?";
    return fn_core_edit_row_no_redirect($query, [$step_id]);
}

/**
 * Reorder steps
 */
function fn_sops_reorder_steps($sop_id, $step_order_array) {
    foreach ($step_order_array as $index => $step_id) {
        $query = "UPDATE sop_steps SET step_order = ? WHERE step_id = ? AND sop_id = ?";
        fn_core_edit_row_no_redirect($query, [$index + 1, $step_id, $sop_id]);
    }

    return true;
}

/**
 * Count SOPs for a company
 */
function fn_sops_count($company_id, $filters = []) {
    $where_clauses = ["company_id = ?"];
    $params = [$company_id];

    if (!empty($filters['status'])) {
        $where_clauses[] = "status = ?";
        $params[] = $filters['status'];
    }

    $where_sql = implode(' AND ', $where_clauses);
    $query = "SELECT COUNT(*) as count FROM sops WHERE $where_sql";

    $result = fn_core_database_row($query, $params);
    return $result['count'] ?? 0;
}

/**
 * Get SOP statistics
 */
function fn_sops_get_stats($company_id) {
    return [
        'total' => fn_sops_count($company_id),
        'published' => fn_sops_count($company_id, ['status' => 'published']),
        'draft' => fn_sops_count($company_id, ['status' => 'draft']),
    ];
}
