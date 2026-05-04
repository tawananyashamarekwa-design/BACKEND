<?php


define('APP_ENV', getenv('APP_ENV') ?: 'development');
define('APP_DEBUG', APP_ENV === 'development');
define('APP_NAME', 'Electronics Ecommerce Platform');
define('APP_VERSION', '1.0.0');

// APPLICATION TIMEZONE & LOCALE
// Set the default timezone for all time operations throughout the application
// This ensures consistent time handling across different server environments

date_default_timezone_set('UTC');
define('APP_TIMEZONE', 'UTC');
define('APP_LOCALE', 'en_US');


// DATABASE CONFIGURATION

// Database connection parameters using PDO (PHP Data Objects)
// These values would typically come from environment variables in production

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'electronics_ecommerce');
define('DB_PORT', getenv('DB_PORT') ?: 3306);
define('DB_CHARSET', 'utf8mb4');


// API CONFIGURATION

// Settings for RESTful API behavior, response formats, and rate limiting

define('API_VERSION', '1.0');
define('API_RESPONSE_FORMAT', 'json');  // Default response format
define('MAX_REQUEST_SIZE', 10485760);   // Maximum request size in bytes (10MB)
define('API_TIMEOUT', 30);              // API timeout in seconds
define('RATE_LIMIT_ENABLED', false);    // Disable for demo project
define('ITEMS_PER_PAGE', 15);           // Default pagination limit


// SECURITY CONFIGURATION

// Security-related settings including encryption keys, token expiration, etc.
// Note: These are simplified for the demo project

define('JWT_SECRET', getenv('JWT_SECRET') ?: 'demo-secret-key-change-in-production');
define('JWT_EXPIRY', 86400);                      // Token expiry: 24 hours (in seconds)
define('PASSWORD_MIN_LENGTH', 6);                 // Minimum password length
define('BCRYPT_COST', 10);                        // BCrypt hashing cost factor
define('SESSION_TIMEOUT', 3600);                  // Session timeout: 1 hour (in seconds)

// FILE UPLOAD CONFIGURATION

// Settings for file upload handling, storage locations, and allowed file types

define('UPLOAD_DIR', __DIR__ . '/../uploads');
define('PRODUCTS_UPLOAD_DIR', UPLOAD_DIR . '/products');
define('MAX_UPLOAD_SIZE', 5242880);               // Maximum file size: 5MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('ALLOWED_FILE_TYPES', ['pdf', 'doc', 'docx', 'xls', 'xlsx']);

// ============================================================================
// CORS (Cross-Origin Resource Sharing) CONFIGURATION
// ============================================================================
// Configure which domains are allowed to access this API

define('CORS_ENABLED', true);
define('ALLOWED_ORIGINS', array_values(array_filter([
    'http://localhost:3000',
    'http://localhost:5173',
    'http://localhost:8080',
    'http://127.0.0.1:3000',
    'http://127.0.0.1:5173',
    'http://127.0.0.1:8080',
    'https://harareelectronichub.vercel.app',
    rtrim(getenv('FRONTEND_URL') ?: '', '/')
])));


// EMAIL CONFIGURATION (Optional for school demo)

// Email settings for sending notifications, order confirmations, etc.

define('MAIL_FROM_ADDRESS', getenv('MAIL_FROM_ADDRESS') ?: 'noreply@electronics.local');
define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: 'Electronics Store');
define('MAIL_ENABLED', false);  // Disabled for demo


// LOGGING CONFIGURATION

// Settings for application logging and error tracking

define('LOG_DIR', __DIR__ . '/../logs');
define('LOG_FILE', LOG_DIR . '/application.log');
define('LOG_LEVEL', APP_DEBUG ? 'DEBUG' : 'ERROR');


// BUSINESS CONSTANTS

// Domain-specific constants for order statuses, user roles, etc.

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



// HTTP STATUS CODES

// Common HTTP response codes used throughout the application

define('HTTP_OK', 200);
define('HTTP_CREATED', 201);
define('HTTP_BAD_REQUEST', 400);
define('HTTP_UNAUTHORIZED', 401);
define('HTTP_FORBIDDEN', 403);
define('HTTP_NOT_FOUND', 404);
define('HTTP_CONFLICT', 409);
define('HTTP_INTERNAL_ERROR', 500);
define('HTTP_SERVICE_UNAVAILABLE', 503);

// Ensure required directories exist and are writable
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}
if (!is_dir(PRODUCTS_UPLOAD_DIR)) {
    mkdir(PRODUCTS_UPLOAD_DIR, 0755, true);
}
if (!is_dir(LOG_DIR)) {
    mkdir(LOG_DIR, 0755, true);
}

?>
