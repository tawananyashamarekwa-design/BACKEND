<?php

/**
 * ============================================================================
 * AUTHENTICATION HELPER CLASS
 * ============================================================================
 * 
 * This class handles JWT (JSON Web Token) generation, verification, and
 * user authentication for the API. JWTs are used for stateless authentication,
 * allowing clients to maintain user sessions without server-side storage.
 * 
 * JWT Structure:
 * - Header: Contains token type and hashing algorithm
 * - Payload: Contains claims (user data) with expiration time
 * - Signature: HMAC-SHA256 hash for token validation
 * 
 * Format: header.payload.signature (all base64url encoded)
 * 
 * @package EcommerceElectronics\Utils
 * @author School Project
 * @version 1.0.0
 * ============================================================================
 */

class Auth {
    
    /**
     * Current authenticated user data from token
     * Cached after first verification
     * 
     * @var array|null
     */
    private static $currentUser = null;
    
    /**
     * Flag indicating if user data has been fetched
     * 
     * @var bool
     */
    private static $userFetched = false;
    
    
    /**
     * Generate a JWT token with provided payload data
     * 
     * @param array $payload The data to encode in the token
     * @param int $expiresIn Token expiration time in seconds (default: 24 hours)
     * @return string The generated JWT token
     */
    public static function generateToken($payload, $expiresIn = 86400) {
        // Token header
        $header = [
            'typ' => 'JWT',
            'alg' => 'HS256'
        ];
        
        // Add timestamps to payload
        $payload['iat'] = time();              // Issued at time
        $payload['exp'] = time() + $expiresIn; // Expiration time
        
        // Encode header and payload to base64url format
        $headerEncoded = self::base64UrlEncode(json_encode($header));
        $payloadEncoded = self::base64UrlEncode(json_encode($payload));
        
        // Create signature
        $signature = hash_hmac(
            'sha256',
            "$headerEncoded.$payloadEncoded",
            JWT_SECRET,
            true
        );
        $signatureEncoded = self::base64UrlEncode($signature);
        
        // Combine parts and return
        return "$headerEncoded.$payloadEncoded.$signatureEncoded";
    }
    
    
    /**
     * Verify a JWT token and return decoded payload
     * 
     * @param string $token The JWT token to verify
     * @return array|null The decoded payload if valid, null if invalid
     */
    public static function verifyToken($token) {
        // Check if token format is correct
        if (empty($token)) {
            return null;
        }
        
        // Split token into parts
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }
        
        list($headerEncoded, $payloadEncoded, $signatureEncoded) = $parts;
        
        // Verify signature
        $expectedSignature = hash_hmac(
            'sha256',
            "$headerEncoded.$payloadEncoded",
            JWT_SECRET,
            true
        );
        $expectedSignatureEncoded = self::base64UrlEncode($expectedSignature);
        
        if ($signatureEncoded !== $expectedSignatureEncoded) {
            return null; // Invalid signature
        }
        
        // Decode payload
        $payload = json_decode(
            self::base64UrlDecode($payloadEncoded),
            true
        );
        
        if (!$payload) {
            return null;
        }
        
        // Check expiration
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return null; // Token expired
        }
        
        return $payload;
    }
    
    
    /**
     * Get the current authenticated user from the request
     * Extracts token from Authorization header and verifies it
     * 
     * @return array|null Current user data or null if not authenticated
     */
    public static function getCurrentUser() {
        if (self::$userFetched) {
            return self::$currentUser;
        }
        
        // Get token from Authorization header
        $token = self::getTokenFromHeader();
        
        if (!$token) {
            self::$userFetched = true;
            return null;
        }
        
        // Verify and decode token
        $user = self::verifyToken($token);
        
        self::$currentUser = $user;
        self::$userFetched = true;
        
        return $user;
    }
    
    
    /**
     * Check if current user is authenticated
     * 
     * @return bool True if user is authenticated, false otherwise
     */
    public static function isAuthenticated() {
        return self::getCurrentUser() !== null;
    }
    
    
    /**
     * Get current authenticated user ID
     * 
     * @return int|null User ID or null if not authenticated
     */
    public static function getUserId() {
        $user = self::getCurrentUser();
        return $user['id'] ?? null;
    }
    
    
    /**
     * Check if current user has a specific role
     * 
     * @param string $role The role to check
     * @return bool True if user has the role
     */
    public static function hasRole($role) {
        $user = self::getCurrentUser();
        return $user && ($user['role'] ?? null) === $role;
    }
    
    
    /**
     * Check if current user is an admin
     * 
     * @return bool True if user is admin
     */
    public static function isAdmin() {
        return self::hasRole(USER_ROLE_ADMIN);
    }
    
    
    /**
     * Require authentication
     * Terminates with unauthorized response if user is not authenticated
     * 
     * @param string $message Error message to return
     * @return void
     */
    public static function requireAuth($message = 'Authentication required. Please provide a valid token.') {
        if (!self::isAuthenticated()) {
            Response::unauthorized($message);
        }
    }
    
    
    /**
     * Require admin role
     * Terminates with forbidden response if user is not admin
     * 
     * @param string $message Error message to return
     * @return void
     */
    public static function requireAdmin($message = 'Admin access required.') {
        self::requireAuth();
        if (!self::isAdmin()) {
            Response::forbidden($message);
        }
    }
    
    
    /**
     * Extract JWT token from Authorization header
     * 
     * Bearer token format: "Bearer <token>"
     * 
     * @return string|null The token without "Bearer " prefix, or null
     */
    private static function getTokenFromHeader() {
        $headers = getallheaders() ?? [];
        
        // Search for Authorization header (case-insensitive)
        foreach ($headers as $name => $value) {
            if (strtolower($name) === 'authorization') {
                // Check for Bearer token format
                if (preg_match('/^Bearer\s+(.+)$/i', $value, $matches)) {
                    return $matches[1];
                }
            }
        }
        
        return null;
    }
    
    
    /**
     * Base64 URL encode a string
     * Used for JWT encoding (URL-safe variant of base64)
     * 
     * @param string $data The data to encode
     * @return string The base64url encoded string
     */
    private static function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    
    /**
     * Base64 URL decode a string
     * Used for JWT decoding
     * 
     * @param string $data The base64url encoded string
     * @return string The decoded data
     */
    private static function base64UrlDecode($data) {
        $b64 = strtr($data, '-_', '+/');
        switch (strlen($b64) % 4) {
            case 2:
                $b64 .= '==';
                break;
            case 3:
                $b64 .= '=';
                break;
        }
        return base64_decode($b64);
    }
    
    
    /**
     * Hash a password using bcrypt
     * 
     * @param string $password The plain text password
     * @return string The hashed password
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
    }
    
    
    /**
     * Verify a plain text password against a hash
     * 
     * @param string $password The plain text password
     * @param string $hash The password hash
     * @return bool True if password matches hash
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
}

?>
