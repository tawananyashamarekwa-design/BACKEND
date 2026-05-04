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

function base64UrlDecode($value) {
    $remainder = strlen($value) % 4;
    if ($remainder) {
        $value .= str_repeat('=', 4 - $remainder);
    }

    return base64_decode(strtr($value, '-_', '+/'));
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

function verifyJwt($token) {
    $parts = explode('.', (string)$token);
    if (count($parts) !== 3) {
        return null;
    }

    [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;
    $expectedSignature = base64UrlEncode(
        hash_hmac('sha256', $encodedHeader . '.' . $encodedPayload, JWT_SECRET, true)
    );

    if (!hash_equals($expectedSignature, $encodedSignature)) {
        return null;
    }

    $payload = json_decode(base64UrlDecode($encodedPayload), true);
    if (!is_array($payload)) {
        return null;
    }

    if (isset($payload['exp']) && (int)$payload['exp'] < time()) {
        return null;
    }

    return $payload;
}

function getBearerToken() {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';

    if ($header === '' && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        $header = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    }

    if (!preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
        return null;
    }

    return trim($matches[1]);
}

function requireAuthPayload() {
    $payload = verifyJwt(getBearerToken());
    if (!$payload || empty($payload['sub'])) {
        sendJson(false, 'Authentication required.', null, HTTP_UNAUTHORIZED);
    }

    return $payload;
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

    $user = normalizeUserForResponse($user);

    return array_merge($user, [
        'token' => $token,
        'user' => normalizeUserForResponse($user),
    ]);
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

function paginateResponse(array $items, $total, $page, $perPage, $message) {
    $lastPage = max(1, (int)ceil($total / $perPage));
    sendJson(true, $message, [
        'items' => $items,
        'pagination' => [
            'total' => (int)$total,
            'page' => (int)$page,
            'per_page' => (int)$perPage,
            'last_page' => $lastPage,
            'has_more' => $page < $lastPage,
        ],
    ]);
}

function requestPage() {
    return max(1, (int)($_GET['page'] ?? 1));
}

function requestPerPage($default = ITEMS_PER_PAGE) {
    $perPage = (int)($_GET['perPage'] ?? $_GET['per_page'] ?? $default);
    return max(1, min(100, $perPage));
}

function normalizeProduct(array $product) {
    $product['id'] = (int)$product['id'];
    $product['price'] = (float)$product['price'];
    $product['stockQuantity'] = (int)($product['stockQuantity'] ?? ($product['stockquantity'] ?? 0));
    $product['categoryId'] = isset($product['categoryId'])
        ? (int)$product['categoryId']
        : (isset($product['categoryid']) ? (int)$product['categoryid'] : null);
    $product['categoryName'] = $product['categoryName'] ?? ($product['categoryname'] ?? null);
    unset($product['stockquantity'], $product['categoryid'], $product['categoryname']);

    return $product;
}

function normalizeCategory(array $category) {
    $category['id'] = (int)$category['id'];
    $category['productCount'] = (int)($category['productCount'] ?? ($category['productcount'] ?? 0));
    unset($category['productcount']);

    return $category;
}

function normalizeOrder(array $order) {
    $order['id'] = (int)$order['id'];
    $order['userId'] = isset($order['userId'])
        ? (int)$order['userId']
        : (isset($order['userid']) ? (int)$order['userid'] : null);
    $order['orderNumber'] = $order['orderNumber'] ?? ($order['ordernumber'] ?? '');
    $order['totalAmount'] = (float)($order['totalAmount'] ?? ($order['totalamount'] ?? 0));
    $order['firstName'] = $order['firstName'] ?? ($order['firstname'] ?? '');
    $order['lastName'] = $order['lastName'] ?? ($order['lastname'] ?? '');
    unset($order['userid'], $order['ordernumber'], $order['totalamount'], $order['firstname'], $order['lastname']);

    return $order;
}

function normalizePayment(array $payment) {
    $payment['id'] = (int)$payment['id'];
    $payment['orderId'] = isset($payment['orderId'])
        ? (int)$payment['orderId']
        : (isset($payment['orderid']) ? (int)$payment['orderid'] : null);
    $payment['amount'] = (float)$payment['amount'];
    $payment['paymentMethod'] = $payment['paymentMethod'] ?? ($payment['paymentmethod'] ?? null);
    $payment['transactionId'] = $payment['transactionId'] ?? ($payment['transactionid'] ?? null);
    $payment['orderNumber'] = $payment['orderNumber'] ?? ($payment['ordernumber'] ?? null);
    unset($payment['orderid'], $payment['paymentmethod'], $payment['transactionid'], $payment['ordernumber']);

    return $payment;
}

function currentUser(PDO $pdo) {
    $payload = requireAuthPayload();
    $stmt = $pdo->prepare(
        'SELECT id, email, firstName, lastName, role, isActive
         FROM users
         WHERE id = ?
         LIMIT 1'
    );
    $stmt->execute([(int)$payload['sub']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        sendJson(false, 'User not found.', null, HTTP_NOT_FOUND);
    }

    $isActive = databaseBool($user['isActive'] ?? ($user['isactive'] ?? true), true);
    if (!$isActive) {
        sendJson(false, 'Your account is inactive.', null, HTTP_FORBIDDEN);
    }

    return normalizeUserForResponse($user, $isActive);
}

function requireAdminUser(PDO $pdo) {
    $user = currentUser($pdo);
    if (($user['role'] ?? '') !== USER_ROLE_ADMIN) {
        sendJson(false, 'Admin access required.', null, HTTP_FORBIDDEN);
    }

    return $user;
}

function fetchProduct(PDO $pdo, $productId) {
    $stmt = $pdo->prepare(
        'SELECT p.*, c.name AS categoryName
         FROM products p
         LEFT JOIN categories c ON c.id = p.categoryId
         WHERE p.id = ?
         LIMIT 1'
    );
    $stmt->execute([(int)$productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    return $product ? normalizeProduct($product) : null;
}

function fetchOrder(PDO $pdo, $orderId) {
    $stmt = $pdo->prepare(
        'SELECT o.*, u.email, u.firstName, u.lastName
         FROM orders o
         INNER JOIN users u ON u.id = o.userId
         WHERE o.id = ?
         LIMIT 1'
    );
    $stmt->execute([(int)$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        return null;
    }

    $items = $pdo->prepare(
        'SELECT oi.*, p.name AS productName
         FROM order_items oi
         INNER JOIN products p ON p.id = oi.productId
         WHERE oi.orderId = ?
         ORDER BY oi.id ASC'
    );
    $items->execute([(int)$orderId]);
    $order = normalizeOrder($order);
    $order['items'] = array_map(function ($item) {
        $item['id'] = (int)$item['id'];
        $item['orderId'] = (int)($item['orderId'] ?? ($item['orderid'] ?? 0));
        $item['productId'] = (int)($item['productId'] ?? ($item['productid'] ?? 0));
        $item['productName'] = $item['productName'] ?? ($item['productname'] ?? '');
        $item['quantity'] = (int)$item['quantity'];
        $item['unitPrice'] = (float)($item['unitPrice'] ?? ($item['unitprice'] ?? 0));
        $item['subtotal'] = (float)$item['subtotal'];
        unset($item['orderid'], $item['productid'], $item['productname'], $item['unitprice']);
        return $item;
    }, $items->fetchAll(PDO::FETCH_ASSOC));

    return $order;
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

        if ($fullName === '' && isset($input['username'])) {
            $fullName = trim($input['username']);
        }

        if ($firstName === '' && $fullName !== '') {
            $nameParts = preg_split('/\s+/', $fullName, 2);
            $firstName = $nameParts[0] ?? '';
            $lastName = $lastName !== '' ? $lastName : ($nameParts[1] ?? '');
        }

        if ($firstName === '' && $email !== '') {
            $firstName = ucfirst(strtok($email, '@') ?: 'Customer');
        }

        if ($lastName === '') {
            $lastName = 'Customer';
        }

        if ($email === '' || $password === '') {
            sendJson(false, 'Email and password are required.', null, HTTP_BAD_REQUEST);
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

if (($uri === '/api/v1/auth/profile' || $uri === '/api/v1/auth/me') && $method === 'GET') {
    try {
        global $pdo;

        $user = currentUser($pdo);
        sendJson(true, 'Profile retrieved successfully.', $user);
    } catch (Exception $e) {
        sendJson(false, 'Unable to load profile.', ['error' => $e->getMessage()], HTTP_INTERNAL_ERROR);
    }
}

if ($uri === '/api/v1/products/search' && $method === 'GET') {
    try {
        global $pdo;

        $query = trim($_GET['q'] ?? '');
        if (strlen($query) < 2) {
            sendJson(false, 'Search query must be at least 2 characters.', null, HTTP_BAD_REQUEST);
        }

        $page = requestPage();
        $perPage = requestPerPage();
        $offset = ($page - 1) * $perPage;
        $like = '%' . strtolower($query) . '%';
        $params = [$like, $like];
        $categorySql = '';

        if (!empty($_GET['category'])) {
            $categorySql = ' AND p.categoryId = ?';
            $params[] = (int)$_GET['category'];
        }

        $count = $pdo->prepare(
            "SELECT COUNT(*)
             FROM products p
             WHERE (LOWER(p.name) LIKE ? OR LOWER(COALESCE(p.description, '')) LIKE ?)$categorySql"
        );
        $count->execute($params);

        $stmt = $pdo->prepare(
            "SELECT p.*, c.name AS categoryName
             FROM products p
             LEFT JOIN categories c ON c.id = p.categoryId
             WHERE (LOWER(p.name) LIKE ? OR LOWER(COALESCE(p.description, '')) LIKE ?)$categorySql
             ORDER BY p.id DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->execute(array_merge($params, [$perPage, $offset]));
        $products = array_map('normalizeProduct', $stmt->fetchAll(PDO::FETCH_ASSOC));

        if (!isset($_GET['page']) && !isset($_GET['perPage']) && !isset($_GET['per_page']) && empty($_GET['category'])) {
            sendJson(true, 'Products retrieved successfully', $products);
        }

        paginateResponse(
            $products,
            (int)$count->fetchColumn(),
            $page,
            $perPage,
            'Products retrieved successfully'
        );
    } catch (Exception $e) {
        sendJson(false, 'Unable to search products.', ['error' => $e->getMessage()], HTTP_INTERNAL_ERROR);
    }
}

if ($uri === '/api/v1/products' && $method === 'GET') {
    try {
        global $pdo;

        $page = requestPage();
        $perPage = requestPerPage();
        $offset = ($page - 1) * $perPage;
        $params = [];
        $where = '';

        if (!empty($_GET['category'])) {
            $where = 'WHERE p.categoryId = ?';
            $params[] = (int)$_GET['category'];
        }

        $count = $pdo->prepare("SELECT COUNT(*) FROM products p $where");
        $count->execute($params);

        $stmt = $pdo->prepare(
            "SELECT p.*, c.name AS categoryName
             FROM products p
             LEFT JOIN categories c ON c.id = p.categoryId
             $where
             ORDER BY p.id DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->execute(array_merge($params, [$perPage, $offset]));
        $products = array_map('normalizeProduct', $stmt->fetchAll(PDO::FETCH_ASSOC));

        if (empty($_GET)) {
            sendJson(true, 'Products retrieved successfully', $products);
        }

        paginateResponse(
            $products,
            (int)$count->fetchColumn(),
            $page,
            $perPage,
            'Products retrieved successfully'
        );
    } catch (Exception $e) {
        sendJson(false, 'Products route works but DB/table may not exist yet', ['error' => $e->getMessage()]);
    }
}

if (preg_match('#^/api/v1/products/(\d+)$#', $uri, $matches) && $method === 'GET') {
    try {
        global $pdo;

        $product = fetchProduct($pdo, (int)$matches[1]);
        if (!$product) {
            sendJson(false, 'Product not found.', null, HTTP_NOT_FOUND);
        }

        sendJson(true, 'Product retrieved successfully.', $product);
    } catch (Exception $e) {
        sendJson(false, 'Unable to load product.', ['error' => $e->getMessage()], HTTP_INTERNAL_ERROR);
    }
}

if ($uri === '/api/v1/products' && $method === 'POST') {
    try {
        global $pdo;
        requireAdminUser($pdo);

        $input = getJsonInput();
        $name = trim($input['name'] ?? '');
        $price = (float)($input['price'] ?? 0);
        $stockQuantity = (int)($input['stockQuantity'] ?? $input['stock_quantity'] ?? 0);
        $categoryId = (int)($input['categoryId'] ?? $input['category_id'] ?? 0);
        $description = trim($input['description'] ?? '');
        $sku = trim($input['sku'] ?? '') ?: null;
        $image = trim($input['image'] ?? '') ?: null;

        if ($name === '' || $price < 0 || $stockQuantity < 0 || $categoryId <= 0) {
            sendJson(false, 'Name, price, stock quantity, and category are required.', null, HTTP_BAD_REQUEST);
        }

        if (DB_DRIVER === 'pgsql') {
            $stmt = $pdo->prepare(
                'INSERT INTO products (name, description, price, stockQuantity, categoryId, sku, image)
                 VALUES (?, ?, ?, ?, ?, ?, ?)
                 RETURNING id'
            );
            $stmt->execute([$name, $description, $price, $stockQuantity, $categoryId, $sku, $image]);
            $productId = (int)$stmt->fetchColumn();
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO products (name, description, price, stockQuantity, categoryId, sku, image)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$name, $description, $price, $stockQuantity, $categoryId, $sku, $image]);
            $productId = (int)$pdo->lastInsertId();
        }

        sendJson(true, 'Product created successfully.', fetchProduct($pdo, $productId), HTTP_CREATED);
    } catch (Exception $e) {
        sendJson(false, 'Unable to create product.', ['error' => $e->getMessage()], HTTP_INTERNAL_ERROR);
    }
}

if (preg_match('#^/api/v1/products/(\d+)$#', $uri, $matches) && $method === 'PUT') {
    try {
        global $pdo;
        requireAdminUser($pdo);

        $productId = (int)$matches[1];
        if (!fetchProduct($pdo, $productId)) {
            sendJson(false, 'Product not found.', null, HTTP_NOT_FOUND);
        }

        $input = getJsonInput();
        $stmt = $pdo->prepare(
            'UPDATE products
             SET name = ?, description = ?, price = ?, stockQuantity = ?, categoryId = ?, sku = ?, image = COALESCE(?, image)
             WHERE id = ?'
        );
        $stmt->execute([
            trim($input['name'] ?? ''),
            trim($input['description'] ?? ''),
            (float)($input['price'] ?? 0),
            (int)($input['stockQuantity'] ?? $input['stock_quantity'] ?? 0),
            (int)($input['categoryId'] ?? $input['category_id'] ?? 0),
            trim($input['sku'] ?? '') ?: null,
            trim($input['image'] ?? '') ?: null,
            $productId,
        ]);

        sendJson(true, 'Product updated successfully.', fetchProduct($pdo, $productId));
    } catch (Exception $e) {
        sendJson(false, 'Unable to update product.', ['error' => $e->getMessage()], HTTP_INTERNAL_ERROR);
    }
}

if (preg_match('#^/api/v1/products/(\d+)$#', $uri, $matches) && $method === 'DELETE') {
    try {
        global $pdo;
        requireAdminUser($pdo);

        $stmt = $pdo->prepare('DELETE FROM products WHERE id = ?');
        $stmt->execute([(int)$matches[1]]);
        sendJson(true, 'Product deleted successfully.');
    } catch (Exception $e) {
        sendJson(false, 'Unable to delete product. It may be attached to an order.', ['error' => $e->getMessage()], HTTP_CONFLICT);
    }
}

if ($uri === '/api/v1/categories' && $method === 'GET') {
    try {
        global $pdo;

        $stmt = $pdo->query(
            'SELECT c.*, COUNT(p.id) AS productCount
             FROM categories c
             LEFT JOIN products p ON p.categoryId = c.id
             GROUP BY c.id, c.name, c.description, c.icon, c.created_at, c.updated_at
             ORDER BY c.name ASC'
        );
        sendJson(true, 'Categories retrieved successfully', array_map('normalizeCategory', $stmt->fetchAll(PDO::FETCH_ASSOC)));
    } catch (Exception $e) {
        sendJson(false, 'Categories route works but DB/table may not exist yet', ['error' => $e->getMessage()]);
    }
}

if ($uri === '/api/v1/categories' && $method === 'POST') {
    try {
        global $pdo;
        requireAdminUser($pdo);

        $input = getJsonInput();
        $name = trim($input['name'] ?? '');
        $description = trim($input['description'] ?? '');
        $icon = trim($input['icon'] ?? '') ?: null;

        if ($name === '') {
            sendJson(false, 'Category name is required.', null, HTTP_BAD_REQUEST);
        }

        if (DB_DRIVER === 'pgsql') {
            $stmt = $pdo->prepare(
                'INSERT INTO categories (name, description, icon)
                 VALUES (?, ?, ?)
                 RETURNING *'
            );
            $stmt->execute([$name, $description, $icon]);
            $category = normalizeCategory($stmt->fetch(PDO::FETCH_ASSOC));
        } else {
            $stmt = $pdo->prepare('INSERT INTO categories (name, description, icon) VALUES (?, ?, ?)');
            $stmt->execute([$name, $description, $icon]);
            $lookup = $pdo->prepare('SELECT * FROM categories WHERE id = ?');
            $lookup->execute([(int)$pdo->lastInsertId()]);
            $category = normalizeCategory($lookup->fetch(PDO::FETCH_ASSOC));
        }

        sendJson(true, 'Category created successfully.', $category, HTTP_CREATED);
    } catch (Exception $e) {
        sendJson(false, 'Unable to create category.', ['error' => $e->getMessage()], HTTP_INTERNAL_ERROR);
    }
}

if (preg_match('#^/api/v1/categories/(\d+)$#', $uri, $matches) && $method === 'PUT') {
    try {
        global $pdo;
        requireAdminUser($pdo);

        $input = getJsonInput();
        $stmt = $pdo->prepare('UPDATE categories SET name = ?, description = ?, icon = COALESCE(?, icon) WHERE id = ?');
        $stmt->execute([
            trim($input['name'] ?? ''),
            trim($input['description'] ?? ''),
            trim($input['icon'] ?? '') ?: null,
            (int)$matches[1],
        ]);
        $lookup = $pdo->prepare('SELECT * FROM categories WHERE id = ?');
        $lookup->execute([(int)$matches[1]]);
        $category = $lookup->fetch(PDO::FETCH_ASSOC);

        if (!$category) {
            sendJson(false, 'Category not found.', null, HTTP_NOT_FOUND);
        }

        sendJson(true, 'Category updated successfully.', normalizeCategory($category));
    } catch (Exception $e) {
        sendJson(false, 'Unable to update category.', ['error' => $e->getMessage()], HTTP_INTERNAL_ERROR);
    }
}

if (preg_match('#^/api/v1/categories/(\d+)$#', $uri, $matches) && $method === 'DELETE') {
    try {
        global $pdo;
        requireAdminUser($pdo);

        $count = $pdo->prepare('SELECT COUNT(*) FROM products WHERE categoryId = ?');
        $count->execute([(int)$matches[1]]);
        if ((int)$count->fetchColumn() > 0) {
            sendJson(false, 'Cannot delete a category with products.', null, HTTP_CONFLICT);
        }

        $stmt = $pdo->prepare('DELETE FROM categories WHERE id = ?');
        $stmt->execute([(int)$matches[1]]);
        sendJson(true, 'Category deleted successfully.');
    } catch (Exception $e) {
        sendJson(false, 'Unable to delete category.', ['error' => $e->getMessage()], HTTP_INTERNAL_ERROR);
    }
}

if ($uri === '/api/v1/orders/my' && $method === 'GET') {
    try {
        global $pdo;
        $user = currentUser($pdo);
        $page = requestPage();
        $perPage = requestPerPage();
        $offset = ($page - 1) * $perPage;

        $count = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE userId = ?');
        $count->execute([(int)$user['id']]);

        $stmt = $pdo->prepare(
            'SELECT o.*, u.email, u.firstName, u.lastName
             FROM orders o
             INNER JOIN users u ON u.id = o.userId
             WHERE o.userId = ?
             ORDER BY o.id DESC
             LIMIT ? OFFSET ?'
        );
        $stmt->execute([(int)$user['id'], $perPage, $offset]);

        paginateResponse(
            array_map('normalizeOrder', $stmt->fetchAll(PDO::FETCH_ASSOC)),
            (int)$count->fetchColumn(),
            $page,
            $perPage,
            'Your orders retrieved successfully'
        );
    } catch (Exception $e) {
        sendJson(false, 'Unable to load your orders.', ['error' => $e->getMessage()], HTTP_INTERNAL_ERROR);
    }
}

if ($uri === '/api/v1/orders' && $method === 'GET') {
    try {
        global $pdo;
        requireAdminUser($pdo);
        $page = requestPage();
        $perPage = requestPerPage();
        $offset = ($page - 1) * $perPage;
        $status = trim($_GET['status'] ?? '');
        $params = [];
        $where = '';

        if ($status !== '') {
            $where = 'WHERE o.status = ?';
            $params[] = $status;
        }

        $count = $pdo->prepare("SELECT COUNT(*) FROM orders o $where");
        $count->execute($params);

        $stmt = $pdo->prepare(
            "SELECT o.*, u.email, u.firstName, u.lastName
             FROM orders o
             INNER JOIN users u ON u.id = o.userId
             $where
             ORDER BY o.id DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->execute(array_merge($params, [$perPage, $offset]));

        paginateResponse(
            array_map('normalizeOrder', $stmt->fetchAll(PDO::FETCH_ASSOC)),
            (int)$count->fetchColumn(),
            $page,
            $perPage,
            'Orders retrieved successfully'
        );
    } catch (Exception $e) {
        sendJson(false, 'Unable to load orders.', ['error' => $e->getMessage()], HTTP_INTERNAL_ERROR);
    }
}

if ($uri === '/api/v1/orders' && $method === 'POST') {
    try {
        global $pdo;
        $user = currentUser($pdo);
        $input = getJsonInput();
        $items = $input['items'] ?? [];

        if (!is_array($items) || empty($items)) {
            sendJson(false, 'Order must contain at least one item.', null, HTTP_BAD_REQUEST);
        }

        $orderItems = [];
        $totalAmount = 0;

        foreach ($items as $item) {
            $productId = (int)($item['productId'] ?? $item['product_id'] ?? $item['id'] ?? 0);
            $quantity = (int)($item['quantity'] ?? 0);

            if ($productId <= 0 || $quantity <= 0) {
                sendJson(false, 'Each order item needs a productId and quantity.', null, HTTP_BAD_REQUEST);
            }

            $product = fetchProduct($pdo, $productId);
            if (!$product) {
                sendJson(false, "Product {$productId} not found.", null, HTTP_NOT_FOUND);
            }

            if ((int)$product['stockQuantity'] < $quantity) {
                sendJson(false, "Product '{$product['name']}' has insufficient stock.", null, HTTP_CONFLICT);
            }

            $unitPrice = (float)$product['price'];
            $subtotal = $unitPrice * $quantity;
            $totalAmount += $subtotal;
            $orderItems[] = [
                'productId' => $productId,
                'quantity' => $quantity,
                'unitPrice' => $unitPrice,
                'subtotal' => $subtotal,
            ];
        }

        $pdo->beginTransaction();

        try {
            $orderNumber = 'ORD-' . date('YmdHis') . '-' . random_int(1000, 9999);

            if (DB_DRIVER === 'pgsql') {
                $orderStmt = $pdo->prepare(
                    'INSERT INTO orders (userId, orderNumber, totalAmount, status)
                     VALUES (?, ?, ?, ?)
                     RETURNING id'
                );
                $orderStmt->execute([(int)$user['id'], $orderNumber, $totalAmount, ORDER_STATUS_PROCESSING]);
                $orderId = (int)$orderStmt->fetchColumn();
            } else {
                $orderStmt = $pdo->prepare(
                    'INSERT INTO orders (userId, orderNumber, totalAmount, status)
                     VALUES (?, ?, ?, ?)'
                );
                $orderStmt->execute([(int)$user['id'], $orderNumber, $totalAmount, ORDER_STATUS_PROCESSING]);
                $orderId = (int)$pdo->lastInsertId();
            }

            $itemStmt = $pdo->prepare(
                'INSERT INTO order_items (orderId, productId, quantity, unitPrice, subtotal)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stockStmt = $pdo->prepare('UPDATE products SET stockQuantity = stockQuantity - ? WHERE id = ?');

            foreach ($orderItems as $item) {
                $itemStmt->execute([$orderId, $item['productId'], $item['quantity'], $item['unitPrice'], $item['subtotal']]);
                $stockStmt->execute([$item['quantity'], $item['productId']]);
            }

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }

        sendJson(true, 'Order created successfully.', fetchOrder($pdo, $orderId), HTTP_CREATED);
    } catch (Exception $e) {
        sendJson(false, 'Unable to create order.', ['error' => $e->getMessage()], HTTP_INTERNAL_ERROR);
    }
}

if (preg_match('#^/api/v1/orders/(\d+)$#', $uri, $matches) && $method === 'GET') {
    try {
        global $pdo;
        $user = currentUser($pdo);
        $order = fetchOrder($pdo, (int)$matches[1]);

        if (!$order) {
            sendJson(false, 'Order not found.', null, HTTP_NOT_FOUND);
        }

        if (($user['role'] ?? '') !== USER_ROLE_ADMIN && (int)$order['userId'] !== (int)$user['id']) {
            sendJson(false, 'You do not have access to this order.', null, HTTP_FORBIDDEN);
        }

        sendJson(true, 'Order retrieved successfully.', $order);
    } catch (Exception $e) {
        sendJson(false, 'Unable to load order.', ['error' => $e->getMessage()], HTTP_INTERNAL_ERROR);
    }
}

if (preg_match('#^/api/v1/orders/(\d+)/status$#', $uri, $matches) && $method === 'PUT') {
    try {
        global $pdo;
        requireAdminUser($pdo);

        $input = getJsonInput();
        $status = trim($input['status'] ?? '');
        $validStatuses = [ORDER_STATUS_PROCESSING, ORDER_STATUS_COMPLETED, ORDER_STATUS_FAILED, ORDER_STATUS_CANCELLED];

        if (!in_array($status, $validStatuses, true)) {
            sendJson(false, 'Invalid order status.', null, HTTP_BAD_REQUEST);
        }

        $stmt = $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?');
        $stmt->execute([$status, (int)$matches[1]]);

        sendJson(true, 'Order status updated successfully.', fetchOrder($pdo, (int)$matches[1]));
    } catch (Exception $e) {
        sendJson(false, 'Unable to update order status.', ['error' => $e->getMessage()], HTTP_INTERNAL_ERROR);
    }
}

if ($uri === '/api/v1/payments' && $method === 'GET') {
    try {
        global $pdo;
        requireAdminUser($pdo);

        $page = requestPage();
        $perPage = requestPerPage();
        $offset = ($page - 1) * $perPage;
        $status = trim($_GET['status'] ?? '');
        $params = [];
        $where = '';

        if ($status !== '') {
            $where = 'WHERE p.status = ?';
            $params[] = $status;
        }

        $count = $pdo->prepare("SELECT COUNT(*) FROM payments p $where");
        $count->execute($params);

        $stmt = $pdo->prepare(
            "SELECT p.*, o.orderNumber, u.email
             FROM payments p
             INNER JOIN orders o ON o.id = p.orderId
             INNER JOIN users u ON u.id = o.userId
             $where
             ORDER BY p.id DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->execute(array_merge($params, [$perPage, $offset]));

        paginateResponse(
            array_map('normalizePayment', $stmt->fetchAll(PDO::FETCH_ASSOC)),
            (int)$count->fetchColumn(),
            $page,
            $perPage,
            'Payments retrieved successfully'
        );
    } catch (Exception $e) {
        sendJson(false, 'Unable to load payments.', ['error' => $e->getMessage()], HTTP_INTERNAL_ERROR);
    }
}

if ($uri === '/api/v1/payments/initiate' && $method === 'POST') {
    try {
        global $pdo;
        $user = currentUser($pdo);
        $input = getJsonInput();
        $orderId = (int)($input['orderId'] ?? $input['order_id'] ?? 0);
        $paymentMethod = trim($input['paymentMethod'] ?? $input['payment_method'] ?? 'paynow');

        if ($orderId <= 0) {
            sendJson(false, 'Order ID is required.', null, HTTP_BAD_REQUEST);
        }

        $order = fetchOrder($pdo, $orderId);
        if (!$order) {
            sendJson(false, 'Order not found.', null, HTTP_NOT_FOUND);
        }

        if (($user['role'] ?? '') !== USER_ROLE_ADMIN && (int)$order['userId'] !== (int)$user['id']) {
            sendJson(false, 'You do not have access to this order.', null, HTTP_FORBIDDEN);
        }

        $existing = $pdo->prepare('SELECT * FROM payments WHERE orderId = ? LIMIT 1');
        $existing->execute([$orderId]);
        $payment = $existing->fetch(PDO::FETCH_ASSOC);

        if ($payment) {
            $update = $pdo->prepare('UPDATE payments SET paymentMethod = ?, status = ? WHERE id = ?');
            $update->execute([$paymentMethod, PAYMENT_STATUS_PENDING, (int)$payment['id']]);
            $paymentId = (int)$payment['id'];
        } elseif (DB_DRIVER === 'pgsql') {
            $insert = $pdo->prepare(
                'INSERT INTO payments (orderId, amount, paymentMethod, status)
                 VALUES (?, ?, ?, ?)
                 RETURNING id'
            );
            $insert->execute([$orderId, $order['totalAmount'], $paymentMethod, PAYMENT_STATUS_PENDING]);
            $paymentId = (int)$insert->fetchColumn();
        } else {
            $insert = $pdo->prepare(
                'INSERT INTO payments (orderId, amount, paymentMethod, status)
                 VALUES (?, ?, ?, ?)'
            );
            $insert->execute([$orderId, $order['totalAmount'], $paymentMethod, PAYMENT_STATUS_PENDING]);
            $paymentId = (int)$pdo->lastInsertId();
        }

        $lookup = $pdo->prepare('SELECT * FROM payments WHERE id = ?');
        $lookup->execute([$paymentId]);
        sendJson(true, 'Payment initiated successfully.', normalizePayment($lookup->fetch(PDO::FETCH_ASSOC)), HTTP_CREATED);
    } catch (Exception $e) {
        sendJson(false, 'Unable to initiate payment.', ['error' => $e->getMessage()], HTTP_INTERNAL_ERROR);
    }
}

if ($uri === '/api/v1/payments/verify' && $method === 'POST') {
    try {
        global $pdo;
        $input = getJsonInput();
        $orderId = (int)($input['orderId'] ?? $input['order_id'] ?? 0);
        $transactionId = trim($input['transactionId'] ?? $input['transaction_id'] ?? ('TXN-' . time()));
        $status = strtolower(trim($input['status'] ?? PAYMENT_STATUS_COMPLETED));
        $paymentStatus = in_array($status, ['completed', 'success', 'paid'], true)
            ? PAYMENT_STATUS_COMPLETED
            : PAYMENT_STATUS_FAILED;

        $payment = $pdo->prepare('SELECT * FROM payments WHERE orderId = ? LIMIT 1');
        $payment->execute([$orderId]);
        $existing = $payment->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            sendJson(false, 'Payment not found.', null, HTTP_NOT_FOUND);
        }

        $update = $pdo->prepare('UPDATE payments SET status = ?, transactionId = ? WHERE id = ?');
        $update->execute([$paymentStatus, $transactionId, (int)$existing['id']]);

        $orderStatus = $paymentStatus === PAYMENT_STATUS_COMPLETED
            ? ORDER_STATUS_COMPLETED
            : ORDER_STATUS_FAILED;
        $orderUpdate = $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?');
        $orderUpdate->execute([$orderStatus, $orderId]);

        sendJson(true, 'Payment verified successfully.', [
            'orderId' => $orderId,
            'transactionId' => $transactionId,
            'status' => $paymentStatus,
        ]);
    } catch (Exception $e) {
        sendJson(false, 'Unable to verify payment.', ['error' => $e->getMessage()], HTTP_INTERNAL_ERROR);
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

sendJson(false, 'Route not found', ['path' => $uri, 'method' => $method], 404);
