<?php
require_once(__DIR__ . '/../includes/Security.php');
require_once(__DIR__ . '/../includes/Session.php');
require_once(__DIR__ . '/../includes/BaseAPI.php');

class AuthAPI extends BaseAPI {
    
    public function handleRequest() {
        $action = $this->getQuery('action', '');
        
        switch($action) {
            case 'login':
                $this->login();
                break;
            case 'register':
                $this->register();
                break;
            case 'logout':
                $this->logout();
                break;
            case 'check':
                $this->checkAuth();
                break;
            case 'profile':
                $this->getProfile();
                break;
            case 'update_profile':
                $this->updateProfile();
                break;
            case 'change_password':
                $this->changePassword();
                break;
            case 'csrf':
                $this->getCSRFToken();
                break;
            default:
                $this->sendError('Invalid action', 400);
        }
    }
    
    private function getCSRFToken() {
        $token = Security::generateCSRFToken();
        $this->sendSuccess(['csrf_token' => $token]);
    }
    
    private function login() {
        // Rate limiting
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $this->checkRateLimit("login:$ip", 5, 300);
        
        $input = $this->getInput();
        
        // Validate and sanitize input
        require_once(__DIR__ . '/../includes/Validator.php');
        
        $username = Validator::string($input['username'] ?? '', 1, 255);
        $password = $input['password'] ?? '';
        
        if (!$username || empty($password)) {
            $this->sendError('Username and password are required');
        }
        
        // Additional validation for username format
        if (!preg_match('/^[a-zA-Z0-9_@.+-]+$/', $username)) {
            $this->sendError('Invalid username format');
        }
        
        // Find user
        $stmt = $this->db->prepare("
            SELECT * FROM users 
            WHERE (username = :username OR email = :username) AND is_active = 1
        ");
        $stmt->bindValue(':username', $username, SQLITE3_TEXT);
        $result = $stmt->execute();
        $user = $result->fetchArray(SQLITE3_ASSOC);
        
        // Verify credentials
        if (!$user || !Security::verifyPassword($password, $user['password'])) {
            Security::logSecurityEvent('login_failed', "Failed login attempt for: $username", 'warning');
            $this->logActivity(null, 'login_failed', "Failed login attempt for: $username");
            
            // Add delay to prevent timing attacks
            usleep(rand(100000, 500000));
            
            $this->sendError('Invalid credentials', 401);
        }
        
        // Update last login
        $stmt = $this->db->prepare("UPDATE users SET last_login = :time WHERE id = :id");
        $stmt->bindValue(':time', date('Y-m-d H:i:s'), SQLITE3_TEXT);
        $stmt->bindValue(':id', $user['id'], SQLITE3_INTEGER);
        $stmt->execute();
        
        // Create session
        $sessionToken = $this->session->create($user['id'], $user['username'], $user['role']);
        
        // Log successful login
        Security::logSecurityEvent('login_success', "User {$user['username']} logged in", 'info');
        $this->logActivity($user['id'], 'login', 'User logged in successfully');
        
        // Generate JWT token for response
        require_once(__DIR__ . '/../includes/JWT.php');
        $jwtPayload = [
            'user_id' => $user['id'],
            'username' => $user['username'],
            'role' => $user['role']
        ];
        $jwtToken = JWT::encode($jwtPayload, 604800);
        
        // Return user data (without password)
        unset($user['password']);
        $this->sendSuccess([
            'message' => 'Login successful',
            'user' => $user,
            'token' => $jwtToken,
            'session_token' => $sessionToken
        ]);
    }
    
    private function register() {
        // Rate limiting
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $this->checkRateLimit("register:$ip", 3, 3600);
        
        $input = $this->getInput();
        
        // Validate and sanitize input using Validator
        require_once(__DIR__ . '/../includes/Validator.php');
        
        $username = Validator::username($input['username'] ?? '', 3, 32);
        $email = Validator::email($input['email'] ?? '');
        $password = $input['password'] ?? '';
        $confirmPassword = $input['confirm_password'] ?? '';
        
        // Check validation results
        if (!$username) {
            $this->sendError('Invalid username. Must be 3-32 characters, alphanumeric and underscore only');
        }
        
        if (!$email) {
            $this->sendError('Invalid email format');
        }
        
        if (empty($password) || empty($confirmPassword)) {
            $this->sendError('Password and confirmation are required');
        }
        
        // Validate password strength
        $passwordErrors = Security::validatePasswordStrength($password);
        if (!empty($passwordErrors)) {
            $this->sendError(implode('. ', $passwordErrors));
        }
        
        // Check password confirmation
        if ($password !== $confirmPassword) {
            $this->sendError('Passwords do not match');
        }
        
        // Check if username exists
        $stmt = $this->db->prepare("SELECT id FROM users WHERE username = :username");
        $stmt->bindValue(':username', $username, SQLITE3_TEXT);
        $result = $stmt->execute();
        if ($result->fetchArray()) {
            $this->sendError('Username already exists');
        }
        
        // Check if email exists
        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->bindValue(':email', $email, SQLITE3_TEXT);
        $result = $stmt->execute();
        if ($result->fetchArray()) {
            $this->sendError('Email already exists');
        }
        
        // Create user with secure password hash
        $hashedPassword = Security::hashPassword($password);
        $stmt = $this->db->prepare("
            INSERT INTO users (username, email, password, role, created_at, is_active) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bindValue(1, $username, SQLITE3_TEXT);
        $stmt->bindValue(2, $email, SQLITE3_TEXT);
        $stmt->bindValue(3, $hashedPassword, SQLITE3_TEXT);
        $stmt->bindValue(4, 'user', SQLITE3_TEXT);
        $stmt->bindValue(5, date('Y-m-d H:i:s'), SQLITE3_TEXT);
        $stmt->bindValue(6, 1, SQLITE3_INTEGER);
        
        if ($stmt->execute()) {
            $userId = $this->db->lastInsertRowID();
            
            Security::logSecurityEvent('user_registered', "New user: $username", 'info');
            $this->logActivity($userId, 'register', 'New user registered');
            
            $this->sendSuccess([
                'message' => 'Registration successful! You can now login.',
                'user_id' => $userId
            ], 201);
        } else {
            $this->sendError('Registration failed', 500);
        }
    }
    
    private function logout() {
        $user = $this->session->getUser();
        
        if ($user) {
            $this->logActivity($user['id'], 'logout', 'User logged out');
            Security::logSecurityEvent('logout', "User {$user['username']} logged out", 'info');
        }
        
        $this->session->destroy();
        $this->sendSuccess(['message' => 'Logged out successfully']);
    }
    
    private function checkAuth() {
        if (!$this->session->validate()) {
            $this->sendSuccess(['authenticated' => false], 401);
        }
        
        $user = $this->session->getUser();
        
        // Get full user data
        $stmt = $this->db->prepare("
            SELECT id, username, email, role, created_at, last_login 
            FROM users 
            WHERE id = :id AND is_active = 1
        ");
        $stmt->bindValue(':id', $user['id'], SQLITE3_INTEGER);
        $result = $stmt->execute();
        $userData = $result->fetchArray(SQLITE3_ASSOC);
        
        if ($userData) {
            $this->sendSuccess([
                'authenticated' => true,
                'user' => $userData
            ]);
        } else {
            $this->session->destroy();
            $this->sendSuccess(['authenticated' => false], 401);
        }
    }
    
    private function getProfile() {
        $user = $this->requireAuth();
        
        $stmt = $this->db->prepare("
            SELECT id, username, email, role, created_at, last_login 
            FROM users 
            WHERE id = :id
        ");
        $stmt->bindValue(':id', $user['id'], SQLITE3_INTEGER);
        $result = $stmt->execute();
        $userData = $result->fetchArray(SQLITE3_ASSOC);
        
        if ($userData) {
            $this->sendSuccess(['user' => $userData]);
        } else {
            $this->sendError('User not found', 404);
        }
    }
    
    private function updateProfile() {
        $user = $this->requireAuth();
        $input = $this->getInput();
        
        $email = Security::sanitizeInput($input['email'] ?? '');
        
        if (!Security::validateEmail($email)) {
            $this->sendError('Invalid email format');
        }
        
        // Check if email is already taken by another user
        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = :email AND id != :id");
        $stmt->bindValue(':email', $email, SQLITE3_TEXT);
        $stmt->bindValue(':id', $user['id'], SQLITE3_INTEGER);
        $result = $stmt->execute();
        if ($result->fetchArray()) {
            $this->sendError('Email already in use');
        }
        
        $stmt = $this->db->prepare("UPDATE users SET email = :email WHERE id = :id");
        $stmt->bindValue(':email', $email, SQLITE3_TEXT);
        $stmt->bindValue(':id', $user['id'], SQLITE3_INTEGER);
        
        if ($stmt->execute()) {
            $this->logActivity($user['id'], 'update_profile', 'Profile updated');
            $this->sendSuccess(['message' => 'Profile updated successfully']);
        } else {
            $this->sendError('Update failed', 500);
        }
    }
    
    private function changePassword() {
        $user = $this->requireAuth();
        $input = $this->getInput();
        
        $currentPassword = $input['current_password'] ?? '';
        $newPassword = $input['new_password'] ?? '';
        $confirmPassword = $input['confirm_password'] ?? '';
        
        $this->validateRequired([
            'current_password' => $currentPassword,
            'new_password' => $newPassword,
            'confirm_password' => $confirmPassword
        ], ['current_password', 'new_password', 'confirm_password']);
        
        // Validate new password strength
        $passwordErrors = Security::validatePasswordStrength($newPassword);
        if (!empty($passwordErrors)) {
            $this->sendError(implode('. ', $passwordErrors));
        }
        
        if ($newPassword !== $confirmPassword) {
            $this->sendError('Passwords do not match');
        }
        
        // Verify current password
        $stmt = $this->db->prepare("SELECT password FROM users WHERE id = :id");
        $stmt->bindValue(':id', $user['id'], SQLITE3_INTEGER);
        $result = $stmt->execute();
        $userData = $result->fetchArray(SQLITE3_ASSOC);
        
        if (!$userData || !Security::verifyPassword($currentPassword, $userData['password'])) {
            $this->sendError('Current password is incorrect');
        }
        
        // Update password
        $hashedPassword = Security::hashPassword($newPassword);
        $stmt = $this->db->prepare("UPDATE users SET password = :password WHERE id = :id");
        $stmt->bindValue(':password', $hashedPassword, SQLITE3_TEXT);
        $stmt->bindValue(':id', $user['id'], SQLITE3_INTEGER);
        
        if ($stmt->execute()) {
            // Invalidate all other sessions
            $stmt = $this->db->prepare("DELETE FROM sessions WHERE user_id = :id AND session_token != :token");
            $stmt->bindValue(':id', $user['id'], SQLITE3_INTEGER);
            $stmt->bindValue(':token', $_SESSION['session_token'] ?? '', SQLITE3_TEXT);
            $stmt->execute();
            
            Security::logSecurityEvent('password_changed', "User {$user['username']} changed password", 'info');
            $this->logActivity($user['id'], 'change_password', 'Password changed successfully');
            
            $this->sendSuccess(['message' => 'Password changed successfully. Other sessions have been logged out.']);
        } else {
            $this->sendError('Password change failed', 500);
        }
    }
}

$api = new AuthAPI();
$api->handleRequest();
