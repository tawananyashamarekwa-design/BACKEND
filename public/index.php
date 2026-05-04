<?php

require_once __DIR__ . '/../config/app.php';

function setCorsHeaders() {
    if (!CORS_ENABLED) {
        return;
    }

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $isAllowedVercelOrigin = (bool) preg_match('/^https:\/\/[a-z0-9-]+\.vercel\.app$/i', $origin);

    if (in_array($origin, ALLOWED_ORIGINS, true) || $isAllowedVercelOrigin) {
        header("Access-Control-Allow-Origin: $origin");
        header('Access-Control-Allow-Credentials: true');
        header('Vary: Origin');
    }

    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Access-Control-Max-Age: 3600');
    header('Content-Type: application/json');
}

function sendJson($success, $message, $data = null, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
    ]);
    exit;
}

function getJsonInput() {
    $data = json_decode(file_get_contents('php://input'), true);
    return is_array($data) ? $data : [];
}

function base64UrlEncode($value) {
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function createJwt(array $payload) {
    $header = [
        'typ' => 'JWT',
        'alg' => 'HS256',
    ];

    $segments = [
        base64UrlEncode(json_encode($header)),
        base64UrlEncode(json_encode($payload)),
    ];

    $signature = hash_hmac('sha256', implode('.', $segments), JWT_SECRET, true);
    $segments[] = base64UrlEncode($signature);

    return implode('.', $segments);
}

function databaseBool($value, $default = false) {
    if ($value === null) {
        return $default;
    }

    if (is_bool($value)) {
        return $value;
    }

    return in_array(strtolower((string)$value), ['1', 'true', 't', 'yes', 'y'], true);
}

function normalizeUserForResponse(array $user, $isActive = null) {
    unset($user['password']);

    $user['id'] = (int)$user['id'];
    $user['firstName'] = $user['firstName'] ?? ($user['firstname'] ?? '');
    $user['lastName'] = $user['lastName'] ?? ($user['lastname'] ?? '');
    $user['isActive'] = $isActive ?? databaseBool($user['isActive'] ?? ($user['isactive'] ?? true), true);
    unset($user['firstname'], $user['lastname'], $user['isactive']);

    return $user;
}

function createAuthResponse(array $user) {
    $now = time();
    $token = createJwt([
        'sub' => (int)$user['id'],
        'email' => $user['email'],
        'role' => $user['role'],
        'iat' => $now,
        'exp' => $now + JWT_EXPIRY,
    ]);

    return [
        'token' => $token,
        'user' => normalizeUserForResponse($user),
    ];
}

function ensurePaynowOrderColumns(PDO $pdo) {
    if (DB_DRIVER === 'pgsql') {
        $statements = [
            'ALTER TABLE orders ADD COLUMN IF NOT EXISTS payment_method VARCHAR(50)',
            "ALTER TABLE orders ADD COLUMN IF NOT EXISTS payment_status VARCHAR(50) DEFAULT 'Pending'",
            'ALTER TABLE orders ADD COLUMN IF NOT EXISTS paynow_poll_url TEXT',
            'ALTER TABLE orders ADD COLUMN IF NOT EXISTS paynow_reference VARCHAR(100)',
        ];

        foreach ($statements as $sql) {
            $pdo->exec($sql);
        }
        return;
    }

    $requiredColumns = [
        'payment_method' => 'ALTER TABLE orders ADD COLUMN payment_method VARCHAR(50)',
        'payment_status' => "ALTER TABLE orders ADD COLUMN payment_status VARCHAR(50) DEFAULT 'Pending'",
        'paynow_poll_url' => 'ALTER TABLE orders ADD COLUMN paynow_poll_url TEXT',
        'paynow_reference' => 'ALTER TABLE orders ADD COLUMN paynow_reference VARCHAR(100)',
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
            $pdo->exec($sql);
        }
    }
}

function requirePaynowSdk() {
    if (!class_exists('\\Paynow\\Payments\\Paynow')) {
        sendJson(false, 'Paynow SDK is not installed.', null, 500);
    }
}

