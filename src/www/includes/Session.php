<?php
/**
 * Session Management Class
 * Handles secure session operations
 */
class Session {
    private $db;
    
    public function __construct($db = null) {
        $this->db = $db;
    }
    
    /**
     * Create user session with JWT
     */
    public function create($userId, $username, $role) {
        // Generate secure session token
        $sessionToken = Security::generateToken(32);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+7 days'));
        
        // Store in database
        if ($this->db) {
            $stmt = $this->db->prepare("
                INSERT INTO sessions (user_id, session_token, ip_address, user_agent, created_at, expires_at) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bindValue(1, $userId, SQLITE3_INTEGER);
            $stmt->bindValue(2, $sessionToken, SQLITE3_TEXT);
            $stmt->bindValue(3, $_SERVER['REMOTE_ADDR'] ?? '', SQLITE3_TEXT);
            $stmt->bindValue(4, $_SERVER['HTTP_USER_AGENT'] ?? '', SQLITE3_TEXT);
            $stmt->bindValue(5, date('Y-m-d H:i:s'), SQLITE3_TEXT);
            $stmt->bindValue(6, $expiresAt, SQLITE3_TEXT);
            $stmt->execute();
        }
        
        // Set session variables
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $username;
        $_SESSION['role'] = $role;
        $_SESSION['session_token'] = $sessionToken;
        $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? '';
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $_SESSION['last_activity'] = time();
        
        // Generate JWT token
        require_once(__DIR__ . '/JWT.php');
        $jwtPayload = [
            'user_id' => $userId,
            'username' => $username,
            'role' => $role,
            'session_token' => $sessionToken
        ];
        $jwtToken = JWT::encode($jwtPayload, 604800); // 7 days
        
        // Set JWT in HTTP-only cookie
        setcookie(
            'jwt_token',
            $jwtToken,
            [
                'expires' => time() + 604800,
                'path' => '/',
                'domain' => '',
                'secure' => false, // Set to true in production with HTTPS
                'httponly' => true,
                'samesite' => 'Strict'
            ]
        );
        
        // Regenerate session ID for security
        session_regenerate_id(true);
        
        return $sessionToken;
    }
    
    /**
     * Validate session via JWT (preferred) or PHP session
     */
    public function validate() {
        // Try JWT validation first
        require_once(__DIR__ . '/JWT.php');
        $jwtToken = JWT::getTokenFromRequest();
        
        if ($jwtToken) {
            $payload = JWT::decode($jwtToken);
            if ($payload && isset($payload['user_id'])) {
                // Restore session from JWT
                if (!isset($_SESSION['user_id'])) {
                    $_SESSION['user_id'] = $payload['user_id'];
                    $_SESSION['username'] = $payload['username'];
                    $_SESSION['role'] = $payload['role'];
                    $_SESSION['session_token'] = $payload['session_token'];
                    $_SESSION['last_activity'] = time();
                }
                return $this->validateSession();
            }
        }
        
        // Fallback to PHP session validation
        return $this->validateSession();
    }
    
    /**
     * Validate current session (internal)
     */
    private function validateSession() {
        // Check if session exists
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        
        // Check session timeout (30 minutes of inactivity)
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
            $this->destroy();
            return false;
        }
        
        // Update last activity
        $_SESSION['last_activity'] = time();
        
        // Verify IP address hasn't changed (optional, can be strict)
        if (isset($_SESSION['ip_address'])) {
            $currentIP = $_SERVER['REMOTE_ADDR'] ?? '';
            if ($_SESSION['ip_address'] !== $currentIP) {
                Security::logSecurityEvent('session_hijack_attempt', 
                    "IP mismatch: {$_SESSION['ip_address']} vs $currentIP", 'warning');
                // Uncomment to enforce strict IP checking
                // $this->destroy();
                // return false;
            }
        }
        
        // Verify user agent hasn't changed
        if (isset($_SESSION['user_agent'])) {
            $currentUA = $_SERVER['HTTP_USER_AGENT'] ?? '';
            if ($_SESSION['user_agent'] !== $currentUA) {
                Security::logSecurityEvent('session_hijack_attempt', 
                    "User agent mismatch", 'warning');
                // Uncomment to enforce strict UA checking
                // $this->destroy();
                // return false;
            }
        }
        
        // Verify session in database
        if ($this->db && isset($_SESSION['session_token'])) {
            $stmt = $this->db->prepare("
                SELECT s.id, s.expires_at, u.is_active 
                FROM sessions s
                JOIN users u ON s.user_id = u.id
                WHERE s.session_token = :token AND s.user_id = :user_id
            ");
            $stmt->bindValue(':token', $_SESSION['session_token'], SQLITE3_TEXT);
            $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
            $result = $stmt->execute();
            $session = $result->fetchArray(SQLITE3_ASSOC);
            
            if (!$session) {
                $this->destroy();
                return false;
            }
            
            // Check if session expired
            if (strtotime($session['expires_at']) < time()) {
                $this->destroy();
                return false;
            }
            
            // Check if user is still active
            if ($session['is_active'] != 1) {
                $this->destroy();
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Destroy session and JWT
     */
    public function destroy() {
        // Remove from database
        if ($this->db && isset($_SESSION['session_token'])) {
            $stmt = $this->db->prepare("DELETE FROM sessions WHERE session_token = :token");
            $stmt->bindValue(':token', $_SESSION['session_token'], SQLITE3_TEXT);
            $stmt->execute();
        }
        
        // Clear session data
        $_SESSION = [];
        
        // Destroy session cookie
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        
        // Destroy JWT cookie
        if (isset($_COOKIE['jwt_token'])) {
            setcookie('jwt_token', '', time() - 3600, '/', '', false, true);
        }
        
        // Destroy session
        session_destroy();
    }
    
    /**
     * Get current user data
     */
    public function getUser() {
        if (!$this->validate()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['user_id'] ?? null,
            'username' => $_SESSION['username'] ?? null,
            'role' => $_SESSION['role'] ?? null
        ];
    }
    
    /**
     * Check if user has role
     */
    public function hasRole($role) {
        return isset($_SESSION['role']) && $_SESSION['role'] === $role;
    }
    
    /**
     * Clean expired sessions
     */
    public function cleanExpired() {
        if ($this->db) {
            $this->db->exec("DELETE FROM sessions WHERE expires_at < datetime('now')");
        }
    }
}
