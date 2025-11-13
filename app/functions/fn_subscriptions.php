<?php
// Subscription Management Functions

/**
 * Get team's current subscription
 */
function fn_subscriptions_get_team($company_id) {
    $query = "
        SELECT
            ts.*,
            sp.plan_name,
            sp.plan_slug,
            sp.features,
            sp.limits,
            sp.price_monthly,
            sp.price_yearly
        FROM team_subscriptions ts
        JOIN subscription_plans sp ON ts.plan_id = sp.plan_id
        WHERE ts.team_id = ?
        AND ts.status IN ('active', 'trialing')
        ORDER BY ts.created_at DESC
        LIMIT 1
    ";

    $result = fn_core_database_row($query, [$company_id]);

    if ($result) {
        $result['features'] = json_decode($result['features'], true);
        $result['limits'] = json_decode($result['limits'], true);
    }

    return $result;
}

/**
 * Get all available subscription plans
 */
function fn_subscriptions_get_plans() {
    $query = "
        SELECT *
        FROM subscription_plans
        WHERE is_active = 1
        ORDER BY price_monthly ASC
    ";

    $plans = fn_core_database_rows($query);

    foreach ($plans as &$plan) {
        $plan['features'] = json_decode($plan['features'], true);
        $plan['limits'] = json_decode($plan['limits'], true);
    }

    return $plans;
}

/**
 * Get plan by slug
 */
function fn_subscriptions_get_plan_by_slug($slug) {
    $query = "SELECT * FROM subscription_plans WHERE plan_slug = ? AND is_active = 1";
    $plan = fn_core_database_row($query, [$slug]);

    if ($plan) {
        $plan['features'] = json_decode($plan['features'], true);
        $plan['limits'] = json_decode($plan['limits'], true);
    }

    return $plan;
}

/**
 * Check if team has a specific feature
 */
function fn_subscriptions_has_feature($company_id, $feature) {
    $subscription = fn_subscriptions_get_team($company_id);

    if (!$subscription) {
        return false;
    }

    $features = $subscription['features'] ?? [];
    return in_array($feature, $features);
}

/**
 * Check if action is within plan limits
 */
function fn_subscriptions_check_limit($company_id, $limit_key) {
    $subscription = fn_subscriptions_get_team($company_id);

    if (!$subscription) {
        return false;
    }

    $limits = $subscription['limits'] ?? [];

    // If limit doesn't exist or is -1, it's unlimited
    if (!isset($limits[$limit_key]) || $limits[$limit_key] === -1) {
        return true;
    }

    // Get current usage
    $usage = fn_subscriptions_get_usage($company_id, $limit_key);

    return $usage < $limits[$limit_key];
}

/**
 * Check if team can create a new SOP
 */
function fn_subscriptions_can_create_sop($company_id) {
    $subscription = fn_subscriptions_get_team($company_id);

    if (!$subscription) {
        return false;
    }

    $limits = $subscription['limits'] ?? [];
    $max_sops = $limits['max_sops'] ?? 10;

    // Unlimited
    if ($max_sops === -1) {
        return true;
    }

    // Check current count
    $current_count = fn_sops_count($company_id);

    return $current_count < $max_sops;
}

/**
 * Check if team can create API key
 */
function fn_subscriptions_can_create_api_key($company_id) {
    return fn_subscriptions_has_feature($company_id, 'api_access');
}

/**
 * Get usage statistics for a team
 */
function fn_subscriptions_get_usage($company_id, $metric = null) {
    if ($metric) {
        $query = "
            SELECT SUM(usage_value) as total
            FROM usage_tracking
            WHERE team_id = ?
            AND metric_name = ?
            AND tracked_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ";
        $result = fn_core_database_row($query, [$company_id, $metric]);
        return $result['total'] ?? 0;
    }

    // Get all metrics
    $query = "
        SELECT
            metric_name,
            SUM(usage_value) as total
        FROM usage_tracking
        WHERE team_id = ?
        AND tracked_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY metric_name
    ";

    $rows = fn_core_database_rows($query, [$company_id]);

    $usage = [];
    foreach ($rows as $row) {
        $usage[$row['metric_name']] = $row['total'];
    }

    return $usage;
}

