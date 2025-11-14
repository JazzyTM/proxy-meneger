<?php
// Test API endpoint to check database and permissions
header('Content-Type: application/json');

try {
    require_once('../database/db.php');
    
    $db = new MyDB();
    
    // Test database connection
    $result = $db->query("SELECT name FROM sqlite_master WHERE type='table'");
    $tables = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $tables[] = $row['name'];
    }
    
    // Test domain table structure
    $result = $db->query("PRAGMA table_info(domain)");
    $columns = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $columns[] = $row;
    }
    
    // Count domains
    $result = $db->query("SELECT COUNT(*) as count FROM domain");
    $count = $result->fetchArray(SQLITE3_ASSOC)['count'];
    
    echo json_encode([
        'success' => true,
        'database' => '/db/db.db',
        'tables' => $tables,
        'domain_columns' => $columns,
        'domain_count' => $count,
        'php_version' => phpversion(),
        'sqlite_version' => SQLite3::version()['versionString']
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT);
}
