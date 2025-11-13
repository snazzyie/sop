<?php
// Chrome Extension API: Stop Recording Session

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
    $end_url = $input['end_url'] ?? null;

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

    // Calculate duration
    $start_time = strtotime($session['start_time']);
    $duration = time() - $start_time;

    // Update session
    $query = "
        UPDATE recording_sessions
        SET status = 'completed',
            end_time = NOW(),
            end_url = ?,
            duration = ?
        WHERE session_id = ?
    ";

    fn_core_edit_row_no_redirect($query, [$end_url, $duration, $session_id]);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Recording stopped',
        'duration' => $duration
    ]);
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
