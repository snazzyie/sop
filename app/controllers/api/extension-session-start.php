<?php
// Chrome Extension API: Start Recording Session

header('Content-Type: application/json');

// Check if user is logged in (session-based)
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] < 1) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$company_id = $_SESSION['company_id'];

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);

    $start_url = $input['start_url'] ?? null;
    $start_title = $input['start_title'] ?? null;

    // Generate session ID
    $session_id = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );

    // Create recording session
    $query = "
        INSERT INTO recording_sessions (
            session_id,
            user_id,
            company_id,
            status,
            start_time,
            start_url,
            start_title,
            created_at
        ) VALUES (?, ?, ?, 'recording', NOW(), ?, ?, NOW())
    ";

    fn_core_insert_row_no_redirect($query, [
        $session_id,
        $user_id,
        $company_id,
        $start_url,
        $start_title
    ]);

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'session_id' => $session_id,
        'message' => 'Recording started'
    ]);
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
