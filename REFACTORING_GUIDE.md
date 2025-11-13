# SOP Recorder - Refactoring Guide

## Overview

The SOP Recorder has been refactored from a class-based RESTful API architecture to a procedural, session-based traditional PHP application following your boilerplate patterns.

## Key Changes

### 1. Architecture Shift

**Before:**
- RESTful API with JWT authentication
- Separate frontend (HTML/JS) and backend (PHP API)
- Class-based object-oriented PHP
- External routing library

**After:**
- Traditional PHP web application
- Session-based authentication
- Procedural function-based PHP
- Simple array-based routing
- Server-rendered views with partials

### 2. Directory Structure

```
/
├── config.php                      # Main configuration file
├── public/                         # Document root
│   ├── index.php                   # Front controller
│   ├── .htaccess                   # Apache URL rewriting
│   └── assets/                     # Static files (CSS, JS, images)
├── app/
│   ├── controllers/                # Page controllers
│   │   ├── login/
│   │   │   ├── login.php
│   │   │   ├── register.php
│   │   │   └── oauth-google-callback.php
│   │   ├── dash/
│   │   │   └── index.php
│   │   ├── sops/
│   │   │   ├── sops.php
│   │   │   └── sops-view.php
│   │   └── subscriptions/
│   │       └── subscriptions-checkout.php
│   └── functions/                  # Function libraries
│       ├── fn_core_database.php    # Database helpers
│       ├── fn_core_session.php     # Session & auth
│       ├── fn_core_router.php      # Routing
│       ├── fn_core_settings.php    # Settings management
│       ├── fn_core_menu.php        # Menu generation
│       ├── fn_core_debug.php       # Debug helpers
│       ├── fn_sops.php             # SOP management
│       ├── fn_sessions.php         # Recording sessions
│       ├── fn_subscriptions.php    # Subscription management
│       ├── fn_stripe.php           # Stripe integration
│       ├── fn_oauth.php            # OAuth integration
│       ├── fn_api_keys.php         # API key management
│       ├── fn_shares.php           # Share links
│       └── fn_exports.php          # Export functionality
├── views/                          # View templates
│   ├── _partials/                  # Reusable components
│   │   ├── html_head.php
│   │   ├── html_header.php
│   │   ├── html_sidebar.php
│   │   └── html_footer.php
│   ├── login/
│   │   ├── index.php
│   │   └── register.php
│   ├── dash/
│   │   └── index.php
│   └── sops/
│       ├── index.php
│       └── view.php
├── chrome-extension/               # Chrome extension (unchanged)
└── database/                       # Database schemas
```

### 3. Authentication System

**Permission Levels:**
- `0` = Guest (not logged in)
- `1` = Registered (needs company)
- `2` = Company Created (needs subscription)
- `3` = Paid Subscriber (full access)
- `10` = Super Admin

**Session Variables:**
```php
$_SESSION['user_id']      // User ID
$_SESSION['email']        // User email
$_SESSION['name']         // User name
$_SESSION['company_id']   // Team/company ID
$_SESSION['user_type']    // Permission level (0-10)
```

### 4. Routing System

Routes are defined in `app/functions/fn_core_router.php`:

```php
$routes = [
    '/' => 'login/login',
    '/login' => 'login/login',
    '/register' => 'login/register',
    '/dash' => 'dash/index',
    '/sops' => 'sops/sops',
    '/sops/view' => 'sops/sops-view',
    '/oauth/google/callback' => 'login/oauth-google-callback',
    '/subscriptions/checkout' => 'subscriptions/subscriptions-checkout',
    // ... more routes
];
```

### 5. Controller Pattern

Controllers use inline authentication checks:

```php
<?php
// Example: /app/controllers/sops/sops.php

// Require login
if ($_SESSION['user_type'] === 0) {
    header("Location: /login");
    exit;
}

// Get user data
$company_id = $_SESSION['company_id'];
$user_id = $_SESSION['user_id'];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Process form submission
}

// Get data for view
$sops = fn_sops_get_all($company_id);

// Set view variables
$page_title = 'SOPs';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/dash'],
    ['label' => 'SOPs', 'url' => '/sops']
];

// Load view
require BASE_PATH . 'views/sops/index.php';
```

