<?php

class MikroTikAPI {
    private $host;
    private $username;
    private $password;
    private $port;
    private $use_ssl;
    private $base_url;
    private $timeout = 10;
    
    public function __construct($config = null) {
        if ($config === null) {
            $config = getConfig('mikrotik');
        }
        
        if (!$config) {
            throw new Exception('MikroTik configuration not found');
        }
        
        $this->host = $config['host'];
        $this->username = $config['username'];
        $this->password = $config['password'];
        $this->port = $config['port'] ?? '443';
        $this->use_ssl = $config['use_ssl'] ?? true;
        
        if (empty($this->host) || empty($this->username)) {
            throw new Exception('MikroTik host and username are required');
        }
        
        $protocol = $this->use_ssl ? 'https' : 'http';
        $this->base_url = $protocol . '://' . $this->host . ':' . $this->port . '/rest';
    }
    
    /**
     * Make HTTP request to MikroTik REST API
     */
    private function makeRequest($endpoint, $method = 'GET', $data = null) {
        $url = $this->base_url . $endpoint;
        
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5, // Reduced timeout to prevent hanging
            CURLOPT_CONNECTTIMEOUT => 3, // Connection timeout
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => $this->username . ':' . $this->password,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json'
            ]
        ]);
        
        if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        
        curl_close($curl);
        
        
        if ($response === false) {
            throw new Exception('Cannot connect to MikroTik router at ' . $this->host . ':' . $this->port . ' - ' . $error);
        }
        
        if ($http_code >= 400) {
            $error_data = json_decode($response, true);
            $error_message = $error_data['message'] ?? 'HTTP error: ' . $http_code;
            throw new Exception($error_message);
        }

        // DELETE requests often return empty response, handle gracefully
        if (empty($response)) {
            // Empty response is success for DELETE operations
            return ($method === 'DELETE' && $http_code >= 200 && $http_code < 300) ? true : null;
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // If not valid JSON but request was successful, return true for DELETE
            if ($method === 'DELETE' && $http_code >= 200 && $http_code < 300) {
                return true;
            }
            throw new Exception('Invalid JSON response from MikroTik');
        }

        return $decoded;
    }
    
    /**
     * Test connection to MikroTik
     */
    public function testConnection() {
        try {
            $result = $this->makeRequest('/system/resource');
            
            if (is_array($result) && !empty($result)) {
                $resource = $result[0] ?? $result;
                return [
                    'success' => true,
                    'message' => 'Connected successfully to ' . ($resource['board-name'] ?? 'MikroTik Router'),
                    'data' => [
                        'board' => $resource['board-name'] ?? 'Unknown',
                        'version' => $resource['version'] ?? 'Unknown',
                        'architecture' => $resource['architecture-name'] ?? 'Unknown',
                        'uptime' => $resource['uptime'] ?? 'Unknown'
                    ]
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Connection successful',
                'data' => []
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => []
            ];
        }
    }
    
    /**
     * Get system resource information
     */
    public function getSystemResource() {
        return $this->makeRequest('/system/resource');
    }
    
    /**
     * Get L2TP server status
     */
    public function getL2TPServerStatus() {
        try {
            $result = $this->makeRequest('/interface/l2tp-server/server');
            
            // Handle both array and object response formats
            if (is_array($result) && isset($result['enabled'])) {
                // Direct object response
                $enabled = $result['enabled'];
                return $enabled === 'true' || $enabled === true;
            } elseif (is_array($result) && count($result) > 0 && isset($result[0]['enabled'])) {
                // Array response
                $enabled = $result[0]['enabled'];
                return $enabled === 'true' || $enabled === true;
            }
            return false;
        } catch (Exception $e) {
            error_log("L2TP status error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get PPTP server status
     */
    public function getPPTPServerStatus() {
        try {
            $result = $this->makeRequest('/interface/pptp-server/server');
            
            // Handle both array and object response formats
            if (is_array($result) && isset($result['enabled'])) {
                // Direct object response
                $enabled = $result['enabled'];
                return $enabled === 'true' || $enabled === true;
            } elseif (is_array($result) && count($result) > 0 && isset($result[0]['enabled'])) {
                // Array response
                $enabled = $result[0]['enabled'];
                return $enabled === 'true' || $enabled === true;
            }
            return false;
        } catch (Exception $e) {
            error_log("PPTP status error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get SSTP server status
     */
    public function getSSTServerStatus() {
        try {
            $result = $this->makeRequest('/interface/sstp-server/server');
            
            // Handle both array and object response formats
            if (is_array($result) && isset($result['enabled'])) {
                // Direct object response
                $enabled = $result['enabled'];
                return $enabled === 'true' || $enabled === true;
            } elseif (is_array($result) && count($result) > 0 && isset($result[0]['enabled'])) {
                // Array response
                $enabled = $result[0]['enabled'];
                return $enabled === 'true' || $enabled === true;
            }
            return false;
        } catch (Exception $e) {
            error_log("SSTP status error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Toggle L2TP server
     */
    public function toggleL2TPServer($enable = true) {
        try {
            error_log("toggleL2TPServer called with enable: " . ($enable ? 'true' : 'false'));
            
            // Use console command execution instead of REST API configuration
            $enabled_value = $enable ? 'yes' : 'no';
            $command = "/interface l2tp-server server set enabled=$enabled_value";
            
            error_log("Executing L2TP command: $command");
            
            $result = $this->makeRequest('/execute', 'POST', [
                'script' => $command
            ]);
            
            error_log("L2TP command result: " . json_encode($result));
            
            // Verify the change took effect
            $verify_result = $this->getL2TPServerStatus();
            error_log("L2TP status after toggle: " . ($verify_result ? 'enabled' : 'disabled'));
            
            return $result;
        } catch (Exception $e) {
            error_log("L2TP toggle error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Toggle PPTP server
     */
    public function togglePPTPServer($enable = true) {
        try {
            error_log("togglePPTPServer called with enable: " . ($enable ? 'true' : 'false'));
            
            // Use console command execution
            $enabled_value = $enable ? 'yes' : 'no';
            $command = "/interface pptp-server server set enabled=$enabled_value";
            
            error_log("Executing PPTP command: $command");
            
            $result = $this->makeRequest('/execute', 'POST', [
                'script' => $command
            ]);
            
            error_log("PPTP command result: " . json_encode($result));
            
            // Verify the change took effect
            $verify_result = $this->getPPTPServerStatus();
            error_log("PPTP status after toggle: " . ($verify_result ? 'enabled' : 'disabled'));
            
            return $result;
        } catch (Exception $e) {
            error_log("PPTP toggle error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Toggle SSTP server
     */
    public function toggleSSTServer($enable = true) {
        try {
            error_log("toggleSSTServer called with enable: " . ($enable ? 'true' : 'false'));
            
            // Use console command execution
            $enabled_value = $enable ? 'yes' : 'no';
            $command = "/interface sstp-server server set enabled=$enabled_value";
            
            error_log("Executing SSTP command: $command");
            
            $result = $this->makeRequest('/execute', 'POST', [
                'script' => $command
            ]);
            
            error_log("SSTP command result: " . json_encode($result));
            
            // Verify the change took effect
            $verify_result = $this->getSSTServerStatus();
            error_log("SSTP status after toggle: " . ($verify_result ? 'enabled' : 'disabled'));
            
            return $result;
        } catch (Exception $e) {
            error_log("SSTP toggle error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get all VPN services status
     */
    public function getVPNServicesStatus() {
        return [
            'l2tp' => $this->getL2TPServerStatus(),
            'pptp' => $this->getPPTPServerStatus(),
            'sstp' => $this->getSSTServerStatus()
        ];
    }
    
    public function getRawServiceStatus($service) {
        switch (strtolower($service)) {
            case 'l2tp':
                return $this->makeRequest('/interface/l2tp-server/server');
            case 'pptp':
                return $this->makeRequest('/interface/pptp-server/server');
            case 'sstp':
                return $this->makeRequest('/interface/sstp-server/server');
            default:
                throw new Exception('Invalid service: ' . $service);
        }
    }
    
    /**
     * Toggle VPN service
     */
    public function toggleVPNService($service, $enable = true) {
        switch (strtolower($service)) {
            case 'l2tp':
                return $this->toggleL2TPServer($enable);
                
            case 'pptp':
                return $this->togglePPTPServer($enable);
                
            case 'sstp':
                return $this->toggleSSTServer($enable);
                
            default:
                throw new Exception('Invalid service type: ' . $service);
        }
    }
    
    /**
     * Export configuration
     */
    public function exportConfiguration($filename = null) {
        if (!$filename) {
            $filename = 'backup-' . date('Y-m-d-H-i-s') . '.rsc';
        }
        
        // Use console command to export configuration
        $command = "/export compact file=$filename";
        
        return $this->makeRequest('/execute', 'POST', [
            'script' => $command
        ]);
    }
    
    /**
     * Get exported file content
     */
    public function getExportedFile($filename) {
        // Get file list to check if file exists
        $files = $this->makeRequest('/file');
        
        $found_file = null;
        foreach ($files as $file) {
            if (isset($file['name']) && $file['name'] === $filename) {
                $found_file = $file;
                break;
            }
        }
        
        if (!$found_file) {
            throw new Exception("Export file not found: $filename");
        }
        
        // Download file content using fetch tool
        $temp_url = "http://127.0.0.1/file=$filename";
        $fetch_command = "/tool/fetch url=\"$temp_url\" mode=http";
        
        // For now, we'll use a different approach - read the file content directly
        // This is a simplified approach, in production you might want to use proper file download
        
        return [
            'filename' => $filename,
            'size' => $found_file['size'] ?? 0,
            'created' => $found_file['creation-time'] ?? date('Y-m-d H:i:s'),
        ];
    }
    
    /**
     * Export configuration and get content for Telegram
     */
    public function exportConfigurationForTelegram($filename = null) {
        if (!$filename) {
            $filename = 'backup-' . date('Y-m-d-H-i-s') . '.rsc';
        }
        
        error_log("exportConfigurationForTelegram: Starting export for $filename");
        
        try {
            // Create a comprehensive backup by gathering configuration from various endpoints
            $backup_content = $this->createConfigurationBackup();
            
            if (empty($backup_content)) {
                throw new Exception('Failed to create configuration backup');
            }
            
            error_log("exportConfigurationForTelegram: Backup content length: " . strlen($backup_content));
            
            // Also create file on MikroTik for backup
            $this->exportConfiguration($filename);
            
            return [
                'filename' => $filename,
                'content' => $backup_content,
                'success' => true
            ];
            
        } catch (Exception $e) {
            error_log("exportConfigurationForTelegram error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Create comprehensive configuration backup
     */
    private function createConfigurationBackup() {
        $backup_content = "# MikroTik Configuration Backup\n";
        $backup_content .= "# Generated: " . date('Y-m-d H:i:s') . "\n";
        $backup_content .= "# Source: VPN Remote Manager\n\n";
        
        try {
            // System Information
            $system_info = $this->getSystemResource();
            if ($system_info && !empty($system_info)) {
                $resource = is_array($system_info) ? ($system_info[0] ?? $system_info) : $system_info;
                $backup_content .= "# ===== SYSTEM INFORMATION =====\n";
                if (isset($resource['board-name'])) {
                    $backup_content .= "# Board: " . $resource['board-name'] . "\n";
                }
                if (isset($resource['version'])) {
                    $backup_content .= "# RouterOS Version: " . $resource['version'] . "\n";
                }
                if (isset($resource['architecture-name'])) {
                    $backup_content .= "# Architecture: " . $resource['architecture-name'] . "\n";
                }
                if (isset($resource['cpu'])) {
                    $backup_content .= "# CPU: " . $resource['cpu'] . "\n";
                }
                if (isset($resource['uptime'])) {
                    $backup_content .= "# Uptime: " . $resource['uptime'] . "\n";
                }
                $backup_content .= "\n";
            }
        } catch (Exception $e) {
            $backup_content .= "# System info error: " . $e->getMessage() . "\n\n";
        }
        
        try {
            // PPP Secrets (VPN Users)
            $ppp_users = $this->getPPPSecrets();
            $backup_content .= "# ===== PPP SECRETS (" . count($ppp_users) . " users) =====\n";
            foreach ($ppp_users as $user) {
                $backup_content .= "/ppp secret add";
                if (isset($user['name'])) $backup_content .= " name=\"" . $user['name'] . "\"";
                if (isset($user['service'])) $backup_content .= " service=" . $user['service'];
                if (isset($user['profile'])) $backup_content .= " profile=\"" . $user['profile'] . "\"";
                if (isset($user['remote-address'])) $backup_content .= " remote-address=" . $user['remote-address'];
                if (isset($user['comment'])) $backup_content .= " comment=\"" . $user['comment'] . "\"";
                if (isset($user['disabled']) && $user['disabled'] === 'true') $backup_content .= " disabled=yes";
                $backup_content .= "\n";
            }
            $backup_content .= "\n";
        } catch (Exception $e) {
            $backup_content .= "# PPP secrets error: " . $e->getMessage() . "\n\n";
        }
        
        try {
            // Firewall NAT Rules
            $nat_rules = $this->getFirewallNAT();
            if (!empty($nat_rules)) {
                $backup_content .= "# ===== FIREWALL NAT RULES (" . count($nat_rules) . " rules) =====\n";
                foreach ($nat_rules as $rule) {
                    $backup_content .= "/ip firewall nat add";
                    if (isset($rule['chain'])) $backup_content .= " chain=" . $rule['chain'];
                    if (isset($rule['action'])) $backup_content .= " action=" . $rule['action'];
                    if (isset($rule['protocol'])) $backup_content .= " protocol=" . $rule['protocol'];
                    if (isset($rule['dst-port'])) $backup_content .= " dst-port=" . $rule['dst-port'];
                    if (isset($rule['to-addresses'])) $backup_content .= " to-addresses=" . $rule['to-addresses'];
                    if (isset($rule['to-ports'])) $backup_content .= " to-ports=" . $rule['to-ports'];
                    if (isset($rule['comment'])) $backup_content .= " comment=\"" . $rule['comment'] . "\"";
                    if (isset($rule['disabled']) && $rule['disabled'] === 'true') $backup_content .= " disabled=yes";
                    $backup_content .= "\n";
                }
                $backup_content .= "\n";
            }
        } catch (Exception $e) {
            $backup_content .= "# Firewall NAT error: " . $e->getMessage() . "\n\n";
        }
        
        try {
            // VPN Service Status
            $services = $this->getVPNServicesStatus();
            $backup_content .= "# ===== VPN SERVICES STATUS =====\n";
            $backup_content .= "# L2TP Server: " . ($services['l2tp'] ? 'enabled' : 'disabled') . "\n";
            $backup_content .= "# PPTP Server: " . ($services['pptp'] ? 'enabled' : 'disabled') . "\n";
            $backup_content .= "# SSTP Server: " . ($services['sstp'] ? 'enabled' : 'disabled') . "\n";
            $backup_content .= "\n";
        } catch (Exception $e) {
            $backup_content .= "# VPN services error: " . $e->getMessage() . "\n\n";
        }
        
        $backup_content .= "# ===== END OF BACKUP =====\n";
        $backup_content .= "# Generated by VPN Remote Manager\n";
        $backup_content .= "# Total lines: " . substr_count($backup_content, "\n") . "\n";
        
        return $backup_content;
    }
    
    /**
     * Get PPP secrets (users)
     */
    public function getPPPSecrets() {
        return $this->makeRequest('/ppp/secret');
    }
    
    /**
     * Get PPP profiles
     */
    public function getPPPProfiles() {
        return $this->makeRequest('/ppp/profile');
    }
    
    /**
     * Create PPP profile for specific VPN service
     */
    public function createServiceProfile($service) {
        $profiles = [
            'l2tp' => [
                'name' => 'L2TP',
                'local-address' => '10.51.0.1',
                'bridge-learning' => 'default',
                'use-ipv6' => 'no',
                'use-mpls' => 'no',
                'use-compression' => 'no',
                'use-encryption' => 'no',
                'only-one' => 'yes',
                'change-tcp-mss' => 'default',
                'use-upnp' => 'default',
                'address-list' => '',
                'on-up' => '',
                'on-down' => ''
            ],
            'pptp' => [
                'name' => 'PPTP',
                'local-address' => '10.52.0.1',
                'bridge-learning' => 'default',
                'use-ipv6' => 'no',
                'use-mpls' => 'no',
                'use-compression' => 'no',
                'use-encryption' => 'no',
                'only-one' => 'yes',
                'change-tcp-mss' => 'default',
                'use-upnp' => 'default',
                'address-list' => '',
                'on-up' => '',
                'on-down' => ''
            ],
            'sstp' => [
                'name' => 'SSTP',
                'local-address' => '10.53.0.1',
                'bridge-learning' => 'default',
                'use-ipv6' => 'no',
                'use-mpls' => 'no',
                'use-compression' => 'no',
                'use-encryption' => 'no',
                'only-one' => 'yes',
                'change-tcp-mss' => 'default',
                'use-upnp' => 'default',
                'address-list' => '',
                'on-up' => '',
                'on-down' => ''
            ]
        ];
        
        $service = strtolower($service);
        if (!isset($profiles[$service])) {
            throw new Exception('Invalid service type: ' . $service);
        }
        
        $profile_data = $profiles[$service];
        
        error_log("Creating PPP profile for service: $service");
        error_log("Profile data: " . json_encode($profile_data));
        
        // Check if profile already exists
        $existing_profiles = $this->getPPPProfiles();
        foreach ($existing_profiles as $profile) {
            if (isset($profile['name']) && $profile['name'] === $profile_data['name']) {
                error_log("Profile {$profile_data['name']} already exists, updating instead");
                // Update existing profile
                return $this->updatePPPProfile($profile['.id'], $profile_data);
            }
        }
        
        // Create new profile
        $result = $this->makeRequest('/ppp/profile', 'PUT', $profile_data);
        
        if ($result) {
            error_log("Profile {$profile_data['name']} created successfully");
            
            // Set as default profile for the service
            $this->setServiceDefaultProfile($service, $profile_data['name']);
        }
        
        return $result;
    }
    
    /**
     * Update existing PPP profile
     */
    public function updatePPPProfile($id, $data) {
        return $this->makeRequest('/ppp/profile/' . $id, 'PATCH', $data);
    }
    
    /**
     * Set default profile for VPN service
     */
    public function setServiceDefaultProfile($service, $profile_name) {
        error_log("Setting default profile for $service service to: $profile_name");
        
        $commands = [
            'l2tp' => "/interface l2tp-server server set default-profile=\"$profile_name\"",
            'pptp' => "/interface pptp-server server set default-profile=\"$profile_name\"",
            'sstp' => "/interface sstp-server server set default-profile=\"$profile_name\""
        ];
        
        $service = strtolower($service);
        if (!isset($commands[$service])) {
            throw new Exception('Invalid service type for default profile: ' . $service);
        }
        
        $command = $commands[$service];
        error_log("Executing command: $command");
        
        $result = $this->makeRequest('/execute', 'POST', [
            'script' => $command
        ]);
        
        error_log("Set default profile result: " . json_encode($result));
        
        return $result;
    }
    
    /**
     * Get PPP active sessions
     */
    public function getPPPActive() {
        return $this->makeRequest('/ppp/active');
    }

    /**
     * Get PPP active sessions with traffic statistics
     * Combines /ppp/active with /interface data to get traffic counters
     */
    public function getPPPActiveWithTraffic() {
        // Get active PPP sessions
        $activeSessions = $this->getPPPActive();

        // Get all interfaces to match PPP interfaces
        $interfaces = $this->makeRequest('/interface');

        // Match each active session with its interface to get traffic data
        foreach ($activeSessions as &$session) {
            // PPP interfaces can have different naming patterns:
            // Pattern 1: <sstp-username>
            // Pattern 2: sstp-username (without brackets)
            // We'll try multiple patterns

            $patterns = [
                '<' . $session['service'] . '-' . $session['name'] . '>',  // <sstp-TestSSTP1>
                $session['service'] . '-' . $session['name'],              // sstp-TestSSTP1
                '<' . $session['name'] . '>',                              // <TestSSTP1>
                $session['name']                                           // TestSSTP1
            ];

            $matched = false;

            // Try each pattern to find matching interface
            foreach ($patterns as $pattern) {
                foreach ($interfaces as $interface) {
                    if ($interface['name'] === $pattern) {
                        // Add traffic data from interface
                        $session['bytes-in'] = $interface['rx-byte'] ?? '0';
                        $session['bytes-out'] = $interface['tx-byte'] ?? '0';
                        $session['rx-byte'] = $interface['rx-byte'] ?? '0';
                        $session['tx-byte'] = $interface['tx-byte'] ?? '0';
                        $session['rx-packet'] = $interface['rx-packet'] ?? '0';
                        $session['tx-packet'] = $interface['tx-packet'] ?? '0';
                        $session['interface-name'] = $interface['name']; // Store matched interface name for debugging
                        $matched = true;
                        break 2; // Break both loops
                    }
                }
            }

            // If no traffic data found, set to 0
            if (!$matched) {
                $session['bytes-in'] = '0';
                $session['bytes-out'] = '0';
                $session['rx-byte'] = '0';
                $session['tx-byte'] = '0';
                $session['interface-name'] = 'not-found'; // For debugging
            }
        }

        return $activeSessions;
    }

    /**
     * Get firewall NAT rules
     */
    public function getFirewallNAT() {
        return $this->makeRequest('/ip/firewall/nat');
    }
    
    /**
     * Check if masquerade NAT rule exists
     */
    public function checkMasqueradeNAT() {
        try {
            $nat_rules = $this->getFirewallNAT();
            
            if (is_array($nat_rules)) {
                foreach ($nat_rules as $rule) {
                    if (isset($rule['chain']) && $rule['chain'] === 'srcnat' &&
                        isset($rule['action']) && $rule['action'] === 'masquerade') {
                        return true;
                    }
                }
            }
            
            return false;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Create masquerade NAT rule
     */
    public function createMasqueradeNAT() {
        try {
            $nat_data = [
                'chain' => 'srcnat',
                'action' => 'masquerade',
                'comment' => 'BB'
            ];
            
            $result = $this->addFirewallNAT($nat_data);
            return $result !== false;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Add PPP secret (user)
     */
    public function addPPPSecret($data) {
        return $this->makeRequest('/ppp/secret', 'PUT', $data);
    }
    
    /**
     * Update PPP secret
     */
    public function updatePPPSecret($id, $data) {
        return $this->makeRequest('/ppp/secret/' . $id, 'PATCH', $data);
    }
    
    /**
     * Delete PPP secret
     */
    public function deletePPPSecret($id) {
        return $this->makeRequest('/ppp/secret/' . $id, 'DELETE');
    }
    
    /**
     * Add firewall NAT rule
     */
    public function addFirewallNAT($data) {
        return $this->makeRequest('/ip/firewall/nat', 'PUT', $data);
    }
    
    /**
     * Delete firewall NAT rule
     */
    public function deleteFirewallNAT($id) {
        error_log("[MIKROTIK NAT] Attempting to delete NAT rule with ID: $id");
        
        try {
            $result = $this->makeRequest('/ip/firewall/nat/' . $id, 'DELETE');
            error_log("[MIKROTIK NAT] Delete NAT rule result: " . json_encode($result));
            return $result;
        } catch (Exception $e) {
            error_log("[MIKROTIK NAT] Failed to delete NAT rule ID $id: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get used ports from NAT rules
     */
    public function getUsedPorts() {
        try {
            $nat_rules = $this->getFirewallNAT();
            $used_ports = [];
            
            foreach ($nat_rules as $rule) {
                if (isset($rule['dst-port']) && !empty($rule['dst-port'])) {
                    $port = $rule['dst-port'];
                    if (strpos($port, ',') !== false) {
                        $ports = explode(',', $port);
                        $used_ports = array_merge($used_ports, array_map('trim', $ports));
                    } else {
                        $used_ports[] = trim($port);
                    }
                }
            }
            
            return array_unique(array_filter($used_ports, 'is_numeric'));
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Generate random unused port
     */
    public function generateRandomPort($min = 1000, $max = 9999) {
        $used_ports = $this->getUsedPorts();
        $max_attempts = 100;
        
        for ($i = 0; $i < $max_attempts; $i++) {
            $port = rand($min, $max);
            if (!in_array($port, $used_ports)) {
                return $port;
            }
        }
        
        throw new Exception('Unable to find available port after ' . $max_attempts . ' attempts');
    }
    
    /**
     * Get log entries
     */
    public function getLogEntries($limit = 50) {
        return $this->makeRequest('/log?' . http_build_query(['.proplist' => 'time,topics,message']));
    }
    
    /**
     * Get PPP active sessions (alias for getPPPActive)
     */
    public function getPPPActiveSessions() {
        return $this->getPPPActive();
    }
    
    /**
     * Get PPP logs (filtered log entries for PPP-related activities)
     */
    public function getPPPLogs($limit = 100) {
        try {
            $logs = $this->getLogEntries($limit);
            return $logs;
        } catch (Exception $e) {
            error_log("Error getting PPP logs: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get system logs (general log entries)
     */
    public function getSystemLogs($limit = 100) {
        try {
            return $this->getLogEntries($limit);
        } catch (Exception $e) {
            error_log("Error getting system logs: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get single PPP secret by ID
     */
    public function getPPPSecret($id) {
        
        try {
            // MikroTik API requires URL encoding for IDs with special characters like *
            $encoded_id = urlencode($id);
            $result = $this->makeRequest('/ppp/secret/' . $encoded_id);
            
            // Handle different response formats
            if (is_array($result)) {
                // If it's an array with single item, return the item
                if (count($result) === 1) {
                    return $result[0];
                }
                // If it's an array with multiple items, return first one
                if (count($result) > 0) {
                    return $result[0];
                }
                // Empty array, try alternative method
                return $this->findSecretById($id);
            }
            
            // If it's an object/associative array, return as is
            return $result;
            
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Find PPP secret by ID from all secrets (alternative method)
     */
    private function findSecretById($id) {
        
        try {
            $all_secrets = $this->getPPPSecrets();
            
            if (is_array($all_secrets)) {
                foreach ($all_secrets as $secret) {
                    if (isset($secret['.id']) && $secret['.id'] === $id) {
                        return $secret;
                    }
                }
            }
            
            return null;
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Get firewall NAT rules by comment
     */
    public function getFirewallNATByComment($comment) {
        $nat_rules = $this->getFirewallNAT();
        $filtered_rules = [];
        
        if (is_array($nat_rules) && count($nat_rules) > 0) {
        }
        
        if (is_array($nat_rules)) {
            foreach ($nat_rules as $rule) {
                if (isset($rule['comment']) && $rule['comment'] === $comment) {
                    $filtered_rules[] = $rule;
                }
            }
        }
        
        return $filtered_rules;
    }
    
    /**
     * Get firewall NAT rules by IP address (to-addresses field)
     */
    public function getFirewallNATByIP($ip_address) {
        $nat_rules = $this->getFirewallNAT();
        $filtered_rules = [];
        
        
        if (is_array($nat_rules)) {
            foreach ($nat_rules as $rule) {
                $rule_ip = isset($rule['to-addresses']) ? $rule['to-addresses'] : 'N/A';
                
                if (isset($rule['to-addresses']) && $rule['to-addresses'] === $ip_address) {
                    $filtered_rules[] = $rule;
                }
            }
        }
        
        return $filtered_rules;
    }
    
    /**
     * Delete firewall NAT rules by comment
     */
    public function deleteFirewallNATByComment($comment) {
        error_log("[MIKROTIK NAT] Searching for NAT rules with comment: $comment");
        
        $nat_rules = $this->getFirewallNATByComment($comment);
        $deleted_count = 0;
        
        error_log("[MIKROTIK NAT] Found " . count($nat_rules) . " NAT rules to delete");
        
        foreach ($nat_rules as $rule) {
            if (isset($rule['.id'])) {
                try {
                    error_log("[MIKROTIK NAT] Deleting NAT rule ID: {$rule['.id']} for comment: $comment");
                    $result = $this->deleteFirewallNAT($rule['.id']);
                    if ($result !== false) {
                        $deleted_count++;
                        error_log("[MIKROTIK NAT] Successfully deleted NAT rule ID: {$rule['.id']}");
                    } else {
                        error_log("[MIKROTIK NAT] Failed to delete NAT rule ID: {$rule['.id']} (returned false)");
                    }
                } catch (Exception $e) {
                    error_log("[MIKROTIK NAT] Error deleting NAT rule {$rule['.id']}: " . $e->getMessage());
                }
            }
        }
        
        error_log("[MIKROTIK NAT] Successfully deleted $deleted_count NAT rules for comment: $comment");
        return $deleted_count;
    }
    
    /**
     * Delete firewall NAT rules by IP address (to-addresses)
     */
    public function deleteFirewallNATByIP($ip_address) {
        error_log("[MIKROTIK NAT] Searching for NAT rules with to-addresses: $ip_address");
        
        $nat_rules = $this->getFirewallNATByIP($ip_address);
        $deleted_count = 0;
        
        error_log("[MIKROTIK NAT] Found " . count($nat_rules) . " NAT rules to delete by IP");
        
        foreach ($nat_rules as $rule) {
            if (isset($rule['.id'])) {
                try {
                    error_log("[MIKROTIK NAT] Deleting NAT rule ID: {$rule['.id']} for IP: $ip_address");
                    $result = $this->deleteFirewallNAT($rule['.id']);
                    if ($result !== false) {
                        $deleted_count++;
                        error_log("[MIKROTIK NAT] Successfully deleted NAT rule ID: {$rule['.id']}");
                    } else {
                        error_log("[MIKROTIK NAT] Failed to delete NAT rule ID: {$rule['.id']} (returned false)");
                    }
                } catch (Exception $e) {
                    error_log("[MIKROTIK NAT] Error deleting NAT rule {$rule['.id']}: " . $e->getMessage());
                }
            }
        }
        
        error_log("[MIKROTIK NAT] Successfully deleted $deleted_count NAT rules for IP: $ip_address");
        return $deleted_count;
    }
    
    /**
     * Get next available IP address for a service based on profile configuration
     */
    public function getNextAvailableIP($service) {
        error_log("[MIKROTIK IP] Getting next available IP for service: $service");
        
        try {
            // Service to profile mapping
            $profile_mapping = [
                'l2tp' => 'L2TP',
                'pptp' => 'PPTP', 
                'sstp' => 'SSTP',
                'any' => 'default'
            ];
            
            $profile_name = $profile_mapping[strtolower($service)] ?? 'default';
            
            // Get profile information to find IP range
            $profiles = $this->getPPPProfiles();
            $target_profile = null;
            
            foreach ($profiles as $profile) {
                if (isset($profile['name']) && $profile['name'] === $profile_name) {
                    $target_profile = $profile;
                    break;
                }
            }
            
            if (!$target_profile) {
                error_log("[MIKROTIK IP] Profile $profile_name not found, using default ranges");
                // Use default IP ranges if profile not found
                $ip_ranges = $this->getDefaultIPRanges($service);
            } else {
                error_log("[MIKROTIK IP] Found profile: " . json_encode($target_profile));
                $ip_ranges = $this->extractIPRangeFromProfile($target_profile, $service);
            }
            
            // Get all used IPs from existing PPP users
            $used_ips = $this->getUsedPPPIPs();
            error_log("[MIKROTIK IP] Used IPs: " . json_encode($used_ips));
            
            // Find next available IP
            foreach ($ip_ranges as $range) {
                $available_ip = $this->findNextAvailableIPInRange($range, $used_ips);
                if ($available_ip) {
                    error_log("[MIKROTIK IP] Found available IP: $available_ip in range $range");
                    return $available_ip;
                }
            }
            
            error_log("[MIKROTIK IP] No available IP found in any range");
            return null;
            
        } catch (Exception $e) {
            error_log("[MIKROTIK IP] Error getting next available IP: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get default IP ranges for services
     */
    private function getDefaultIPRanges($service) {
        $default_ranges = [
            'l2tp' => ['10.51.0.0/24'],
            'pptp' => ['10.52.0.0/24'],
            'sstp' => ['10.53.0.0/24'],
            'any' => ['10.50.0.0/24']
        ];
        
        return $default_ranges[strtolower($service)] ?? ['10.50.0.0/24'];
    }
    
    /**
     * Extract IP range from profile configuration
     */
    private function extractIPRangeFromProfile($profile, $service) {
        // Try to get local-address from profile which indicates the server IP
        $local_address = $profile['local-address'] ?? '';
        
        if (!empty($local_address)) {
            // Extract network from local address (assume /24)
            $parts = explode('.', $local_address);
            if (count($parts) >= 3) {
                $network = $parts[0] . '.' . $parts[1] . '.' . $parts[2] . '.0/24';
                error_log("[MIKROTIK IP] Extracted network from profile: $network");
                return [$network];
            }
        }
        
        // Fallback to default ranges
        return $this->getDefaultIPRanges($service);
    }
    
    /**
     * Get all used IP addresses from PPP secrets
     */
    private function getUsedPPPIPs() {
        try {
            $secrets = $this->getPPPSecrets();
            $used_ips = [];
            
            foreach ($secrets as $secret) {
                if (isset($secret['remote-address']) && !empty($secret['remote-address'])) {
                    $used_ips[] = $secret['remote-address'];
                }
            }
            
            return $used_ips;
        } catch (Exception $e) {
            error_log("[MIKROTIK IP] Error getting used PPP IPs: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Find next available IP in given CIDR range
     */
    private function findNextAvailableIPInRange($cidr_range, $used_ips) {
        list($network, $prefix) = explode('/', $cidr_range);
        $prefix = (int)$prefix;
        
        // Convert network to long
        $network_long = ip2long($network);
        $hosts = pow(2, 32 - $prefix) - 2; // Subtract network and broadcast
        
        // Start from .2 (skip network .0 and gateway .1)
        for ($i = 2; $i <= $hosts; $i++) {
            $ip_long = $network_long + $i;
            $ip = long2ip($ip_long);
            
            // Skip broadcast address
            if ($i == $hosts) continue;
            
            // Check if IP is not in used list
            if (!in_array($ip, $used_ips)) {
                return $ip;
            }
        }
        
        return null;
    }

    /**
     * Create Netwatch entry for PPP user
     */
    public function createNetwatch($host, $comment) {
        try {
            $data = [
                'host' => $host,
                'comment' => $comment,
                'interval' => '00:01:00', // Check every 1 minute
                'timeout' => '00:00:05'   // 5 second timeout
            ];

            error_log("[MIKROTIK NETWATCH] Attempting to create netwatch for host: $host, comment: $comment");

            $response = $this->makeRequest('/tool/netwatch', 'PUT', $data);

            error_log("[MIKROTIK NETWATCH] Response: " . json_encode($response));

            if (isset($response['.id'])) {
                error_log("[MIKROTIK NETWATCH] Successfully created netwatch with ID: " . $response['.id']);
                return $response;
            }

            error_log("[MIKROTIK NETWATCH] Response did not contain .id field");
            return false;
        } catch (Exception $e) {
            // Log error but don't throw - netwatch is optional
            error_log("[MIKROTIK NETWATCH] Exception caught: " . $e->getMessage());
            error_log("[MIKROTIK NETWATCH] Stack trace: " . $e->getTraceAsString());
            return false;
        }
    }

    /**
     * Get all Netwatch entries
     */
    public function getNetwatchEntries() {
        return $this->makeRequest('/tool/netwatch');
    }

    /**
     * Delete Netwatch entry by comment
     */
    public function deleteNetwatchByComment($comment) {
        $netwatch_entries = $this->getNetwatchEntries();

        if (!is_array($netwatch_entries)) {
            return false;
        }

        $deleted_count = 0;
        foreach ($netwatch_entries as $entry) {
            if (isset($entry['comment']) && $entry['comment'] === $comment) {
                $this->makeRequest('/tool/netwatch/' . $entry['.id'], 'DELETE');
                $deleted_count++;
            }
        }

        return $deleted_count > 0;
    }

    /**
     * Delete Netwatch entry by host IP
     */
    public function deleteNetwatchByHost($host) {
        $netwatch_entries = $this->getNetwatchEntries();

        if (!is_array($netwatch_entries)) {
            return false;
        }

        $deleted_count = 0;
        foreach ($netwatch_entries as $entry) {
            if (isset($entry['host']) && $entry['host'] === $host) {
                $this->makeRequest('/tool/netwatch/' . $entry['.id'], 'DELETE');
                $deleted_count++;
            }
        }

        return $deleted_count > 0;
    }

    /**
     * Add Netwatch entry
     */
    public function addNetwatch($data) {
        return $this->makeRequest('/tool/netwatch', 'PUT', $data);
    }

    /**
     * Delete Netwatch entry by ID
     */
    public function deleteNetwatch($id) {
        return $this->makeRequest('/tool/netwatch/' . $id, 'DELETE');
    }

}
?>