<?php
// Subscription & Billing API endpoints

require_once '../config/stripe.php';
require_once '../classes/Subscription.php';

function handleSubscriptionsRequest($action, $param, $method, $body) {
    switch ($action) {
        case 'plans':
            if ($method === 'GET') {
                getPlans();
            } else {
                Response::error('Method not allowed', 405);
            }
            break;

        case 'current':
            if ($method === 'GET') {
                getCurrentSubscription();
            } else {
                Response::error('Method not allowed', 405);
            }
            break;

        case 'checkout':
            if ($method === 'POST') {
                createCheckoutSession($body);
            } else {
                Response::error('Method not allowed', 405);
            }
            break;

        case 'portal':
            if ($method === 'POST') {
                createPortalSession($body);
            } else {
                Response::error('Method not allowed', 405);
            }
            break;

        case 'cancel':
            if ($method === 'POST') {
                cancelSubscription($body);
            } else {
                Response::error('Method not allowed', 405);
            }
            break;

        case 'usage':
            if ($method === 'GET') {
                getUsage();
            } else {
                Response::error('Method not allowed', 405);
            }
            break;

        case 'webhook':
            if ($method === 'POST') {
                handleStripeWebhook();
            } else {
                Response::error('Method not allowed', 405);
            }
            break;

        default:
            Response::notFound('Subscription endpoint not found');
    }
}

/**
 * Get available subscription plans
 */
function getPlans() {
    try {
        $plans = Subscription::getAvailablePlans();

        Response::success('Plans retrieved', [
            'plans' => $plans,
            'publishable_key' => STRIPE_PUBLISHABLE_KEY
        ]);

    } catch (Exception $e) {
        Response::serverError('Failed to get plans: ' . $e->getMessage());
    }
}

/**
 * Get current team's subscription
 */
function getCurrentSubscription() {
    $auth = authenticateUser();
    $userId = $auth['user_id'];

    try {
        $db = Database::getInstance()->getConnection();

        // Get user's primary team
        $stmt = $db->prepare("
            SELECT team_id FROM teams WHERE owner_user_id = ?
            UNION
            SELECT team_id FROM team_members WHERE user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$userId, $userId]);
        $team = $stmt->fetch();

        if (!$team) {
            Response::error('No team found', 404);
        }

        $teamId = $team['team_id'];
        $subscription = Subscription::getTeamSubscription($teamId);
        $usage = Subscription::getUsage($teamId);

        Response::success('Subscription retrieved', [
            'subscription' => $subscription,
            'usage' => $usage
        ]);

    } catch (Exception $e) {
        Response::serverError('Failed to get subscription: ' . $e->getMessage());
    }
}

/**
 * Create Stripe checkout session
 */
