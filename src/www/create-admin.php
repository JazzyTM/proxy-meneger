#!/usr/bin/env php
<?php
/**
 * Admin User Management Script
 * Usage: 
 *   docker compose exec webui php /var/www/html/create-admin.php [password]
 *   docker compose exec webui php /var/www/html/create-admin.php --reset
 */

require_once(__DIR__ . '/database/users_db.php');

$username = 'admin';
$email = 'admin@localhost';

// Parse arguments
$resetPassword = in_array('--reset', $argv);
$password = null;

if ($resetPassword) {
    // Generate new random password
    $password = bin2hex(random_bytes(8));
} elseif (isset($argv[1]) && $argv[1] !== '--reset') {
    // Use provided password
    $password = $argv[1];
} else {
    // Generate random password
    $password = bin2hex(random_bytes(8));
}

try {
    $db = new UsersDB();
    
    echo "==========================================\n";
    echo "  Admin User Management\n";
    echo "==========================================\n\n";
    
    // Check if admin exists
    $stmt = $db->prepare("SELECT id, username, email, created_at FROM users WHERE username = :username");
    $stmt->bindValue(':username', $username, SQLITE3_TEXT);
    $result = $stmt->execute();
    $exists = $result->fetchArray(SQLITE3_ASSOC);
    
    if ($exists) {
        // Update existing admin
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $db->prepare("UPDATE users SET password = :password, email = :email, is_active = 1 WHERE username = :username");
        $stmt->bindValue(':password', $hashedPassword, SQLITE3_TEXT);
        $stmt->bindValue(':email', $email, SQLITE3_TEXT);
        $stmt->bindValue(':username', $username, SQLITE3_TEXT);
        $stmt->execute();
        
        echo "✓ Admin password updated\n";
        echo "  User ID: {$exists['id']}\n";
        echo "  Created: {$exists['created_at']}\n";
    } else {
        // Create new admin
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $db->prepare("INSERT INTO users (username, email, password, role, created_at, is_active) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bindValue(1, $username, SQLITE3_TEXT);
        $stmt->bindValue(2, $email, SQLITE3_TEXT);
        $stmt->bindValue(3, $hashedPassword, SQLITE3_TEXT);
        $stmt->bindValue(4, 'admin', SQLITE3_TEXT);
        $stmt->bindValue(5, date('Y-m-d H:i:s'), SQLITE3_TEXT);
        $stmt->bindValue(6, 1, SQLITE3_INTEGER);
        $stmt->execute();
        
        $userId = $db->lastInsertRowID();
        echo "✓ Admin user created\n";
        echo "  User ID: $userId\n";
    }
    
    echo "\n";
    echo "==========================================\n";
    echo "  ADMIN CREDENTIALS\n";
    echo "==========================================\n";
    echo "\n";
    echo "  Username: $username\n";
    echo "  Email:    $email\n";
    echo "  Password: $password\n";
    echo "\n";
    echo "==========================================\n";
    echo "\n";
    echo "Save these credentials securely!\n";
    echo "\n";
    
    exit(0);
    
} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
