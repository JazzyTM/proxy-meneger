<?php
header('Content-Type: application/json');
require_once('../database/db.php');

class DomainAPI {
    private $db;
    
    public function __construct() {
        try {
            $this->db = new MyDB();
        } catch (Exception $e) {
            $this->sendResponse([
                'error' => 'Database connection failed: ' . $e->getMessage()
            ], 500);
        }
        $this->db->exec('CREATE TABLE IF NOT EXISTS domain (
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
        );');
        
        // Add new columns if they don't exist (for existing databases)
        $this->addColumnIfNotExists('tls_version', 'TEXT DEFAULT "TLSv1.2 TLSv1.3"');
        $this->addColumnIfNotExists('http_version', 'TEXT DEFAULT "http2"');
        $this->addColumnIfNotExists('proxy_timeout', 'INTEGER DEFAULT 60');
        $this->addColumnIfNotExists('proxy_buffer_size', 'TEXT DEFAULT "4k"');
        $this->addColumnIfNotExists('client_max_body_size', 'TEXT DEFAULT "10m"');
        $this->addColumnIfNotExists('custom_headers', 'TEXT');
        $this->addColumnIfNotExists('custom_config', 'TEXT');
        $this->addColumnIfNotExists('enable_websocket', 'INTEGER DEFAULT 0');
        $this->addColumnIfNotExists('enable_gzip', 'INTEGER DEFAULT 1');
        $this->addColumnIfNotExists('port', 'INTEGER DEFAULT 80');
    }
    
