<?php
// SOPs Management API endpoints

function handleSopsRequest($action, $param, $method, $body) {
    switch ($action) {
        case '':
        case 'list':
            if ($method === 'GET') {
                listSOPs();
            } else {
                Response::error('Method not allowed', 405);
            }
            break;

        case 'view':
            if ($method === 'GET' && !empty($param)) {
                viewSOP($param);
            } else {
                Response::notFound('SOP not found');
            }
            break;

        case 'update':
            if ($method === 'PUT' && !empty($param)) {
                updateSOP($param, $body);
            } else {
                Response::error('Method not allowed', 405);
            }
            break;

        case 'delete':
            if ($method === 'DELETE' && !empty($param)) {
                deleteSOP($param);
            } else {
                Response::error('Method not allowed', 405);
            }
            break;

        default:
            // Try to parse as /api/sops/:sopId
            if (is_numeric($action)) {
                if ($method === 'GET') {
                    viewSOP($action);
                } else if ($method === 'PUT' || $method === 'PATCH') {
                    updateSOP($action, $body);
                } else if ($method === 'DELETE') {
                    deleteSOP($action);
                } else {
                    Response::error('Method not allowed', 405);
                }
            } else {
                Response::notFound('SOP endpoint not found');
            }
    }
}

/**
 * List all SOPs for authenticated user
 */
