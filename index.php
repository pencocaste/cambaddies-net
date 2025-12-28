<?php
/**
 * Main entry point for CamBaddies
 * Server-Side Rendering with PHP
 */

// Error reporting for development (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Include required files
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/templates.php';

// Get the current path
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Normalize path
$path = rtrim($path, '/');
if ($path === '') {
    $path = '/';
}

// Valid routes
$validRoutes = ['/', '/girls', '/men', '/couples', '/trans'];

// Check if this is an API request
if ($path === '/api/rooms') {
    handleApiRequest();
    exit;
}

// Check if route is valid
if (!in_array($path, $validRoutes)) {
    // 404 - redirect to home or show 404 page
    header('HTTP/1.1 301 Moved Permanently');
    header('Location: /');
    exit;
}

// Get page configuration
$pageConfig = getPageConfig($path);

// Build API parameters based on route
$apiParams = [
    'limit' => DEFAULT_LIMIT,
    'offset' => 0,
];

if ($pageConfig['gender']) {
    $apiParams['gender'] = $pageConfig['gender'];
}

// Fetch initial rooms from API (with cache)
$apiResponse = fetchRoomsWithCache($apiParams);

$rooms = [];
$totalRooms = 0;

if ($apiResponse !== null) {
    $rooms = $apiResponse['results'] ?? [];
    $totalRooms = $apiResponse['count'] ?? 0;
}

// Occasionally clean up old cache files (1% chance per request)
if (mt_rand(1, 100) === 1) {
    clearExpiredCache();
}

// Render and output the page
echo renderPage($path, $rooms, $totalRooms);

/**
 * Handle API requests for dynamic loading
 */
function handleApiRequest() {
    header('Content-Type: application/json');

    // Get parameters from query string
    $params = [
        'limit' => isset($_GET['limit']) ? intval($_GET['limit']) : DEFAULT_LIMIT,
        'offset' => isset($_GET['offset']) ? intval($_GET['offset']) : 0,
    ];

    // Gender filter
    if (!empty($_GET['gender'])) {
        $params['gender'] = $_GET['gender'];
    }

    // Region filter
    if (!empty($_GET['region'])) {
        $params['region'] = $_GET['region'];
    }

    // Tags filter (can be multiple)
    if (!empty($_GET['tag'])) {
        $tags = is_array($_GET['tag']) ? $_GET['tag'] : [$_GET['tag']];
        $params['tags'] = $tags;
    }

    // HD filter
    if (isset($_GET['hd'])) {
        $params['hd'] = $_GET['hd'] === 'true' || $_GET['hd'] === '1';
    }

    // Fetch rooms (with cache)
    $response = fetchRoomsWithCache($params);

    if ($response === null) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch rooms']);
        return;
    }

    echo json_encode($response);
}
