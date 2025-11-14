<?php
/**
 * Database Security Layer
 * Provides additional security for database operations
 */
class DatabaseSecurity {
    
    /**
     * Escape SQLite identifier (table/column name)
     */
    public static function escapeIdentifier($identifier) {
        // Remove any non-alphanumeric characters except underscore
        $identifier = preg_replace('/[^a-zA-Z0-9_]/', '', $identifier);
        
        // Wrap in double quotes for SQLite
        return '"' . $identifier . '"';
    }
    
    /**
     * Validate table name
     */
    public static function validateTableName($tableName) {
        $allowedTables = [
            'users',
            'sessions',
            'activity_log',
            'domains',
            'certificates'
        ];
        
        if (in_array($tableName, $allowedTables)) {
            return $tableName;
        }
        
        throw new Exception("Invalid table name: $tableName");
    }
    
    /**
     * Validate column name
     */
    public static function validateColumnName($columnName, $tableName) {
        $allowedColumns = [
            'users' => ['id', 'username', 'email', 'password', 'role', 'created_at', 'last_login', 'is_active'],
            'sessions' => ['id', 'user_id', 'session_token', 'ip_address', 'user_agent', 'created_at', 'expires_at'],
            'activity_log' => ['id', 'user_id', 'action', 'details', 'ip_address', 'created_at'],
            'domains' => ['id', 'user_id', 'name', 'ip', 'port', 'status', 'cert_status', 'date', 'resolved_ip'],
        ];
        
        if (isset($allowedColumns[$tableName]) && in_array($columnName, $allowedColumns[$tableName])) {
            return $columnName;
        }
        
        throw new Exception("Invalid column name: $columnName for table: $tableName");
    }
    
    /**
     * Build safe WHERE clause
     */
    public static function buildWhereClause(array $conditions, $tableName) {
        $whereParts = [];
        $params = [];
        
        foreach ($conditions as $column => $value) {
            $safeColumn = self::validateColumnName($column, $tableName);
            $whereParts[] = self::escapeIdentifier($safeColumn) . ' = :' . $safeColumn;
            $params[':' . $safeColumn] = $value;
        }
        
        return [
            'where' => implode(' AND ', $whereParts),
            'params' => $params
        ];
    }
    
    /**
     * Sanitize ORDER BY clause
     */
    public static function sanitizeOrderBy($orderBy, $tableName) {
        // Parse ORDER BY
        $parts = explode(' ', trim($orderBy));
        $column = $parts[0];
        $direction = isset($parts[1]) ? strtoupper($parts[1]) : 'ASC';
        
        // Validate
        $safeColumn = self::validateColumnName($column, $tableName);
        $safeDirection = in_array($direction, ['ASC', 'DESC']) ? $direction : 'ASC';
        
        return self::escapeIdentifier($safeColumn) . ' ' . $safeDirection;
    }
    
    /**
     * Sanitize LIMIT clause
     */
    public static function sanitizeLimit($limit, $offset = 0) {
        $limit = (int) $limit;
        $offset = (int) $offset;
        
        if ($limit < 0) $limit = 10;
        if ($limit > 1000) $limit = 1000;
        if ($offset < 0) $offset = 0;
        
        return [
            'limit' => $limit,
            'offset' => $offset
        ];
    }
    
    /**
     * Prevent SQL injection in LIKE patterns
     */
    public static function escapeLike($value) {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
    
    /**
     * Log suspicious database activity
     */
    public static function logSuspiciousActivity($query, $params = []) {
        $suspiciousPatterns = [
            '/UNION\s+SELECT/i',
            '/DROP\s+TABLE/i',
            '/DELETE\s+FROM/i',
            '/TRUNCATE/i',
            '/ALTER\s+TABLE/i',
            '/CREATE\s+TABLE/i',
            '/EXEC\s*\(/i',
            '/EXECUTE\s*\(/i',
            '/--/',
            '/\/\*.*\*\//s',
            '/;\s*DROP/i',
            '/;\s*DELETE/i',
        ];
        
        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $query)) {
                Security::logSecurityEvent(
                    'sql_injection_attempt',
                    "Suspicious query detected: " . substr($query, 0, 200),
                    'critical'
                );
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Validate and sanitize SQL query
     */
    public static function validateQuery($query) {
        // Check for suspicious patterns
        if (self::logSuspiciousActivity($query)) {
            throw new Exception("Suspicious SQL query detected");
        }
        
        // Ensure query uses prepared statements (contains placeholders)
        $allowedWithoutParams = ['SELECT', 'INSERT', 'UPDATE', 'DELETE'];
        $hasParams = preg_match('/[:?]/', $query);
        
        if (!$hasParams) {
            $firstWord = strtoupper(trim(explode(' ', $query)[0]));
            if (!in_array($firstWord, $allowedWithoutParams)) {
                Security::logSecurityEvent(
                    'unsafe_query',
                    "Query without parameters: " . substr($query, 0, 100),
                    'warning'
                );
            }
        }
        
        return true;
    }
    
    /**
     * Create safe prepared statement
     */
    public static function prepareSafe($db, $query, $params = []) {
        // Validate query
        self::validateQuery($query);
        
        // Prepare statement
        $stmt = $db->prepare($query);
        
        if (!$stmt) {
            throw new Exception("Failed to prepare statement");
        }
        
        // Bind parameters with type checking
        foreach ($params as $key => $value) {
            $type = SQLITE3_TEXT;
            
            if (is_int($value)) {
                $type = SQLITE3_INTEGER;
            } elseif (is_float($value)) {
                $type = SQLITE3_FLOAT;
            } elseif (is_null($value)) {
                $type = SQLITE3_NULL;
            }
            
            $stmt->bindValue($key, $value, $type);
        }
        
        return $stmt;
    }
    
    /**
     * Execute query with security checks
     */
    public static function executeSafe($db, $query, $params = []) {
        $stmt = self::prepareSafe($db, $query, $params);
        $result = $stmt->execute();
        
        if (!$result) {
            throw new Exception("Query execution failed");
        }
        
        return $result;
    }
    
    /**
     * Prevent NoSQL injection (for JSON fields)
     */
    public static function sanitizeJSON($value) {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                // Re-encode to ensure it's safe
                return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
