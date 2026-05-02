<?php

/**
 * ============================================================================
 * ROUTER CLASS - API ROUTE HANDLER
 * ============================================================================
 * 
 * This class handles all HTTP routing for the application. It parses incoming
 * requests, matches them against defined routes, and dispatches control to the
 * appropriate controller method. This is the core request dispatcher for the
 * entire API.
 
 * @package EcommerceElectronics\Core
 * @author School Project
 * @version 1.0.0
 * ============================================================================
 */

require_once __DIR__ . '/../config/app.php';

class Router {
    
    private $routes = [];
    
    private $middlewares = [];
    
    private $method = '';
    
    private $uri = '';
    
    private $parameters = [];
    
    private $baseUri = '/api/v1';
    
   
    public function __construct() {
        $this->method = $_SERVER['REQUEST_METHOD'];
        
        $this->uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        $this->uri = str_replace('/index.php', '', $this->uri);
        
        foreach (['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS'] as $method) {
            $this->routes[$method] = [];
        }
    }
    
    public function get($path, $handler) {
        return $this->registerRoute('GET', $path, $handler);
    }
    
    public function post($path, $handler) {
        return $this->registerRoute('POST', $path, $handler);
    }
    
    public function put($path, $handler) {
        return $this->registerRoute('PUT', $path, $handler);
    }

    public function patch($path, $handler) {
        return $this->registerRoute('PATCH', $path, $handler);
    }
    
    public function delete($path, $handler) {
        return $this->registerRoute('DELETE', $path, $handler);
    }
  
    public function any($methods, $path, $handler) {
        $methods = is_array($methods) ? $methods : [$methods];
        foreach ($methods as $method) {
            $this->registerRoute($method, $path, $handler);
        }
        return $this;
    }
  
    private function registerRoute($method, $path, $handler) {
        $path = ltrim($path, '/');
       
        $this->routes[$method][$path] = $handler;
        
        return $this;
    }
  
    public function middleware($middleware) {
        $this->middlewares[] = $middleware;
        return $this;
    }
    
    
    public function dispatch() {
        
        $this->setCorsHeaders();
        
        if ($this->method === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
        
        $requestUri = $this->normalizeUri();
        
        $matched = $this->matchRoute($requestUri);
        
        if (!$matched) {
            $this->notFound();
            return;
        }
        
        foreach ($this->middlewares as $middleware) {
            $this->executeMiddleware($middleware);
        }
    
        $this->executeHandler($matched);
    }
    
   
    private function normalizeUri() {
        $uri = $this->uri;
        
        if (strpos($uri, $this->baseUri) === 0) {
            $uri = substr($uri, strlen($this->baseUri));
        }
        
        $uri = ltrim($uri, '/');
        
        $uri = rtrim($uri, '/');
        
        if (strpos($uri, '?') !== false) {
            $uri = substr($uri, 0, strpos($uri, '?'));
        }
        
        return $uri;
    }
    
   
    private function matchRoute($requestUri) {
        $methodRoutes = $this->routes[$this->method] ?? [];
        
        if (isset($methodRoutes[$requestUri])) {
            return [
                'handler' => $methodRoutes[$requestUri],
                'parameters' => []
            ];
        }
        
        foreach ($methodRoutes as $route => $handler) {
            
            $pattern = $this->routeToRegex($route);
            
            if (preg_match($pattern, $requestUri, $matches)) {
                $params = [];
                foreach ($matches as $key => $value) {
                    if (!is_numeric($key)) {
                        $params[$key] = $value;
                    }
                }
                
                return [
                    'handler' => $handler,
                    'parameters' => $params
                ];
            }
        }
        
        return false;
    }
    
    

   private function routeToRegex($route) {
    $route = trim($route, '/');

    // Convert routes like products/{id} to products/(?P<id>[^/]+)
    $pattern = preg_replace(
        '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
        '(?P<$1>[^/]+)',
        $route
    );

    // Convert routes like products/:id to products/(?P<id>[^/]+)
    $pattern = preg_replace(
        '/\:([a-zA-Z_][a-zA-Z0-9_]*)/',
        '(?P<$1>[^/]+)',
        $pattern
    );

    return '/^' . str_replace('/', '\/', $pattern) . '$/';
}
    
    private function executeHandler($matched) {
        $handler = $matched['handler'];
        $parameters = $matched['parameters'];
        
        $GLOBALS['route_parameters'] = $parameters;
        
        if (strpos($handler, '@') === false) {
            require_once __DIR__ . '/../' . $handler;
            return;
        }
        
        list($controllerName, $methodName) = explode('@', $handler);
        
        $controllerPath = __DIR__ . '/../app/controllers/' . $controllerName . '.php';
        if (!file_exists($controllerPath)) {
            $this->notFound();
            return;
        }
        
        require_once $controllerPath;
        
        if (class_exists($controllerName)) {
            $controller = new $controllerName();
            
            if (method_exists($controller, $methodName)) {
                call_user_func_array([$controller, $methodName], $parameters);
            } else {
                $this->notFound();
            }
        } else {
            $this->notFound();
        }
    }
    
    private function notFound() {
        http_response_code(HTTP_NOT_FOUND);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Route not found',
            'path' => $this->uri,
            'method' => $this->method
        ]);
        exit;
    }
    
    private function setCorsHeaders() {
        if (!CORS_ENABLED) {
            return;
        }
        
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
       
        if (in_array($origin, ALLOWED_ORIGINS)) {
            header("Access-Control-Allow-Origin: $origin");
            header('Access-Control-Allow-Credentials: true');
        }
        
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');
        
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        
        header('Access-Control-Max-Age: 3600');
    }
    

    public function setBaseUri($baseUri) {
        $this->baseUri = $baseUri;
        return $this;
    }
    
    public function getMethod() {
        return $this->method;
    }
   
    public function getUri() {
        return $this->uri;
    }
   
    public function getParameters() {
        return $this->parameters;
    }
}

?>
