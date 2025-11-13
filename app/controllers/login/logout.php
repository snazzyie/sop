<?php
// Logout Controller

// Logout user
fn_core_session_logout();

// Redirect to login
header("Location: /login?success=" . urlencode('You have been logged out'));
exit;
