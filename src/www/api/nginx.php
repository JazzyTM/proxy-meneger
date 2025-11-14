<?php
header('Content-Type: application/json');
require_once('../database/db.php');

class NginxAPI {
    private $db;
    private $certsPath = '/certs';
    private $nginxConfigPath = '/nginx-configs';
    
    public function __construct() {
        $this->db = new MyDB();
    }
    
    public function handleRequest() {
        $action = $_GET['action'] ?? '';
        
        switch($action) {
            case 'generate_config':
                $this->generateConfig();
                break;
            case 'reload':
                $this->reloadNginx();
                break;
            case 'test':
                $this->testNginx();
                break;
            case 'status':
                $this->getNginxStatus();
                break;
            default:
                $this->sendResponse(['error' => 'Invalid action'], 400);
        }
    }
    
    private function generateConfig() {
        session_start();
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            $this->sendResponse(['error' => 'Unauthorized'], 401);
            return;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $domainId = $input['domain_id'] ?? null;
        $destinationIP = $input['destination_ip'] ?? null;
        
        if(!$domainId) {
            $this->sendResponse(['error' => 'Domain ID required'], 400);
            return;
        }
        
        $stmt = $this->db->prepare("SELECT * FROM domain WHERE id = :id AND user_id = :user_id");
        $stmt->bindValue(':id', $domainId, SQLITE3_INTEGER);
        $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $domain = $result->fetchArray(SQLITE3_ASSOC);
        
        if(!$domain) {
            $this->sendResponse(['error' => 'Domain not found or access denied'], 404);
            return;
        }
        
        // ALWAYS use IP from database - ignore input
        $destIP = $domain['ip'];
        
        if(!$destIP) {
            $this->sendResponse(['error' => 'Destination IP not set in database. Please update domain settings first.'], 400);
            return;
        }
        
        $domainName = $domain['name'];
        $logs = [];
        $logs[] = "[" . date('Y-m-d H:i:s') . "] Generating Nginx config for: $domainName";
        
        // Check if SSL certificate exists
        $fullchainPath = "{$this->certsPath}/certificates/live/$domainName/fullchain.pem";
        $privkeyPath = "{$this->certsPath}/certificates/live/$domainName/privkey.pem";
        $hasCertificate = is_link($fullchainPath) && is_link($privkeyPath);
        
        // Choose template based on certificate availability
        if ($hasCertificate) {
            $logs[] = "[" . date('Y-m-d H:i:s') . "] SSL certificate found - generating HTTPS config";
            $template = file_get_contents('/nginx-templates/vhost-template.txt');
        } else {
            $logs[] = "[" . date('Y-m-d H:i:s') . "] No SSL certificate - generating HTTP-only config";
            // Check if HTTP-only template exists, otherwise use main template
            if (file_exists('/nginx-templates/vhost-http-only.txt')) {
                $template = file_get_contents('/nginx-templates/vhost-http-only.txt');
            } else {
                $template = file_get_contents('/nginx-templates/vhost-template.txt');
                // Remove SSL-specific directives
                $template = preg_replace('/listen 443.*?;/s', '', $template);
                $template = preg_replace('/ssl_certificate.*?;/s', '', $template);
                $template = preg_replace('/ssl_certificate_key.*?;/s', '', $template);
                $template = preg_replace('/ssl_protocols.*?;/s', '', $template);
                $template = preg_replace('/HTTP_VERSION_DIRECTIVE/s', '', $template);
            }
        }
        
        // Apply domain-specific settings
        $includeWww = $domain['include_www'] ?? 0;
        $serverName = $includeWww ? "$domainName www.$domainName" : $domainName;
        $config = str_replace('DOMAIN', $serverName, $template);
        $config = str_replace('DESTINATIONIP', $destIP, $config);
        $config = str_replace('DESTINATION_PORT', $domain['port'] ?? 80, $config);
        
        if($includeWww) {
            $logs[] = "[" . date('Y-m-d H:i:s') . "] Including www subdomain";
        }
        
        // TLS version (only for HTTPS configs)
        if ($hasCertificate) {
            $tlsVersion = $domain['tls_version'] ?? 'TLSv1.2 TLSv1.3';
            $config = str_replace('TLS_VERSION', $tlsVersion, $config);
            $logs[] = "[" . date('Y-m-d H:i:s') . "] TLS Version: $tlsVersion";
        }
        
        // HTTP version (new Nginx 1.25+ syntax)
        $httpVersion = $domain['http_version'] ?? 'http2';
        $httpVersionDirective = $httpVersion === 'http2' ? 'http2 on;' : '';
        $config = str_replace('HTTP_VERSION_DIRECTIVE', $httpVersionDirective, $config);
        $logs[] = "[" . date('Y-m-d H:i:s') . "] HTTP Version: $httpVersion";
        
        // Proxy timeout
        $proxyTimeout = $domain['proxy_timeout'] ?? 60;
        $config = str_replace('PROXY_TIMEOUTs', $proxyTimeout . 's', $config);
        
        // Proxy buffer size
        $proxyBufferSize = $domain['proxy_buffer_size'] ?? '4k';
        $config = str_replace('PROXY_BUFFER_SIZE', $proxyBufferSize, $config);
        
        // Client max body size
        $clientMaxBodySize = $domain['client_max_body_size'] ?? '10m';
        $config = str_replace('CLIENT_MAX_BODY_SIZE', $clientMaxBodySize, $config);
        
        // Gzip configuration
        $enableGzip = $domain['enable_gzip'] ?? 1;
        $gzipConfig = $enableGzip ? 
            "gzip on;\n    gzip_vary on;\n    gzip_proxied any;\n    gzip_comp_level 6;\n    gzip_types text/plain text/css text/xml text/javascript application/json application/javascript application/xml+rss application/rss+xml font/truetype font/opentype application/vnd.ms-fontobject image/svg+xml;" :
            "gzip off;";
        $config = str_replace('GZIP_CONFIG', $gzipConfig, $config);
        
        // WebSocket support
        $enableWebsocket = $domain['enable_websocket'] ?? 0;
        $websocketConfig = $enableWebsocket ?
            "proxy_http_version 1.1;\n        proxy_set_header Upgrade \$http_upgrade;\n        proxy_set_header Connection \"upgrade\";" :
            "";
        $config = str_replace('WEBSOCKET_CONFIG', $websocketConfig, $config);
        
        // Custom headers
        $customHeaders = $domain['custom_headers'] ?? '';
        if(!empty($customHeaders)) {
            $headers = explode("\n", $customHeaders);
            $headerConfig = "";
            foreach($headers as $header) {
                $header = trim($header);
                if(!empty($header)) {
                    $headerConfig .= "add_header $header;\n        ";
                }
            }
            $config = str_replace('CUSTOM_HEADERS', $headerConfig, $config);
            $logs[] = "[" . date('Y-m-d H:i:s') . "] Custom headers applied";
        } else {
            $config = str_replace('CUSTOM_HEADERS', '', $config);
        }
        
        // Cache assets
        $enableCache = $domain['enable_cache'] ?? 0;
        $port = $domain['port'] ?? 80;
        $cacheConfig = $enableCache ?
            "location ~* \\.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {\n        expires 30d;\n        add_header Cache-Control \"public, immutable\";\n        proxy_pass http://{$destIP}:{$port};\n        proxy_set_header Host \$host;\n    }\n    " :
            "";
        $config = str_replace('CACHE_CONFIG', $cacheConfig, $config);
        if($enableCache) {
            $logs[] = "[" . date('Y-m-d H:i:s') . "] Asset caching enabled";
        }
        
        // Block common exploits
        $blockExploits = $domain['block_exploits'] ?? 1;
        $exploitsConfig = $blockExploits ?
            "# Block common exploits\n    if (\$request_uri ~* \"(\\.\\.|\\.git|wp-admin|wp-login|phpmyadmin|eval\\(|base64_decode)\") {\n        return 403;\n    }\n    " :
            "";
        $config = str_replace('BLOCK_EXPLOITS', $exploitsConfig, $config);
        if($blockExploits) {
            $logs[] = "[" . date('Y-m-d H:i:s') . "] Exploit blocking enabled";
        }
        
        // Custom configuration
        $customConfig = $domain['custom_config'] ?? '';
        $config = str_replace('CUSTOM_CONFIG', $customConfig, $config);
        if(!empty($customConfig)) {
            $logs[] = "[" . date('Y-m-d H:i:s') . "] Custom configuration applied";
        }
        
        // Save configuration file
        $configFile = "{$this->nginxConfigPath}/$domainName.conf";
        file_put_contents($configFile, $config);
        
        $logs[] = "[" . date('Y-m-d H:i:s') . "] Config file created: $configFile";
        $logs[] = "[" . date('Y-m-d H:i:s') . "] Destination: $destIP:" . ($domain['port'] ?? 80);
        
        $this->sendResponse([
            'success' => true,
            'message' => 'Nginx config generated successfully',
            'config_file' => $configFile,
            'logs' => $logs
        ]);
    }
    
