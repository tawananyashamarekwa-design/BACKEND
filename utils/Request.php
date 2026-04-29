<?php

/**
 * ============================================================================
 * REQUEST HELPER CLASS
 * ============================================================================
 * 
 * This class provides utilities for handling incoming HTTP requests.
 * It abstracts the complexity of accessing request data, methods, and headers
 * into simple, consistent methods.
 * 
 * Features:
 * - Extract JSON request body
 * - Access GET/POST/PUT/DELETE parameters
 * - Header management and validation
 * - File upload handling
 * - Request validation helpers
 * 
 * @package EcommerceElectronics\Utils
 * @author School Project
 * @version 1.0.0
 * ============================================================================
 */

class Request {
    
    /**
     * Cached JSON body data
     * Decoded JSON from the request body, cached for reuse
     * 
     * @var array|null
     */
    private static $jsonBody = null;
    
    /**
     * Flag indicating if JSON body has been parsed
     * 
     * @var bool
     */
    private static $jsonBodyParsed = false;
    
    
    /**
     * Get the HTTP request method (GET, POST, PUT, DELETE, etc.)
     * 
     * @return string The HTTP method in uppercase
     */
    public static function getMethod() {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }
    
    
    /**
     * Check if the request is a specific method
     * 
     * @param string $method The method to check (case-insensitive)
     * @return bool True if request matches the method
     */
    public static function isMethod($method) {
        return strtoupper(self::getMethod()) === strtoupper($method);
    }
    
    
    /**
     * Get the request URI
     * 
     * @return string The full request URI
     */
    public static function getUri() {
        return $_SERVER['REQUEST_URI'] ?? '';
    }
    
    
    /**
     * Get the request path (without query string)
     * 
     * @return string The request path
     */
    public static function getPath() {
        return parse_url(self::getUri(), PHP_URL_PATH) ?? '/';
    }
    
    
    /**
     * Get all query string parameters ($_GET)
     * 
     * @return array The query parameters
     */
    public static function getQuery() {
        return $_GET ?? [];
    }
    
    
    /**
     * Get a specific query parameter
     * 
     * @param string $key The parameter key
     * @param mixed $default Default value if key doesn't exist
     * @return mixed The parameter value or default
     */
    public static function query($key, $default = null) {
        return $_GET[$key] ?? $default;
    }
    
    
    /**
     * Get all POST form data ($_POST)
     * 
     * @return array The form data
     */
    public static function getForm() {
        return $_POST ?? [];
    }
    
    
    /**
     * Get a specific form field value
     * 
     * @param string $key The field name
     * @param mixed $default Default value if key doesn't exist
     * @return mixed The field value or default
     */
    public static function input($key, $default = null) {
        return $_POST[$key] ?? $default;
    }
    
    
    /**
     * Get the JSON request body as an associative array
     * Parses the raw request body and decodes JSON
     * 
     * @return array The decoded JSON data as associative array
     */
    public static function getJsonBody() {
        if (self::$jsonBodyParsed) {
            return self::$jsonBody;
        }
        
        // Get the raw request body
        $body = file_get_contents('php://input');
        
        // Try to decode as JSON
        if (!empty($body)) {
            self::$jsonBody = json_decode($body, true);
        } else {
            self::$jsonBody = [];
        }
        
        self::$jsonBodyParsed = true;
        return self::$jsonBody;
    }
    
    
    /**
     * Get a specific field from JSON body
     * 
     * @param string $key The field name
     * @param mixed $default Default value if key doesn't exist
     * @return mixed The field value or default
     */
    public static function json($key = null, $default = null) {
        $body = self::getJsonBody();
        
        if ($key === null) {
            return $body;
        }
        
        return $body[$key] ?? $default;
    }
    
    
    /**
     * Get all request data (combines GET, POST, and JSON body)
     * 
     * @return array Combined request data
     */
    public static function all() {
        return array_merge(
            self::getQuery(),
            self::getForm(),
            self::getJsonBody()
        );
    }
    
    
    /**
     * Get a request parameter from any source
     * Checks JSON body first, then POST, then GET
     * 
     * @param string $key The parameter key
     * @param mixed $default Default value if not found
     * @return mixed The parameter value or default
     */
    public static function get($key, $default = null) {
        $body = self::getJsonBody();
        if (isset($body[$key])) {
            return $body[$key];
        }
        
        if (isset($_POST[$key])) {
            return $_POST[$key];
        }
        
        return $_GET[$key] ?? $default;
    }
    
    
    /**
     * Check if a request parameter exists
     * Checks all sources (JSON, POST, GET)
     * 
     * @param string $key The parameter key
     * @return bool True if parameter exists
     */
    public static function has($key) {
        $body = self::getJsonBody();
        return isset($body[$key]) || isset($_POST[$key]) || isset($_GET[$key]);
    }
    
    
    /**
     * Get all request headers
     * 
     * @return array All HTTP headers
     */
    public static function getHeaders() {
        return getallheaders() ?? [];
    }
    
    
    /**
     * Get a specific header value
     * 
     * @param string $key The header name (case-insensitive)
     * @param mixed $default Default value if header doesn't exist
     * @return mixed The header value or default
     */
    public static function header($key, $default = null) {
        $headers = getallheaders() ?? [];
        
        // Search case-insensitively
        foreach ($headers as $name => $value) {
            if (strtolower($name) === strtolower($key)) {
                return $value;
            }
        }
        
        return $default;
    }
    
    
    /**
     * Get the Authorization header value (for Bearer tokens)
     * 
     * @return string|null The token without "Bearer " prefix, or null
     */
    public static function getBearerToken() {
        $auth = self::header('Authorization');
        
        if (!$auth) {
            return null;
        }
        
        // Check for Bearer token format
        if (preg_match('/^Bearer\s+(.+)$/i', $auth, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
    
    
    /**
     * Get uploaded files
     * 
     * @return array The $_FILES array
     */
    public static function getFiles() {
        return $_FILES ?? [];
    }
    
    
    /**
     * Get a specific uploaded file
     * 
     * @param string $fieldName The file input field name
     * @return array|null The file data or null if not uploaded
     */
    public static function file($fieldName) {
        return $_FILES[$fieldName] ?? null;
    }
    
    
    /**
     * Check if a file was uploaded
     * 
     * @param string $fieldName The file input field name
     * @return bool True if file was uploaded
     */
    public static function hasFile($fieldName) {
        return isset($_FILES[$fieldName]) && $_FILES[$fieldName]['error'] === UPLOAD_ERR_OK;
    }
    
    
    /**
     * Get the client IP address
     * Handles both direct connections and proxied connections
     * 
     * @return string The client IP address
     */
    public static function getClientIp() {
        // Check for shared internet
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        }
        
        // Check for forwarded requests (behind proxy)
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // Get the first IP if multiple are present
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }
        
        // Default to remote address
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    
    /**
     * Get the content type of the request
     * 
     * @return string The content type header value
     */
    public static function getContentType() {
        return $_SERVER['CONTENT_TYPE'] ?? '';
    }
    
    
    /**
     * Check if request is JSON
     * 
     * @return bool True if request Content-Type is JSON
     */
    public static function isJson() {
        $contentType = self::getContentType();
        return strpos($contentType, 'application/json') !== false;
    }
    
    
    /**
     * Check if request is a form submission
     * 
     * @return bool True if request Content-Type is form-urlencoded or form-data
     */
    public static function isForm() {
        $contentType = self::getContentType();
        return strpos($contentType, 'application/x-www-form-urlencoded') !== false ||
               strpos($contentType, 'multipart/form-data') !== false;
    }
}

?>
