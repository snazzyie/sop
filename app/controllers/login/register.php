<?php
// Register Controller

// If already logged in, redirect to dashboard
if ($_SESSION['user_type'] > 0) {
    header("Location: /dash");
    exit;
}

// Handle POST request (registration submission)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    // Validation
    if (empty($name) || empty($email) || empty($password)) {
        $error = 'Please fill in all fields';
    } elseif ($password !== $password_confirm) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters';
    } else {
        // Check if email already exists
        $existing = fn_core_session_get_user_data($email);

        if ($existing) {
            $error = 'Email already registered';
        } else {
            // Create user
            $user_id = fn_core_user_create($email, $password, $name);

            if ($user_id) {
                // Auto-login after registration
                $user = fn_core_session_get_user_by_id($user_id);

                session_regenerate_id(true);
                $_SESSION['user_id'] = $user_id;
                $_SESSION['email'] = $email;
                $_SESSION['name'] = $name;
                $_SESSION['company_id'] = $user['company_id'];
                $_SESSION['user_type'] = fn_core_session_calculate_permission_level($user);

                header("Location: /dash");
                exit;
            } else {
                $error = 'Failed to create account. Please try again.';
            }
        }
    }
}

// Load view
require BASE_PATH . 'views/login/register.php';
