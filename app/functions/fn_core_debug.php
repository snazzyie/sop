<?php
// Core Debug Functions

/**
 * Pretty print variable with formatting
 */
function fn_debug($var, $label = null, $die = false) {
    // Only show debug in development mode
    $config = require BASE_PATH . 'config.php';
    if (!($config['debug'] ?? false)) {
        return;
    }

    echo '<div style="background: #f8f9fa; border: 2px solid #dee2e6; border-radius: 4px; padding: 15px; margin: 10px 0; font-family: monospace; font-size: 13px;">';

    if ($label) {
        echo '<strong style="color: #495057; display: block; margin-bottom: 8px;">' . htmlspecialchars($label) . '</strong>';
    }

    echo '<pre style="margin: 0; white-space: pre-wrap; word-wrap: break-word;">';
    print_r($var);
    echo '</pre>';

    echo '</div>';

    if ($die) {
        die();
    }
}

/**
 * Dump variable and die
 */
function fn_dd($var, $label = null) {
    fn_debug($var, $label, true);
}

/**
 * Log to error log
 */
function fn_log($message, $context = []) {
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? json_encode($context) : '';

    $logMessage = "[$timestamp] $message";
    if ($contextStr) {
        $logMessage .= " | Context: $contextStr";
    }

    error_log($logMessage);
}

/**
 * Debug SQL query
 */
function fn_debug_query($query, $params = [], $label = 'SQL Query') {
    $config = require BASE_PATH . 'config.php';
    if (!($config['debug'] ?? false)) {
        return;
    }

    echo '<div style="background: #fff3cd; border: 2px solid #ffc107; border-radius: 4px; padding: 15px; margin: 10px 0; font-family: monospace; font-size: 13px;">';
    echo '<strong style="color: #856404; display: block; margin-bottom: 8px;">' . htmlspecialchars($label) . '</strong>';
    echo '<pre style="margin: 0; white-space: pre-wrap; word-wrap: break-word; color: #212529;">';
    echo htmlspecialchars($query);
    echo '</pre>';

    if (!empty($params)) {
        echo '<div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #ffc107;">';
        echo '<strong style="color: #856404;">Parameters:</strong>';
        echo '<pre style="margin: 5px 0 0 0;">';
        print_r($params);
        echo '</pre>';
        echo '</div>';
    }

    echo '</div>';
}

/**
 * Display session data
 */
function fn_debug_session($die = false) {
    fn_debug($_SESSION, 'Session Data', $die);
}

/**
 * Display POST data
 */
function fn_debug_post($die = false) {
    fn_debug($_POST, 'POST Data', $die);
}

/**
 * Display GET data
 */
function fn_debug_get($die = false) {
    fn_debug($_GET, 'GET Data', $die);
}

/**
 * Display all request data
 */
function fn_debug_request($die = false) {
    echo '<div style="background: #e7f3ff; border: 2px solid #2196f3; border-radius: 4px; padding: 15px; margin: 10px 0; font-family: monospace; font-size: 13px;">';
    echo '<strong style="color: #0d47a1; display: block; margin-bottom: 15px; font-size: 16px;">Request Debug</strong>';

    echo '<div style="margin-bottom: 15px;">';
    echo '<strong style="color: #1565c0;">Method:</strong> ' . htmlspecialchars($_SERVER['REQUEST_METHOD']);
    echo ' | <strong style="color: #1565c0;">URI:</strong> ' . htmlspecialchars($_SERVER['REQUEST_URI']);
    echo '</div>';

    if (!empty($_GET)) {
        echo '<div style="margin-bottom: 10px;">';
        echo '<strong style="color: #1565c0;">GET:</strong>';
        echo '<pre style="margin: 5px 0 0 0;">';
        print_r($_GET);
        echo '</pre>';
        echo '</div>';
    }

    if (!empty($_POST)) {
        echo '<div style="margin-bottom: 10px;">';
        echo '<strong style="color: #1565c0;">POST:</strong>';
        echo '<pre style="margin: 5px 0 0 0;">';
        print_r($_POST);
        echo '</pre>';
        echo '</div>';
    }

    if (!empty($_SESSION)) {
        echo '<div style="margin-bottom: 10px;">';
        echo '<strong style="color: #1565c0;">SESSION:</strong>';
        echo '<pre style="margin: 5px 0 0 0;">';
        print_r($_SESSION);
        echo '</pre>';
        echo '</div>';
    }

    echo '</div>';

    if ($die) {
        die();
    }
}

/**
 * Measure execution time
 */
function fn_debug_timer_start($name = 'default') {
    global $__debug_timers;
    if (!isset($__debug_timers)) {
        $__debug_timers = [];
    }
    $__debug_timers[$name] = microtime(true);
}

/**
 * End timer and display result
 */
function fn_debug_timer_end($name = 'default', $label = null) {
    global $__debug_timers;

    if (!isset($__debug_timers[$name])) {
        fn_log("Timer '$name' was not started");
        return;
    }

    $elapsed = microtime(true) - $__debug_timers[$name];
    $elapsed_ms = round($elapsed * 1000, 2);

    $display_label = $label ?: "Timer: $name";

    $config = require BASE_PATH . 'config.php';
    if ($config['debug'] ?? false) {
        echo '<div style="background: #d1ecf1; border: 2px solid #17a2b8; border-radius: 4px; padding: 10px; margin: 10px 0; font-family: monospace; font-size: 13px;">';
        echo '<strong style="color: #0c5460;">' . htmlspecialchars($display_label) . ':</strong> ';
        echo '<span style="color: #0c5460;">' . $elapsed_ms . 'ms</span>';
        echo '</div>';
    }

    unset($__debug_timers[$name]);
}

/**
 * Display error message
 */
function fn_debug_error($message, $details = null) {
    echo '<div style="background: #f8d7da; border: 2px solid #dc3545; border-radius: 4px; padding: 15px; margin: 10px 0; font-family: sans-serif;">';
    echo '<strong style="color: #721c24; font-size: 16px;">Error:</strong> ';
    echo '<span style="color: #721c24;">' . htmlspecialchars($message) . '</span>';

    if ($details) {
        echo '<pre style="margin: 10px 0 0 0; padding: 10px; background: white; border-radius: 4px; font-size: 12px; overflow: auto;">';
        print_r($details);
        echo '</pre>';
    }

    echo '</div>';
}

/**
 * Display success message
 */
function fn_debug_success($message) {
    echo '<div style="background: #d4edda; border: 2px solid #28a745; border-radius: 4px; padding: 15px; margin: 10px 0; font-family: sans-serif;">';
    echo '<strong style="color: #155724; font-size: 16px;">Success:</strong> ';
    echo '<span style="color: #155724;">' . htmlspecialchars($message) . '</span>';
    echo '</div>';
}

/**
 * Check if debug mode is enabled
 */
function fn_is_debug_mode() {
    $config = require BASE_PATH . 'config.php';
    return $config['debug'] ?? false;
}
