<?php

/**
 * ============================================================================
 * APPLICATION ENTRY POINT
 * ============================================================================
 * 
 * This is the main entry point for the API application.
 * All HTTP requests are routed through this file.
 * 
 * Process Flow:
 * 1. Load configuration and bootstrap the application
 * 2. Create router instance and register routes
 * 3. Dispatch the incoming request to appropriate controller
 * 4. Return JSON response to client
 * 
 * Directory Structure:
 * /public/index.php (this file)
 *     ↓
 * Request comes in from client
 *     ↓
 * Router parses URL and HTTP method
 *     ↓
 * Router finds matching route
 *     ↓
 * Controller method is called
 *     ↓
 * Controller interacts with models
 *     ↓
 * Response sent back to client as JSON
 * 
 * @package EcommerceElectronics
 * @author School Project
 * @version 1.0.0
 * ============================================================================
 */

// ============================================================================
// BOOTSTRAP APPLICATION
// ============================================================================
// Load configuration
require_once __DIR__ . '/../config/app.php';

// ============================================================================
// ERROR HANDLING & DEBUGGING
// ============================================================================
// Set error reporting based on environment
error_reporting(E_ALL);
if (APP_DEBUG) {
    ini_set('display_errors', 1);
} else {
    ini_set('display_errors', 0);
}

// Set internal encoding
ini_set('default_charset', 'utf-8');

// ============================================================================
// LOAD CORE CLASSES
// ============================================================================
// Load core framework classes

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../Core/router.php';

// ============================================================================
// LOAD UTILITY CLASSES
// ============================================================================
// Load utility/helper classes

require_once __DIR__ . '/../utils/Request.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../utils/Auth.php';

// ============================================================================
// LOAD BASE CLASSES
// ============================================================================
// Load base model and controller classes

require_once __DIR__ . '/../app/models/Model.php';
require_once __DIR__ . '/../app/controllers/Controller.php';

// ============================================================================
// AUTOLOAD USER-DEFINED CLASSES
// ============================================================================
// Simple autoloader for models and controllers
// In production, use Composer's autoloader

spl_autoload_register(function($class) {
    // Try to load from models directory
    $modelPath = __DIR__ . '/../app/models/' . $class . '.php';
    if (file_exists($modelPath)) {
        require_once $modelPath;
        return;
    }
    
    // Try to load from controllers directory
    $controllerPath = __DIR__ . '/../app/controllers/' . $class . '.php';
    if (file_exists($controllerPath)) {
        require_once $controllerPath;
        return;
    }
});

// ============================================================================
// INITIALIZE AND CONFIGURE ROUTER
// ============================================================================
// Create router instance and register API routes

$router = new Router();

// Set API base URI
$router->setBaseUri('/api/v1');

// ============================================================================
// AUTHENTICATION ROUTES
// ============================================================================
// Routes for user registration, login, and profile

$router->post('auth/register', 'AuthController@register');
$router->post('auth/login', 'AuthController@login');
$router->get('auth/profile', 'AuthController@profile');
$router->post('auth/logout', 'AuthController@logout');

// ============================================================================
// PRODUCT ROUTES
// ============================================================================
// Routes for product management (list, view, create, update, delete, search)

// Note: Static routes (search, trending, category) must come BEFORE dynamic :id routes
$router->get('products/trending', 'ProductController@trending');
$router->get('products/search', 'ProductController@search');
$router->get('products/category/:categoryId', 'ProductController@category');

// Dynamic routes (must come AFTER static routes)
$router->get('products', 'ProductController@index');
$router->get('products/:id', 'ProductController@show');
$router->post('products', 'ProductController@store');
$router->put('products/:id', 'ProductController@update');
$router->delete('products/:id', 'ProductController@destroy');

// ============================================================================
// CATEGORY ROUTES
// ============================================================================
// Routes for product category management

$router->get('categories/search', 'CategoryController@search');
$router->get('categories', 'CategoryController@index');
$router->get('categories/:id', 'CategoryController@show');
$router->post('categories', 'CategoryController@store');
$router->put('categories/:id', 'CategoryController@update');
$router->delete('categories/:id', 'CategoryController@destroy');

// ============================================================================
// ORDER ROUTES
// ============================================================================
// Routes for customer orders (list, view, create, update status)

$router->get('orders/my', 'OrderController@myOrders');
$router->get('orders', 'OrderController@index');
$router->get('orders/:id', 'OrderController@show');
$router->post('orders', 'OrderController@store');
$router->put('orders/:id/status', 'OrderController@updateStatus');

// ============================================================================
// PAYMENT ROUTES
// ============================================================================
// Routes for payment processing and status checking

$router->get('payments/status', 'PaymentController@status');
$router->get('payments', 'PaymentController@index');
$router->post('payments/initiate', 'PaymentController@initiate');
$router->post('payments/verify', 'PaymentController@verify');
$router->post('payments/webhook', 'PaymentController@webhook');

// ============================================================================
// MIDDLEWARE
// ============================================================================
// Register global middleware (runs for all requests)

// $router->middleware('AuthMiddleware');
// $router->middleware('LoggingMiddleware');

// ============================================================================
// DISPATCH REQUEST
// ============================================================================
// Parse the incoming request and route to appropriate controller

try {
    $router->dispatch();
} catch (Exception $e) {
    // Catch any unhandled exceptions
    error_log('Uncaught exception: ' . $e->getMessage());
    
    if (APP_DEBUG) {
        // In debug mode, show detailed error
        Response::internalError('Error: ' . $e->getMessage());
    } else {
        // In production, show generic error
        Response::internalError('An internal error occurred');
    }
}

?>
