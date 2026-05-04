<?php

define('APP_ENV', getenv('APP_ENV') ?: 'development');
define('APP_DEBUG', APP_ENV === 'development');
define('APP_NAME', 'Electronics Ecommerce Platform');
define('APP_VERSION', '1.0.0');

date_default_timezone_set('UTC');
define('APP_TIMEZONE', 'UTC');
define('APP_LOCALE', 'en_US');

define('DB_DRIVER', getenv('DB_DRIVER') ?: (
    str_starts_with(getenv('DATABASE_URL') ?: '', 'postgres') || getenv('DB_PORT') === '5432'
        ? 'pgsql'
        : 'pgsql'
));
define('DB_HOST', getenv('DB_HOST') ?: 'dpg-d7rks5i8qa3s73dj1t40-a');
define('DB_USER', getenv('DB_USER') ?: 'backend_db_zzin_user');
define('DB_PASS', getenv('DB_PASSWORD') ?: (getenv('DB_PASS') ?: ''));
define('DB_NAME', getenv('DB_NAME') ?: 'backend_db_zzin');
define('DB_PORT', getenv('DB_PORT') ?: (DB_DRIVER === 'pgsql' ? 5432 : 3306));
define('DB_CHARSET', 'utf8mb4');

define('API_VERSION', '1.0');
define('API_RESPONSE_FORMAT', 'json');
define('MAX_REQUEST_SIZE', 10485760);
define('API_TIMEOUT', 30);
define('RATE_LIMIT_ENABLED', false);
define('ITEMS_PER_PAGE', 15);

define('JWT_SECRET', getenv('JWT_SECRET') ?: 'demo-secret-key-change-in-production');
define('JWT_EXPIRY', 86400);
define('PASSWORD_MIN_LENGTH', 6);
define('BCRYPT_COST', 10);
define('SESSION_TIMEOUT', 3600);

define('UPLOAD_DIR', __DIR__ . '/../uploads');
define('PRODUCTS_UPLOAD_DIR', UPLOAD_DIR . '/products');
define('MAX_UPLOAD_SIZE', 5242880);
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('ALLOWED_FILE_TYPES', ['pdf', 'doc', 'docx', 'xls', 'xlsx']);

define('CORS_ENABLED', true);
define('ALLOWED_ORIGINS', array_values(array_filter([
    'http://localhost:3000',
    'http://localhost:5173',
    'http://localhost:8080',
    'http://127.0.0.1:3000',
    'http://127.0.0.1:5173',
    'http://127.0.0.1:8080',
    'https://harareelectronichub.vercel.app',
    rtrim(getenv('FRONTEND_URL') ?: '', '/'),
])));

define('MAIL_FROM_ADDRESS', getenv('MAIL_FROM_ADDRESS') ?: 'noreply@electronics.local');
define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: 'Electronics Store');
define('MAIL_ENABLED', false);

define('LOG_DIR', __DIR__ . '/../logs');
define('LOG_FILE', LOG_DIR . '/application.log');
define('LOG_LEVEL', APP_DEBUG ? 'DEBUG' : 'ERROR');

define('ORDER_STATUS_PROCESSING', 'processing');
define('ORDER_STATUS_COMPLETED', 'completed');
define('ORDER_STATUS_FAILED', 'failed');
define('ORDER_STATUS_CANCELLED', 'cancelled');

define('USER_ROLE_CUSTOMER', 'customer');
define('USER_ROLE_ADMIN', 'admin');
define('USER_ROLE_GUEST', 'guest');

define('PAYMENT_STATUS_PENDING', 'pending');
define('PAYMENT_STATUS_COMPLETED', 'completed');
define('PAYMENT_STATUS_FAILED', 'failed');
define('PAYMENT_STATUS_REFUNDED', 'refunded');

define('PAYNOW_INTEGRATION_ID', getenv('PAYNOW_INTEGRATION_ID') ?: '');
define('PAYNOW_INTEGRATION_KEY', getenv('PAYNOW_INTEGRATION_KEY') ?: '');
define('PAYNOW_RETURN_URL', getenv('PAYNOW_RETURN_URL') ?: 'http://localhost:5173/payment-success');
define('PAYNOW_RESULT_URL', getenv('PAYNOW_RESULT_URL') ?: 'http://localhost:8000/api/v1/paynow-result');

define('HTTP_OK', 200);
define('HTTP_CREATED', 201);
define('HTTP_BAD_REQUEST', 400);
define('HTTP_UNAUTHORIZED', 401);
define('HTTP_FORBIDDEN', 403);
define('HTTP_NOT_FOUND', 404);
define('HTTP_CONFLICT', 409);
define('HTTP_INTERNAL_ERROR', 500);
define('HTTP_SERVICE_UNAVAILABLE', 503);

foreach ([UPLOAD_DIR, PRODUCTS_UPLOAD_DIR, LOG_DIR] as $directory) {
    if (!is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
}