function createPaynowClient($returnUrl = PAYNOW_RETURN_URL, $resultUrl = PAYNOW_RESULT_URL) {
    if (!PAYNOW_INTEGRATION_ID || !PAYNOW_INTEGRATION_KEY) {
        sendJson(false, 'Paynow credentials are missing.', null, 500);
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
    return is_object($statusUpdate) && method_exists($statusUpdate, 'status')
        ? (string)$statusUpdate->status()
        : 'Pending';
}

function isPaynowPaid($statusUpdate) {
    return is_object($statusUpdate)
        && method_exists($statusUpdate, 'paid')
        && $statusUpdate->paid();
}

setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? 1 : 0);
header('Content-Type: application/json');

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

if ($uri === '/' && $method === 'GET') {
    sendJson(true, 'Backend is running successfully', ['api_base' => '/api/v1']);
}

if ($uri === '/api/v1' && $method === 'GET') {
    sendJson(true, 'API v1 is working');
}

require_once __DIR__ . '/../config/database.php';

if ($uri === '/api/v1/auth/login' && $method === 'POST') {
    try {
        global $pdo;

        $input = getJsonInput();
        $email = strtolower(trim($input['email'] ?? ''));
        $password = (string)($input['password'] ?? '');

        if ($email === '' || $password === '') {
            sendJson(false, 'Email and password are required.', null, HTTP_BAD_REQUEST);
        }

        $stmt = $pdo->prepare(
            'SELECT id, email, password, firstName, lastName, role, isActive
             FROM users
             WHERE LOWER(email) = ?
             LIMIT 1'
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password'])) {
            sendJson(false, 'Invalid email or password.', null, HTTP_UNAUTHORIZED);
        }

        $isActive = databaseBool($user['isActive'] ?? ($user['isactive'] ?? true), true);
        if (!$isActive) {
            sendJson(false, 'Your account is inactive.', null, HTTP_FORBIDDEN);
        }

        sendJson(true, 'Login successful.', createAuthResponse($user));
    } catch (Exception $e) {
        sendJson(false, 'Unable to login.', ['error' => $e->getMessage()], HTTP_INTERNAL_ERROR);
    }
}

