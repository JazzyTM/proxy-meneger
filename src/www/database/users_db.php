<?php
class UsersDB extends SQLite3 {
    function __construct() {
        $this->open('/db/users.db');
        $this->initTables();
    }
    
    private function initTables() {
        // Users table
        $this->exec('CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            email TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            role TEXT DEFAULT "user",
            created_at TEXT NOT NULL,
            last_login TEXT,
            is_active INTEGER DEFAULT 1
        )');
        
        // Sessions table
        $this->exec('CREATE TABLE IF NOT EXISTS sessions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            session_token TEXT UNIQUE NOT NULL,
            ip_address TEXT,
            user_agent TEXT,
            created_at TEXT NOT NULL,
            expires_at TEXT NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )');
        
        // Activity log table
        $this->exec('CREATE TABLE IF NOT EXISTS activity_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            action TEXT NOT NULL,
            details TEXT,
            ip_address TEXT,
            created_at TEXT NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )');
        
        // Check if admin exists, if not create default admin
        $result = $this->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'");
        $row = $result->fetchArray(SQLITE3_ASSOC);
        
        if ($row['count'] == 0) {
            $this->createDefaultAdmin();
        }
    }
    
    private function createDefaultAdmin() {
        // Load auth_config.php if not already loaded
        if (!defined('AUTH_PASSWORD')) {
            $configPath = __DIR__ . '/../auth_config.php';
            if (file_exists($configPath)) {
                require_once($configPath);
            }
        }
        
        // Create default admin with password from auth_config.php
        if (defined('AUTH_PASSWORD')) {
            $password = password_hash(AUTH_PASSWORD, PASSWORD_BCRYPT);
            $stmt = $this->prepare("INSERT INTO users (username, email, password, role, created_at) VALUES (?, ?, ?, ?, ?)");
            $stmt->bindValue(1, 'admin', SQLITE3_TEXT);
            $stmt->bindValue(2, 'admin@localhost', SQLITE3_TEXT);
            $stmt->bindValue(3, $password, SQLITE3_TEXT);
            $stmt->bindValue(4, 'admin', SQLITE3_TEXT);
            $stmt->bindValue(5, date('Y-m-d H:i:s'), SQLITE3_TEXT);
            $stmt->execute();
            
            // Log admin creation
            error_log("✓ Default admin user created automatically");
        }
    }
}
