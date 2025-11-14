<?php
/**
 * Input Validation and Sanitization Class
 * Provides comprehensive validation and sanitization methods
 */
class Validator {
    
    /**
     * Validate and sanitize string
     */
    public static function string($value, $minLength = 0, $maxLength = 255) {
        if (!is_string($value)) {
            return null;
        }
        
        $value = trim($value);
        $value = strip_tags($value);
        $value = htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        $length = mb_strlen($value);
        if ($length < $minLength || $length > $maxLength) {
            return null;
        }
        
        return $value;
    }
    
    /**
     * Validate email
     */
    public static function email($value) {
        $value = filter_var($value, FILTER_SANITIZE_EMAIL);
        if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return strtolower($value);
        }
        return null;
    }
    
    /**
     * Validate integer
     */
    public static function integer($value, $min = null, $max = null) {
        if (!is_numeric($value)) {
            return null;
        }
        
        $value = filter_var($value, FILTER_VALIDATE_INT);
        if ($value === false) {
            return null;
        }
        
        if ($min !== null && $value < $min) {
            return null;
        }
        
        if ($max !== null && $value > $max) {
            return null;
        }
        
        return $value;
    }
    
    /**
     * Validate float
     */
    public static function float($value, $min = null, $max = null) {
        if (!is_numeric($value)) {
            return null;
        }
        
        $value = filter_var($value, FILTER_VALIDATE_FLOAT);
        if ($value === false) {
            return null;
        }
        
        if ($min !== null && $value < $min) {
            return null;
        }
        
        if ($max !== null && $value > $max) {
            return null;
        }
        
        return $value;
    }
    
    /**
     * Validate boolean
     */
    public static function boolean($value) {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }
    
    /**
     * Validate URL
     */
    public static function url($value) {
        $value = filter_var($value, FILTER_SANITIZE_URL);
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }
        return null;
    }
    
    /**
     * Validate IP address
     */
    public static function ip($value) {
        if (filter_var($value, FILTER_VALIDATE_IP)) {
            return $value;
        }
        return null;
    }
    
    /**
     * Validate domain name
     */
    public static function domain($value) {
        $value = strtolower(trim($value));
        
        // Remove protocol
        $value = preg_replace('#^https?://#i', '', $value);
        
        // Remove path
        $value = preg_replace('#/.*$#', '', $value);
        
        // Remove port
        $value = preg_replace('#:\d+$#', '', $value);
        
        // Validate domain format
        if (preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9][a-z0-9-]{0,61}[a-z0-9]$/i', $value)) {
            return $value;
        }
        
        return null;
    }
    
    /**
     * Validate username (alphanumeric + underscore)
     */
    public static function username($value, $minLength = 3, $maxLength = 32) {
        $value = trim($value);
        
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $value)) {
            return null;
        }
        
        $length = strlen($value);
        if ($length < $minLength || $length > $maxLength) {
            return null;
        }
        
        return $value;
    }
    
    /**
     * Validate alphanumeric string
     */
    public static function alphanumeric($value, $minLength = 1, $maxLength = 255) {
        $value = trim($value);
        
        if (!preg_match('/^[a-zA-Z0-9]+$/', $value)) {
            return null;
        }
        
        $length = strlen($value);
        if ($length < $minLength || $length > $maxLength) {
            return null;
        }
        
        return $value;
    }
    
    /**
     * Validate array
     */
    public static function array($value, $allowEmpty = false) {
        if (!is_array($value)) {
            return null;
        }
        
        if (!$allowEmpty && empty($value)) {
            return null;
        }
        
        return $value;
    }
    
    /**
     * Validate JSON
     */
    public static function json($value) {
        if (!is_string($value)) {
            return null;
        }
        
        $decoded = json_decode($value, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }
        
        return $decoded;
    }
    
    /**
     * Validate enum (value must be in allowed list)
     */
    public static function enum($value, array $allowedValues) {
        if (in_array($value, $allowedValues, true)) {
            return $value;
        }
        return null;
    }
    
    /**
     * Validate date
     */
    public static function date($value, $format = 'Y-m-d') {
        $date = DateTime::createFromFormat($format, $value);
        if ($date && $date->format($format) === $value) {
            return $value;
        }
        return null;
    }
    
    /**
     * Validate port number
     */
    public static function port($value) {
        return self::integer($value, 1, 65535);
    }
    
    /**
     * Sanitize SQL LIKE pattern
     */
    public static function likeSanitize($value) {
        // Escape special LIKE characters
        $value = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
        return $value;
    }
    
    /**
     * Validate file path (prevent directory traversal)
     */
    public static function filepath($value) {
        // Remove null bytes
        $value = str_replace("\0", '', $value);
        
        // Check for directory traversal attempts
        if (strpos($value, '..') !== false) {
            return null;
        }
        
        // Check for absolute paths
        if (strpos($value, '/') === 0 || preg_match('/^[a-zA-Z]:/', $value)) {
            return null;
        }
        
        return $value;
    }
    
    /**
     * Validate command (prevent command injection)
     */
    public static function command($value) {
        // Only allow alphanumeric, dash, underscore, and dot
        if (preg_match('/^[a-zA-Z0-9._-]+$/', $value)) {
            return $value;
        }
        return null;
    }
    
    /**
     * Escape shell argument
     */
    public static function escapeShellArg($value) {
        return escapeshellarg($value);
    }
    
    /**
     * Validate regex pattern
     */
    public static function regex($value, $pattern) {
        if (preg_match($pattern, $value)) {
            return $value;
        }
        return null;
    }
    
    /**
     * Batch validation
     */
    public static function validate(array $data, array $rules) {
        $validated = [];
        $errors = [];
        
        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? null;
            
            // Parse rule
            $parts = explode(':', $rule, 2);
            $type = $parts[0];
            $params = isset($parts[1]) ? explode(',', $parts[1]) : [];
            
            // Apply validation
            $result = null;
            switch ($type) {
                case 'string':
                    $result = self::string($value, $params[0] ?? 0, $params[1] ?? 255);
                    break;
                case 'email':
                    $result = self::email($value);
                    break;
                case 'integer':
                    $result = self::integer($value, $params[0] ?? null, $params[1] ?? null);
                    break;
                case 'username':
                    $result = self::username($value, $params[0] ?? 3, $params[1] ?? 32);
                    break;
                case 'domain':
                    $result = self::domain($value);
                    break;
                case 'ip':
                    $result = self::ip($value);
                    break;
                case 'port':
                    $result = self::port($value);
                    break;
                case 'boolean':
                    $result = self::boolean($value);
                    break;
                case 'enum':
                    $result = self::enum($value, $params);
                    break;
                case 'required':
                    if (empty($value)) {
                        $errors[$field] = "Field $field is required";
                    }
                    $result = $value;
                    break;
                default:
                    $result = $value;
            }
            
            if ($result === null && !empty($value)) {
                $errors[$field] = "Invalid value for field $field";
            } else {
                $validated[$field] = $result;
            }
        }
        
        return [
            'valid' => empty($errors),
            'data' => $validated,
            'errors' => $errors
        ];
    }
}
