<?php
// Core Router Functions

function fn_core_router($uri) {
    // Define routes (URL => Controller file path)
    $routes = [
        // Public routes
        '/' => 'login/login',
        '/login' => 'login/login',
        '/register' => 'login/register',
        '/logout' => 'login/logout',
        '/reset-password' => 'login/reset-password',
        '/oauth/google/login' => 'login/oauth-google-login',
        '/oauth/google/callback' => 'login/oauth-google-callback',

        // Dashboard
        '/dash' => 'dash/index',

        // SOPs
        '/sops' => 'sops/sops',
        '/sops/view' => 'sops/sops-view',
        '/sops/edit' => 'sops/sops-edit',
        '/sops/delete' => 'sops/sops-delete',
        '/sops/export' => 'sops/sops-export',
        '/sops/share' => 'sops/sops-share',

        // Recording Sessions
        '/sessions' => 'sessions/sessions',
        '/sessions/new' => 'sessions/sessions-new',
        '/sessions/finalize' => 'sessions/sessions-finalize',
        '/sessions/steps' => 'sessions/sessions-steps',

        // Subscriptions & Plans
        '/subscriptions' => 'subscriptions/subscriptions',
        '/subscriptions/checkout' => 'subscriptions/subscriptions-checkout',
        '/subscriptions/portal' => 'subscriptions/subscriptions-portal',
        '/subscriptions/cancel' => 'subscriptions/subscriptions-cancel',
        '/plans' => 'plans/plans',
        '/pricing' => 'plans/pricing',

        // Users
        '/users' => 'users/users',
        '/users/new' => 'users/users-new',
        '/users/edit' => 'users/users-edit',
        '/users/delete' => 'users/users-delete',

        // Company
        '/company' => 'company/company',
        '/company/new' => 'company/company-new',
        '/company/edit' => 'company/company-edit',

        // API Keys
        '/api-keys' => 'settings/api-keys',
        '/api-keys/new' => 'settings/api-keys-new',
        '/api-keys/revoke' => 'settings/api-keys-revoke',
        '/api-keys/delete' => 'settings/api-keys-delete',

        // Settings
        '/settings' => 'settings/settings',
        '/settings/profile' => 'settings/settings-profile',
        '/settings/billing' => 'settings/settings-billing',

        // Webhooks
        '/webhook/stripe' => 'webhook/stripe',
        '/webhook/install' => 'webhook/install',

        // Cron
        '/cron/subscriptions' => 'cron/subscriptions',

        // Errors
        '/404' => 'errors/404',
        '/403' => 'errors/403'
    ];

    // Check if route exists
    if (array_key_exists($uri, $routes)) {
        $controllerPath = BASE_PATH . 'app/controllers/' . $routes[$uri] . '.php';

        if (file_exists($controllerPath)) {
            require $controllerPath;
        } else {
            fn_core_router_404();
        }
    } else {
        fn_core_router_404();
    }
}

function fn_core_router_404() {
    header("HTTP/1.0 404 Not Found");
    $controllerPath = BASE_PATH . 'app/controllers/errors/404.php';

    if (file_exists($controllerPath)) {
        require $controllerPath;
    } else {
        echo "<h1>404 - Page Not Found</h1>";
    }
    exit;
}
