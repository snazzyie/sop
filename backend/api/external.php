<?php
// External API endpoints for third-party app integration
// Requires API key authentication

function handleExternalRequest($action, $param, $method, $body) {
    // Authenticate with API key
    $apiAuth = authenticateApiKey();

    if (!$apiAuth) {
        Response::unauthorized('Invalid or missing API key');
    }

    $teamId = $apiAuth['team_id'];
    $permissions = $apiAuth['permissions'];

    // Track API usage
    Subscription::trackUsage($teamId, 'api_calls', 1);

    // Check API rate limits
    if (!Subscription::canPerformAction($teamId, 'api_call')) {
        Response::error('API rate limit exceeded. Upgrade your plan for higher limits.', 429);
    }

    switch ($action) {
        case 'sops':
            if (!checkPermission($permissions, 'read_sops')) {
                Response::forbidden('API key does not have permission to read SOPs');
            }

            if ($method === 'GET' && $param) {
                externalGetSOP($param, $teamId);
            } elseif ($method === 'GET') {
                externalListSOPs($teamId);
            } else {
                Response::error('Method not allowed', 405);
            }
            break;

        case 'steps':
            if (!checkPermission($permissions, 'read_sops')) {
                Response::forbidden('API key does not have permission to read steps');
            }

            if ($method === 'GET' && $param) {
                externalGetSteps($param, $teamId);
            } else {
                Response::error('Method not allowed', 405);
            }
            break;

        default:
            Response::notFound('External API endpoint not found');
    }
}

/**
 * Authenticate using API key
 */
