<?php
// Google OAuth Callback Controller

$code = $_GET['code'] ?? null;
$state = $_GET['state'] ?? null;
$error = $_GET['error'] ?? null;

// Check for OAuth error
if ($error) {
    header("Location: /login?error=" . urlencode('OAuth failed: ' . $error));
    exit;
}

// Validate code and state
if (!$code || !$state) {
    header("Location: /login?error=" . urlencode('Invalid OAuth response'));
    exit;
}

// Handle OAuth callback
$result = fn_oauth_google_callback($code, $state);

if (isset($result['error'])) {
    header("Location: /login?error=" . urlencode($result['error']));
    exit;
}

// Success - redirect to dashboard
header("Location: /dash");
exit;
