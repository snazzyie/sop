<?php
// SOP Recorder Configuration

return [
    'app_name' => 'SOP Recorder',
    'app_domain' => 'soprecorder',
    'app_url' => 'http://localhost:8000',

    'database' => [
        'host' => 'localhost',
        'port' => 3306,
        'dbname' => 'sop_recorder',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4'
    ],

    'debug' => true, // Master debug flag
    'debug_display' => true,
    'debug_session' => false,
    'debug_server' => false,

    'postmarkapp' => [
        'server_api' => '',
        'email_domain' => 'soprecorder.com',
        'from_email_domain' => 'noreply@soprecorder.com'
    ],

    'stripe' => [
        'secret_key' => 'pk_test_YOUR_KEY_HERE',
        'private_key' => 'sk_test_YOUR_KEY_HERE',
        'webhook_secret' => 'whsec_YOUR_SECRET_HERE'
    ],

    'google_oauth' => [
        'client_id' => 'YOUR_CLIENT_ID.apps.googleusercontent.com',
        'client_secret' => 'YOUR_CLIENT_SECRET',
        'redirect_uri' => 'http://localhost:8000/oauth/google/callback'
    ],

    'session' => [
        'lifetime' => 3600, // 1 hour
        'cookie_name' => 'SOP_SESSION'
    ],

    'upload' => [
        'max_size' => 10485760, // 10MB
        'allowed_types' => ['image/png', 'image/jpeg', 'image/jpg'],
        'path' => 'uploads/sessions'
    ]
];