function authenticateApiKey() {
    // Get API key from header
    $apiKey = null;

    if (isset($_SERVER['HTTP_X_API_KEY'])) {
        $apiKey = $_SERVER['HTTP_X_API_KEY'];
    } elseif (isset($_GET['api_key'])) {
        $apiKey = $_GET['api_key'];
    }

    if (!$apiKey) {
        return false;
    }

    try {
        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare("
            SELECT
                ak.*,
                t.owner_user_id
            FROM api_keys ak
            JOIN teams t ON ak.team_id = t.team_id
            WHERE ak.api_key = ?
            AND ak.is_active = TRUE
            AND (ak.expires_at IS NULL OR ak.expires_at > NOW())
        ");

        $stmt->execute([$apiKey]);
        $keyData = $stmt->fetch();

        if (!$keyData) {
            return false;
        }

        // Update last used
        $stmt = $db->prepare("
            UPDATE api_keys
            SET last_used_at = NOW(), last_used_ip = ?
            WHERE api_key_id = ?
        ");
        $stmt->execute([$_SERVER['REMOTE_ADDR'] ?? null, $keyData['api_key_id']]);

        // Decode permissions
        $keyData['permissions'] = json_decode($keyData['permissions'], true) ?? [];

        return $keyData;

    } catch (Exception $e) {
        error_log('API key auth error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Check if API key has specific permission
 */
function checkPermission($permissions, $required) {
    if (in_array('*', $permissions)) {
        return true; // Wildcard permission
    }

    return in_array($required, $permissions);
}

/**
 * List SOPs (external API)
 */
function externalListSOPs($teamId) {
    try {
        $db = Database::getInstance()->getConnection();

        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $perPage = isset($_GET['per_page']) ? min(100, (int)$_GET['per_page']) : 20;
        $status = isset($_GET['status']) ? $_GET['status'] : 'published';
        $offset = ($page - 1) * $perPage;

        // Only return published SOPs for external API (or those owned by the team)
        $stmt = $db->prepare("
            SELECT
                sop_id,
                title,
                description,
                status,
                total_steps,
                views_count,
                created_at,
                updated_at
            FROM sops
            WHERE (
                user_id IN (SELECT user_id FROM team_members WHERE team_id = ?)
                OR user_id = (SELECT owner_user_id FROM teams WHERE team_id = ?)
            )
            AND status = ?
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ");

        $stmt->execute([$teamId, $teamId, $status, $perPage, $offset]);
        $sops = $stmt->fetchAll();

        // Get total count
        $stmt = $db->prepare("
            SELECT COUNT(*) as total
            FROM sops
            WHERE (
                user_id IN (SELECT user_id FROM team_members WHERE team_id = ?)
                OR user_id = (SELECT owner_user_id FROM teams WHERE team_id = ?)
            )
            AND status = ?
        ");
        $stmt->execute([$teamId, $teamId, $status]);
        $total = $stmt->fetch()['total'];

        Response::success('SOPs retrieved', [
            'sops' => $sops,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => ceil($total / $perPage)
            ]
        ]);

    } catch (Exception $e) {
        Response::serverError('Failed to list SOPs: ' . $e->getMessage());
    }
}

/**
 * Get single SOP (external API)
 */
function externalGetSOP($sopId, $teamId) {
    try {
        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare("
            SELECT
                s.*,
                u.name as author_name
            FROM sops s
            JOIN users u ON s.user_id = u.user_id
            WHERE s.sop_id = ?
            AND (
                s.user_id IN (SELECT user_id FROM team_members WHERE team_id = ?)
                OR s.user_id = (SELECT owner_user_id FROM teams WHERE team_id = ?)
            )
            AND s.status = 'published'
        ");

        $stmt->execute([$sopId, $teamId, $teamId]);
        $sop = $stmt->fetch();

        if (!$sop) {
            Response::notFound('SOP not found or not accessible');
        }

        // Get steps
        $stmt = $db->prepare("
            SELECT
                step_number,
                action_type,
                element_data,
                page_data,
                screenshot_path,
                ai_description,
                action_verb,
                tips
            FROM steps
            WHERE sop_id = ?
            ORDER BY step_number ASC
        ");

        $stmt->execute([$sopId]);
        $steps = $stmt->fetchAll();

        // Decode JSON and build full URLs
        foreach ($steps as &$step) {
            $step['element_data'] = json_decode($step['element_data'], true);
            $step['page_data'] = json_decode($step['page_data'], true);

            if ($step['screenshot_path']) {
                $step['screenshot_url'] = BASE_URL . $step['screenshot_path'];
            }
        }

        $sop['branding'] = json_decode($sop['branding'], true);
        $sop['metadata'] = json_decode($sop['metadata'], true);

        Response::success('SOP retrieved', [
            'sop' => $sop,
            'steps' => $steps
        ]);

    } catch (Exception $e) {
        Response::serverError('Failed to get SOP: ' . $e->getMessage());
    }
}

/**
 * Get steps for a SOP (external API)
 */
function externalGetSteps($sopId, $teamId) {
    try {
        $db = Database::getInstance()->getConnection();

        // Verify access to SOP
        $stmt = $db->prepare("
            SELECT sop_id
            FROM sops
            WHERE sop_id = ?
            AND (
                user_id IN (SELECT user_id FROM team_members WHERE team_id = ?)
                OR user_id = (SELECT owner_user_id FROM teams WHERE team_id = ?)
            )
            AND status = 'published'
        ");

        $stmt->execute([$sopId, $teamId, $teamId]);

        if (!$stmt->fetch()) {
            Response::notFound('SOP not found or not accessible');
        }

        // Get steps
        $stmt = $db->prepare("
            SELECT
                step_id,
                step_number,
                action_type,
                element_data,
                page_data,
                screenshot_path,
                ai_description,
                action_verb,
                tips
            FROM steps
            WHERE sop_id = ?
            ORDER BY step_number ASC
        ");

        $stmt->execute([$sopId]);
        $steps = $stmt->fetchAll();

        // Decode JSON and build URLs
        foreach ($steps as &$step) {
            $step['element_data'] = json_decode($step['element_data'], true);
            $step['page_data'] = json_decode($step['page_data'], true);

            if ($step['screenshot_path']) {
                $step['screenshot_url'] = BASE_URL . $step['screenshot_path'];
            }
        }

        Response::success('Steps retrieved', [
            'sop_id' => $sopId,
            'steps' => $steps,
            'total_steps' => count($steps)
        ]);

    } catch (Exception $e) {
        Response::serverError('Failed to get steps: ' . $e->getMessage());
    }
}
