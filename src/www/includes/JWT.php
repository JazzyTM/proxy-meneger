<?php
/**
 * JWT (JSON Web Token) Implementation
 * Simple and secure JWT handling without external dependencies
 */
class JWT {
    private static $secret = null;
    
    /**
     * Initialize JWT secret
     */
    private static function getSecret() {
        if (self::$secret === null) {
            // Generate or load secret key
            $secretFile = '/db/.jwt_secret';
            if (file_exists($secretFile)) {
                self::$secret = file_get_contents($secretFile);
            } else {
                self::$secret = bin2hex(random_bytes(32));
                @file_put_contents($secretFile, self::$secret);
                @chmod($secretFile, 0600);
            }
        }
        return self::$secret;
    }
    
    /**
     * Encode data to JWT
     */
    public static function encode($payload, $expiresIn = 86400) {
        $header = [
            'typ' => 'JWT',
            'alg' => 'HS256'
        ];
        
        $payload['iat'] = time();
        $payload['exp'] = time() + $expiresIn;
        
        $base64Header = self::base64UrlEncode(json_encode($header));
        $base64Payload = self::base64UrlEncode(json_encode($payload));
        
        $signature = hash_hmac(
            'sha256',
            $base64Header . '.' . $base64Payload,
            self::getSecret(),
            true
        );
        
        $base64Signature = self::base64UrlEncode($signature);
        
        return $base64Header . '.' . $base64Payload . '.' . $base64Signature;
    }
    
    /**
     * Decode and verify JWT
     */
    public static function decode($token) {
        $parts = explode('.', $token);
        
        if (count($parts) !== 3) {
            return null;
        }
        
        list($base64Header, $base64Payload, $base64Signature) = $parts;
        
        // Verify signature
        $signature = hash_hmac(
            'sha256',
            $base64Header . '.' . $base64Payload,
            self::getSecret(),
            true
        );
        
        $expectedSignature = self::base64UrlEncode($signature);
        
        if (!hash_equals($expectedSignature, $base64Signature)) {
            return null;
        }
        
        // Decode payload
        $payload = json_decode(self::base64UrlDecode($base64Payload), true);
        
        if (!$payload) {
            return null;
        }
        
        // Check expiration
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return null;
        }
        
        return $payload;
    }
    
    /**
     * Base64 URL encode
     */
    private static function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    /**
     * Base64 URL decode
     */
    private static function base64UrlDecode($data) {
        return base64_decode(strtr($data, '-_', '+/'));
    }
    
    /**
     * Extract token from request
     */
    public static function getTokenFromRequest() {
        // Check Authorization header
        $headers = getallheaders();
        if (isset($headers['Authorization'])) {
            $auth = $headers['Authorization'];
            if (preg_match('/Bearer\s+(.*)$/i', $auth, $matches)) {
                return $matches[1];
            }
        }
        
        // Check cookie
        if (isset($_COOKIE['jwt_token'])) {
            return $_COOKIE['jwt_token'];
        }
        
        return null;
    }
}