### 6. Function Naming Convention

All functions follow the `fn_[module]_[action]` pattern:

**Core Functions:**
- `fn_core_database_rows()` - Fetch multiple rows
- `fn_core_database_row()` - Fetch single row
- `fn_core_insert_row()` - Insert and redirect
- `fn_core_session_log_me_in()` - Login user
- `fn_check_security()` - Verify access

**Feature Functions:**
- `fn_sops_get_all()` - Get SOPs for company
- `fn_sops_create()` - Create new SOP
- `fn_subscriptions_get_team()` - Get team subscription
- `fn_stripe_create_checkout_session()` - Create Stripe checkout
- `fn_oauth_google_get_auth_url()` - Get Google OAuth URL

### 7. Database Queries

All queries use prepared statements via helper functions:

```php
// Fetch multiple rows
$sops = fn_core_database_rows(
    "SELECT * FROM sops WHERE company_id = ? ORDER BY created_at DESC",
    [$company_id]
);

// Fetch single row
$sop = fn_core_database_row(
    "SELECT * FROM sops WHERE sop_id = ? AND company_id = ?",
    [$sop_id, $company_id]
);

// Insert with redirect
fn_core_insert_row(
    "INSERT INTO sops (title, company_id, created_by) VALUES (?, ?, ?)",
    [$title, $company_id, $user_id],
    '/sops'
);

// Insert without redirect
$sop_id = fn_core_insert_row_no_redirect(
    "INSERT INTO sops (title, company_id) VALUES (?, ?)",
    [$title, $company_id]
);

// Update
fn_core_edit_row_no_redirect(
    "UPDATE sops SET title = ? WHERE sop_id = ?",
    [$title, $sop_id]
);
```

### 8. View Templates

Views use PHP partials for reusability:

```php
<?php require BASE_PATH . 'views/_partials/html_head.php'; ?>

<?php require BASE_PATH . 'views/_partials/html_header.php'; ?>

<div class="main-layout">
    <?php require BASE_PATH . 'views/_partials/html_sidebar.php'; ?>

    <main class="main-content">
        <h2><?php echo htmlspecialchars($page_title); ?></h2>

        <!-- Content here -->
    </main>
</div>

<?php require BASE_PATH . 'views/_partials/html_footer.php'; ?>
```

### 9. Multi-Tenancy

All data is filtered by `company_id`:

```php
// In controllers
$company_id = $_SESSION['company_id'];

// In function calls
$sops = fn_sops_get_all($company_id);

// In security checks
fn_check_security($user_id, $company_id);

// Super admins (user_type = 10) can access all data
```

### 10. Configuration

All configuration is in `/config.php`:

```php
return [
    'app_name' => 'SOP Recorder',
    'app_url' => 'http://localhost:8000',
    'debug' => true,

    'database' => [
        'host' => 'localhost',
        'dbname' => 'sop_recorder',
        'username' => 'root',
        'password' => '',
    ],

    'stripe' => [
        'secret_key' => 'sk_test_...',
        'webhook_secret' => 'whsec_...',
    ],

    'google_oauth' => [
        'client_id' => '...',
        'client_secret' => '...',
        'redirect_uri' => 'http://localhost:8000/oauth/google/callback',
    ],
];
```

## Setup Instructions

### 1. Apache Configuration

Point your virtual host document root to `/public/`:

```apache
<VirtualHost *:80>
    ServerName soprecorder.local
    DocumentRoot /path/to/sop/public

    <Directory /path/to/sop/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

### 2. Database Setup

Run the existing SQL migration files:

```bash
mysql -u root -p sop_recorder < database/schema.sql
mysql -u root -p sop_recorder < database/saas_migration.sql
```

### 3. Configuration

Update `/config.php` with your settings:
- Database credentials
- Stripe API keys
- Google OAuth credentials
- App URL

### 4. File Permissions

Ensure upload directories are writable:

```bash
chmod 755 public/assets/
chmod 755 public/uploads/
```

### 5. Test the Application

1. Navigate to `http://soprecorder.local/`
2. You should see the login page
3. Register a new account
4. Explore the dashboard

## Key Features

### Session-Based Authentication

