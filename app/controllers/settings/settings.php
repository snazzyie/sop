<?php
// Settings Controller

// Require login
if ($_SESSION['user_type'] === 0) {
    header("Location: /login");
    exit;
}

$user_id = $_SESSION['user_id'];
$email = $_SESSION['email'];

// Get user data
$user_data = fn_core_session_get_user_data($email);

// Handle POST (update settings)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $name = $_POST['name'] ?? '';

        if ($name) {
            fn_core_user_update($user_id, ['name' => $name]);
            $_SESSION['name'] = $name;
            $success = 'Profile updated successfully';
            $user_data = fn_core_session_get_user_data($email);
        }
    } elseif ($action === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if ($new_password !== $confirm_password) {
            $error = 'Passwords do not match';
        } elseif (strlen($new_password) < 8) {
            $error = 'Password must be at least 8 characters';
        } elseif (password_verify($current_password, $user_data['password_hash'])) {
            fn_core_user_update($user_id, ['password' => $new_password]);
            $success = 'Password updated successfully';
        } else {
            $error = 'Current password is incorrect';
        }
    }
}

// Get OAuth providers
$oauth_providers = fn_oauth_get_providers($user_id);

// Get menu
$menu = fn_core_menu_main();

// Breadcrumbs
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/dash'],
    ['label' => 'Settings', 'url' => '/settings']
];

// Page title
$page_title = 'Settings';

// Load view
require BASE_PATH . 'views/settings/index.php';
