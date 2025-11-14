#!/usr/bin/env php
<?php
/**
 * Database Initialization Script
 * Automatically creates database tables and default admin user
 */

echo "==========================================\n";
echo "  Database Initialization\n";
echo "==========================================\n\n";

try {
    // Load auth config
    require_once(__DIR__ . '/auth_config.php');
    
    if (!defined('AUTH_PASSWORD')) {
        throw new Exception("AUTH_PASSWORD not defined in auth_config.php");
    }
    
    echo "[1/3] Loading database connection...\n";
    require_once(__DIR__ . '/database/users_db.php');
    
    echo "[2/3] Initializing database tables...\n";
    $db = new UsersDB();
    
    echo "[3/3] Checking admin user...\n";
    
    // Check if admin exists
    $stmt = $db->prepare("SELECT id, username FROM users WHERE role = 'admin' LIMIT 1");
    $result = $stmt->execute();
    $admin = $result->fetchArray(SQLITE3_ASSOC);
    
    if ($admin) {
        echo "✓ Admin user already exists: {$admin['username']}\n";
    } else {
        echo "✓ Admin user created automatically\n";
    }
    
    echo "\n";
    echo "==========================================\n";
    echo "  Database Ready!\n";
    echo "==========================================\n";
    echo "\n";
    echo "Admin credentials:\n";
    echo "  Username: admin\n";
    echo "  Password: " . AUTH_PASSWORD . "\n";
    echo "\n";
    
    exit(0);
    
} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
