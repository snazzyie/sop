<?php
// SOPs List Controller

// Require login
if ($_SESSION['user_type'] === 0) {
    header("Location: /login");
    exit;
}

// Get user data
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
if (!empty($_GET['search'])) {
    $filters['search'] = $_GET['search'];
}

// Get SOPs
$sops = fn_sops_get_all($company_id, $offset, $limit, $filters);

// Get total count for pagination
$total_sops = fn_sops_count($company_id, $filters);
$total_pages = ceil($total_sops / $limit);

// Get statistics
$stats = fn_sops_get_stats($company_id);

// Get menu
$menu = fn_core_menu_main();

// Breadcrumbs
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/dash'],
    ['label' => 'SOPs', 'url' => '/sops']
];

// Page title
$page_title = 'SOPs';

// Load view
require BASE_PATH . 'views/sops/index.php';