function createCheckoutSession($body) {
    $auth = authenticateUser();
    $userId = $auth['user_id'];

    if (empty($body['plan_slug']) || empty($body['billing_cycle'])) {
        Response::validationError(['plan_slug' => 'Plan and billing cycle required']);
    }

    $planSlug = $body['plan_slug'];
    $billingCycle = $body['billing_cycle']; // 'monthly' or 'yearly'

    try {
        $db = Database::getInstance()->getConnection();

        // Get user's team
        $stmt = $db->prepare("SELECT team_id, name, billing_email, stripe_customer_id FROM teams WHERE owner_user_id = ?");
        $stmt->execute([$userId]);
        $team = $stmt->fetch();

        if (!$team) {
            Response::error('Team not found', 404);
        }

        // Get plan details
        $stmt = $db->prepare("SELECT * FROM subscription_plans WHERE plan_slug = ?");
        $stmt->execute([$planSlug]);
        $plan = $stmt->fetch();

        if (!$plan) {
            Response::error('Plan not found', 404);
        }

        // Get user email
        $stmt = $db->prepare("SELECT email, name FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        // Get or create Stripe customer
        if ($team['stripe_customer_id']) {
            $customerId = $team['stripe_customer_id'];
        } else {
            $customer = StripeHelper::createCustomer(
                $team['billing_email'] ?? $user['email'],
                $team['name'],
                ['team_id' => $team['team_id'], 'user_id' => $userId]
            );

            $customerId = $customer->id;

            // Save customer ID
            $stmt = $db->prepare("UPDATE teams SET stripe_customer_id = ? WHERE team_id = ?");
            $stmt->execute([$customerId, $team['team_id']]);
        }

        // Get Stripe price ID
        $priceId = $billingCycle === 'yearly'
            ? $plan['stripe_price_id_yearly']
            : $plan['stripe_price_id_monthly'];

        if (!$priceId) {
            Response::error('Stripe price ID not configured for this plan', 400);
        }

        // Create checkout session
        $session = StripeHelper::createCheckoutSession(
            $customerId,
            $priceId,
            [
                'team_id' => $team['team_id'],
                'plan_id' => $plan['plan_id'],
                'billing_cycle' => $billingCycle
            ]
        );

        Response::success('Checkout session created', [
            'session_id' => $session->id,
            'url' => $session->url
        ]);

    } catch (Exception $e) {
        Response::serverError('Failed to create checkout session: ' . $e->getMessage());
    }
}

/**
 * Create billing portal session
 */
function createPortalSession($body) {
    $auth = authenticateUser();
    $userId = $auth['user_id'];

    try {
        $db = Database::getInstance()->getConnection();

        // Get user's team
        $stmt = $db->prepare("SELECT stripe_customer_id FROM teams WHERE owner_user_id = ?");
        $stmt->execute([$userId]);
        $team = $stmt->fetch();

        if (!$team || !$team['stripe_customer_id']) {
            Response::error('No Stripe customer found', 404);
        }

        $returnUrl = $body['return_url'] ?? BASE_URL . '/pages/billing.html';

        $session = StripeHelper::createPortalSession($team['stripe_customer_id'], $returnUrl);

        Response::success('Portal session created', [
            'url' => $session->url
        ]);

    } catch (Exception $e) {
        Response::serverError('Failed to create portal session: ' . $e->getMessage());
    }
}

/**
 * Cancel subscription
 */
function cancelSubscription($body) {
    $auth = authenticateUser();
    $userId = $auth['user_id'];

    $atPeriodEnd = $body['at_period_end'] ?? true;

    try {
        $db = Database::getInstance()->getConnection();

        // Get user's team subscription
        $stmt = $db->prepare("
            SELECT ts.*
            FROM team_subscriptions ts
            JOIN teams t ON ts.team_id = t.team_id
            WHERE t.owner_user_id = ?
            AND ts.status IN ('active', 'trialing')
            ORDER BY ts.created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $subscription = $stmt->fetch();

        if (!$subscription) {
            Response::error('No active subscription found', 404);
        }

        if (!$subscription['stripe_subscription_id']) {
            Response::error('No Stripe subscription found', 404);
        }

        // Cancel in Stripe
        StripeHelper::cancelSubscription($subscription['stripe_subscription_id'], $atPeriodEnd);

        // Update database
        if ($atPeriodEnd) {
            $stmt = $db->prepare("
                UPDATE team_subscriptions
                SET cancel_at_period_end = TRUE
                WHERE subscription_id = ?
            ");
            $stmt->execute([$subscription['subscription_id']]);
        } else {
            $stmt = $db->prepare("
                UPDATE team_subscriptions
                SET status = 'canceled', canceled_at = NOW()
                WHERE subscription_id = ?
            ");
            $stmt->execute([$subscription['subscription_id']]);
        }

        Response::success('Subscription canceled');

    } catch (Exception $e) {
        Response::serverError('Failed to cancel subscription: ' . $e->getMessage());
    }
}

/**
 * Get usage statistics
 */
function getUsage() {
    $auth = authenticateUser();
    $userId = $auth['user_id'];

    try {
        $db = Database::getInstance()->getConnection();

        // Get user's team
        $stmt = $db->prepare("SELECT team_id FROM teams WHERE owner_user_id = ?");
        $stmt->execute([$userId]);
        $team = $stmt->fetch();

        if (!$team) {
            Response::error('Team not found', 404);
        }

        $teamId = $team['team_id'];
        $subscription = Subscription::getTeamSubscription($teamId);
        $usage = Subscription::getUsage($teamId);
        $limits = $subscription['limits'] ?? [];

        // Format usage with limits
        $usageData = [
            'sops_created' => [
                'current' => $usage['sops_created'] ?? 0,
                'limit' => $limits['sops_per_month'] ?? 5,
                'percentage' => $limits['sops_per_month'] > 0
                    ? round((($usage['sops_created'] ?? 0) / $limits['sops_per_month']) * 100)
                    : 0
            ],
            'api_calls' => [
                'current' => $usage['api_calls'] ?? 0,
                'limit' => $limits['api_calls_per_day'] ?? 100,
                'percentage' => $limits['api_calls_per_day'] > 0
                    ? round((($usage['api_calls'] ?? 0) / $limits['api_calls_per_day']) * 100)
                    : 0
            ],
            'storage_used_mb' => [
                'current' => $usage['storage_mb'] ?? 0,
                'limit' => $limits['storage_mb'] ?? 100,
                'percentage' => $limits['storage_mb'] > 0
                    ? round((($usage['storage_mb'] ?? 0) / $limits['storage_mb']) * 100)
                    : 0
            ]
        ];

        Response::success('Usage retrieved', [
            'usage' => $usageData,
            'plan' => $subscription['plan_slug']
        ]);

    } catch (Exception $e) {
        Response::serverError('Failed to get usage: ' . $e->getMessage());
    }
}

/**
 * Handle Stripe webhooks
 */
function handleStripeWebhook() {
    $payload = @file_get_contents('php://input');
    $sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

    try {
        // Verify webhook signature
        $event = \Stripe\Webhook::constructEvent(
            $payload,
            $sigHeader,
            STRIPE_WEBHOOK_SECRET
        );

    } catch (\Exception $e) {
        Response::error('Webhook signature verification failed', 400);
    }

    try {
        $db = Database::getInstance()->getConnection();

        // Log event
        $stmt = $db->prepare("
            INSERT INTO stripe_webhook_events (stripe_event_id, event_type, payload)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$event->id, $event->type, json_encode($event->data->object)]);

        // Handle event
        switch ($event->type) {
            case 'checkout.session.completed':
                handleCheckoutCompleted($event->data->object);
                break;

            case 'customer.subscription.updated':
            case 'customer.subscription.created':
                handleSubscriptionUpdated($event->data->object);
                break;

            case 'customer.subscription.deleted':
                handleSubscriptionDeleted($event->data->object);
                break;

            case 'invoice.payment_succeeded':
                handlePaymentSucceeded($event->data->object);
                break;

            case 'invoice.payment_failed':
                handlePaymentFailed($event->data->object);
                break;
        }

        // Mark event as processed
        $stmt = $db->prepare("UPDATE stripe_webhook_events SET processed = TRUE, processed_at = NOW() WHERE stripe_event_id = ?");
        $stmt->execute([$event->id]);

        http_response_code(200);
        echo json_encode(['received' => true]);

    } catch (Exception $e) {
        error_log('Webhook error: ' . $e->getMessage());
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function handleCheckoutCompleted($session) {
    $db = Database::getInstance()->getConnection();

    $metadata = $session->metadata;
    $teamId = $metadata->team_id ?? null;
    $planId = $metadata->plan_id ?? null;

    if (!$teamId || !$planId) return;

    $subscriptionId = $session->subscription;
    $customerId = $session->customer;

    // Get subscription details from Stripe
    $subscription = \Stripe\Subscription::retrieve($subscriptionId);

    $stmt = $db->prepare("
        INSERT INTO team_subscriptions (
            team_id, plan_id, stripe_subscription_id, stripe_customer_id,
            status, billing_cycle, current_period_start, current_period_end
        ) VALUES (?, ?, ?, ?, ?, ?, FROM_UNIXTIME(?), FROM_UNIXTIME(?))
        ON DUPLICATE KEY UPDATE
            plan_id = VALUES(plan_id),
            status = VALUES(status),
            current_period_start = VALUES(current_period_start),
            current_period_end = VALUES(current_period_end)
    ");

    $stmt->execute([
        $teamId,
        $planId,
        $subscriptionId,
        $customerId,
        $subscription->status,
        $metadata->billing_cycle ?? 'monthly',
        $subscription->current_period_start,
        $subscription->current_period_end
    ]);
}

function handleSubscriptionUpdated($subscription) {
    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare("
        UPDATE team_subscriptions
        SET
            status = ?,
            current_period_start = FROM_UNIXTIME(?),
            current_period_end = FROM_UNIXTIME(?)
        WHERE stripe_subscription_id = ?
    ");

    $stmt->execute([
        $subscription->status,
        $subscription->current_period_start,
        $subscription->current_period_end,
        $subscription->id
    ]);
}

function handleSubscriptionDeleted($subscription) {
    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare("
        UPDATE team_subscriptions
        SET status = 'canceled', canceled_at = NOW()
        WHERE stripe_subscription_id = ?
    ");

    $stmt->execute([$subscription->id]);
}

function handlePaymentSucceeded($invoice) {
    $db = Database::getInstance()->getConnection();

    // Get team from customer ID
    $stmt = $db->prepare("SELECT team_id FROM teams WHERE stripe_customer_id = ?");
    $stmt->execute([$invoice->customer]);
    $team = $stmt->fetch();

    if (!$team) return;

    // Log payment
    $stmt = $db->prepare("
        INSERT INTO payment_history (
            team_id, stripe_payment_intent_id, amount, currency,
            status, description, receipt_url
        ) VALUES (?, ?, ?, ?, 'succeeded', ?, ?)
    ");

    $stmt->execute([
        $team['team_id'],
        $invoice->payment_intent,
        $invoice->amount_paid / 100,
        strtoupper($invoice->currency),
        'Subscription payment',
        $invoice->hosted_invoice_url
    ]);
}

function handlePaymentFailed($invoice) {
    $db = Database::getInstance()->getConnection();

    // Get team from customer ID
    $stmt = $db->prepare("SELECT team_id FROM teams WHERE stripe_customer_id = ?");
    $stmt->execute([$invoice->customer]);
    $team = $stmt->fetch();

    if (!$team) return;

    // Log failed payment
    $stmt = $db->prepare("
        INSERT INTO payment_history (
            team_id, stripe_payment_intent_id, amount, currency,
            status, description
        ) VALUES (?, ?, ?, ?, 'failed', ?)
    ");

    $stmt->execute([
        $team['team_id'],
        $invoice->payment_intent,
        $invoice->amount_due / 100,
        strtoupper($invoice->currency),
        'Payment failed: ' . ($invoice->last_finalization_error->message ?? 'Unknown error')
    ]);
}
