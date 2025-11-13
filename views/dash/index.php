<?php require BASE_PATH . 'views/_partials/html_head.php'; ?>

<?php require BASE_PATH . 'views/_partials/html_header.php'; ?>

<div class="main-layout">
    <?php require BASE_PATH . 'views/_partials/html_sidebar.php'; ?>

    <main class="main-content">
        <div class="content-header">
            <h2>Dashboard</h2>

            <div class="header-actions">
                <a href="/sessions/start" class="btn btn-primary">
                    <i class="material-icons">fiber_manual_record</i>
                    <span>Start Recording</span>
                </a>
            </div>
        </div>

        <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: #667eea;">
                    <i class="material-icons">description</i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $sop_stats['total'] ?? 0; ?></div>
                    <div class="stat-label">Total SOPs</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: #10b981;">
                    <i class="material-icons">check_circle</i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $sop_stats['published'] ?? 0; ?></div>
                    <div class="stat-label">Published</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: #f59e0b;">
                    <i class="material-icons">fiber_manual_record</i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $session_stats['recording'] ?? 0; ?></div>
                    <div class="stat-label">Active Sessions</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: #8b5cf6;">
                    <i class="material-icons">share</i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $subscription['plan_name'] ?? 'Free'; ?></div>
                    <div class="stat-label">Current Plan</div>
                </div>
            </div>
        </div>

        <!-- Recent SOPs -->
        <div class="content-section">
            <div class="section-header">
                <h3>Recent SOPs</h3>
                <a href="/sops" class="btn btn-text">View All</a>
            </div>

            <?php if (!empty($recent_sops)): ?>
                <div class="sops-list">
                    <?php foreach ($recent_sops as $sop): ?>
                        <div class="sop-item">
                            <div class="sop-info">
                                <h4>
                                    <a href="/sops/view?id=<?php echo $sop['sop_id']; ?>">
                                        <?php echo htmlspecialchars($sop['title']); ?>
                                    </a>
                                </h4>
                                <p class="sop-meta">
                                    <?php echo $sop['step_count'] ?? 0; ?> steps
                                    •
                                    <?php echo date('M j, Y', strtotime($sop['created_at'])); ?>
                                    •
                                    by <?php echo htmlspecialchars($sop['creator_name'] ?? 'Unknown'); ?>
                                </p>
                            </div>

                            <div class="sop-status">
                                <span class="badge badge-<?php echo $sop['status']; ?>">
                                    <?php echo ucfirst($sop['status']); ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="material-icons">description</i>
                    <p>No SOPs yet. Start recording to create your first SOP.</p>
                    <a href="/sessions/start" class="btn btn-primary">Start Recording</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Active Sessions -->
        <?php if (!empty($active_sessions)): ?>
            <div class="content-section">
                <div class="section-header">
                    <h3>Active Recording Sessions</h3>
                    <a href="/sessions" class="btn btn-text">View All</a>
                </div>

                <div class="sessions-list">
                    <?php foreach ($active_sessions as $session): ?>
                        <div class="session-item">
                            <div class="session-indicator recording"></div>
                            <div class="session-info">
                                <h4>Recording Session #<?php echo $session['session_id']; ?></h4>
                                <p class="session-meta">
                                    Started <?php echo date('M j, Y g:i A', strtotime($session['created_at'])); ?>
                                    <?php if ($session['start_url']): ?>
                                        • <span class="session-url"><?php echo htmlspecialchars($session['start_url']); ?></span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="session-actions">
                                <a href="/sessions/view?id=<?php echo $session['session_id']; ?>" class="btn btn-sm btn-primary">
                                    View
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </main>
</div>

<?php require BASE_PATH . 'views/_partials/html_footer.php'; ?>
