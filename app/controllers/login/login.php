<?php
// Login Controller

// If already logged in, redirect to dashboard
if ($_SESSION['user_type'] > 0) {
    header("Location: /dash");
    exit;
}

// Handle POST request (login submission)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password';
    } else {
        // Get user by email
        $user = fn_core_session_get_user_data($email);

        if (!$user) {
            $error = 'Invalid email or password';
        } else {
            // Attempt login
            $result = fn_core_session_log_me_in($password, $user['password_hash'], $email);

            if (!$result) {
                $error = 'Invalid email or password';
            }
            // If successful, fn_core_session_log_me_in redirects automatically
        }
    }
}

// Load view
require BASE_PATH . 'views/login/index.php';
