<?php

header('Access-Control-Allow-Origin: https://harareelectronichub.vercel.app');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// CORS HEADERS - MUST BE FIRST
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

$allowedOrigins = [
    'http://localhost:5173',
    'http://127.0.0.1:5173',
    'http://localhost:3000',
    'http://127.0.0.1:3000',
    'http://localhost:8080',
    'http://127.0.0.1:8080',
    'https://harareelectronichub.vercel.app'
];

$frontendUrl = getenv('FRONTEND_URL');
if ($frontendUrl) {
    $allowedOrigins[] = rtrim($frontendUrl, '/');
}

$isAllowedVercelOrigin = (bool) preg_match('/^https:\/\/[a-z0-9-]+\.vercel\.app$/i', $origin);

if (in_array($origin, $allowedOrigins, true) || $isAllowedVercelOrigin) {
    header("Access-Control-Allow-Origin: $origin");
    header('Vary: Origin');
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

$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

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

function sendJson($success, $message, $data = null, $statusCode = 200) {
    http_response_code($statusCode);

    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

function getJsonInput() {
    $body = file_get_contents('php://input');
    $data = json_decode($body, true);

    return is_array($data) ? $data : [];
}

function ensurePaynowOrderColumns(PDO $pdo) {
    $requiredColumns = [
        'payment_method' => "ALTER TABLE orders ADD COLUMN payment_method VARCHAR(50)",
        'payment_status' => "ALTER TABLE orders ADD COLUMN payment_status VARCHAR(50) DEFAULT 'Pending'",
        'paynow_poll_url' => "ALTER TABLE orders ADD COLUMN paynow_poll_url TEXT",
        'paynow_reference' => "ALTER TABLE orders ADD COLUMN paynow_reference VARCHAR(100)"
    ];

    $columnCheck = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME = 'orders'
         AND COLUMN_NAME = ?"
    );

    foreach ($requiredColumns as $column => $sql) {
        $columnCheck->execute([$column]);
        if ((int)$columnCheck->fetchColumn() === 0) {
            $pdo->prepare($sql)->execute();
        }
    }
}

function requirePaynowSdk() {
    if (!class_exists('\\Paynow\\Payments\\Paynow')) {
        sendJson(
            false,
            'Paynow SDK is not installed. Run composer require paynow/php-sdk in the backend folder.',
            null,
            500
        );
    }
}

function createPaynowClient($returnUrl = PAYNOW_RETURN_URL, $resultUrl = PAYNOW_RESULT_URL) {
    if (!PAYNOW_INTEGRATION_ID || !PAYNOW_INTEGRATION_KEY) {
        sendJson(
            false,
            'Paynow credentials are missing. Set PAYNOW_INTEGRATION_ID and PAYNOW_INTEGRATION_KEY as backend environment variables.',
            null,
            500
        );
    }

    return new \Paynow\Payments\Paynow(
        PAYNOW_INTEGRATION_ID,
        PAYNOW_INTEGRATION_KEY,
        $returnUrl,
        $resultUrl
    );
}

function getOrderForPayment(PDO $pdo, $orderId) {
    $stmt = $pdo->prepare(
        "SELECT
            o.id,
            o.orderNumber,
            o.totalAmount,
            u.email AS customer_email,
            COALESCE(SUM(oi.subtotal), o.totalAmount) AS calculated_total
         FROM orders o
         INNER JOIN users u ON u.id = o.userId
         LEFT JOIN order_items oi ON oi.orderId = o.id
         WHERE o.id = ?
         GROUP BY o.id, o.orderNumber, o.totalAmount, u.email"
    );
    $stmt->execute([$orderId]);

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getPaynowStatusText($statusUpdate) {
    if (is_object($statusUpdate) && method_exists($statusUpdate, 'status')) {
        return (string)$statusUpdate->status();
    }

    return 'Pending';
}

function isPaynowPaid($statusUpdate) {
    return is_object($statusUpdate)
        && method_exists($statusUpdate, 'paid')
        && $statusUpdate->paid();
}

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
| PAYNOW PAYMENT CREATE ROUTE
|--------------------------------------------------------------------------
*/
if (($uri === '/api/v1/create-paynow-payment' || $uri === '/api/v1/payments/paynow/create') && $method === 'POST') {
    try {
        global $pdo;

        ensurePaynowOrderColumns($pdo);
        requirePaynowSdk();

        $input = getJsonInput();
        $orderId = isset($input['order_id']) ? (int)$input['order_id'] : (int)($input['orderId'] ?? 0);

        if ($orderId <= 0) {
            sendJson(false, 'order_id is required.', null, 400);
        }

        $order = getOrderForPayment($pdo, $orderId);
        if (!$order) {
            sendJson(false, 'Order not found.', null, 404);
        }

        $orderTotal = round((float)$order['calculated_total'], 2);
        if ($orderTotal <= 0) {
            sendJson(false, 'Order total must be greater than zero.', null, 400);
        }

        $separator = strpos(PAYNOW_RETURN_URL, '?') === false ? '?' : '&';
        $returnUrl = PAYNOW_RETURN_URL . $separator . 'order_id=' . urlencode((string)$orderId);

        $paynow = createPaynowClient($returnUrl, PAYNOW_RESULT_URL);
        $reference = 'Order #' . $order['id'];
        $payment = $paynow->createPayment($reference, $order['customer_email']);
        $payment->add($reference, $orderTotal);

        $response = $paynow->send($payment);

        if (!$response->success()) {
            sendJson(false, 'Paynow could not create the payment. Please try again.', null, 502);
        }

        $pollUrl = $response->pollUrl();
        $redirectUrl = method_exists($response, 'redirectUrl')
            ? $response->redirectUrl()
            : $response->redirectLink();

        $update = $pdo->prepare(
            "UPDATE orders
             SET payment_method = ?, payment_status = ?, paynow_poll_url = ?, paynow_reference = ?
             WHERE id = ?"
        );
        $update->execute(['Paynow', 'Pending', $pollUrl, $reference, $orderId]);

        sendJson(true, 'Paynow payment created.', [
            'redirect_url' => $redirectUrl,
            'order_id' => $orderId,
            'payment_status' => 'Pending'
        ]);
    } catch (Exception $e) {
        sendJson(false, 'Unable to create Paynow payment.', ['error' => $e->getMessage()], 500);
    }
}

/*
|--------------------------------------------------------------------------
| PAYNOW RESULT/CALLBACK ROUTE
|--------------------------------------------------------------------------
*/
if ($uri === '/api/v1/paynow-result' && in_array($method, ['GET', 'POST'])) {
    try {
        global $pdo;

        ensurePaynowOrderColumns($pdo);
        requirePaynowSdk();

        $paynow = createPaynowClient();
        $callbackData = array_merge($_GET, $_POST);
        $pollUrl = $callbackData['pollurl'] ?? $callbackData['pollUrl'] ?? $callbackData['paynow_poll_url'] ?? null;
        $statusUpdate = null;

        if (method_exists($paynow, 'processStatusUpdate')) {
            try {
                $statusUpdate = $paynow->processStatusUpdate();
                if (!$pollUrl && is_object($statusUpdate) && method_exists($statusUpdate, 'pollUrl')) {
                    $pollUrl = $statusUpdate->pollUrl();
                }
            } catch (Exception $e) {
                $statusUpdate = null;
            }
        }

        if (!$statusUpdate && $pollUrl) {
            $statusUpdate = $paynow->pollTransaction($pollUrl);
        }

        if ($pollUrl && $statusUpdate) {
            $newStatus = isPaynowPaid($statusUpdate) ? 'Paid' : getPaynowStatusText($statusUpdate);

            $update = $pdo->prepare(
                "UPDATE orders
                 SET payment_status = ?
                 WHERE paynow_poll_url = ?"
            );
            $update->execute([$newStatus, $pollUrl]);
        }

        header('Content-Type: text/plain; charset=utf-8');
        echo 'OK';
        exit;
    } catch (Exception $e) {
        error_log('Paynow result error: ' . $e->getMessage());
        header('Content-Type: text/plain; charset=utf-8');
        echo 'OK';
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| ORDER PAYMENT STATUS ROUTE
|--------------------------------------------------------------------------
*/
if (preg_match('#^/api/v1/orders/(\d+)/payment-status$#', $uri, $matches) && $method === 'GET') {
    try {
        global $pdo;

        ensurePaynowOrderColumns($pdo);

        $stmt = $pdo->prepare(
            "SELECT id, orderNumber, totalAmount, payment_method, payment_status
             FROM orders
             WHERE id = ?"
        );
        $stmt->execute([(int)$matches[1]]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            sendJson(false, 'Order not found.', null, 404);
        }

        sendJson(true, 'Order payment status retrieved.', $order);
    } catch (Exception $e) {
        sendJson(false, 'Unable to load payment status.', ['error' => $e->getMessage()], 500);
    }
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
