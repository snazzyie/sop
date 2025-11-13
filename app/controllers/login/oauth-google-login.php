<?php
// Google OAuth Login Init Controller

// Get Google OAuth URL
$auth_url = fn_oauth_google_get_auth_url();

// Redirect to Google
header("Location: $auth_url");
exit;
