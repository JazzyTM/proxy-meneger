<?php
header('Content-Type: application/json');
require_once('../database/db.php');

class CertificateAPI {
    private $db;
    private $certsPath = '/certs';
    
    public function __construct() {
        $this->db = new MyDB();
    }
    
    public function handleRequest() {
        $action = $_GET['action'] ?? '';
        
        switch($action) {
            case 'generate':
                $this->generateCertificate();
                break;
            case 'check':
                $this->checkCertificate();
                break;
            case 'status':
                $this->getCertificateStatus();
                break;
            case 'revoke':
                $this->revokeCertificate();
                break;
            case 'delete':
                $this->deleteCertificate();
                break;
            case 'view':
                $this->viewCertificate();
                break;
            default:
                $this->sendResponse(['error' => 'Invalid action'], 400);
        }
    }
    
    private function generateCertificate() {
        $input = json_decode(file_get_contents('php://input'), true);
        $domainId = $input['domain_id'] ?? null;
        
        if(!$domainId) {
            $this->sendResponse(['error' => 'Domain ID required'], 400);
            return;
        }
        
        $stmt = $this->db->prepare("SELECT * FROM domain WHERE id = :id");
        $stmt->bindValue(':id', $domainId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $domain = $result->fetchArray(SQLITE3_ASSOC);
        
        if(!$domain) {
            $this->sendResponse(['error' => 'Domain not found'], 404);
            return;
        }
        
        $domainName = $domain['name'];
        $serverIP = trim(shell_exec('curl -s https://ipinfo.io/ip'));
        $resolvedIP = gethostbyname($domainName);
        
        $logs = [];
        $logs[] = "[" . date('Y-m-d H:i:s') . "] Starting certificate generation for: $domainName";
        $logs[] = "[" . date('Y-m-d H:i:s') . "] Server IP: $serverIP";
        $logs[] = "[" . date('Y-m-d H:i:s') . "] Resolved IP: $resolvedIP";
        
        if($resolvedIP !== $serverIP) {
            $errorMsg = "Domain not pointed to server IP. Expected: $serverIP, Got: $resolvedIP";
            $logs[] = "[" . date('Y-m-d H:i:s') . "] ERROR: $errorMsg";
            
            $this->updateDomainStatus($domainId, 'error', 'dns_mismatch', implode("\n", $logs));
            $this->sendResponse([
                'success' => false,
                'error' => $errorMsg,
                'logs' => $logs
            ]);
            return;
        }
        
        $cmd = "/usr/bin/certbot certonly -n --agree-tos --no-redirect --webroot --register-unsafely-without-email -d $domainName -w /var/www/html --config-dir {$this->certsPath}/certificates --work-dir {$this->certsPath}/certificates --logs-dir {$this->certsPath}/certificates 2>&1";
        
        $logs[] = "[" . date('Y-m-d H:i:s') . "] Executing: certbot certonly for $domainName";
        
        $output = [];
        $status = -1;
        exec($cmd, $output, $status);
        
        $logs[] = "[" . date('Y-m-d H:i:s') . "] Certbot exit code: $status";
        $logs = array_merge($logs, array_map(function($line) {
            return "[" . date('Y-m-d H:i:s') . "] " . $line;
        }, $output));
        
        $fullchainPath = "{$this->certsPath}/certificates/live/$domainName/fullchain.pem";
        $privkeyPath = "{$this->certsPath}/certificates/live/$domainName/privkey.pem";
        
        $certExists = is_link($fullchainPath) && is_link($privkeyPath);
        
        if($certExists) {
            $logs[] = "[" . date('Y-m-d H:i:s') . "] SUCCESS: Certificate files created";
            
            // Fix permissions for nginx to read certificates
            $logs[] = "[" . date('Y-m-d H:i:s') . "] Fixing certificate permissions...";
            exec("chmod -R 755 {$this->certsPath}/certificates/", $permOutput, $permStatus);
            if($permStatus === 0) {
                $logs[] = "[" . date('Y-m-d H:i:s') . "] Permissions fixed successfully";
            }
            
            $this->updateDomainStatus($domainId, 'active', 'valid', implode("\n", $logs));
            
            // Auto-regenerate Nginx config with HTTPS
            $logs[] = "[" . date('Y-m-d H:i:s') . "] Auto-updating Nginx config to enable HTTPS...";
            $this->regenerateNginxConfig($domainId, $logs);
            
            $this->sendResponse([
                'success' => true,
                'message' => 'Certificate generated successfully. Nginx config updated to HTTPS.',
                'logs' => $logs
            ]);
        } else {
            $logs[] = "[" . date('Y-m-d H:i:s') . "] ERROR: Certificate files not found";
            $this->updateDomainStatus($domainId, 'error', 'cert_failed', implode("\n", $logs));
            
            $this->sendResponse([
                'success' => false,
                'error' => 'Certificate generation failed',
                'logs' => $logs
            ]);
        }
    }
    
    private function checkCertificate() {
        $domainId = $_GET['domain_id'] ?? null;
        
        if(!$domainId) {
            $this->sendResponse(['error' => 'Domain ID required'], 400);
            return;
        }
        
        $stmt = $this->db->prepare("SELECT * FROM domain WHERE id = :id");
        $stmt->bindValue(':id', $domainId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $domain = $result->fetchArray(SQLITE3_ASSOC);
        
        if(!$domain) {
            $this->sendResponse(['error' => 'Domain not found'], 404);
            return;
        }
        
        $domainName = $domain['name'];
        $fullchainPath = "{$this->certsPath}/certificates/live/$domainName/fullchain.pem";
        $privkeyPath = "{$this->certsPath}/certificates/live/$domainName/privkey.pem";
        
        $certExists = is_link($fullchainPath) && is_link($privkeyPath);
        
        $info = [
            'exists' => $certExists,
            'fullchain_path' => $fullchainPath,
            'privkey_path' => $privkeyPath
        ];
        
        if($certExists) {
            $certInfo = shell_exec("openssl x509 -in $fullchainPath -noout -dates -subject 2>&1");
            $info['details'] = $certInfo;
        }
        
        $this->sendResponse([
            'success' => true,
            'certificate' => $info
        ]);
    }
    
    private function getCertificateStatus() {
        $stmt = $this->db->prepare("SELECT id, name, cert_status, last_check FROM domain");
        $result = $stmt->execute();
        
        $data = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $data[] = $row;
        }
        
        $this->sendResponse(['success' => true, 'data' => $data]);
    }
    
    private function updateDomainStatus($id, $status, $certStatus, $logs) {
        $stmt = $this->db->prepare("UPDATE domain SET status = :status, cert_status = :cert_status, last_check = :last_check, error_log = :error_log WHERE id = :id");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $stmt->bindValue(':status', $status, SQLITE3_TEXT);
        $stmt->bindValue(':cert_status', $certStatus, SQLITE3_TEXT);
        $stmt->bindValue(':last_check', date('Y-m-d H:i:s'), SQLITE3_TEXT);
        $stmt->bindValue(':error_log', $logs, SQLITE3_TEXT);
        $stmt->execute();
    }
    
    private function regenerateNginxConfig($domainId, &$logs) {
        // Trigger nginx config regeneration via internal call
        $stmt = $this->db->prepare("SELECT * FROM domain WHERE id = :id");
        $stmt->bindValue(':id', $domainId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $domain = $result->fetchArray(SQLITE3_ASSOC);
        
        if (!$domain) {
            $logs[] = "[" . date('Y-m-d H:i:s') . "] ERROR: Domain not found for config update";
            return;
        }
        
        // Call nginx API internally to regenerate config
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "http://localhost/api/nginx.php?action=generate_config");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'domain_id' => $domainId,
            'destination_ip' => $domain['ip']
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode == 200) {
            $logs[] = "[" . date('Y-m-d H:i:s') . "] SUCCESS: Nginx config updated with HTTPS";
        } else {
            $logs[] = "[" . date('Y-m-d H:i:s') . "] WARNING: Failed to auto-update Nginx config";
        }
    }
    
