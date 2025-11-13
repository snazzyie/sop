<?php
// SOP View Controller

// Require login
if ($_SESSION['user_type'] === 0) {
    header("Location: /login");
    exit;
}

// Get user data
$company_id = $_SESSION['company_id'];
$user_id = $_SESSION['user_id'];

// Get SOP ID from query string
$sop_id = $_GET['id'] ?? null;

if (!$sop_id) {
    header("Location: /sops");
    exit;
}

// Get SOP with steps
$sop = fn_sops_get_with_steps($sop_id, $company_id);

if (!$sop) {
    header("Location: /sops?error=SOP+not+found");
    exit;
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'update':
            $result = fn_sops_update($sop_id, [
                'title' => $_POST['title'] ?? $sop['title'],
                'description' => $_POST['description'] ?? $sop['description']
            ], $company_id);

            if ($result) {
                $success = 'SOP updated successfully';
                $sop = fn_sops_get_with_steps($sop_id, $company_id); // Refresh data
            } else {
                $error = 'Failed to update SOP';
            }
            break;

        case 'publish':
            fn_sops_publish($sop_id, $company_id);
            $success = 'SOP published successfully';
            $sop = fn_sops_get_with_steps($sop_id, $company_id);
            break;

        case 'unpublish':
            fn_sops_unpublish($sop_id, $company_id);
            $success = 'SOP unpublished';
            $sop = fn_sops_get_with_steps($sop_id, $company_id);
            break;

        case 'delete':
            fn_sops_delete($sop_id, $company_id);
            header("Location: /sops?success=SOP+deleted");
            exit;

        case 'duplicate':
            $result = fn_sops_duplicate($sop_id, $company_id, $user_id);
            if (isset($result['error'])) {
                $error = $result['error'];
            } else {
                header("Location: /sops/view?id=" . $result['sop_id']);
                exit;
            }
            break;
    }
}

// Get shares for this SOP
$shares = fn_shares_get_for_sop($sop_id, $company_id);

// Get export history
$export_history = fn_exports_get_history($sop_id, $company_id);

// Get menu
$menu = fn_core_menu_main();

// Breadcrumbs
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/dash'],
    ['label' => 'SOPs', 'url' => '/sops'],
    ['label' => $sop['title'], 'url' => '/sops/view?id=' . $sop_id]
];

// Page title
$page_title = $sop['title'];

// Load view
require BASE_PATH . 'views/sops/view.php';
