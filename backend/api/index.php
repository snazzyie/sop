<?php
// Main API Router

// Load configuration
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/JWT.php';
require_once '../classes/Response.php';

// Enable CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Parse the request
$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];

// Remove query string and base path
$path = parse_url($requestUri, PHP_URL_PATH);
$path = str_replace('/api', '', $path);
$path = trim($path, '/');

// Split path into segments
$segments = explode('/', $path);
$endpoint = $segments[0] ?? '';
$action = $segments[1] ?? '';
$param = $segments[2] ?? '';

// Get request body for POST/PUT/PATCH
$requestBody = null;
if (in_array($requestMethod, ['POST', 'PUT', 'PATCH'])) {
    $requestBody = json_decode(file_get_contents('php://input'), true);
}

// Route the request
try {
    switch ($endpoint) {
        case 'auth':
            require_once '../api/auth.php';
            handleAuthRequest($action, $requestMethod, $requestBody);
            break;

        case 'sessions':
            require_once '../api/sessions.php';
            handleSessionsRequest($action, $param, $requestMethod, $requestBody);
            break;

        case 'sops':
            require_once '../api/sops.php';
            handleSopsRequest($action, $param, $requestMethod, $requestBody);
            break;

        case 'steps':
            require_once '../api/steps.php';
            handleStepsRequest($action, $param, $requestMethod, $requestBody);
            break;

        case 'shares':
            require_once '../api/shares.php';
            handleSharesRequest($action, $param, $requestMethod, $requestBody);
            break;

        case 'exports':
            require_once '../api/exports.php';
            handleExportsRequest($action, $param, $requestMethod, $requestBody);
            break;

        case '':
            Response::success('SOP Recorder API v1.0', [
                'endpoints' => [
                    'auth' => ['login', 'register', 'logout'],
                    'sessions' => ['create', 'finalize', 'steps'],
                    'sops' => ['list', 'view', 'update', 'delete'],
                    'shares' => ['create', 'view'],
                    'exports' => ['pdf', 'html', 'markdown']
                ]
            ]);
            break;

        default:
            Response::notFound('Endpoint not found');
    }

} catch (Exception $e) {
    if (ENV === 'development') {
        Response::serverError($e->getMessage());
    } else {
        Response::serverError('An error occurred');
    }
}

/**
 * Middleware: Authenticate user from JWT token
 */
function authenticateUser() {
    $token = JWT::getBearerToken();

    if (!$token) {
        Response::unauthorized('No token provided');
    }

    try {
        $payload = JWT::decode($token);

        if (!isset($payload['user_id'])) {
            Response::unauthorized('Invalid token');
        }

        return $payload;

    } catch (Exception $e) {
        Response::unauthorized('Invalid or expired token');
    }
}

/**
 * Get current user from token
 */
function getCurrentUser() {
    $token = JWT::getBearerToken();

    if (!$token) {
        return null;
    }

    try {
        $payload = JWT::decode($token);
        return $payload['user_id'] ?? null;
    } catch (Exception $e) {
        return null;
    }
}
