<?php
// Stripe Webhook Handler

header('Content-Type: application/json');

// Get the request body
$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

// Verify webhook signature
try {
    $event = fn_stripe_verify_webhook($payload, $sig_header);

    if (!$event) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid signature']);
        exit;
    }

    // Log the event
    $query = "
        INSERT INTO stripe_webhook_events (
            stripe_event_id,
            event_type,
            payload,
            processed,
            created_at
        ) VALUES (?, ?, ?, FALSE, NOW())
    ";

    fn_core_insert_row_no_redirect($query, [
        $event->id,
        $event->type,
        json_encode($event->data->object)
    ]);

    // Handle the event
    $handled = fn_stripe_handle_webhook($event);

    if ($handled) {
        // Mark as processed
        $query = "UPDATE stripe_webhook_events SET processed = TRUE, processed_at = NOW() WHERE stripe_event_id = ?";
        fn_core_edit_row_no_redirect($query, [$event->id]);
    }

    http_response_code(200);
    echo json_encode(['status' => 'success']);

} catch (Exception $e) {
    fn_log('Stripe webhook error: ' . $e->getMessage());

    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
