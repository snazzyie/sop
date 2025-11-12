<?php
// Application Configuration

// Environment
define('ENV', 'development'); // 'development' or 'production'

// Base paths
define('BASE_PATH', dirname(__DIR__));
define('UPLOAD_PATH', BASE_PATH . '/uploads');
define('SESSION_UPLOAD_PATH', UPLOAD_PATH . '/sessions');

// URLs
define('BASE_URL', 'http://localhost:8000');
define('API_URL', BASE_URL . '/api');

// JWT Settings
define('JWT_SECRET', 'your-secret-key-change-this-in-production');
define('JWT_ALGORITHM', 'HS256');
define('JWT_EXPIRATION', 86400); // 24 hours in seconds

// File upload settings
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_IMAGE_TYPES', ['image/png', 'image/jpeg', 'image/jpg']);

// Recording settings
define('MAX_STEPS_PER_SESSION', 1000);
define('MAX_SESSION_DURATION', 3600); // 1 hour in seconds

// CORS settings
define('ALLOWED_ORIGINS', [
    'chrome-extension://*',
    'http://localhost',
    'http://localhost:8000'
]);

// Error reporting
if (ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Timezone
date_default_timezone_set('UTC');

// Create necessary directories
if (!file_exists(UPLOAD_PATH)) {
    mkdir(UPLOAD_PATH, 0777, true);
}
if (!file_exists(SESSION_UPLOAD_PATH)) {
    mkdir(SESSION_UPLOAD_PATH, 0777, true);
}
