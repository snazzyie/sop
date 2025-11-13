<?php
// Pricing Page Controller

// Get all available plans
$plans = fn_subscriptions_get_plans();

// Get current subscription if logged in
$current_subscription = null;
if (isset($_SESSION['company_id'])) {
    $current_subscription = fn_subscriptions_get_team($_SESSION['company_id']);
}

// Page title
$page_title = 'Pricing';

// Load view
require BASE_PATH . 'views/plans/pricing.php';
