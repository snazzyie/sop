<?php
// Chrome Extension API: Add Action to Session

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] < 1) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$company_id = $_SESSION['company_id'];

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);

    $session_id = $input['session_id'] ?? null;
    $action_type = $input['action_type'] ?? 'unknown';
    $element_selector = $input['element_selector'] ?? null;
    $element_text = $input['element_text'] ?? null;
    $input_value = $input['input_value'] ?? null;
    $url = $input['url'] ?? null;
    $screenshot_url = $input['screenshot_url'] ?? null;
    $timestamp = $input['timestamp'] ?? time() * 1000;
    $metadata = $input['metadata'] ?? null;

    if (!$session_id) {
        http_response_code(400);
        echo json_encode(['error' => 'session_id is required']);
        exit;
    }

    // Verify session belongs to user's company
    $session = fn_sessions_get_by_id($session_id, $company_id);

    if (!$session) {
        http_response_code(404);
        echo json_encode(['error' => 'Session not found']);
        exit;
    }

    // Add action to session
    $action_id = fn_sessions_add_action($session_id, [
        'action_type' => $action_type,
        'element_selector' => $element_selector,
        'element_text' => $element_text,
        'input_value' => $input_value,
        'url' => $url,
        'screenshot_url' => $screenshot_url,
        'timestamp' => $timestamp,
        'metadata' => $metadata
    ]);

    // Update session total_steps
    $query = "UPDATE recording_sessions SET total_steps = total_steps + 1 WHERE session_id = ?";
    fn_core_edit_row_no_redirect($query, [$session_id]);

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'action_id' => $action_id,
        'message' => 'Action added'
    ]);
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
