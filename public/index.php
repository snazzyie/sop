<?php
// SOP Recorder - Front Controller

// Define base path constant
const BASE_PATH = __DIR__."/../";

// Load configuration
$config = require BASE_PATH . 'config.php';

// Start session
session_start();

// Load core functions
require_once BASE_PATH . 'app/functions/fn_core_database.php';
require_once BASE_PATH . 'app/functions/fn_core_session.php';
require_once BASE_PATH . 'app/functions/fn_core_router.php';
require_once BASE_PATH . 'app/functions/fn_core_settings.php';
require_once BASE_PATH . 'app/functions/fn_core_menu.php';
require_once BASE_PATH . 'app/functions/fn_core_debug.php';

// Load feature functions
require_once BASE_PATH . 'app/functions/fn_sops.php';
require_once BASE_PATH . 'app/functions/fn_sessions.php';
require_once BASE_PATH . 'app/functions/fn_subscriptions.php';
require_once BASE_PATH . 'app/functions/fn_stripe.php';
require_once BASE_PATH . 'app/functions/fn_oauth.php';
require_once BASE_PATH . 'app/functions/fn_api_keys.php';
require_once BASE_PATH . 'app/functions/fn_shares.php';
require_once BASE_PATH . 'app/functions/fn_exports.php';

// Initialize session
fn_core_session_initialise_session();

// Get the requested URI
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Route the request
fn_core_router($uri);
