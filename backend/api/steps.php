<?php
// Steps API endpoints - for editing individual steps

function handleStepsRequest($action, $param, $method, $body) {
    if (is_numeric($action)) {
        $stepId = $action;

        switch ($method) {
            case 'GET':
                getStep($stepId);
                break;
            case 'PUT':
            case 'PATCH':
                updateStep($stepId, $body);
                break;
            case 'DELETE':
                deleteStep($stepId);
                break;
            default:
                Response::error('Method not allowed', 405);
        }
    } else {
        Response::notFound('Step endpoint not found');
    }
}

/**
 * Get single step
 */
function getStep($stepId) {
    $auth = authenticateUser();
    $userId = $auth['user_id'];

    try {
        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare("
            SELECT s.*, so.user_id as sop_owner
            FROM steps s
            JOIN sops so ON s.sop_id = so.sop_id
            WHERE s.step_id = ?
        ");
        $stmt->execute([$stepId]);
        $step = $stmt->fetch();

        if (!$step) {
            Response::notFound('Step not found');
        }

        if ($step['sop_owner'] != $userId) {
            Response::forbidden('You do not have permission to view this step');
        }

        $step['element_data'] = json_decode($step['element_data'], true);
        $step['page_data'] = json_decode($step['page_data'], true);

        Response::success('Step retrieved', $step);

    } catch (Exception $e) {
        Response::serverError('Failed to get step');
    }
}

/**
 * Update step
 */
function updateStep($stepId, $body) {
    $auth = authenticateUser();
    $userId = $auth['user_id'];

    try {
        $db = Database::getInstance()->getConnection();

        // Verify ownership
        $stmt = $db->prepare("
            SELECT s.sop_id, so.user_id as sop_owner
            FROM steps s
            JOIN sops so ON s.sop_id = so.sop_id
            WHERE s.step_id = ?
        ");
        $stmt->execute([$stepId]);
        $step = $stmt->fetch();

        if (!$step) {
            Response::notFound('Step not found');
        }

        if ($step['sop_owner'] != $userId) {
            Response::forbidden('You do not have permission to update this step');
        }

        // Build update query
        $updates = [];
        $params = [];

        if (isset($body['ai_description'])) {
            $updates[] = "ai_description = ?";
            $params[] = $body['ai_description'];
        }

        if (isset($body['tips'])) {
            $updates[] = "tips = ?";
            $params[] = $body['tips'];
        }

        if (isset($body['action_verb'])) {
            $updates[] = "action_verb = ?";
            $params[] = $body['action_verb'];
        }

        if (empty($updates)) {
            Response::error('No fields to update', 400);
        }

        $params[] = $stepId;

        $updateClause = implode(', ', $updates);
        $stmt = $db->prepare("UPDATE steps SET $updateClause WHERE step_id = ?");
        $stmt->execute($params);

        Response::success('Step updated');

    } catch (Exception $e) {
        Response::serverError('Failed to update step');
    }
}

/**
 * Delete step
 */
function deleteStep($stepId) {
    $auth = authenticateUser();
    $userId = $auth['user_id'];

    try {
        $db = Database::getInstance()->getConnection();

        // Verify ownership
        $stmt = $db->prepare("
            SELECT s.sop_id, so.user_id as sop_owner
            FROM steps s
            JOIN sops so ON s.sop_id = so.sop_id
            WHERE s.step_id = ?
        ");
        $stmt->execute([$stepId]);
        $step = $stmt->fetch();

        if (!$step) {
            Response::notFound('Step not found');
        }

        if ($step['sop_owner'] != $userId) {
            Response::forbidden('You do not have permission to delete this step');
        }

        // Delete step
        $stmt = $db->prepare("DELETE FROM steps WHERE step_id = ?");
        $stmt->execute([$stepId]);

        // Update SOP total steps
        $stmt = $db->prepare("
            UPDATE sops
            SET total_steps = (SELECT COUNT(*) FROM steps WHERE sop_id = ?)
            WHERE sop_id = ?
        ");
        $stmt->execute([$step['sop_id'], $step['sop_id']]);

        Response::success('Step deleted');

    } catch (Exception $e) {
        Response::serverError('Failed to delete step');
    }
}