/**
 * Track usage for a team
 */
function fn_subscriptions_track_usage($company_id, $metric, $value = 1) {
    $query = "
        INSERT INTO usage_tracking (
            team_id,
            metric_name,
            usage_value,
            tracked_at
        ) VALUES (?, ?, ?, NOW())
    ";

    fn_core_insert_row_no_redirect($query, [$company_id, $metric, $value]);
}

/**
 * Create or update team subscription
 */
function fn_subscriptions_create($company_id, $plan_id, $stripe_subscription_id = null, $status = 'active') {
    // Check if subscription exists
    $existing = fn_subscriptions_get_team($company_id);

    if ($existing) {
        // Update existing
        $query = "
            UPDATE team_subscriptions
            SET plan_id = ?,
                stripe_subscription_id = ?,
                status = ?,
                updated_at = NOW()
            WHERE team_id = ?
        ";

        fn_core_edit_row_no_redirect($query, [$plan_id, $stripe_subscription_id, $status, $company_id]);
    } else {
        // Create new
        $query = "
            INSERT INTO team_subscriptions (
                team_id,
                plan_id,
                stripe_subscription_id,
                status,
                created_at,
                updated_at
            ) VALUES (?, ?, ?, ?, NOW(), NOW())
        ";

        fn_core_insert_row_no_redirect($query, [$company_id, $plan_id, $stripe_subscription_id, $status]);
    }

    return true;
}

/**
 * Update subscription status
 */
function fn_subscriptions_update_status($stripe_subscription_id, $status) {
    $query = "
        UPDATE team_subscriptions
        SET status = ?,
            updated_at = NOW()
        WHERE stripe_subscription_id = ?
    ";

    return fn_core_edit_row_no_redirect($query, [$status, $stripe_subscription_id]);
}

/**
 * Cancel subscription
 */
function fn_subscriptions_cancel($company_id) {
    $subscription = fn_subscriptions_get_team($company_id);

    if (!$subscription) {
        return false;
    }

    // Cancel in Stripe if exists
    if ($subscription['stripe_subscription_id']) {
        fn_stripe_cancel_subscription($subscription['stripe_subscription_id']);
    }

    // Update status
    $query = "
        UPDATE team_subscriptions
        SET status = 'canceled',
            canceled_at = NOW(),
            updated_at = NOW()
        WHERE team_id = ?
    ";

    return fn_core_edit_row_no_redirect($query, [$company_id]);
}

/**
 * Get subscription history for a team
 */
function fn_subscriptions_get_history($company_id) {
    $query = "
        SELECT
            ts.*,
            sp.plan_name,
            sp.plan_slug
        FROM team_subscriptions ts
        JOIN subscription_plans sp ON ts.plan_id = sp.plan_id
        WHERE ts.team_id = ?
        ORDER BY ts.created_at DESC
    ";

    return fn_core_database_rows($query, [$company_id]);
}

/**
 * Get team by Stripe subscription ID
 */
function fn_subscriptions_get_team_by_stripe_id($stripe_subscription_id) {
    $query = "
        SELECT team_id
        FROM team_subscriptions
        WHERE stripe_subscription_id = ?
    ";

    $result = fn_core_database_row($query, [$stripe_subscription_id]);
    return $result['team_id'] ?? null;
}

/**
 * Upgrade/downgrade subscription
 */
function fn_subscriptions_change_plan($company_id, $new_plan_slug) {
    $new_plan = fn_subscriptions_get_plan_by_slug($new_plan_slug);

    if (!$new_plan) {
        return ['error' => 'Plan not found'];
    }

    $current_subscription = fn_subscriptions_get_team($company_id);

    if (!$current_subscription) {
        return ['error' => 'No active subscription'];
    }

    // Update in Stripe if exists
    if ($current_subscription['stripe_subscription_id']) {
        // This would be handled by Stripe checkout
        return ['redirect_to_stripe' => true];
    }

    // Direct update (for free plan or manual changes)
    fn_subscriptions_create($company_id, $new_plan['plan_id'], null, 'active');

    return ['success' => true];
}
