<?php
// Core Menu Functions

/**
 * Generate main navigation menu based on user permissions
 */
function fn_core_menu_main() {
    $user_type = $_SESSION['user_type'] ?? 0;
    $current_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    $menu_items = [];

    // Dashboard (all logged-in users)
    if ($user_type >= 1) {
        $menu_items[] = [
            'label' => 'Dashboard',
            'url' => '/dash',
            'icon' => 'dashboard',
            'active' => $current_uri === '/dash'
        ];
    }

    // SOPs (all logged-in users)
    if ($user_type >= 1) {
        $menu_items[] = [
            'label' => 'SOPs',
            'url' => '/sops',
            'icon' => 'description',
            'active' => str_starts_with($current_uri, '/sops')
        ];
    }

    // Recording Sessions (all logged-in users)
    if ($user_type >= 1) {
        $menu_items[] = [
            'label' => 'Sessions',
            'url' => '/sessions',
            'icon' => 'fiber_manual_record',
            'active' => str_starts_with($current_uri, '/sessions')
        ];
    }

    // API Keys (paid subscribers)
    if ($user_type >= 3) {
        $menu_items[] = [
            'label' => 'API Keys',
            'url' => '/api-keys',
            'icon' => 'key',
            'active' => str_starts_with($current_uri, '/api-keys')
        ];
    }

    // Team Management (has company)
    if ($user_type >= 2) {
        $menu_items[] = [
            'label' => 'Team',
            'url' => '/team',
            'icon' => 'people',
            'active' => str_starts_with($current_uri, '/team')
        ];
    }

    // Subscription (has company)
    if ($user_type >= 2) {
        $menu_items[] = [
            'label' => 'Subscription',
            'url' => '/subscription',
            'icon' => 'credit_card',
            'active' => str_starts_with($current_uri, '/subscription')
        ];
    }

    // Pricing (needs company or not subscribed)
    if ($user_type >= 1 && $user_type < 3) {
        $menu_items[] = [
            'label' => 'Upgrade',
            'url' => '/pricing',
            'icon' => 'star',
            'active' => $current_uri === '/pricing',
            'badge' => 'Pro'
        ];
    }

    // Settings (all logged-in users)
    if ($user_type >= 1) {
        $menu_items[] = [
            'label' => 'Settings',
            'url' => '/settings',
            'icon' => 'settings',
            'active' => str_starts_with($current_uri, '/settings')
        ];
    }

    // Super Admin section
    if ($user_type === 10) {
        $menu_items[] = [
            'label' => 'divider'
        ];

        $menu_items[] = [
            'label' => 'Admin',
            'url' => '/admin',
            'icon' => 'admin_panel_settings',
            'active' => str_starts_with($current_uri, '/admin')
        ];

        $menu_items[] = [
            'label' => 'All Users',
            'url' => '/admin/users',
            'icon' => 'supervisor_account',
            'active' => str_starts_with($current_uri, '/admin/users')
        ];

        $menu_items[] = [
            'label' => 'All Teams',
            'url' => '/admin/teams',
            'icon' => 'business',
            'active' => str_starts_with($current_uri, '/admin/teams')
        ];

        $menu_items[] = [
            'label' => 'System Settings',
            'url' => '/admin/settings',
            'icon' => 'tune',
            'active' => str_starts_with($current_uri, '/admin/settings')
        ];
    }

    return $menu_items;
}

/**
 * Render menu as HTML
 */
function fn_core_menu_render($menu_items = null) {
    if ($menu_items === null) {
        $menu_items = fn_core_menu_main();
    }

    if (empty($menu_items)) {
        return '';
    }

    $html = '<nav class="sidebar-menu">';

    foreach ($menu_items as $item) {
        if ($item['label'] === 'divider') {
            $html .= '<div class="menu-divider"></div>';
            continue;
        }

        $active_class = ($item['active'] ?? false) ? ' active' : '';
        $icon = $item['icon'] ?? 'circle';
        $badge = isset($item['badge']) ? '<span class="menu-badge">' . htmlspecialchars($item['badge']) . '</span>' : '';

        $html .= '<a href="' . htmlspecialchars($item['url']) . '" class="menu-item' . $active_class . '">';
        $html .= '<i class="material-icons">' . $icon . '</i>';
        $html .= '<span>' . htmlspecialchars($item['label']) . '</span>';
        $html .= $badge;
        $html .= '</a>';
    }

    $html .= '</nav>';

    return $html;
}

/**
 * Generate breadcrumbs
 */
function fn_core_menu_breadcrumbs($crumbs) {
    if (empty($crumbs)) {
        return '';
    }

    $html = '<nav class="breadcrumbs">';

    $count = count($crumbs);
    foreach ($crumbs as $index => $crumb) {
        $is_last = ($index === $count - 1);

        if ($is_last) {
            $html .= '<span class="breadcrumb-current">' . htmlspecialchars($crumb['label']) . '</span>';
        } else {
            $html .= '<a href="' . htmlspecialchars($crumb['url']) . '" class="breadcrumb-link">' . htmlspecialchars($crumb['label']) . '</a>';
            $html .= '<span class="breadcrumb-separator">/</span>';
        }
    }

    $html .= '</nav>';

    return $html;
}

/**
 * Get menu item count (e.g., number of SOPs, sessions)
 */
function fn_core_menu_get_counts() {
    $user_type = $_SESSION['user_type'] ?? 0;
    $company_id = $_SESSION['company_id'] ?? null;

    if ($user_type === 0 || !$company_id) {
        return [];
    }

    $counts = [];

    // Count SOPs
    $counts['sops'] = fn_core_count_rows_company('sops', $company_id);

    // Count active sessions
    $query = "SELECT COUNT(*) as count FROM recording_sessions WHERE company_id = ? AND status = 'recording'";
    $result = fn_core_database_row($query, [$company_id]);
    $counts['active_sessions'] = $result['count'] ?? 0;

    // Count API keys
    $counts['api_keys'] = fn_core_count_rows_company('api_keys', $company_id);

    // Count team members
    $query = "SELECT COUNT(*) as count FROM users WHERE company_id = ?";
    $result = fn_core_database_row($query, [$company_id]);
    $counts['team_members'] = $result['count'] ?? 0;

    return $counts;
}

/**
 * Check if user has access to menu item
 */
function fn_core_menu_has_access($required_level) {
    $user_type = $_SESSION['user_type'] ?? 0;
    return $user_type >= $required_level;
}
