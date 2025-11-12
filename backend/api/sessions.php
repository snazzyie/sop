<?php
// Recording Sessions API endpoints

function handleSessionsRequest($action, $param, $method, $body) {
    switch ($action) {
        case 'create':
            if ($method !== 'POST') {
                Response::error('Method not allowed', 405);
            }
            createSession($body);
            break;

        case '':
            // GET /api/sessions/:sessionId
            if ($method === 'GET' && !empty($param)) {
                getSession($param);
            } else {
                Response::notFound('Session endpoint not found');
            }
            break;

        default:
            // POST /api/sessions/:sessionId/finalize
            // POST /api/sessions/:sessionId/steps
            if (strpos($action, '-') !== false) {
                list($sessionId, $subAction) = explode('/', $action, 2);
            } else {
                $sessionId = $action;
                $subAction = $param;
            }

            if ($subAction === 'finalize' && $method === 'POST') {
                finalizeSession($sessionId, $body);
            } else if ($subAction === 'steps' && $method === 'POST') {
                addSessionSteps($sessionId, $body);
            } else {
                Response::notFound('Session endpoint not found');
            }
    }
}

/**
 * Create new recording session
 */
function createSession($body) {
    $auth = authenticateUser();
    $userId = $auth['user_id'];

    // Validate input
    if (empty($body['sessionId'])) {
        Response::validationError(['sessionId' => 'Session ID is required']);
    }

    $sessionId = $body['sessionId'];
    $startUrl = $body['startUrl'] ?? null;
    $startTitle = $body['startTitle'] ?? null;
    $recordingOptions = json_encode($body['recordingOptions'] ?? []);

    try {
        $db = Database::getInstance()->getConnection();

        // Check if session already exists
        $stmt = $db->prepare("SELECT session_id FROM sessions WHERE session_id = ?");
        $stmt->execute([$sessionId]);

        if ($stmt->fetch()) {
            Response::error('Session already exists', 409);
        }

        // Create session
        $stmt = $db->prepare("
            INSERT INTO sessions (session_id, user_id, status, start_time, recording_options, start_url, start_title)
            VALUES (?, ?, 'recording', NOW(), ?, ?, ?)
        ");

        $stmt->execute([$sessionId, $userId, $recordingOptions, $startUrl, $startTitle]);

        // Create directory for session screenshots
        $sessionDir = SESSION_UPLOAD_PATH . '/' . $sessionId;
        if (!file_exists($sessionDir)) {
            mkdir($sessionDir, 0777, true);
        }

        Response::success('Session created', [
            'sessionId' => $sessionId,
            'status' => 'recording',
            'uploadPath' => $sessionDir
        ], 201);

    } catch (Exception $e) {
        Response::serverError('Failed to create session: ' . $e->getMessage());
    }
}

/**
 * Get session info
 */
function getSession($sessionId) {
    $auth = authenticateUser();
    $userId = $auth['user_id'];

    try {
        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare("
            SELECT * FROM sessions
            WHERE session_id = ? AND user_id = ?
        ");
        $stmt->execute([$sessionId, $userId]);
        $session = $stmt->fetch();

        if (!$session) {
            Response::notFound('Session not found');
        }

        // Get step count
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM steps WHERE session_id = ?");
        $stmt->execute([$sessionId]);
        $stepCount = $stmt->fetch()['count'];

        $session['step_count'] = $stepCount;
        $session['recording_options'] = json_decode($session['recording_options'], true);

        Response::success('Session retrieved', $session);

    } catch (Exception $e) {
        Response::serverError('Failed to get session');
    }
}

/**
 * Add steps to session
 */
function addSessionSteps($sessionId, $body) {
    $auth = authenticateUser();
    $userId = $auth['user_id'];

    // Validate input
    if (empty($body['steps']) || !is_array($body['steps'])) {
        Response::validationError(['steps' => 'Steps array is required']);
    }

    try {
        $db = Database::getInstance()->getConnection();

        // Verify session belongs to user and is recording
        $stmt = $db->prepare("
            SELECT status FROM sessions
            WHERE session_id = ? AND user_id = ?
        ");
        $stmt->execute([$sessionId, $userId]);
        $session = $stmt->fetch();

        if (!$session) {
            Response::notFound('Session not found');
        }

        if ($session['status'] !== 'recording') {
            Response::error('Session is not in recording state', 400);
        }

        $sessionDir = SESSION_UPLOAD_PATH . '/' . $sessionId;
        $savedSteps = [];

        // Process each step
        foreach ($body['steps'] as $step) {
            // Extract screenshot data
            $screenshotData = $step['screenshot'] ?? null;
            $screenshotPath = null;
            $thumbnailPath = null;

            if ($screenshotData) {
                // Save screenshot
                $stepNumber = $step['stepNumber'];
                $screenshotPath = saveScreenshot($sessionDir, $stepNumber, $screenshotData);

                // Generate thumbnail (simplified version)
                $thumbnailPath = $screenshotPath; // In production, create actual thumbnail
            }

            // Insert step
            $stmt = $db->prepare("
                INSERT INTO steps (
                    session_id, step_number, action_type, element_data,
                    page_data, screenshot_path, thumbnail_path, timestamp
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $sessionId,
                $step['stepNumber'],
                $step['actionType'],
                json_encode($step['element'] ?? []),
                json_encode($step['page'] ?? []),
                $screenshotPath,
                $thumbnailPath,
                $step['timestamp'] ?? time()
            ]);

            $savedSteps[] = [
                'stepId' => $db->lastInsertId(),
                'stepNumber' => $step['stepNumber']
            ];
        }

        // Update total steps count
        $stmt = $db->prepare("
            UPDATE sessions
            SET total_steps = (SELECT COUNT(*) FROM steps WHERE session_id = ?)
            WHERE session_id = ?
        ");
        $stmt->execute([$sessionId, $sessionId]);

        Response::success('Steps added', [
            'sessionId' => $sessionId,
            'stepsAdded' => count($savedSteps),
            'steps' => $savedSteps
        ]);

    } catch (Exception $e) {
        Response::serverError('Failed to add steps: ' . $e->getMessage());
    }
}

/**
 * Finalize recording session and create SOP
 */
function finalizeSession($sessionId, $body) {
    $auth = authenticateUser();
    $userId = $auth['user_id'];

    try {
        $db = Database::getInstance()->getConnection();

        // Verify session
        $stmt = $db->prepare("
            SELECT * FROM sessions
            WHERE session_id = ? AND user_id = ?
        ");
        $stmt->execute([$sessionId, $userId]);
        $session = $stmt->fetch();

        if (!$session) {
            Response::notFound('Session not found');
        }

        // Update session status
        $stmt = $db->prepare("
            UPDATE sessions
            SET status = 'processing', end_time = NOW()
            WHERE session_id = ?
        ");
        $stmt->execute([$sessionId]);

        // Generate SOP title (AI would do this in production)
        $title = generateSOPTitle($session);
        $description = 'Recorded process starting from ' . ($session['start_title'] ?? 'unknown page');

        // Create SOP
        $stmt = $db->prepare("
            INSERT INTO sops (user_id, session_id, title, description, status)
            VALUES (?, ?, ?, ?, 'draft')
        ");
        $stmt->execute([$userId, $sessionId, $title, $description]);

        $sopId = $db->lastInsertId();

        // Link all steps to SOP
        $stmt = $db->prepare("
            UPDATE steps SET sop_id = ? WHERE session_id = ?
        ");
        $stmt->execute([$sopId, $sessionId]);

        // Update SOP total steps
        $stmt = $db->prepare("
            UPDATE sops SET total_steps = (SELECT COUNT(*) FROM steps WHERE sop_id = ?) WHERE sop_id = ?
        ");
        $stmt->execute([$sopId, $sopId]);

        // Update session with SOP ID
        $stmt = $db->prepare("
            UPDATE sessions SET status = 'completed', sop_id = ? WHERE session_id = ?
        ");
        $stmt->execute([$sopId, $sessionId]);

        Response::success('Session finalized', [
            'sessionId' => $sessionId,
            'sopId' => $sopId,
            'title' => $title,
            'viewUrl' => BASE_URL . '/sop/view/' . $sopId
        ]);

    } catch (Exception $e) {
        Response::serverError('Failed to finalize session: ' . $e->getMessage());
    }
}

/**
 * Save screenshot from base64 data
 */
function saveScreenshot($sessionDir, $stepNumber, $screenshotData) {
    // Remove data URL prefix if present
    if (strpos($screenshotData, 'data:image/png;base64,') === 0) {
        $screenshotData = substr($screenshotData, strlen('data:image/png;base64,'));
    }

    $imageData = base64_decode($screenshotData);

    if ($imageData === false) {
        throw new Exception('Invalid screenshot data');
    }

    $filename = "step_{$stepNumber}.png";
    $filepath = $sessionDir . '/' . $filename;

    if (!file_put_contents($filepath, $imageData)) {
        throw new Exception('Failed to save screenshot');
    }

    // Return relative path for database
    return str_replace(BASE_PATH, '', $filepath);
}

/**
 * Generate SOP title from session data
 */
function generateSOPTitle($session) {
    // In production, use AI to generate meaningful title
    // For now, generate from start URL
    $startTitle = $session['start_title'] ?? null;

    if ($startTitle) {
        return "How to: " . $startTitle;
    }

    return "Untitled Process - " . date('Y-m-d H:i');
}
