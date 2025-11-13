<?php
// Subscription Checkout Controller

// Require login and company
if ($_SESSION['user_type'] < 2) {
    header("Location: /login");
    exit;
}

// Get user data
$company_id = $_SESSION['company_id'];
$user_id = $_SESSION['user_id'];

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $plan_slug = $_POST['plan_slug'] ?? null;
    $billing_cycle = $_POST['billing_cycle'] ?? 'monthly';

    if (!$plan_slug) {
        $error = 'Please select a plan';
    } else {
        // Get plan details
        $plan = fn_subscriptions_get_plan_by_slug($plan_slug);

        if (!$plan) {
            $error = 'Invalid plan selected';
        } else {
            // Get team data
            $query = "SELECT * FROM teams WHERE team_id = ?";
            $team = fn_core_database_row($query, [$company_id]);

            // Create or get Stripe customer
            $stripe_customer_id = $team['stripe_customer_id'];

            if (!$stripe_customer_id) {
                // Create new Stripe customer
                $user_data = fn_core_session_get_user_by_id($user_id);
                $stripe_customer_id = fn_stripe_create_customer(
                    $user_data['email'],
                    $user_data['name'],
                    ['team_id' => $company_id]
                );

                if ($stripe_customer_id) {
                    // Save customer ID
                    $query = "UPDATE teams SET stripe_customer_id = ? WHERE team_id = ?";
                    fn_core_edit_row_no_redirect($query, [$stripe_customer_id, $company_id]);
                }
            }

            // Get Stripe price ID
            $price_id = fn_stripe_get_price_id($plan_slug, $billing_cycle);

            if (!$price_id) {
                $error = 'Stripe price ID not configured for this plan';
            } else {
                // Create checkout session
                $config = require BASE_PATH . 'config.php';
                $success_url = $config['app_url'] . '/subscription/success';
                $cancel_url = $config['app_url'] . '/pricing';

                $checkout = fn_stripe_create_checkout_session(
                    $stripe_customer_id,
                    $price_id,
                    $success_url,
                    $cancel_url,
                    [
                        'team_id' => $company_id,
                        'plan_slug' => $plan_slug
                    ]
                );

                if ($checkout) {
                    // Redirect to Stripe checkout
                    header("Location: " . $checkout['url']);
                    exit;
                } else {
                    $error = 'Failed to create checkout session';
                }
            }
        }
    }
}

// If we get here with an error, redirect back to pricing
if (isset($error)) {
    header("Location: /pricing?error=" . urlencode($error));
    exit;
}

// Otherwise show pricing page
header("Location: /pricing");
exit;
