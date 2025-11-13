<header class="main-header">
    <div class="header-container">
        <div class="header-left">
            <button class="sidebar-toggle" id="sidebar-toggle">
                <i class="material-icons">menu</i>
            </button>

            <div class="header-logo">
                <a href="/dash">
                    <h1>SOP Recorder</h1>
                </a>
            </div>
        </div>

        <div class="header-center">
            <?php if (isset($breadcrumbs) && !empty($breadcrumbs)): ?>
                <?php echo fn_core_menu_breadcrumbs($breadcrumbs); ?>
            <?php endif; ?>
        </div>

        <div class="header-right">
            <?php if (isset($_SESSION['user_id'])): ?>
                <div class="header-user">
                    <div class="user-avatar">
                        <?php if (isset($user_data['avatar_url']) && $user_data['avatar_url']): ?>
                            <img src="<?php echo htmlspecialchars($user_data['avatar_url']); ?>" alt="Avatar">
                        <?php else: ?>
                            <i class="material-icons">account_circle</i>
                        <?php endif; ?>
                    </div>

                    <div class="user-info">
                        <div class="user-name"><?php echo htmlspecialchars($_SESSION['name'] ?? 'User'); ?></div>
                        <div class="user-email"><?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?></div>
                    </div>

                    <div class="user-dropdown">
                        <a href="/settings" class="dropdown-item">
                            <i class="material-icons">settings</i>
                            <span>Settings</span>
                        </a>
                        <a href="/logout" class="dropdown-item">
                            <i class="material-icons">logout</i>
                            <span>Logout</span>
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</header>
