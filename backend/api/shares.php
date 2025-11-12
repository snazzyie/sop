<?php
// Shares API endpoints - for creating and viewing shared SOPs

function handleSharesRequest($action, $param, $method, $body) {
    switch ($action) {
        case 'create':
            if ($method !== 'POST') {
                Response::error('Method not allowed', 405);
            }
            createShare($body);
            break;

        default:
            // GET /api/shares/:token
            if ($method === 'GET' && !empty($action)) {
                viewSharedSOP($action);
            } else {
                Response::notFound('Share endpoint not found');
            }
    }
}

/**
 * Create share link for SOP
 */
function createShare($body) {
    $auth = authenticateUser();
    $userId = $auth['user_id'];

    // Validate input
    if (empty($body['sopId'])) {
        Response::validationError(['sopId' => 'SOP ID is required']);
    }

    $sopId = $body['sopId'];
    $visibility = $body['visibility'] ?? 'link-only';
    $password = $body['password'] ?? null;
    $expiresAt = $body['expiresAt'] ?? null;

    if (!in_array($visibility, ['public', 'password', 'link-only'])) {
        Response::validationError(['visibility' => 'Invalid visibility option']);
    }

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
            Response::forbidden('You do not have permission to share this SOP');
        }

        // Generate unique share token
        $shareToken = bin2hex(random_bytes(32));

        // Hash password if provided
        $passwordHash = null;
        if ($visibility === 'password' && $password) {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        }

        // Create share
        $stmt = $db->prepare("
            INSERT INTO shares (sop_id, share_token, visibility, password_hash, expires_at)
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->execute([$sopId, $shareToken, $visibility, $passwordHash, $expiresAt]);

        $shareId = $db->lastInsertId();
        $shareUrl = BASE_URL . '/shared/' . $shareToken;

        Response::success('Share created', [
            'shareId' => $shareId,
            'shareToken' => $shareToken,
            'shareUrl' => $shareUrl,
            'visibility' => $visibility,
            'expiresAt' => $expiresAt
        ], 201);

    } catch (Exception $e) {
        Response::serverError('Failed to create share: ' . $e->getMessage());
    }
}

/**
 * View shared SOP via token
 */
function viewSharedSOP($token) {
    try {
        $db = Database::getInstance()->getConnection();

        // Get share
        $stmt = $db->prepare("
            SELECT * FROM shares
            WHERE share_token = ?
        ");
        $stmt->execute([$token]);
        $share = $stmt->fetch();

        if (!$share) {
            Response::notFound('Share not found');
        }

        // Check if expired
        if ($share['expires_at'] && strtotime($share['expires_at']) < time()) {
            Response::error('Share link has expired', 410);
        }

        // Check password if required
        if ($share['visibility'] === 'password') {
            $password = $_GET['password'] ?? null;

            if (!$password) {
                Response::error('Password required', 401, ['requiresPassword' => true]);
            }

            if (!password_verify($password, $share['password_hash'])) {
                Response::error('Incorrect password', 401);
            }
        }

        // Get SOP with steps
        $stmt = $db->prepare("
            SELECT s.*, u.name as author_name
            FROM sops s
            JOIN users u ON s.user_id = u.user_id
            WHERE s.sop_id = ?
        ");
        $stmt->execute([$share['sop_id']]);
        $sop = $stmt->fetch();

        if (!$sop) {
            Response::notFound('SOP not found');
        }

        // Get steps
        $stmt = $db->prepare("
            SELECT
                step_number, action_type, element_data, page_data,
                screenshot_path, thumbnail_path, ai_description, action_verb, tips
            FROM steps
            WHERE sop_id = ?
            ORDER BY step_number ASC
        ");
        $stmt->execute([$share['sop_id']]);
        $steps = $stmt->fetchAll();

        // Decode JSON and build URLs
        foreach ($steps as &$step) {
            $step['element_data'] = json_decode($step['element_data'], true);
            $step['page_data'] = json_decode($step['page_data'], true);

            if ($step['screenshot_path']) {
                $step['screenshot_url'] = BASE_URL . $step['screenshot_path'];
            }
            if ($step['thumbnail_path']) {
                $step['thumbnail_url'] = BASE_URL . $step['thumbnail_path'];
            }
        }

        // Increment share view count
        $stmt = $db->prepare("UPDATE shares SET views_count = views_count + 1 WHERE share_id = ?");
        $stmt->execute([$share['share_id']]);

        // Increment SOP view count
        $stmt = $db->prepare("UPDATE sops SET views_count = views_count + 1 WHERE sop_id = ?");
        $stmt->execute([$share['sop_id']]);

        Response::success('Shared SOP retrieved', [
            'sop' => [
                'title' => $sop['title'],
                'description' => $sop['description'],
                'author_name' => $sop['author_name'],
                'created_at' => $sop['created_at'],
                'total_steps' => $sop['total_steps']
            ],
            'steps' => $steps
        ]);

    } catch (Exception $e) {
        Response::serverError('Failed to view shared SOP: ' . $e->getMessage());
    }
}