function listSOPs() {
    $auth = authenticateUser();
    $userId = $auth['user_id'];

    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 20;
    $status = isset($_GET['status']) ? $_GET['status'] : null;
    $search = isset($_GET['search']) ? $_GET['search'] : null;

    $page = max(1, $page);
    $perPage = min(100, max(1, $perPage));
    $offset = ($page - 1) * $perPage;

    try {
        $db = Database::getInstance()->getConnection();

        // Build query
        $where = ["user_id = ?"];
        $params = [$userId];

        if ($status && in_array($status, ['draft', 'published', 'archived'])) {
            $where[] = "status = ?";
            $params[] = $status;
        }

        if ($search) {
            $where[] = "(title LIKE ? OR description LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        $whereClause = implode(' AND ', $where);

        // Get total count
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM sops WHERE $whereClause");
        $stmt->execute($params);
        $total = $stmt->fetch()['total'];

        // Get SOPs
        $stmt = $db->prepare("
            SELECT
                sop_id, title, description, status, total_steps,
                views_count, created_at, updated_at
            FROM sops
            WHERE $whereClause
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ");

        $params[] = $perPage;
        $params[] = $offset;
        $stmt->execute($params);

        $sops = $stmt->fetchAll();

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
 * View single SOP with all steps
 */
function viewSOP($sopId) {
    // Check if user is authenticated (optional for public SOPs)
    $userId = getCurrentUser();

    try {
        $db = Database::getInstance()->getConnection();

        // Get SOP
        $stmt = $db->prepare("
            SELECT s.*, u.name as author_name, u.email as author_email
            FROM sops s
            JOIN users u ON s.user_id = u.user_id
            WHERE s.sop_id = ?
        ");
        $stmt->execute([$sopId]);
        $sop = $stmt->fetch();

        if (!$sop) {
            Response::notFound('SOP not found');
        }

        // Check permissions
        if ($sop['status'] !== 'published' && (!$userId || $userId != $sop['user_id'])) {
            Response::forbidden('You do not have permission to view this SOP');
        }

        // Get all steps
        $stmt = $db->prepare("
            SELECT
                step_id, step_number, action_type, element_data, page_data,
                screenshot_path, thumbnail_path, ai_description, action_verb, tips
            FROM steps
            WHERE sop_id = ?
            ORDER BY step_number ASC
        ");
        $stmt->execute([$sopId]);
        $steps = $stmt->fetchAll();

        // Decode JSON fields
        foreach ($steps as &$step) {
            $step['element_data'] = json_decode($step['element_data'], true);
            $step['page_data'] = json_decode($step['page_data'], true);

            // Build full URL for screenshots
            if ($step['screenshot_path']) {
                $step['screenshot_url'] = BASE_URL . $step['screenshot_path'];
            }
            if ($step['thumbnail_path']) {
                $step['thumbnail_url'] = BASE_URL . $step['thumbnail_path'];
            }
        }

        // Decode JSON fields in SOP
        $sop['branding'] = json_decode($sop['branding'], true);
        $sop['metadata'] = json_decode($sop['metadata'], true);

        // Increment view count
        $stmt = $db->prepare("UPDATE sops SET views_count = views_count + 1 WHERE sop_id = ?");
        $stmt->execute([$sopId]);

        // Log analytics (optional)
        logSOPView($sopId, $userId);

        Response::success('SOP retrieved', [
            'sop' => $sop,
            'steps' => $steps
        ]);

    } catch (Exception $e) {
        Response::serverError('Failed to view SOP: ' . $e->getMessage());
    }
}

/**
 * Update SOP
 */
function updateSOP($sopId, $body) {
    $auth = authenticateUser();
    $userId = $auth['user_id'];

    try {
        $db = Database::getInstance()->getConnection();

        // Verify ownership
        $stmt = $db->prepare("SELECT user_id FROM sops WHERE sop_id = ?");
        $stmt->execute([$sopId]);
        $sop = $stmt->fetch();

        if (!$sop) {
            Response::notFound('SOP not found');
        }

        if ($sop['user_id'] != $userId) {
            Response::forbidden('You do not have permission to update this SOP');
        }

        // Build update query
        $updates = [];
        $params = [];

        if (isset($body['title'])) {
            $updates[] = "title = ?";
            $params[] = $body['title'];
        }

        if (isset($body['description'])) {
            $updates[] = "description = ?";
            $params[] = $body['description'];
        }

        if (isset($body['status']) && in_array($body['status'], ['draft', 'published', 'archived'])) {
            $updates[] = "status = ?";
            $params[] = $body['status'];
        }

        if (isset($body['branding'])) {
            $updates[] = "branding = ?";
            $params[] = json_encode($body['branding']);
        }

        if (isset($body['metadata'])) {
            $updates[] = "metadata = ?";
            $params[] = json_encode($body['metadata']);
        }

        if (empty($updates)) {
            Response::error('No fields to update', 400);
        }

        $params[] = $sopId;

        $updateClause = implode(', ', $updates);
        $stmt = $db->prepare("UPDATE sops SET $updateClause WHERE sop_id = ?");
        $stmt->execute($params);

        // Log revision
        logSOPRevision($sopId, $userId, $body);

        Response::success('SOP updated');

    } catch (Exception $e) {
        Response::serverError('Failed to update SOP: ' . $e->getMessage());
    }
}

/**
 * Delete SOP
 */
function deleteSOP($sopId) {
    $auth = authenticateUser();
    $userId = $auth['user_id'];

    try {
        $db = Database::getInstance()->getConnection();

        // Verify ownership
        $stmt = $db->prepare("SELECT user_id, session_id FROM sops WHERE sop_id = ?");
        $stmt->execute([$sopId]);
        $sop = $stmt->fetch();

        if (!$sop) {
            Response::notFound('SOP not found');
        }

        if ($sop['user_id'] != $userId) {
            Response::forbidden('You do not have permission to delete this SOP');
        }

        // Delete SOP (cascades to steps, shares, etc.)
        $stmt = $db->prepare("DELETE FROM sops WHERE sop_id = ?");
        $stmt->execute([$sopId]);

        // Delete session if exists
        if ($sop['session_id']) {
            $sessionDir = SESSION_UPLOAD_PATH . '/' . $sop['session_id'];

            // Delete screenshots
            if (is_dir($sessionDir)) {
                array_map('unlink', glob("$sessionDir/*.*"));
                rmdir($sessionDir);
            }

            $stmt = $db->prepare("DELETE FROM sessions WHERE session_id = ?");
            $stmt->execute([$sop['session_id']]);
        }

        Response::success('SOP deleted');

    } catch (Exception $e) {
        Response::serverError('Failed to delete SOP: ' . $e->getMessage());
    }
}

/**
 * Log SOP view for analytics
 */
function logSOPView($sopId, $userId = null) {
    try {
        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare("
            INSERT INTO sop_analytics (sop_id, user_id, ip_address, user_agent)
            VALUES (?, ?, ?, ?)
        ");

        $stmt->execute([
            $sopId,
            $userId,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);

    } catch (Exception $e) {
        // Silent fail for analytics
        error_log('Failed to log SOP view: ' . $e->getMessage());
    }
}

/**
 * Log SOP revision
 */
function logSOPRevision($sopId, $userId, $changes) {
    try {
        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare("
            INSERT INTO sop_revisions (sop_id, user_id, changes)
            VALUES (?, ?, ?)
        ");

        $stmt->execute([
            $sopId,
            $userId,
            json_encode($changes)
        ]);

    } catch (Exception $e) {
        // Silent fail for revision logging
        error_log('Failed to log revision: ' . $e->getMessage());
    }
}
