<?php
// Database migration script - run this once to update old database structure
header('Content-Type: application/json');
require_once('../database/db.php');

try {
    $db = new MyDB();
    
    $logs = [];
    $logs[] = "Starting database migration...";
    
    // Check if domain table exists
    $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='domain'");
    $tableExists = $result->fetchArray();
    
    if (!$tableExists) {
        $logs[] = "Domain table doesn't exist, creating new one...";
        $db->exec('CREATE TABLE domain (
            id INTEGER PRIMARY KEY AUTOINCREMENT, 
            name TEXT UNIQUE, 
            status TEXT, 
            date TEXT,
            ip TEXT,
            cert_status TEXT DEFAULT "pending",
            last_check TEXT,
            error_log TEXT,
            tls_version TEXT DEFAULT "TLSv1.2 TLSv1.3",
            http_version TEXT DEFAULT "http2",
            proxy_timeout INTEGER DEFAULT 60,
            proxy_buffer_size TEXT DEFAULT "4k",
            client_max_body_size TEXT DEFAULT "10m",
            custom_headers TEXT,
            custom_config TEXT,
            enable_websocket INTEGER DEFAULT 0,
            enable_gzip INTEGER DEFAULT 1,
            port INTEGER DEFAULT 80
        )');
        $logs[] = "✓ Domain table created successfully";
    } else {
        $logs[] = "Domain table exists, checking columns...";
        
        // Get current columns
        $result = $db->query("PRAGMA table_info(domain)");
        $existingColumns = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $existingColumns[] = $row['name'];
        }
        
        $logs[] = "Existing columns: " . implode(', ', $existingColumns);
        
        // Define required columns with their definitions
        $requiredColumns = [
            'cert_status' => 'TEXT DEFAULT "pending"',
            'last_check' => 'TEXT',
            'error_log' => 'TEXT',
            'tls_version' => 'TEXT DEFAULT "TLSv1.2 TLSv1.3"',
            'http_version' => 'TEXT DEFAULT "http2"',
            'proxy_timeout' => 'INTEGER DEFAULT 60',
            'proxy_buffer_size' => 'TEXT DEFAULT "4k"',
            'client_max_body_size' => 'TEXT DEFAULT "10m"',
            'custom_headers' => 'TEXT',
            'custom_config' => 'TEXT',
            'enable_websocket' => 'INTEGER DEFAULT 0',
            'enable_gzip' => 'INTEGER DEFAULT 1',
            'port' => 'INTEGER DEFAULT 80'
        ];
        
        // Add missing columns
        foreach ($requiredColumns as $column => $definition) {
            if (!in_array($column, $existingColumns)) {
                try {
                    $db->exec("ALTER TABLE domain ADD COLUMN $column $definition");
                    $logs[] = "✓ Added column: $column";
                } catch (Exception $e) {
                    $logs[] = "✗ Failed to add column $column: " . $e->getMessage();
                }
            }
        }
    }
    
    // Count domains
    $result = $db->query("SELECT COUNT(*) as count FROM domain");
    $count = $result->fetchArray(SQLITE3_ASSOC)['count'];
    $logs[] = "Total domains in database: $count";
    
    // Option to clear all domains (commented out by default)
    if (isset($_GET['clear']) && $_GET['clear'] === 'yes') {
        $db->exec("DELETE FROM domain");
        $logs[] = "⚠ All domains cleared from database";
        $count = 0;
    }
    
    // Get all domains
    $result = $db->query("SELECT id, name, status, cert_status FROM domain");
    $domains = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $domains[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Migration completed successfully',
        'logs' => $logs,
        'domain_count' => $count,
        'domains' => $domains,
        'note' => 'To clear all domains, add ?clear=yes to the URL'
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT);
}