    private function revokeCertificate() {
        $input = json_decode(file_get_contents('php://input'), true);
        $domainId = $input['domain_id'] ?? null;
        
        if(!$domainId) {
            $this->sendResponse(['error' => 'Domain ID required'], 400);
            return;
        }
        
        $stmt = $this->db->prepare("SELECT * FROM domain WHERE id = :id");
        $stmt->bindValue(':id', $domainId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $domain = $result->fetchArray(SQLITE3_ASSOC);
        
        if(!$domain) {
            $this->sendResponse(['error' => 'Domain not found'], 404);
            return;
        }
        
        $domainName = $domain['name'];
        $logs = [];
        $logs[] = "[" . date('Y-m-d H:i:s') . "] Revoking certificate for: $domainName";
        
        // Revoke certificate with certbot
        $command = "certbot revoke --cert-path {$this->certsPath}/certificates/live/$domainName/cert.pem --non-interactive --config-dir {$this->certsPath}/certificates --work-dir {$this->certsPath}/certificates --logs-dir {$this->certsPath}/certificates 2>&1";
        exec($command, $output, $status);
        
        $logs[] = "[" . date('Y-m-d H:i:s') . "] Certbot revoke exit code: $status";
        $logs = array_merge($logs, array_map(function($line) {
            return "[" . date('Y-m-d H:i:s') . "] " . $line;
        }, $output));
        
        if($status === 0) {
            $logs[] = "[" . date('Y-m-d H:i:s') . "] SUCCESS: Certificate revoked";
            $this->updateDomainStatus($domainId, 'active', 'revoked', implode("\n", $logs));
            
            $this->sendResponse([
                'success' => true,
                'message' => 'Certificate revoked successfully',
                'logs' => $logs
            ]);
        } else {
            $logs[] = "[" . date('Y-m-d H:i:s') . "] ERROR: Failed to revoke certificate";
            
            $this->sendResponse([
                'success' => false,
                'error' => 'Failed to revoke certificate',
                'logs' => $logs
            ]);
        }
    }
    
    private function deleteCertificate() {
        $input = json_decode(file_get_contents('php://input'), true);
        $domainId = $input['domain_id'] ?? null;
        
        if(!$domainId) {
            $this->sendResponse(['error' => 'Domain ID required'], 400);
            return;
        }
        
        $stmt = $this->db->prepare("SELECT * FROM domain WHERE id = :id");
        $stmt->bindValue(':id', $domainId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $domain = $result->fetchArray(SQLITE3_ASSOC);
        
        if(!$domain) {
            $this->sendResponse(['error' => 'Domain not found'], 404);
            return;
        }
        
        $domainName = $domain['name'];
        $logs = [];
        $logs[] = "[" . date('Y-m-d H:i:s') . "] Deleting certificate for: $domainName";
        
        // Delete certificate with certbot
        $command = "certbot delete --cert-name $domainName --non-interactive --config-dir {$this->certsPath}/certificates --work-dir {$this->certsPath}/certificates --logs-dir {$this->certsPath}/certificates 2>&1";
        exec($command, $output, $status);
        
        $logs[] = "[" . date('Y-m-d H:i:s') . "] Certbot delete exit code: $status";
        $logs = array_merge($logs, array_map(function($line) {
            return "[" . date('Y-m-d H:i:s') . "] " . $line;
        }, $output));
        
        // Check if certificate actually exists
        $certExists = is_link("{$this->certsPath}/certificates/live/$domainName/fullchain.pem");
        
        if($status === 0 || !$certExists) {
            if(!$certExists) {
                $logs[] = "[" . date('Y-m-d H:i:s') . "] Certificate files not found, updating status only";
            } else {
                $logs[] = "[" . date('Y-m-d H:i:s') . "] SUCCESS: Certificate deleted";
            }
            
            $this->updateDomainStatus($domainId, 'active', 'none', implode("\n", $logs));
            
            // Regenerate config to HTTP-only
            $logs[] = "[" . date('Y-m-d H:i:s') . "] Updating Nginx config to HTTP-only...";
            $this->regenerateNginxConfig($domainId, $logs);
            
            $this->sendResponse([
                'success' => true,
                'message' => 'Certificate deleted successfully. Config updated to HTTP-only.',
                'logs' => $logs
            ]);
        } else {
            $logs[] = "[" . date('Y-m-d H:i:s') . "] ERROR: Failed to delete certificate";
            
            $this->sendResponse([
                'success' => false,
                'error' => 'Failed to delete certificate',
                'logs' => $logs
            ]);
        }
    }
    
    private function viewCertificate() {
        $domainName = $_GET['domain'] ?? null;
        
        if(!$domainName) {
            $this->sendResponse(['error' => 'Domain name required'], 400);
            return;
        }
        
        $fullchainPath = "{$this->certsPath}/certificates/live/$domainName/fullchain.pem";
        
        if(!is_link($fullchainPath)) {
            $this->sendResponse(['error' => 'Certificate not found'], 404);
            return;
        }
        
        // Get certificate details
        $certInfo = shell_exec("openssl x509 -in $fullchainPath -noout -text 2>&1");
        
        $this->sendResponse([
            'success' => true,
            'certificate' => $certInfo
        ]);
    }
    
    private function sendResponse($data, $code = 200) {
        http_response_code($code);
        echo json_encode($data);
        exit;
    }
}

$api = new CertificateAPI();
$api->handleRequest();
