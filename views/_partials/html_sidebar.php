<aside class="sidebar" id="sidebar">
    <div class="sidebar-content">
        <?php
        // Render menu
        if (isset($menu) && !empty($menu)) {
            echo fn_core_menu_render($menu);
        }
        ?>
    </div>

    <?php if (isset($subscription) && $subscription): ?>
        <div class="sidebar-footer">
            <div class="subscription-info">
                <div class="subscription-plan">
                    <?php echo htmlspecialchars($subscription['plan_name']); ?>
                </div>

                <?php if ($subscription['status'] === 'active' || $subscription['status'] === 'trialing'): ?>
                    <div class="subscription-status active">
                        <i class="material-icons">check_circle</i>
                        <span><?php echo ucfirst($subscription['status']); ?></span>
                    </div>
                <?php else: ?>
                    <div class="subscription-status inactive">
                        <i class="material-icons">warning</i>
                        <span><?php echo ucfirst($subscription['status']); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($subscription['plan_slug'] !== 'pro' && $subscription['plan_slug'] !== 'enterprise'): ?>
                    <a href="/pricing" class="btn-upgrade">
                        <i class="material-icons">star</i>
                        <span>Upgrade Plan</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</aside>
