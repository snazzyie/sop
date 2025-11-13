<?php
// Subscription Management Class

class Subscription {

    /**
     * Get team's current subscription
     */
    public static function getTeamSubscription($teamId) {
        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare("
            SELECT
                ts.*,
                sp.plan_name,
                sp.plan_slug,
                sp.features,
                sp.limits
            FROM team_subscriptions ts
            JOIN subscription_plans sp ON ts.plan_id = sp.plan_id
            WHERE ts.team_id = ?
            AND ts.status IN ('active', 'trialing')
            ORDER BY ts.created_at DESC
            LIMIT 1
        ");

        $stmt->execute([$teamId]);
        $subscription = $stmt->fetch();

        if (!$subscription) {
            // Return free plan if no subscription
            return self::getFreePlan($teamId);
        }

        // Decode JSON fields
        $subscription['features'] = json_decode($subscription['features'], true);
        $subscription['limits'] = json_decode($subscription['limits'], true);

        return $subscription;
    }

    /**
     * Get free plan details
     */
    private static function getFreePlan($teamId) {
        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare("
            SELECT
                plan_id,
                plan_name,
                plan_slug,
                features,
                limits
            FROM subscription_plans
            WHERE plan_slug = 'free'
            LIMIT 1
        ");

        $stmt->execute();
        $plan = $stmt->fetch();

        if (!$plan) {
            throw new Exception('Free plan not found');
        }

        return [
            'subscription_id' => null,
            'team_id' => $teamId,
            'plan_id' => $plan['plan_id'],
            'plan_name' => $plan['plan_name'],
            'plan_slug' => $plan['plan_slug'],
            'status' => 'active',
            'features' => json_decode($plan['features'], true),
            'limits' => json_decode($plan['limits'], true)
        ];
    }

    /**
     * Check if team has access to a feature
     */
    public static function hasFeature($teamId, $feature) {
        $subscription = self::getTeamSubscription($teamId);

        // Enterprise has all features
        if ($subscription['plan_slug'] === 'enterprise') {
            return true;
        }

        // Check feature list
        $features = $subscription['features'] ?? [];

        // Check if feature string is in features array
        foreach ($features as $f) {
            if (stripos($f, $feature) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if team is within usage limits
     */
    public static function checkLimit($teamId, $limitKey, $currentValue = null) {
        $subscription = self::getTeamSubscription($teamId);
        $limits = $subscription['limits'] ?? [];

        if (!isset($limits[$limitKey])) {
            return ['allowed' => true, 'limit' => -1, 'current' => 0];
        }

        $limit = $limits[$limitKey];

        // -1 means unlimited
        if ($limit == -1) {
            return ['allowed' => true, 'limit' => -1, 'current' => $currentValue ?? 0];
        }

        // If current value provided, check it
        if ($currentValue !== null) {
            return [
                'allowed' => $currentValue < $limit,
                'limit' => $limit,
                'current' => $currentValue
            ];
        }

        // Otherwise just return the limit
        return ['allowed' => true, 'limit' => $limit, 'current' => 0];
    }

    /**
     * Get current usage for a team
     */
    public static function getUsage($teamId, $metric = null) {
        $db = Database::getInstance()->getConnection();

        $currentMonth = date('Y-m-01');
        $nextMonth = date('Y-m-01', strtotime('+1 month'));

        if ($metric) {
            $stmt = $db->prepare("
                SELECT metric_name, SUM(metric_value) as total
                FROM usage_tracking
                WHERE team_id = ?
                AND metric_name = ?
                AND period_start >= ?
                AND period_start < ?
                GROUP BY metric_name
            ");
            $stmt->execute([$teamId, $metric, $currentMonth, $nextMonth]);
            $result = $stmt->fetch();

            return $result ? (int)$result['total'] : 0;
        } else {
            $stmt = $db->prepare("
                SELECT metric_name, SUM(metric_value) as total
                FROM usage_tracking
                WHERE team_id = ?
                AND period_start >= ?
                AND period_start < ?
                GROUP BY metric_name
            ");
            $stmt->execute([$teamId, $currentMonth, $nextMonth]);

            $usage = [];
            while ($row = $stmt->fetch()) {
                $usage[$row['metric_name']] = (int)$row['total'];
            }

            return $usage;
        }
    }

    /**
     * Track usage metric
     */
    public static function trackUsage($teamId, $metric, $value = 1) {
        $db = Database::getInstance()->getConnection();

        $periodStart = date('Y-m-01');
        $periodEnd = date('Y-m-t');

        $stmt = $db->prepare("
            INSERT INTO usage_tracking (team_id, metric_name, metric_value, period_start, period_end)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                metric_value = metric_value + VALUES(metric_value),
                updated_at = CURRENT_TIMESTAMP
        ");

        $stmt->execute([$teamId, $metric, $value, $periodStart, $periodEnd]);
    }

    /**
     * Get all available plans
     */
    public static function getAvailablePlans() {
        $db = Database::getInstance()->getConnection();

        $stmt = $db->query("
            SELECT
                plan_id,
                plan_name,
                plan_slug,
                description,
                price_monthly,
                price_yearly,
                features,
                limits,
                sort_order
            FROM subscription_plans
            WHERE is_active = TRUE
            ORDER BY sort_order ASC
        ");

        $plans = [];
        while ($plan = $stmt->fetch()) {
            $plan['features'] = json_decode($plan['features'], true);
            $plan['limits'] = json_decode($plan['limits'], true);
            $plans[] = $plan;
        }

        return $plans;
    }

    /**
     * Check if action is allowed based on limits
     */
    public static function canPerformAction($teamId, $action) {
        $subscription = self::getTeamSubscription($teamId);
        $limits = $subscription['limits'] ?? [];

        switch ($action) {
            case 'create_sop':
                $limit = $limits['sops_per_month'] ?? 5;
                if ($limit == -1) return true; // Unlimited

                $currentUsage = self::getUsage($teamId, 'sops_created');
                return $currentUsage < $limit;

            case 'add_team_member':
                $limit = $limits['team_members'] ?? 1;
                if ($limit == -1) return true; // Unlimited

                $db = Database::getInstance()->getConnection();
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM team_members WHERE team_id = ?");
                $stmt->execute([$teamId]);
                $current = $stmt->fetch()['count'];

                return $current < $limit;

            case 'api_call':
                $limit = $limits['api_calls_per_day'] ?? 100;
                if ($limit == -1) return true; // Unlimited

                $currentUsage = self::getUsage($teamId, 'api_calls');
                return $currentUsage < $limit;

            case 'export_pdf':
                // PDF export only for Pro and Enterprise
                return in_array($subscription['plan_slug'], ['pro', 'enterprise']);

            case 'custom_branding':
                // Custom branding only for Pro and Enterprise
                return in_array($subscription['plan_slug'], ['pro', 'enterprise']);

            case 'api_access':
                // API access only for Pro and Enterprise
                return in_array($subscription['plan_slug'], ['pro', 'enterprise']);

            default:
                return true;
        }
    }
}
