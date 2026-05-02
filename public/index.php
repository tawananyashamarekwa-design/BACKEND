<?php



// CORS HEADERS - MUST BE FIRST
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

$allowedOrigins = [
    'http://localhost:5173',
    'https://harareelectronichub.vercel.app'
];

if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $origin");
}

header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

// Error handling
error_reporting(E_ALL);

if (defined('APP_DEBUG') && APP_DEBUG) {
    ini_set('display_errors', 1);
} else {
    ini_set('display_errors', 0);
}

// JSON response header
header('Content-Type: application/json');

// Get request info
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

/*
|--------------------------------------------------------------------------
| ROOT ROUTE
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

/*
|--------------------------------------------------------------------------
| API BASE CHECK
|--------------------------------------------------------------------------
*/
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
            'message' => 'Products route works but DB/table may not exist yet',
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
            'message' => 'Categories route works but DB/table may not exist yet',
            'error' => $e->getMessage()
        ]);
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| FALLBACK (NOT FOUND)
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