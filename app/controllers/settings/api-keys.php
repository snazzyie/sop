<?php
// API Keys List Controller

// Require paid subscription
if ($_SESSION['user_type'] < 3) {
    header("Location: /pricing");
    exit;
}

$company_id = $_SESSION['company_id'];
$user_id = $_SESSION['user_id'];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = $_POST['name'] ?? '';
        $permissions = $_POST['permissions'] ?? [];

        if ($name) {
            $result = fn_api_keys_create($company_id, $name, $permissions);

            if (isset($result['error'])) {
                $error = $result['error'];
            } else {
                $success = 'API key created successfully';
                $new_api_key = $result['api_key'];
                $new_api_secret = $result['api_secret'];
            }
        } else {
            $error = 'Please provide a name for the API key';
        }
    }
}

// Get all API keys
$api_keys = fn_api_keys_get_all($company_id);

// Get subscription
$subscription = fn_subscriptions_get_team($company_id);

// Get menu
$menu = fn_core_menu_main();

// Breadcrumbs
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/dash'],
    ['label' => 'API Keys', 'url' => '/api-keys']
];

// Page title
$page_title = 'API Keys';

// Load view
require BASE_PATH . 'views/settings/api-keys.php';
