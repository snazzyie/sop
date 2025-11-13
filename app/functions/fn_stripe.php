<?php
// Stripe Integration Functions

/**
 * Initialize Stripe with API key
 */
function fn_stripe_init() {
    $config = require BASE_PATH . 'config.php';

    if (!isset($config['stripe']['secret_key'])) {
        throw new Exception('Stripe secret key not configured');
    }

    \Stripe\Stripe::setApiKey($config['stripe']['secret_key']);
}

/**
 * Create Stripe customer
 */
function fn_stripe_create_customer($email, $name = null, $metadata = []) {
    fn_stripe_init();

    try {
        $customer = \Stripe\Customer::create([
            'email' => $email,
            'name' => $name,
            'metadata' => $metadata
        ]);

        return $customer->id;
    } catch (\Stripe\Exception\ApiErrorException $e) {
        fn_log('Stripe create customer error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Create checkout session
 */
function fn_stripe_create_checkout_session($customer_id, $price_id, $success_url, $cancel_url, $metadata = []) {
    fn_stripe_init();

    try {
        $session = \Stripe\Checkout\Session::create([
            'customer' => $customer_id,
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price' => $price_id,
                'quantity' => 1,
            ]],
            'mode' => 'subscription',
            'success_url' => $success_url,
            'cancel_url' => $cancel_url,
            'metadata' => $metadata,
            'allow_promotion_codes' => true,
        ]);

        return [
            'session_id' => $session->id,
            'url' => $session->url
        ];
    } catch (\Stripe\Exception\ApiErrorException $e) {
        fn_log('Stripe checkout session error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Cancel subscription
 */
function fn_stripe_cancel_subscription($subscription_id, $at_period_end = true) {
    fn_stripe_init();

    try {
        $subscription = \Stripe\Subscription::retrieve($subscription_id);

        if ($at_period_end) {
            $subscription->cancel_at_period_end = true;
            $subscription->save();
        } else {
            $subscription->cancel();
        }

        return true;
    } catch (\Stripe\Exception\ApiErrorException $e) {
        fn_log('Stripe cancel subscription error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Create customer portal session
 */
function fn_stripe_create_portal_session($customer_id, $return_url) {
    fn_stripe_init();

    try {
        $session = \Stripe\BillingPortal\Session::create([
            'customer' => $customer_id,
            'return_url' => $return_url,
        ]);

        return $session->url;
    } catch (\Stripe\Exception\ApiErrorException $e) {
        fn_log('Stripe portal session error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get subscription details
 */
function fn_stripe_get_subscription($subscription_id) {
    fn_stripe_init();

    try {
        $subscription = \Stripe\Subscription::retrieve($subscription_id);

        return [
            'id' => $subscription->id,
            'status' => $subscription->status,
            'current_period_end' => $subscription->current_period_end,
            'cancel_at_period_end' => $subscription->cancel_at_period_end,
            'plan' => [
                'id' => $subscription->plan->id,
                'amount' => $subscription->plan->amount,
                'interval' => $subscription->plan->interval,
            ]
        ];
    } catch (\Stripe\Exception\ApiErrorException $e) {
        fn_log('Stripe get subscription error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Update subscription
 */
function fn_stripe_update_subscription($subscription_id, $new_price_id) {
    fn_stripe_init();

    try {
        $subscription = \Stripe\Subscription::retrieve($subscription_id);

        \Stripe\Subscription::update($subscription_id, [
            'items' => [
                [
                    'id' => $subscription->items->data[0]->id,
                    'price' => $new_price_id,
                ],
            ],
            'proration_behavior' => 'always_invoice',
        ]);

        return true;
    } catch (\Stripe\Exception\ApiErrorException $e) {
        fn_log('Stripe update subscription error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Verify webhook signature
 */
function fn_stripe_verify_webhook($payload, $signature) {
    fn_stripe_init();

    $config = require BASE_PATH . 'config.php';
    $endpoint_secret = $config['stripe']['webhook_secret'] ?? null;

    if (!$endpoint_secret) {
        throw new Exception('Stripe webhook secret not configured');
    }

    try {
        $event = \Stripe\Webhook::constructEvent(
            $payload,
            $signature,
            $endpoint_secret
        );

        return $event;
    } catch (\UnexpectedValueException $e) {
        fn_log('Invalid webhook payload: ' . $e->getMessage());
        return false;
    } catch (\Stripe\Exception\SignatureVerificationException $e) {
        fn_log('Invalid webhook signature: ' . $e->getMessage());
        return false;
    }
}

/**
 * Handle webhook event
 */
function fn_stripe_handle_webhook($event) {
    $type = $event->type;
    $data = $event->data->object;

    fn_log("Stripe webhook received: $type", ['event_id' => $event->id]);

    switch ($type) {
        case 'checkout.session.completed':
            return fn_stripe_webhook_checkout_completed($data);

        case 'customer.subscription.created':
        case 'customer.subscription.updated':
            return fn_stripe_webhook_subscription_updated($data);

        case 'customer.subscription.deleted':
            return fn_stripe_webhook_subscription_deleted($data);

        case 'invoice.payment_succeeded':
            return fn_stripe_webhook_payment_succeeded($data);

        case 'invoice.payment_failed':
            return fn_stripe_webhook_payment_failed($data);

        default:
            fn_log("Unhandled webhook type: $type");
            return true;
    }
}

/**
 * Handle checkout completed webhook
 */
function fn_stripe_webhook_checkout_completed($session) {
    $customer_id = $session->customer;
    $subscription_id = $session->subscription;
    $metadata = $session->metadata;

    // Get team ID from metadata
    $team_id = $metadata->team_id ?? null;
    $plan_slug = $metadata->plan_slug ?? null;

    if (!$team_id || !$plan_slug) {
        fn_log('Missing metadata in checkout session', ['session_id' => $session->id]);
        return false;
    }

    // Get plan
    $plan = fn_subscriptions_get_plan_by_slug($plan_slug);

    if (!$plan) {
        fn_log('Plan not found: ' . $plan_slug);
        return false;
    }

    // Update team's subscription
    fn_subscriptions_create($team_id, $plan['plan_id'], $subscription_id, 'active');

    // Update team's Stripe customer ID
    $query = "UPDATE teams SET stripe_customer_id = ?, updated_at = NOW() WHERE team_id = ?";
    fn_core_edit_row_no_redirect($query, [$customer_id, $team_id]);

    fn_log('Checkout completed for team', ['team_id' => $team_id, 'plan' => $plan_slug]);

    return true;
}

/**
 * Handle subscription updated webhook
 */
function fn_stripe_webhook_subscription_updated($subscription) {
    $subscription_id = $subscription->id;
    $status = $subscription->status;

    // Update subscription status
    fn_subscriptions_update_status($subscription_id, $status);

    fn_log('Subscription updated', ['subscription_id' => $subscription_id, 'status' => $status]);

    return true;
}

/**
 * Handle subscription deleted webhook
 */
function fn_stripe_webhook_subscription_deleted($subscription) {
    $subscription_id = $subscription->id;

    // Update subscription status to canceled
    fn_subscriptions_update_status($subscription_id, 'canceled');

    fn_log('Subscription deleted', ['subscription_id' => $subscription_id]);

    return true;
}

/**
 * Handle payment succeeded webhook
 */
function fn_stripe_webhook_payment_succeeded($invoice) {
    $subscription_id = $invoice->subscription;

    if ($subscription_id) {
        // Ensure subscription is marked as active
        fn_subscriptions_update_status($subscription_id, 'active');
    }

    fn_log('Payment succeeded', ['invoice_id' => $invoice->id]);

    return true;
}

/**
 * Handle payment failed webhook
 */
function fn_stripe_webhook_payment_failed($invoice) {
    $subscription_id = $invoice->subscription;

    if ($subscription_id) {
        // Mark subscription as past_due
        fn_subscriptions_update_status($subscription_id, 'past_due');
    }

    fn_log('Payment failed', ['invoice_id' => $invoice->id]);

    return true;
}

/**
 * Get Stripe price ID for plan
 */
function fn_stripe_get_price_id($plan_slug, $billing_cycle = 'monthly') {
    $plan = fn_subscriptions_get_plan_by_slug($plan_slug);

    if (!$plan) {
        return null;
    }

    if ($billing_cycle === 'yearly') {
        return $plan['stripe_price_id_yearly'] ?? null;
    }

    return $plan['stripe_price_id_monthly'] ?? null;
}
