<?php

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../Core/router.php';

require_once __DIR__ . '/../utils/Request.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../utils/Auth.php';

error_reporting(E_ALL);

if (defined('APP_DEBUG') && APP_DEBUG) {
    ini_set('display_errors', 1);
} else {
    ini_set('display_errors', 0);
}

header('Content-Type: application/json');

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

/*
|--------------------------------------------------------------------------
| BASIC TEST ROUTES
|--------------------------------------------------------------------------
*/

if ($uri === '/' && $method === 'GET') {
    echo json_encode([
        'success' => true,
        'message' => 'Backend is running successfully',
        'api_base' => '/api/v1'
    ]);
    exit;
}

if ($uri === '/api/v1' && $method === 'GET') {
    echo json_encode([
        'success' => true,
        'message' => 'API v1 is working'
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| PRODUCTS ROUTE
|--------------------------------------------------------------------------
*/

if ($uri === '/api/v1/products' && $method === 'GET') {
    try {
        global $pdo;

        $stmt = $pdo->query("SELECT * FROM products");
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'message' => 'Products retrieved successfully',
            'data' => $products
        ]);
        exit;

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Products route is working, but database table may not exist yet',
            'error' => $e->getMessage()
        ]);
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| CATEGORIES ROUTE
|--------------------------------------------------------------------------
*/

if ($uri === '/api/v1/categories' && $method === 'GET') {
    try {
        global $pdo;

        $stmt = $pdo->query("SELECT * FROM categories");
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'message' => 'Categories retrieved successfully',
            'data' => $categories
        ]);
        exit;

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Categories route is working, but database table may not exist yet',
            'error' => $e->getMessage()
        ]);
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| FALLBACK ROUTE
|--------------------------------------------------------------------------
*/

http_response_code(404);

echo json_encode([
    'success' => false,
    'message' => 'Route not found',
    'path' => $uri,
    'method' => $method
]);

exit;