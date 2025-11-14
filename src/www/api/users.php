<?php
require_once(__DIR__ . '/../includes/Security.php');
require_once(__DIR__ . '/../includes/Session.php');
require_once(__DIR__ . '/../includes/BaseAPI.php');
require_once(__DIR__ . '/../includes/Validator.php');

class UsersAPI extends BaseAPI {
    
    public function handleRequest() {
        // Require admin access
        $this->requireAdmin();
        
        $action = $this->getQuery('action', '');
        
        switch($action) {
            case 'list':
                $this->listUsers();
                break;
            case 'activity_log':
                $this->getActivityLog();
                break;
            case 'toggle_status':
                $this->toggleUserStatus();
                break;
            case 'save_settings':
                $this->saveSettings();
                break;
            case 'create':
                $this->createUser();
                break;
            case 'update':
                $this->updateUser();
                break;
            default:
                $this->listUsers();
        }
    }
    
    private function listUsers() {
        $stmt = $this->db->prepare("
            SELECT id, username, email, role, created_at, last_login, is_active 
            FROM users 
            ORDER BY created_at DESC
        ");
        $result = $stmt->execute();
        
        $users = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $users[] = $row;
        }
        
        $this->sendSuccess(['users' => $users]);
    }
    
    private function getActivityLog() {
        $stmt = $this->db->prepare("
            SELECT a.*, u.username 
            FROM activity_log a
            LEFT JOIN users u ON a.user_id = u.id
            ORDER BY a.created_at DESC
            LIMIT 100
        ");
        $result = $stmt->execute();
        
        $logs = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $logs[] = $row;
        }
        
        $this->sendSuccess(['logs' => $logs]);
    }
    
    private function toggleUserStatus() {
        $input = $this->getInput();
        $userId = Validator::integer($input['user_id'] ?? null);
        
        if (!$userId) {
            $this->sendError('Invalid user ID');
        }
        
        // Get current status
        $stmt = $this->db->prepare("SELECT is_active FROM users WHERE id = :id");
        $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $user = $result->fetchArray(SQLITE3_ASSOC);
        
        if (!$user) {
            $this->sendError('User not found', 404);
        }
        
        // Toggle status
        $newStatus = $user['is_active'] ? 0 : 1;
        $stmt = $this->db->prepare("UPDATE users SET is_active = :status WHERE id = :id");
        $stmt->bindValue(':status', $newStatus, SQLITE3_INTEGER);
        $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
        $stmt->execute();
        
        $currentUser = $this->session->getUser();
        $this->logActivity($currentUser['id'], 'toggle_user_status', "User ID $userId status changed to " . ($newStatus ? 'active' : 'inactive'));
        
        $this->sendSuccess(['message' => 'User status updated successfully']);
    }
    
    private function saveSettings() {
        $input = $this->getInput();
        
        // For now, just acknowledge the settings
        // In a real implementation, you would save these to a config file or database
        $this->sendSuccess(['message' => 'Settings saved successfully']);
    }
    
    private function getUser($id) {
        $stmt = $this->db->prepare("SELECT id, username, email, role, created_at, last_login, is_active FROM users WHERE id = :id");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $user = $result->fetchArray(SQLITE3_ASSOC);
        
        if ($user) {
            $this->sendResponse(['success' => true, 'data' => $user]);
        } else {
            $this->sendResponse(['error' => 'User not found'], 404);
        }
    }
    
    private function createUser() {
        $input = $this->getInput();
        
        $username = Validator::username($input['username'] ?? '', 3, 32);
        $email = Validator::email($input['email'] ?? '');
        $password = $input['password'] ?? '';
        $role = Validator::enum($input['role'] ?? 'user', ['admin', 'user']);
        
        if (!$username) {
            $this->sendError('Invalid username. Must be 3-32 characters, alphanumeric and underscore only');
        }
        
        if (!$email) {
            $this->sendError('Invalid email format');
        }
        
        if (empty($password) || strlen($password) < 6) {
            $this->sendError('Password must be at least 6 characters');
        }
        
        if (!$role) {
            $this->sendError('Invalid role');
        }
        
        // Check if username exists
        $stmt = $this->db->prepare("SELECT id FROM users WHERE username = :username");
        $stmt->bindValue(':username', $username, SQLITE3_TEXT);
        if ($stmt->execute()->fetchArray()) {
            $this->sendError('Username already exists');
        }
        
        // Check if email exists
        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->bindValue(':email', $email, SQLITE3_TEXT);
        if ($stmt->execute()->fetchArray()) {
            $this->sendError('Email already exists');
        }
        
        $hashedPassword = Security::hashPassword($password);
        $stmt = $this->db->prepare("INSERT INTO users (username, email, password, role, created_at, is_active) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bindValue(1, $username, SQLITE3_TEXT);
        $stmt->bindValue(2, $email, SQLITE3_TEXT);
        $stmt->bindValue(3, $hashedPassword, SQLITE3_TEXT);
        $stmt->bindValue(4, $role, SQLITE3_TEXT);
        $stmt->bindValue(5, date('Y-m-d H:i:s'), SQLITE3_TEXT);
        $stmt->bindValue(6, 1, SQLITE3_INTEGER);
        
        if ($stmt->execute()) {
            $currentUser = $this->session->getUser();
            $this->logActivity($currentUser['id'], 'create_user', "Created user: $username");
            $this->sendSuccess([
                'message' => 'User created successfully',
                'user_id' => $this->db->lastInsertRowID()
            ]);
        } else {
            $this->sendError('Failed to create user', 500);
        }
    }
    
    private function updateUser() {
        $input = $this->getInput();
        
        $userId = Validator::integer($input['user_id'] ?? null);
        $email = Validator::email($input['email'] ?? '');
        $role = Validator::enum($input['role'] ?? '', ['admin', 'user']);
        $password = $input['password'] ?? '';
        
        if (!$userId) {
            $this->sendError('Invalid user ID');
        }
        
        // Check if user exists
        $stmt = $this->db->prepare("SELECT id FROM users WHERE id = :id");
        $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
        if (!$stmt->execute()->fetchArray()) {
            $this->sendError('User not found', 404);
        }
        
        $updates = [];
        $params = [];
        
        if ($email) {
            // Check if email is already taken by another user
            $stmt = $this->db->prepare("SELECT id FROM users WHERE email = :email AND id != :id");
            $stmt->bindValue(':email', $email, SQLITE3_TEXT);
            $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
            if ($stmt->execute()->fetchArray()) {
                $this->sendError('Email already in use');
            }
            $updates[] = "email = ?";
            $params[] = $email;
        }
        
        if ($role) {
            $updates[] = "role = ?";
            $params[] = $role;
        }
        
        // Update password if provided
        if (!empty($password)) {
            if (strlen($password) < 6) {
                $this->sendError('Password must be at least 6 characters');
            }
            $hashedPassword = Security::hashPassword($password);
            $updates[] = "password = ?";
            $params[] = $hashedPassword;
        }
        
        if (empty($updates)) {
            $this->sendError('No fields to update');
        }
        
        $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
        $params[] = $userId;
        
        $stmt = $this->db->prepare($sql);
        foreach ($params as $i => $param) {
            $stmt->bindValue($i + 1, $param);
        }
        
        if ($stmt->execute()) {
            $currentUser = $this->session->getUser();
            $this->logActivity($currentUser['id'], 'update_user', "Updated user ID: $userId");
            $this->sendSuccess(['message' => 'User updated successfully']);
        } else {
            $this->sendError('Update failed', 500);
        }
    }
}

$api = new UsersAPI();
$api->handleRequest();