```php
// Login
fn_core_session_log_me_in($password, $password_hash, $email);

// Logout
fn_core_session_logout();

// Check if logged in
if (fn_is_logged_in()) {
    // User is logged in
}

// Require specific permission level
fn_require_permission(3); // Requires paid subscriber
```

### OAuth Integration

```php
// Generate Google OAuth URL
$auth_url = fn_oauth_google_get_auth_url();

// Handle callback
$result = fn_oauth_google_callback($code, $state);
```

### Subscription Management

```php
// Get team subscription
$subscription = fn_subscriptions_get_team($company_id);

// Check feature access
if (fn_subscriptions_has_feature($company_id, 'api_access')) {
    // Feature is available
}

// Check limits
if (fn_subscriptions_can_create_sop($company_id)) {
    // Can create SOP
}

// Track usage
fn_subscriptions_track_usage($company_id, 'sops_created');
```

### SOP Management

```php
// List SOPs
$sops = fn_sops_get_all($company_id, $offset, $limit, $filters);

// Get single SOP with steps
$sop = fn_sops_get_with_steps($sop_id, $company_id);

// Create SOP
$result = fn_sops_create([
    'title' => 'My SOP',
    'description' => 'Description'
], $company_id, $user_id);

// Update SOP
fn_sops_update($sop_id, ['title' => 'New Title'], $company_id);

// Publish/Unpublish
fn_sops_publish($sop_id, $company_id);
fn_sops_unpublish($sop_id, $company_id);

// Duplicate SOP
$result = fn_sops_duplicate($sop_id, $company_id, $user_id);
```

### Share Links

```php
// Create share link
$result = fn_shares_create($sop_id, $user_id, [
    'password' => 'secret123',
    'expires_at' => '2024-12-31',
    'max_views' => 100,
    'allow_comments' => true
]);

// Get share URL
$url = fn_shares_get_url($token);

// Verify access
$access = fn_shares_verify_access($token, $password);
```

### Exports

```php
// Export to HTML
$result = fn_exports_to_html($sop_id, $company_id);

// Export to Markdown
$result = fn_exports_to_markdown($sop_id, $company_id);

// Export to JSON
$result = fn_exports_to_json($sop_id, $company_id);

// Download file
$download = fn_exports_download($sop_id, 'html', $company_id);
header('Content-Type: ' . $download['content_type']);
header('Content-Disposition: attachment; filename="' . $download['filename'] . '"');
echo $download['content'];
```

### Debug Functions

```php
// Debug variable
fn_debug($variable, 'Variable Name');

// Debug and die
fn_dd($variable, 'Variable Name');

// Debug SQL query
fn_debug_query($query, $params, 'Query Label');

// Debug request
fn_debug_request();
```

## Migration from Old Structure

If you need to migrate existing data or maintain compatibility with the Chrome extension:

1. **Chrome Extension**: The extension can continue to use the existing API endpoints or be updated to use the new structure.

2. **Database**: No changes needed - the database schema remains the same.

3. **API Compatibility**: You can create API endpoints as controllers that return JSON instead of HTML views.

## Next Steps

1. **Implement Remaining Controllers**: Create controllers for all routes defined in the router
2. **Build View Templates**: Complete all view templates with proper styling
3. **Add CSS/JavaScript**: Move existing CSS from `/web-app/css/` to `/public/assets/css/`
4. **Test Functionality**: Test all features end-to-end
5. **Update Chrome Extension**: Update extension to work with new authentication system
6. **Add Email Functions**: Implement email sending via Postmark
7. **Production Setup**: Configure for production (disable debug, enable HTTPS, etc.)

## Troubleshooting

### Routes Not Working

- Check `.htaccess` exists in `/public/`
- Verify Apache `mod_rewrite` is enabled
- Check Apache configuration allows `.htaccess` overrides

### Session Not Persisting

- Check session directory is writable
- Verify session configuration in `php.ini`
- Check session cookie settings

### Database Connection Failed

- Verify credentials in `/config.php`
- Ensure MySQL is running
- Check database exists

### Permission Denied

- Check file permissions on upload directories
- Verify web server user has access

## Support

For questions or issues with the refactored structure, refer to:
- This documentation
- Inline code comments
- Function docblocks
- Original boilerplate documentation
