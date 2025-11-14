<?php
/**
 * Security Class
 * Handles all security-related operations
 */
class Security {
    
    /**
     * Generate CSRF token
     */
    public static function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Verify CSRF token
     */
    public static function verifyCSRFToken($token) {
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Sanitize input
     */
    public static function sanitizeInput($data) {
        if (is_array($data)) {
            return array_map([self::class, 'sanitizeInput'], $data);
        }
        return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Validate email
     */
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Validate domain name
     */
    public static function validateDomain($domain) {
        $domain = strtolower(trim($domain));
        // Remove protocol if present
        $domain = preg_replace('#^https?://#', '', $domain);
        // Remove trailing slash
        $domain = rtrim($domain, '/');
        // Validate domain format
        return preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9][a-z0-9-]{0,61}[a-z0-9]$/i', $domain);
    }
    
    /**
     * Validate IP address
     */
    public static function validateIP($ip) {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }
    
    /**
     * Rate limiting
     */
    public static function checkRateLimit($identifier, $maxAttempts = 5, $timeWindow = 300) {
        if (!isset($_SESSION['rate_limit'])) {
            $_SESSION['rate_limit'] = [];
        }
        
        $now = time();
        $key = md5($identifier);
        
        // Clean old entries
        if (isset($_SESSION['rate_limit'][$key])) {
            $_SESSION['rate_limit'][$key] = array_filter(
                $_SESSION['rate_limit'][$key],
                function($timestamp) use ($now, $timeWindow) {
                    return ($now - $timestamp) < $timeWindow;
                }
            );
        } else {
            $_SESSION['rate_limit'][$key] = [];
        }
        
        // Check if limit exceeded
        if (count($_SESSION['rate_limit'][$key]) >= $maxAttempts) {
            return false;
        }
        
        // Add current attempt
        $_SESSION['rate_limit'][$key][] = $now;
        return true;
    }
    
    /**
     * Hash password securely
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 3
        ]);
    }
    
    /**
     * Verify password
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Generate secure random token
     */
    public static function generateToken($length = 32) {
        return bin2hex(random_bytes($length));
    }
    
    /**
     * Secure session configuration
     */
    public static function configureSession() {
        // Prevent session fixation
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
            ini_set('session.cookie_samesite', 'Strict');
            ini_set('session.use_strict_mode', 1);
            ini_set('session.use_only_cookies', 1);
            ini_set('session.cookie_lifetime', 0);
            session_start();
        }
        
        // Regenerate session ID periodically
        if (!isset($_SESSION['created'])) {
            $_SESSION['created'] = time();
        } else if (time() - $_SESSION['created'] > 1800) {
            session_regenerate_id(true);
            $_SESSION['created'] = time();
        }
    }
    
    /**
     * Set security headers with CORS
     */
    public static function setSecurityHeaders($allowCORS = false) {
        // Security headers
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
        
        // CORS headers (if enabled)
        if ($allowCORS) {
            $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
            $allowedOrigins = self::getAllowedOrigins();
            
            if (in_array($origin, $allowedOrigins)) {
                header("Access-Control-Allow-Origin: $origin");
                header('Access-Control-Allow-Credentials: true');
                header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
                header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
                header('Access-Control-Max-Age: 86400');
            }
        }
        
        // Uncomment for production with HTTPS
        // header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    
    /**
     * Get allowed CORS origins
     */
    private static function getAllowedOrigins() {
        $currentHost = $_SERVER['HTTP_HOST'] ?? '';
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        
        return [
            "$protocol://$currentHost",
            "http://$currentHost",
            "https://$currentHost",
            'http://localhost:8080',
            'http://127.0.0.1:8080'
        ];
    }
    
    /**
     * Handle CORS preflight request
     */
    public static function handleCORSPreflight() {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            self::setSecurityHeaders(true);
            http_response_code(204);
            exit;
        }
    }
    
    /**
     * Validate password strength
     */
    public static function validatePasswordStrength($password) {
        $errors = [];
        
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter';
        }
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter';
        }
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number';
        }
        
        return $errors;
    }
    
    /**
     * Log security event
     */
    public static function logSecurityEvent($event, $details, $severity = 'info') {
        $logFile = '/var/log/security.log';
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $logEntry = sprintf(
            "[%s] [%s] [%s] %s - %s - UA: %s\n",
            $timestamp,
            strtoupper($severity),
            $ip,
            $event,
            $details,
            $userAgent
        );
        
        // Try to log, but don't fail if we can't
        @error_log($logEntry, 3, $logFile);
    }
    
    /**
     * Check if request is from allowed origin
     */
    public static function validateOrigin() {
        // Get current host
        $currentHost = $_SERVER['HTTP_HOST'] ?? '';
        
        // Get origin from request
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        
        // If no origin header, check referer
        if (empty($origin) && isset($_SERVER['HTTP_REFERER'])) {
            $origin = $_SERVER['HTTP_REFERER'];
        }
        
        // If still no origin, allow (same-origin request)
        if (empty($origin)) {
            return true;
        }
        
        // Parse origin host
        $originHost = parse_url($origin, PHP_URL_HOST);
        
        // Allow if origin matches current host
        if ($originHost === $currentHost) {
            return true;
        }
        
        // Allow localhost variations
        $allowedHosts = ['localhost', '127.0.0.1', '::1'];
        if (in_array($originHost, $allowedHosts) || in_array($currentHost, $allowedHosts)) {
            return true;
        }
        
        return false;
    }
}