if ($uri === '/api/v1/auth/register' && $method === 'POST') {
    try {
        global $pdo;

        $input = getJsonInput();
        $email = strtolower(trim($input['email'] ?? ''));
        $password = (string)($input['password'] ?? '');
        $fullName = trim($input['name'] ?? $input['fullName'] ?? '');
        $firstName = trim($input['firstName'] ?? $input['first_name'] ?? '');
        $lastName = trim($input['lastName'] ?? $input['last_name'] ?? '');

        if ($firstName === '' && $fullName !== '') {
            $nameParts = preg_split('/\s+/', $fullName, 2);
            $firstName = $nameParts[0] ?? '';
            $lastName = $lastName !== '' ? $lastName : ($nameParts[1] ?? '');
        }

        if ($email === '' || $password === '' || $firstName === '' || $lastName === '') {
            sendJson(false, 'Email, password, first name, and last name are required.', null, HTTP_BAD_REQUEST);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            sendJson(false, 'Please enter a valid email address.', null, HTTP_BAD_REQUEST);
        }

        if (strlen($password) < PASSWORD_MIN_LENGTH) {
            sendJson(false, 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.', null, HTTP_BAD_REQUEST);
        }

        $existing = $pdo->prepare('SELECT id FROM users WHERE LOWER(email) = ? LIMIT 1');
        $existing->execute([$email]);
        if ($existing->fetch()) {
            sendJson(false, 'An account with this email already exists.', null, HTTP_CONFLICT);
        }

        $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);

        if (DB_DRIVER === 'pgsql') {
            $stmt = $pdo->prepare(
                'INSERT INTO users (email, password, firstName, lastName, role, isActive)
                 VALUES (?, ?, ?, ?, ?, TRUE)
                 RETURNING id, email, password, firstName, lastName, role, isActive'
            );
            $stmt->execute([$email, $hashedPassword, $firstName, $lastName, USER_ROLE_CUSTOMER]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO users (email, password, firstName, lastName, role, isActive)
                 VALUES (?, ?, ?, ?, ?, 1)'
            );
            $stmt->execute([$email, $hashedPassword, $firstName, $lastName, USER_ROLE_CUSTOMER]);

            $lookup = $pdo->prepare(
                'SELECT id, email, password, firstName, lastName, role, isActive
                 FROM users
                 WHERE id = ?'
            );
            $lookup->execute([(int)$pdo->lastInsertId()]);
            $user = $lookup->fetch(PDO::FETCH_ASSOC);
        }

        sendJson(true, 'Registration successful.', createAuthResponse($user), HTTP_CREATED);
    } catch (Exception $e) {
        if ($e instanceof PDOException && in_array($e->getCode(), ['23000', '23505'], true)) {
            sendJson(false, 'An account with this email already exists.', null, HTTP_CONFLICT);
        }

        sendJson(false, 'Unable to register.', ['error' => $e->getMessage()], HTTP_INTERNAL_ERROR);
    }
}

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

        $redirectUrl = method_exists($response, 'redirectUrl')
            ? $response->redirectUrl()
            : $response->redirectLink();

        $update = $pdo->prepare(
            'UPDATE orders
             SET payment_method = ?, payment_status = ?, paynow_poll_url = ?, paynow_reference = ?
             WHERE id = ?'
        );
        $update->execute(['Paynow', 'Pending', $response->pollUrl(), $reference, $orderId]);

        sendJson(true, 'Paynow payment created.', [
            'redirect_url' => $redirectUrl,
            'order_id' => $orderId,
            'payment_status' => 'Pending',
        ]);
    } catch (Exception $e) {
        sendJson(false, 'Unable to create Paynow payment.', ['error' => $e->getMessage()], 500);
    }
}

if ($uri === '/api/v1/paynow-result' && in_array($method, ['GET', 'POST'], true)) {
    try {
        global $pdo;

        ensurePaynowOrderColumns($pdo);
        requirePaynowSdk();

        $paynow = createPaynowClient();
        $callbackData = array_merge($_GET, $_POST);
        $pollUrl = $callbackData['pollurl']
            ?? $callbackData['pollUrl']
            ?? $callbackData['paynow_poll_url']
            ?? null;
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
            $update = $pdo->prepare('UPDATE orders SET payment_status = ? WHERE paynow_poll_url = ?');
            $update->execute([$newStatus, $pollUrl]);
        }
    } catch (Exception $e) {
        error_log('Paynow result error: ' . $e->getMessage());
    }

    header('Content-Type: text/plain; charset=utf-8');
    echo 'OK';
    exit;
}

if (preg_match('#^/api/v1/orders/(\d+)/payment-status$#', $uri, $matches) && $method === 'GET') {
    try {
        global $pdo;

        ensurePaynowOrderColumns($pdo);
        $stmt = $pdo->prepare(
            'SELECT id, orderNumber, totalAmount, payment_method, payment_status
             FROM orders
             WHERE id = ?'
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

if ($uri === '/api/v1/products' && $method === 'GET') {
    try {
        global $pdo;

        $stmt = $pdo->query('SELECT * FROM products');
        sendJson(true, 'Products retrieved successfully', $stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Exception $e) {
        sendJson(false, 'Products route works but DB/table may not exist yet', ['error' => $e->getMessage()]);
    }
}

if ($uri === '/api/v1/categories' && $method === 'GET') {
    try {
        global $pdo;

        $stmt = $pdo->query('SELECT * FROM categories');
        sendJson(true, 'Categories retrieved successfully', $stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Exception $e) {
        sendJson(false, 'Categories route works but DB/table may not exist yet', ['error' => $e->getMessage()]);
    }
}

sendJson(false, 'Route not found', ['path' => $uri, 'method' => $method], 404);
