<?php
// Recording Sessions List Controller

// Require login
if ($_SESSION['user_type'] === 0) {
    header("Location: /login");
    exit;
}

$company_id = $_SESSION['company_id'];
$user_id = $_SESSION['user_id'];

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Filters
$filters = [];
if (!empty($_GET['status'])) {
    $filters['status'] = $_GET['status'];
}

// Get sessions
$sessions = fn_sessions_get_all($company_id, $offset, $limit, $filters);

// Get total count
$total_sessions = fn_sessions_count($company_id, $filters);
$total_pages = ceil($total_sessions / $limit);

// Get statistics
$stats = fn_sessions_get_stats($company_id);

// Get menu
$menu = fn_core_menu_main();

// Breadcrumbs
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/dash'],
    ['label' => 'Recording Sessions', 'url' => '/sessions']
];

// Page title
$page_title = 'Recording Sessions';

// Load view
require BASE_PATH . 'views/sessions/index.php';
