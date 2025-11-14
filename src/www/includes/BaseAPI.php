<?php
/**
 * Base API Class
 * Provides common functionality for all API endpoints
 */
abstract class BaseAPI {
    protected $db;
    protected $session;
    
    public function __construct() {
        // Handle CORS preflight
        Security::handleCORSPreflight();
        
        // Configure security
        Security::configureSession();
        Security::setSecurityHeaders(true); // Enable CORS for API
        
        // Set JSON header
        header('Content-Type: application/json');
        
        // Initialize database
        require_once(__DIR__ . '/../database/users_db.php');
        $this->db = new UsersDB();
        
        // Initialize session manager
        $this->session = new Session($this->db);
        
        // Clean expired sessions periodically (1% chance)
        if (rand(1, 100) === 1) {
            $this->session->cleanExpired();
        }
    }
    
    /**
     * Get request method
     */
    protected function getMethod() {
        return $_SERVER['REQUEST_METHOD'];
    }
    
    /**
     * Get JSON input
     */
    protected function getInput() {
        $input = file_get_contents('php://input');
        return json_decode($input, true) ?? [];
    }
    
    /**
     * Get query parameter
     */
    protected function getQuery($key, $default = null) {
        return $_GET[$key] ?? $default;
    }
    
    /**
     * Require authentication
     */
    protected function requireAuth() {
        if (!$this->session->validate()) {
            $this->sendError('Authentication required', 401);
        }
        return $this->session->getUser();
    }
    
    /**
     * Require admin role
     */
    protected function requireAdmin() {
        $user = $this->requireAuth();
        if (!$this->session->hasRole('admin')) {
            $this->sendError('Admin access required', 403);
        }
        return $user;
    }
    
    /**
     * Verify CSRF token (optional - only for state-changing operations)
     */
    protected function verifyCSRF($required = false) {
        $input = $this->getInput();
        $token = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        
        // If token is provided, verify it
        if (!empty($token)) {
            if (!Security::verifyCSRFToken($token)) {
                Security::logSecurityEvent('csrf_validation_failed', 
                    'Invalid CSRF token', 'warning');
                $this->sendError('Invalid CSRF token', 403);
            }
        } elseif ($required) {
            // Token is required but not provided
            $this->sendError('CSRF token required', 403);
        }
    }
    
    /**
     * Check rate limit
     */
    protected function checkRateLimit($identifier, $maxAttempts = 10, $timeWindow = 60) {
        if (!Security::checkRateLimit($identifier, $maxAttempts, $timeWindow)) {
            Security::logSecurityEvent('rate_limit_exceeded', 
                "Identifier: $identifier", 'warning');
            $this->sendError('Rate limit exceeded. Please try again later.', 429);
        }
    }
    
    /**
     * Validate required fields
     */
    protected function validateRequired($data, $fields) {
        $missing = [];
        foreach ($fields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $missing[] = $field;
            }
        }
        
        if (!empty($missing)) {
            $this->sendError('Missing required fields: ' . implode(', ', $missing), 400);
        }
    }
    
    /**
     * Send success response
     */
    protected function sendSuccess($data = [], $code = 200) {
        http_response_code($code);
        echo json_encode(array_merge(['success' => true], $data));
        exit;
    }
    
    /**
     * Send error response
     */
    protected function sendError($message, $code = 400) {
        http_response_code($code);
        echo json_encode([
            'success' => false,
            'error' => $message
        ]);
        exit;
    }
    
    /**
     * Log activity
     */
    protected function logActivity($userId, $action, $details) {
        if (!$userId) return;
        
        require_once(__DIR__ . '/Validator.php');
        
        // Sanitize inputs
        $action = Validator::string($action, 1, 100);
        $details = Validator::string($details, 0, 1000);
        
        if (!$action) return;
        
        $stmt = $this->db->prepare("
            INSERT INTO activity_log (user_id, action, details, ip_address, created_at) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bindValue(1, $userId, SQLITE3_INTEGER);
        $stmt->bindValue(2, $action, SQLITE3_TEXT);
        $stmt->bindValue(3, $details, SQLITE3_TEXT);
        $stmt->bindValue(4, $_SERVER['REMOTE_ADDR'] ?? '', SQLITE3_TEXT);
        $stmt->bindValue(5, date('Y-m-d H:i:s'), SQLITE3_TEXT);
        $stmt->execute();
    }
    
    /**
     * Execute safe database query
     */
    protected function executeSafeQuery($query, $params = []) {
        require_once(__DIR__ . '/DatabaseSecurity.php');
        return DatabaseSecurity::executeSafe($this->db, $query, $params);
    }
    
    /**
     * Prepare safe database statement
     */
    protected function prepareSafeStatement($query, $params = []) {
        require_once(__DIR__ . '/DatabaseSecurity.php');
        return DatabaseSecurity::prepareSafe($this->db, $query, $params);
    }
}
