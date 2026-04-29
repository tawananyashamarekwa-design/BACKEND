<?php

/**
 * ============================================================================
 * RESPONSE HELPER CLASS
 * ============================================================================
 * 
 * This class provides a standardized way to format and send API responses.
 * All responses follow a consistent JSON structure with success flag, message,
 * and optional data. This consistency makes API consumption easier for clients.
 * 
 * Response Format:
 * {
 *     "success": true/false,
 *     "message": "Human-readable message",
 *     "data": { ... },
 *     "timestamp": "ISO 8601 timestamp"
 * }
 * 
 * @package EcommerceElectronics\Utils
 * @author School Project
 * @version 1.0.0
 * ============================================================================
 */

class Response {
    
    /**
     * Flag to track if headers have been sent
     * Prevents setting headers after response has started
     * 
     * @var bool
     */
    private static $headersSent = false;
    
    
    /**
     * Send a successful JSON response
     * 
     * @param string $message Success message
     * @param array $data Optional response data
     * @param int $statusCode HTTP status code (default: 200)
     * @return void
     */
    public static function success($message, $data = null, $statusCode = HTTP_OK) {
        self::json(true, $message, $data, $statusCode);
    }
    
    
    /**
     * Send an error JSON response
     * 
     * @param string $message Error message
     * @param array $data Optional error data or details
     * @param int $statusCode HTTP status code (default: 400)
     * @return void
     */
    public static function error($message, $data = null, $statusCode = HTTP_BAD_REQUEST) {
        self::json(false, $message, $data, $statusCode);
    }
    
    
    /**
     * Send a created resource response (201 Created)
     * 
     * @param string $message Success message
     * @param array $data The created resource data
     * @return void
     */
    public static function created($message, $data = null) {
        self::json(true, $message, $data, HTTP_CREATED);
    }
    
    
    /**
     * Send an unauthorized response (401)
     * 
     * @param string $message Error message
     * @return void
     */
    public static function unauthorized($message = 'Unauthorized. Please provide valid credentials.') {
        self::json(false, $message, null, HTTP_UNAUTHORIZED);
    }
    
    
    /**
     * Send a forbidden response (403)
     * 
     * @param string $message Error message
     * @return void
     */
    public static function forbidden($message = 'Access forbidden. You do not have permission.') {
        self::json(false, $message, null, HTTP_FORBIDDEN);
    }
    
    
    /**
     * Send a not found response (404)
     * 
     * @param string $message Error message
     * @return void
     */
    public static function notFound($message = 'Resource not found.') {
        self::json(false, $message, null, HTTP_NOT_FOUND);
    }
    
    
    /**
     * Send a validation error response (400)
     * 
     * @param array $errors Validation error messages (key => error)
     * @return void
     */
    public static function validationError($errors) {
        self::json(false, 'Validation failed', ['errors' => $errors], HTTP_BAD_REQUEST);
    }
    
    
    /**
     * Send a conflict response (409)
     * Used when request conflicts with existing resource (e.g., duplicate email)
     * 
     * @param string $message Error message
     * @return void
     */
    public static function conflict($message = 'Request conflicts with existing resource.') {
        self::json(false, $message, null, HTTP_CONFLICT);
    }
    
    
    /**
     * Send an internal server error response (500)
     * 
     * @param string $message Error message
     * @return void
     */
    public static function internalError($message = 'An internal server error occurred.') {
        self::json(false, $message, null, HTTP_INTERNAL_ERROR);
    }
    
    
    /**
     * Send a service unavailable response (503)
     * 
     * @param string $message Error message
     * @return void
     */
    public static function unavailable($message = 'Service temporarily unavailable.') {
        self::json(false, $message, null, HTTP_SERVICE_UNAVAILABLE);
    }
    
    
    /**
     * Core JSON response method
     * All other methods delegate to this for consistency
     * 
     * @param bool $success Success flag
     * @param string $message Response message
     * @param mixed $data Response payload
     * @param int $statusCode HTTP status code
     * @return void
     */
    public static function json($success, $message, $data = null, $statusCode = HTTP_OK) {
        // Set HTTP status code
        http_response_code($statusCode);
        
        // Set response headers if not already sent
        if (!self::$headersSent) {
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
            
            self::$headersSent = true;
        }
        
        // Build response array
        $response = [
            'success' => (bool)$success,
            'message' => (string)$message,
            'data' => $data,
            'timestamp' => date('c') // ISO 8601 format
        ];
        
        // Send JSON response and exit
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }
    
    
    /**
     * Send a paginated response
     * Useful for list endpoints that support pagination
     * 
     * @param array $items The paginated items
     * @param int $total Total number of items
     * @param int $page Current page number
     * @param int $perPage Items per page
     * @param string $message Success message
     * @return void
     */
    public static function paginated($items, $total, $page, $perPage, $message = 'Success') {
        $lastPage = ceil($total / $perPage);
        $hasMore = $page < $lastPage;
        
        $data = [
            'items' => $items,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'last_page' => $lastPage,
                'has_more' => $hasMore
            ]
        ];
        
        self::success($message, $data);
    }
    
    
    /**
     * Send raw content response
     * Useful for downloading files, images, etc.
     * 
     * @param string $content The response content
     * @param string $contentType The Content-Type header
     * @param int $statusCode HTTP status code
     * @return void
     */
    public static function raw($content, $contentType = 'text/plain', $statusCode = HTTP_OK) {
        http_response_code($statusCode);
        header("Content-Type: $contentType");
        echo $content;
        exit;
    }
    
    
    /**
     * Redirect to another URL
     * 
     * @param string $url The URL to redirect to
     * @param int $statusCode HTTP redirect code (301, 302, etc.)
     * @return void
     */
    public static function redirect($url, $statusCode = 302) {
        http_response_code($statusCode);
        header("Location: $url");
        exit;
    }
    
    
    /**
     * Download a file
     * 
     * @param string $filePath Path to the file to download
     * @param string $fileName Optional custom file name for download
     * @return void
     */
    public static function download($filePath, $fileName = null) {
        if (!file_exists($filePath)) {
            self::notFound('File not found');
            return;
        }
        
        $fileName = $fileName ?? basename($filePath);
        $fileSize = filesize($filePath);
        $mimeType = mime_content_type($filePath) ?: 'application/octet-stream';
        
        // Send download headers
        header("Content-Type: $mimeType");
        header("Content-Length: $fileSize");
        header("Content-Disposition: attachment; filename=\"$fileName\"");
        header('Cache-Control: no-cache, no-store, must-revalidate');
        
        // Output file content
        readfile($filePath);
        exit;
    }
    
    
    /**
     * Set a custom response header
     * 
     * @param string $name Header name
     * @param string $value Header value
     * @return void
     */
    public static function header($name, $value) {
        if (!self::$headersSent) {
            header("$name: $value");
        }
    }
    
    
    /**
     * Send multiple responses in sequence
     * Useful for streaming multiple JSON objects
     * 
     * @param array $responses Array of response arrays
     * @return void
     */
    public static function stream($responses) {
        header('Content-Type: application/json');
        header('Transfer-Encoding: chunked');
        
        foreach ($responses as $response) {
            echo json_encode($response) . "\n";
            flush();
        }
        
        exit;
    }
}

?>