    private function reloadNginx() {
        $logs = [];
        $logs[] = "[" . date('Y-m-d H:i:s') . "] Reloading Nginx...";
        
        // Execute nginx reload directly in container
        $cmd = "docker exec reverse-proxy nginx -s reload 2>&1";
        $output = [];
        $status = -1;
        exec($cmd, $output, $status);
        
        $logs[] = "[" . date('Y-m-d H:i:s') . "] Exit code: $status";
        $logs = array_merge($logs, array_map(function($line) {
            return "[" . date('Y-m-d H:i:s') . "] " . $line;
        }, $output));
        
        if($status === 0) {
            $logs[] = "[" . date('Y-m-d H:i:s') . "] SUCCESS: Nginx reloaded";
            $this->sendResponse([
                'success' => true,
                'message' => 'Nginx reloaded successfully',
                'logs' => $logs
            ]);
        } else {
            $logs[] = "[" . date('Y-m-d H:i:s') . "] ERROR: Nginx reload failed";
            $this->sendResponse([
                'success' => false,
                'error' => 'Nginx reload failed',
                'logs' => $logs
            ]);
        }
    }
    
    private function testNginx() {
        $logs = [];
        $logs[] = "[" . date('Y-m-d H:i:s') . "] Testing Nginx configuration...";
        
        // Execute nginx test directly in container
        $cmd = "docker exec reverse-proxy nginx -t 2>&1";
        $output = [];
        $status = -1;
        exec($cmd, $output, $status);
        
        $logs[] = "[" . date('Y-m-d H:i:s') . "] Exit code: $status";
        $logs = array_merge($logs, array_map(function($line) {
            return "[" . date('Y-m-d H:i:s') . "] " . $line;
        }, $output));
        
        if($status === 0) {
            $logs[] = "[" . date('Y-m-d H:i:s') . "] SUCCESS: Configuration is valid";
            $this->sendResponse([
                'success' => true,
                'message' => 'Nginx configuration is valid',
                'logs' => $logs
            ]);
        } else {
            $logs[] = "[" . date('Y-m-d H:i:s') . "] ERROR: Configuration has errors";
            $this->sendResponse([
                'success' => false,
                'error' => 'Nginx configuration has errors',
                'logs' => $logs
            ]);
        }
    }
    
    private function getNginxStatus() {
        $cmd = "docker exec reverse-proxy nginx -V 2>&1";
        $output = shell_exec($cmd);
        
        $this->sendResponse([
            'success' => true,
            'version' => $output
        ]);
    }
    
    private function sendResponse($data, $code = 200) {
        http_response_code($code);
        echo json_encode($data);
        exit;
    }
}

$api = new NginxAPI();
$api->handleRequest();