    private function addColumnIfNotExists($columnName, $columnDef) {
        try {
            $result = $this->db->query("PRAGMA table_info(domain)");
            $columns = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $columns[] = $row['name'];
            }
            if (!in_array($columnName, $columns)) {
                $this->db->exec("ALTER TABLE domain ADD COLUMN $columnName $columnDef");
            }
        } catch (Exception $e) {
            // Column might already exist
        }
    }
    
    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        
        switch($method) {
            case 'GET':
                if(isset($_GET['id'])) {
                    $this->getDomain($_GET['id']);
                } else {
                    $this->listDomains();
                }
                break;
            case 'POST':
                $this->addDomains();
                break;
            case 'PUT':
                $this->updateDomain();
                break;
            case 'DELETE':
                $id = $_GET['id'] ?? null;
                if (!$id) {
                    $input = json_decode(file_get_contents("php://input"), true);
                    $id = $input['id'] ?? null;
                }
                $this->deleteDomain($id);
                break;
            default:
                $this->sendResponse(['error' => 'Method not allowed'], 405);
        }
    }
    
    private function listDomains() {
        $stmt = $this->db->prepare("SELECT * FROM domain ORDER BY id DESC");
        $result = $stmt->execute();
        
        $data = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $row['resolved_ip'] = gethostbyname($row['name']);
            $data[] = $row;
        }
        
        $this->sendResponse(['success' => true, 'data' => $data]);
    }
    
    private function getDomain($id) {
        $stmt = $this->db->prepare("SELECT * FROM domain WHERE id = :id");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        
        $data = $result->fetchArray(SQLITE3_ASSOC);
        if($data) {
            $data['resolved_ip'] = gethostbyname($data['name']);
            $this->sendResponse(['success' => true, 'data' => $data]);
        } else {
            $this->sendResponse(['error' => 'Domain not found'], 404);
        }
    }
    
    private function addDomains() {
        $input = json_decode(file_get_contents('php://input'), true);
        $names = $input['names'] ?? [];
        $ip = $input['ip'] ?? '';
        
        if(empty($names) || empty($ip)) {
            $this->sendResponse(['error' => 'Names and IP are required'], 400);
            return;
        }
        
        $date = date("Y-m-d H:i:s");
        $added = [];
        $skipped = [];
        $errors = [];
        
        foreach($names as $name) {
            $name = trim($name);
            if(empty($name)) continue;
            
            try {
                $stmt = $this->db->prepare("INSERT OR IGNORE INTO domain (
                    name, status, date, ip, cert_status, 
                    tls_version, http_version, proxy_timeout, 
                    proxy_buffer_size, client_max_body_size, 
                    enable_websocket, enable_gzip, port
                ) VALUES (
                    :name, 'new', :date, :ip, 'pending',
                    'TLSv1.2 TLSv1.3', 'http2', 60,
                    '4k', '10m',
                    0, 1, 80
                )");
                
                $stmt->bindValue(':name', $name, SQLITE3_TEXT);
                $stmt->bindValue(':date', $date, SQLITE3_TEXT);
                $stmt->bindValue(':ip', $ip, SQLITE3_TEXT);
                
                if($stmt->execute()) {
                    if($this->db->changes() > 0) {
                        $added[] = $name;
                    } else {
                        $skipped[] = $name;
                    }
                } else {
                    $errors[] = $name . ': ' . $this->db->lastErrorMsg();
                }
            } catch (Exception $e) {
                $errors[] = $name . ': ' . $e->getMessage();
            }
        }
        
        if(count($errors) > 0) {
            $this->sendResponse([
                'success' => false,
                'error' => 'Some domains failed to add',
                'added' => $added,
                'skipped' => $skipped,
                'errors' => $errors
            ], 400);
            return;
        }
        
        $this->sendResponse([
            'success' => true,
            'message' => count($added) . ' domains added, ' . count($skipped) . ' skipped',
            'added' => $added,
            'skipped' => $skipped
        ]);
    }
    
    private function updateDomain() {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = $input['id'] ?? null;
        
        if(!$id) {
            $this->sendResponse(['error' => 'Domain ID required'], 400);
            return;
        }
        
        $updates = [];
        $values = [];
        
        $allowedFields = [
            'ip', 'tls_version', 'http_version', 'proxy_timeout', 
            'proxy_buffer_size', 'client_max_body_size', 'custom_headers',
            'custom_config', 'enable_websocket', 'enable_gzip', 'port'
        ];
        
        foreach($allowedFields as $field) {
            if(isset($input[$field])) {
                $updates[] = "$field = ?";
                $values[] = $input[$field];
            }
        }
        
        if(empty($updates)) {
            $this->sendResponse(['error' => 'No fields to update'], 400);
            return;
        }
        
        $values[] = $id;
        $sql = "UPDATE domain SET " . implode(', ', $updates) . " WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        foreach($values as $i => $value) {
            $stmt->bindValue($i + 1, $value);
        }
        
        if($stmt->execute()) {
            $this->sendResponse(['success' => true, 'message' => 'Domain updated successfully']);
        } else {
            $this->sendResponse(['error' => 'Failed to update domain'], 400);
        }
    }
    
    private function deleteDomain($id) {
        if(!$id) {
            $this->sendResponse(['error' => 'Domain ID required'], 400);
            return;
        }
        
        // Get domain name before deleting
        $stmt = $this->db->prepare("SELECT name FROM domain WHERE id = :id");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $domain = $result->fetchArray(SQLITE3_ASSOC);
        
        if(!$domain) {
            $this->sendResponse(['error' => 'Domain not found'], 404);
            return;
        }
        
        $domainName = $domain['name'];
        
        // Delete from database
        $stmt = $this->db->prepare("DELETE FROM domain WHERE id = :id");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        
        if($stmt->execute() && $this->db->changes() > 0) {
            // Try to delete nginx config file
            $configFile = "/etc/nginx/conf.d/$domainName.conf";
            if(file_exists($configFile)) {
                @unlink($configFile);
            }
            
            $this->sendResponse([
                'success' => true, 
                'message' => 'Domain deleted successfully. Remember to reload Nginx.'
            ]);
        } else {
            $this->sendResponse(['error' => 'Failed to delete domain from database'], 400);
        }
    }
    
    private function sendResponse($data, $code = 200) {
        http_response_code($code);
        echo json_encode($data);
        exit;
    }
}

$api = new DomainAPI();
$api->handleRequest();
