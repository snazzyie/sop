<?php
// Dashboard Controller

// Require login
if ($_SESSION['user_type'] === 0) {
    header("Location: /login");
    exit;
}

// Get user data
$user_id = $_SESSION['user_id'];
$company_id = $_SESSION['company_id'];
$email = $_SESSION['email'];

// Get user information
$user_data = fn_core_session_get_user_data($email);

// Get statistics
$sop_stats = fn_sops_get_stats($company_id);
$session_stats = fn_sessions_get_stats($company_id);

// Get recent SOPs
$recent_sops = fn_sops_get_all($company_id, 0, 5);

// Get active recording sessions
$active_sessions = fn_sessions_get_all($company_id, 0, 5, ['status' => 'recording']);

// Get subscription info
$subscription = fn_subscriptions_get_team($company_id);

// Get menu
$menu = fn_core_menu_main();

// Breadcrumbs
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/dash']
];

// Page title
$page_title = 'Dashboard';

// Load view
require BASE_PATH . 'views/dash/index.php';
