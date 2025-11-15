<?php
// Prevent any output before JSON
ob_start();

// Enable detailed error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display to output
ini_set('log_errors', 1);

// Custom error handler to capture all errors
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (ob_get_level() > 0) ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'PHP Error: ' . $errstr,
        'debug' => [
            'file' => $errfile,
            'line' => $errline,
            'type' => $errno
        ]
    ]);
    exit;
});

// Custom exception handler
set_exception_handler(function($e) {
    if (ob_get_level() > 0) ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Uncaught Exception: ' . $e->getMessage(),
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]
    ]);
    exit;
});

header('Content-Type: application/json');
session_start();


require_once '../includes/auth.php';
require_once '../includes/config.php';
require_once '../includes/mikrotik.php';

// Check authentication for API calls - moved to individual cases that need it

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';


$input = [];
if ($method === 'POST') {
    $raw_input = file_get_contents('php://input');
        
    $input = json_decode($raw_input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid JSON: ' . json_last_error_msg()
        ]);
        exit;
    }
    
    $action = $input['action'] ?? $action;
}


try {
    switch ($action) {
        case 'test_connection':
            requireAuth(); // Need auth
            testConnection();
            break;
            
        case 'toggle_service':
            requireAuth(); // Need auth
            if (empty($input)) {
                throw new Exception('No input data provided for toggle_service');
            }
            toggleService($input);
            break;
            
            
        case 'send_backup':
            requireAuth(); // Need auth
            sendBackup();
            break;
            
        case 'create_service_profile':
            if (empty($input)) {
                throw new Exception('No input data provided for create_service_profile');
            }
            createServiceProfile($input);
            break;
            
        case 'check_profiles_status':
            checkProfilesStatus();
            break;
            
        case 'check_nat_status':
            checkNATStatus();
            break;
            
        case 'create_nat_masquerade':
            createNATMasquerade();
            break;
            
        case 'system_resource':
            getSystemResource();
            break;
            
        case 'ppp_stats':
            getPPPStats();
            break;
            
        case 'ppp_logs':
            getPPPLogs();
            break;
            
        case 'get_ppp_users':
            getPPPUsers();
            break;
            
        case 'get_ppp_profiles':
            getPPPProfiles();
            break;

        case 'get_ppp_active':
            getPPPActive();
            break;

        case 'get_available_services':
            getAvailableServices();
            break;

        case 'get_nat_by_user':
            requireAuth();
            if (empty($input)) {
                throw new Exception('No input data provided');
            }
            getNATByUser($input);
            break;

        case 'add_ppp_user':
            requireAuth();
            if (empty($input)) {
                throw new Exception('No input data provided for add_ppp_user');
            }
            addPPPUser($input);
            break;
            
        case 'edit_ppp_user':
            if (empty($input)) {
                throw new Exception('No input data provided for edit_ppp_user');
            }
            editPPPUser($input);
            break;
            
        case 'delete_ppp_user':
            if (empty($input)) {
                throw new Exception('No input data provided for delete_ppp_user');
            }
            deletePPPUser($input);
            break;
            
        case 'toggle_ppp_user_status':
            if (empty($input)) {
                throw new Exception('No input data provided for toggle_ppp_user_status');
            }
            togglePPPUserStatus($input);
            break;
            
        case 'bulk_delete_ppp_users':
            if (empty($input)) {
                throw new Exception('No input data provided for bulk_delete_ppp_users');
            }
            bulkDeletePPPUsers($input);
            break;
            
        case 'bulk_toggle_ppp_users':
            if (empty($input)) {
                throw new Exception('No input data provided for bulk_toggle_ppp_users');
            }
            bulkTogglePPPUsers($input);
            break;
            
        case 'get_user_details':
            requireAuth(); // Need auth
            if (empty($input)) {
                throw new Exception('No input data provided for get_user_details');
            }
            getUserDetails($input);
            break;
            
        case 'get_user_password':
            if (empty($input)) {
                throw new Exception('No input data provided for get_user_password');
            }
            getUserPassword($input);
            break;
            
            
        case 'simple_test':
            // Simple test without dependencies
            echo json_encode([
                'success' => true,
                'message' => 'Simple test working',
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;
            
        case 'get_available_ip':
            if (empty($input)) {
                throw new Exception('No input data provided for get_available_ip');
            }
            getAvailableIP($input);
            break;

        case 'get_netwatch':
            requireAuth();
            getNetwatch();
            break;

        case 'add_netwatch':
            requireAuth();
            if (empty($input)) {
                throw new Exception('No input data provided for add_netwatch');
            }
            addNetwatch($input);
            break;

        case 'delete_netwatch':
            requireAuth();
            if (empty($input)) {
                throw new Exception('No input data provided for delete_netwatch');
            }
            deleteNetwatch($input);
            break;

        default:
            throw new Exception('Invalid action: ' . $action);
    }
} catch (Exception $e) {
            
    // Clean any output buffer to remove stray content
    if (ob_get_level() > 0) {
        $buffer_content = ob_get_contents();
        if (!empty($buffer_content)) {
                    }
        ob_clean();
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_type' => get_class($e)
    ]);
    exit;
}

function testConnection() {
    try {
        $mikrotik = new MikroTikAPI();
        $result = $mikrotik->testConnection();
        
        if ($result['success']) {
            // Update service statuses in config
            $services = $mikrotik->getVPNServicesStatus();
            updateConfigSection('services', $services);
            
            echo json_encode([
                'success' => true,
                'message' => $result['message'],
                'data' => $result['data']
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => $result['message']
            ]);
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

function toggleService($input) {
    
    try {
        $service = $input['service'] ?? '';
        $enable = $input['enable'] ?? false;
        
        
        if (empty($service)) {
            throw new Exception('Service parameter required');
        }
        
        if (!in_array($service, ['l2tp', 'pptp', 'sstp'])) {
            throw new Exception('Invalid service type: ' . $service);
        }
        
        $mikrotik = new MikroTikAPI();
        
        // Toggle service on MikroTik
        $mikrotik->toggleVPNService($service, $enable);
        
        // Update service status in config
        $result = updateConfig('services', $service, $enable);
        
        if (!$result) {
            throw new Exception('Service toggled on MikroTik but failed to update local configuration');
        }
        
        $message = ucfirst($service) . ' service ' . ($enable ? 'enabled' : 'disabled') . ' successfully';
        
        echo json_encode([
            'success' => true,
            'message' => $message
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

function sendBackup() {
    try {
        
        $telegram_config = getConfig('telegram');
        
        if (!$telegram_config || empty($telegram_config['bot_token']) || empty($telegram_config['chat_id'])) {
            throw new Exception('Telegram bot not configured. Please set bot token and chat ID first.');
        }
        
        if (!$telegram_config['enabled']) {
            throw new Exception('Telegram bot is not enabled. Please enable it in settings.');
        }
        
        $mikrotik = new MikroTikAPI();
        
        // Generate backup filename
        $filename = 'backup-' . date('Y-m-d-H-i-s') . '.rsc';
        
        // Export configuration and get content
        
        $export_data = $mikrotik->exportConfigurationForTelegram($filename);
        
        if (!$export_data || !isset($export_data['content'])) {
            throw new Exception('Failed to export configuration from MikroTik');
        }
        
        
        // Prepare content for Telegram
        $content = $export_data['content'];
        if (is_array($content)) {
            // If content is array (from API response), convert to string
            $content = implode("\n", $content);
        }
        
        if (empty($content) || strlen(trim($content)) < 10) {
            throw new Exception('Export content is empty or too small. Please check MikroTik connection.');
        }
        
        // Create caption with system info
        $system_info = $mikrotik->getSystemResource();
        $caption = "📦 *MikroTik Configuration Backup*\n\n";
        $caption .= "🗓 *Date:* " . date('Y-m-d H:i:s') . "\n";
        
        if ($system_info && is_array($system_info) && !empty($system_info)) {
            $resource = $system_info[0] ?? $system_info;
            if (isset($resource['board-name'])) {
                $caption .= "🖥 *Board:* " . $resource['board-name'] . "\n";
            }
            if (isset($resource['version'])) {
                $caption .= "⚙️ *Version:* " . $resource['version'] . "\n";
            }
            if (isset($resource['uptime'])) {
                $caption .= "⏱ *Uptime:* " . $resource['uptime'] . "\n";
            }
        }
        
        $caption .= "\n📄 *File:* " . $filename;
        
        
        // Send to Telegram using internal API
        $telegram_data = [
            'filename' => $filename,
            'content' => $content,
            'caption' => $caption
        ];
        
        // Call Telegram API directly
        $telegram_result = sendFileToTelegram($telegram_data);
        
        if (!$telegram_result) {
            throw new Exception('Failed to send backup to Telegram');
        }
        
        
        echo json_encode([
            'success' => true,
            'message' => "✅ Backup created and sent to Telegram successfully!\n\n📄 File: {$filename}\n📤 Sent to chat ID: {$telegram_config['chat_id']}"
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

function sendFileToTelegram($data) {
    $telegram_config = getConfig('telegram');
    $bot_token = $telegram_config['bot_token'];
    $chat_id = $telegram_config['chat_id'];
    
    // Create temporary file
    $temp_file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $data['filename'];
    file_put_contents($temp_file, $data['content']);
    
    $send_url = "https://api.telegram.org/bot{$bot_token}/sendDocument";
    
    // Prepare multipart form data
    $post_data = [
        'chat_id' => $chat_id,
        'document' => new CURLFile($temp_file, 'text/plain', $data['filename']),
        'caption' => $data['caption'] ?? '',
        'parse_mode' => 'Markdown'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $send_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    // Clean up temporary file
    if (file_exists($temp_file)) {
        unlink($temp_file);
    }
    
    if ($error) {
        return false;
    }
    
    if ($http_code !== 200) {
        return false;
    }
    
    $result = json_decode($response, true);
    
    if (!$result || !$result['ok']) {
        $error_msg = isset($result['description']) ? $result['description'] : 'Unknown error';
        return false;
    }
    
    return true;
}

function createServiceProfile($input) {
    
    try {
        $service = $input['service'] ?? '';
        
        
        if (empty($service)) {
            throw new Exception('Service parameter required');
        }
        
        if (!in_array(strtolower($service), ['l2tp', 'pptp', 'sstp'])) {
            throw new Exception('Invalid service type: ' . $service);
        }
        
        $mikrotik = new MikroTikAPI();
        
        
        // Create the service profile
        $result = $mikrotik->createServiceProfile($service);
        
        if (!$result) {
            throw new Exception('Failed to create profile for service: ' . $service);
        }
        
        $profile_name = strtoupper($service);
        $message = "✅ {$profile_name} profile created successfully and set as default for {$profile_name} server!";
        
        
        echo json_encode([
            'success' => true,
            'message' => $message,
            'service' => $service,
            'profile_name' => $profile_name
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

function checkProfilesStatus() {
    
    try {
        $mikrotik = new MikroTikAPI();
        
        $profiles = $mikrotik->getPPPProfiles();
        
        $profile_status = [
            'l2tp' => false,
            'pptp' => false,
            'sstp' => false
        ];
        
        // Check if service profiles exist
        foreach ($profiles as $profile) {
            if (isset($profile['name'])) {
                $name = $profile['name'];
                if ($name === 'L2TP') {
                    $profile_status['l2tp'] = true;
                } elseif ($name === 'PPTP') {
                    $profile_status['pptp'] = true;
                } elseif ($name === 'SSTP') {
                    $profile_status['sstp'] = true;
                }
            }
        }
        
        
        echo json_encode([
            'success' => true,
            'profiles' => $profile_status
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage(),
            'profiles' => [
                'l2tp' => false,
                'pptp' => false,
                'sstp' => false
            ]
        ]);
    }
}

function checkNATStatus() {
    
    try {
        $mikrotik = new MikroTikAPI();
        
        $nat_exists = $mikrotik->checkMasqueradeNAT();
        
        echo json_encode([
            'success' => true,
            'nat_exists' => $nat_exists
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage(),
            'nat_exists' => false
        ]);
    }
}

function createNATMasquerade() {
    
    try {
        $mikrotik = new MikroTikAPI();
        
        // Check if masquerade NAT already exists
        $nat_exists = $mikrotik->checkMasqueradeNAT();
        
        if ($nat_exists) {
            echo json_encode([
                'success' => true,
                'message' => 'Masquerade NAT rule already exists',
                'already_exists' => true
            ]);
            return;
        }
        
        // Create masquerade NAT rule
        $result = $mikrotik->createMasqueradeNAT();
        
        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Masquerade NAT rule created successfully',
                'created' => true
            ]);
        } else {
            throw new Exception('Failed to create masquerade NAT rule');
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

function getSystemResource() {
    
    try {
        $mikrotik = new MikroTikAPI();
        $result = $mikrotik->getSystemResource();
        
        if ($result && is_array($result) && !empty($result)) {
            // If result is array, get first element
            $data = isset($result[0]) ? $result[0] : $result;
            
            echo json_encode([
                'success' => true,
                'data' => $data
            ]);
        } else {
            throw new Exception('Failed to get system resource data');
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

function getPPPStats() {
    
    // Clean output buffer before processing
    if (ob_get_level() > 0) {
        ob_get_clean();
    }
    
    try {
        $mikrotik = new MikroTikAPI();
        
        // Get all PPP secrets (total users)
        $secrets = $mikrotik->getPPPSecrets();
        $total_users = is_array($secrets) ? count($secrets) : 0;
        
        // Get active PPP sessions (online users)
        $active_sessions = $mikrotik->getPPPActiveSessions();
        $online_users = is_array($active_sessions) ? count($active_sessions) : 0;
        
        // Calculate offline users
        $offline_users = $total_users - $online_users;
        if ($offline_users < 0) $offline_users = 0;
        
        $stats = [
            'total' => $total_users,
            'online' => $online_users,
            'offline' => $offline_users
        ];
        
        
        echo json_encode([
            'success' => true,
            'data' => $stats
        ]);
        
    } catch (Exception $e) {
        
        // If it's a connection error, return mock data
        if (strpos($e->getMessage(), 'Cannot connect to MikroTik') !== false || strpos($e->getMessage(), 'cURL error') !== false) {
            echo json_encode([
                'success' => true,
                'data' => [
                    'total' => 2,
                    'online' => 1,
                    'offline' => 1
                ],
                'mock_data' => true
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [
                    'total' => 0,
                    'online' => 0,
                    'offline' => 0
                ]
            ]);
        }
    }
}

function getPPPLogs() {
    
    try {
        $mikrotik = new MikroTikAPI();
        
        // Get PPP logs - try to get logs related to PPP connections
        $logs = $mikrotik->getPPPLogs();
        
        if (!$logs || !is_array($logs)) {
            // If no specific PPP logs, try general system logs
            $logs = $mikrotik->getSystemLogs();
        }
        
        // Filter and format logs for PPP-related entries
        $ppp_logs = [];
        
        if (is_array($logs)) {
            foreach ($logs as $log) {
                // Check if log contains PPP-related keywords
                $message = isset($log['message']) ? $log['message'] : 
                          (isset($log['topics']) ? $log['topics'] : '');
                          
                if (stripos($message, 'ppp') !== false || 
                    stripos($message, 'l2tp') !== false ||
                    stripos($message, 'pptp') !== false ||
                    stripos($message, 'sstp') !== false ||
                    stripos($message, 'login') !== false ||
                    stripos($message, 'logout') !== false ||
                    stripos($message, 'connect') !== false ||
                    stripos($message, 'disconnect') !== false) {
                    
                    $ppp_logs[] = [
                        'time' => $log['time'] ?? date('H:i:s'),
                        'message' => $message,
                        'topics' => $log['topics'] ?? 'ppp'
                    ];
                }
            }
        }
        
        // Limit to last 50 entries and reverse for newest first
        $ppp_logs = array_slice(array_reverse($ppp_logs), 0, 50);
        
        
        echo json_encode([
            'success' => true,
            'data' => $ppp_logs
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage(),
            'data' => []
        ]);
    }
}

function getPPPUsers() {
    
    // Clean output buffer before processing
    if (ob_get_level() > 0) {
        ob_get_clean();
    }
    
    try {
        $mikrotik = new MikroTikAPI();
        
        // Get all PPP secrets (users)
        $users = $mikrotik->getPPPSecrets();
        
        if (!is_array($users)) {
            $users = [];
        }
        
        
        $response = [
            'success' => true,
            'data' => $users
        ];
        echo json_encode($response);
        
    } catch (Exception $e) {
                
        // If it's a connection error, return mock data for development
        if (strpos($e->getMessage(), 'Cannot connect to MikroTik') !== false || strpos($e->getMessage(), 'cURL error') !== false) {
            echo json_encode([
                'success' => true,
                'data' => [
                    [
                        '.id' => '*1',
                        'name' => 'user5377',
                        'password' => 'pass123',
                        'service' => 'sstp',
                        'remote-address' => '10.51.0.100',
                        'disabled' => 'false'
                    ],
                    [
                        '.id' => '*2', 
                        'name' => 'user8291',
                        'password' => 'mypass456',
                        'service' => 'l2tp',
                        'remote-address' => '10.51.0.101',
                        'disabled' => 'false'
                    ]
                ],
                'mock_data' => true
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => []
            ]);
        }
    }
}

function getPPPProfiles() {

    try {
        $mikrotik = new MikroTikAPI();

        // Get all PPP profiles
        $profiles = $mikrotik->getPPPProfiles();

        if (!is_array($profiles)) {
            $profiles = [];
        }


        echo json_encode([
            'success' => true,
            'data' => $profiles
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage(),
            'data' => []
        ]);
    }
}

function getPPPActive() {
    try {
        $mikrotik = new MikroTikAPI();

        // Get all active PPP sessions WITH traffic statistics from interfaces
        $activeSessions = $mikrotik->getPPPActiveWithTraffic();

        if (!is_array($activeSessions)) {
            $activeSessions = [];
        }

        echo json_encode([
            'success' => true,
            'data' => $activeSessions
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage(),
            'data' => []
        ]);
    }
}

function getAvailableServices() {
    try {
        $mikrotik = new MikroTikAPI();

        // Get VPN service status from MikroTik
        $services = $mikrotik->getVPNServicesStatus();

        // Filter only enabled services
        $availableServices = [];
        foreach ($services as $service => $status) {
            if ($status === true || $status === 'enabled') {
                $availableServices[] = strtolower($service);
            }
        }

        echo json_encode([
            'success' => true,
            'data' => $availableServices
        ]);

    } catch (Exception $e) {
        // Return default services if error
        echo json_encode([
            'success' => true,
            'data' => ['l2tp', 'pptp', 'sstp', 'any']
        ]);
    }
}

function getNATByUser($input) {
    try {
        // Validate required field
        if (empty($input['username'])) {
            throw new Exception("Username is required");
        }

        $mikrotik = new MikroTikAPI();
        $username = $input['username'];

        // Get NAT rules filtered by comment (username)
        $nat_rules = $mikrotik->getFirewallNATByComment($username);

        if (!is_array($nat_rules)) {
            $nat_rules = [];
        }

        ob_clean();
        echo json_encode([
            'success' => true,
            'data' => $nat_rules
        ]);

    } catch (Exception $e) {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage(),
            'data' => []
        ]);
    }
}

function addPPPUser($input) {
    
    try {
        $mikrotik = new MikroTikAPI();
        
        // Validate required fields
        $required_fields = ['name', 'password', 'service'];
        foreach ($required_fields as $field) {
            if (empty($input[$field])) {
                throw new Exception("Field '$field' is required");
            }
        }
        
        $user_data = [
            'name' => $input['name'],
            'password' => $input['password'],
            'service' => $input['service']
        ];
        
        // Auto-determine profile based on service
        $service = strtolower($input['service']);
        $profile_mapping = [
            'l2tp' => 'L2TP',
            'pptp' => 'PPTP', 
            'sstp' => 'SSTP',
            'any' => 'default'
        ];
        
        if (isset($profile_mapping[$service])) {
            $user_data['profile'] = $profile_mapping[$service];
        }
        
        // Handle remote address - auto-assign if not provided
        if (!empty($input['remote_address'])) {
            $user_data['remote-address'] = $input['remote_address'];
        } else {
            // Auto-assign IP if not provided
            try {
                $available_ip = $mikrotik->getNextAvailableIP($service);
                if ($available_ip) {
                    $user_data['remote-address'] = $available_ip;
                } else {
                }
            } catch (Exception $e) {
                // Continue without remote-address - let MikroTik handle it
            }
        }
        
        // Create PPP user
        $result = $mikrotik->addPPPSecret($user_data);
        
        if (!$result) {
            throw new Exception('Failed to create PPP user');
        }
        
        // Create firewall NAT rules if requested and ports provided
        $nat_results = [];

        // Use the actual remote address assigned (either from input or auto-assigned)
        $remote_address = $user_data['remote-address'] ?? $input['remote_address'] ?? null;

        if (isset($input['createNatRule']) && !empty($remote_address)) {
            
            // If no ports specified, use default ports 8291 and 8728
            if (empty($input['ports'])) {
                $ports = ['8291', '8728'];
            } else {
                $ports = explode(',', $input['ports']);
                $ports = array_map('trim', $ports);
                $ports = array_filter($ports);
            }
            
            // Check if createMultipleNat is enabled
            $createMultipleNat = isset($input['createMultipleNat']) && $input['createMultipleNat'];
            
            if ($createMultipleNat && count($ports) > 1) {
                // Mode 1: Create separate NAT rules for each port with same remote address
                // This is useful when you want individual port forwarding rules
                foreach ($ports as $port) {
                    if (!is_numeric($port) || $port < 1 || $port > 65535) {
                        continue; // Skip invalid ports
                    }
                    
                    // Generate random external port for each internal port
                    try {
                        $external_port = generateRandomPort($mikrotik);
                    } catch (Exception $e) {
                        $external_port = rand(1000, 9999); // Fallback
                    }
                    
                    $nat_data = [
                        'chain' => 'dstnat',
                        'action' => 'dst-nat',
                        'protocol' => 'tcp',
                        'dst-port' => (string)$external_port,
                        'to-addresses' => $remote_address,
                        'to-ports' => (string)$port,
                        'comment' => $input['name'] . ' (Port ' . $port . ')'
                    ];
                    
                    try {
                        $nat_result = $mikrotik->addFirewallNAT($nat_data);
                        
                        if ($nat_result !== false && $nat_result !== null) {
                            $nat_results[] = [
                                'external_port' => $external_port,
                                'internal_port' => $port,
                                'success' => true,
                                'type' => 'individual'
                            ];
                        } else {
                            $nat_results[] = [
                                'external_port' => $external_port,
                                'internal_port' => $port,
                                'success' => false,
                                'error' => 'NAT creation returned false/null',
                                'type' => 'individual'
                            ];
                        }
                    } catch (Exception $e) {
                        $nat_results[] = [
                            'external_port' => $external_port,
                            'internal_port' => $port,
                            'success' => false,
                            'error' => $e->getMessage(),
                            'type' => 'individual'
                        ];
                    }
                }
            } else {
                // Mode 2: Create single NAT rule with port range (default behavior)
                // This is more efficient when you want to forward multiple consecutive ports
                
                if (count($ports) == 1) {
                    // Single port - create one rule
                    $port = $ports[0];
                    if (is_numeric($port) && $port >= 1 && $port <= 65535) {
                        try {
                            $external_port = generateRandomPort($mikrotik);
                        } catch (Exception $e) {
                            $external_port = rand(1000, 9999);
                        }
                        
                        $nat_data = [
                            'chain' => 'dstnat',
                            'action' => 'dst-nat',
                            'protocol' => 'tcp',
                            'dst-port' => (string)$external_port,
                            'to-addresses' => $remote_address,
                            'to-ports' => (string)$port,
                            'comment' => $input['name']
                        ];
                        
                        try {
                            $nat_result = $mikrotik->addFirewallNAT($nat_data);
                            
                            if ($nat_result !== false && $nat_result !== null) {
                                $nat_results[] = [
                                    'external_port' => $external_port,
                                    'internal_port' => $port,
                                    'success' => true,
                                    'type' => 'single'
                                ];
                            }
                        } catch (Exception $e) {
                            $nat_results[] = [
                                'external_port' => $external_port,
                                'internal_port' => $port,
                                'success' => false,
                                'error' => $e->getMessage(),
                                'type' => 'single'
                            ];
                        }
                    }
                } else {
                    // Multiple ports - create one rule per port (default behavior)
                    foreach ($ports as $port) {
                        if (!is_numeric($port) || $port < 1 || $port > 65535) {
                            continue;
                        }
                        
                        try {
                            $external_port = generateRandomPort($mikrotik);
                        } catch (Exception $e) {
                            $external_port = rand(1000, 9999);
                        }
                        
                        $nat_data = [
                            'chain' => 'dstnat',
                            'action' => 'dst-nat',
                            'protocol' => 'tcp',
                            'dst-port' => (string)$external_port,
                            'to-addresses' => $remote_address,
                            'to-ports' => (string)$port,
                            'comment' => $input['name']
                        ];
                        
                        try {
                            $nat_result = $mikrotik->addFirewallNAT($nat_data);
                            
                            if ($nat_result !== false && $nat_result !== null) {
                                $nat_results[] = [
                                    'external_port' => $external_port,
                                    'internal_port' => $port,
                                    'success' => true,
                                    'type' => 'default'
                                ];
                            } else {
                                $nat_results[] = [
                                    'external_port' => $external_port,
                                    'internal_port' => $port,
                                    'success' => false,
                                    'error' => 'NAT creation returned false/null',
                                    'type' => 'default'
                                ];
                            }
                        } catch (Exception $e) {
                            $nat_results[] = [
                                'external_port' => $external_port,
                                'internal_port' => $port,
                                'success' => false,
                                'error' => $e->getMessage(),
                                'type' => 'default'
                            ];
                        }
                    }
                }
            }
        }
        
        $message = 'PPP user created successfully';
        if (!empty($nat_results)) {
            $successful_nats = array_filter($nat_results, function($nat) {
                return $nat['success'];
            });
            
            $failed_nats = array_filter($nat_results, function($nat) {
                return !$nat['success'];
            });
            
            if (count($successful_nats) > 0) {
                // Check if we used individual NAT mode
                $individual_nats = array_filter($successful_nats, function($nat) {
                    return isset($nat['type']) && $nat['type'] === 'individual';
                });
                
                if (count($individual_nats) > 0) {
                    $message .= '. ' . count($successful_nats) . ' individual NAT rule(s) created (separate rules for each port).';
                } else {
                    $message .= '. ' . count($successful_nats) . ' NAT rule(s) created.';
                }
            }
            
            if (count($failed_nats) > 0) {
                $error_details = array_map(function($nat) {
                    return "Port {$nat['internal_port']}: {$nat['error']}";
                }, $failed_nats);
                
                $message .= ' WARNING: ' . count($failed_nats) . ' NAT rule(s) failed: ' . implode('; ', $error_details);
            }
        }

        // Create Netwatch entry for the user's remote address
        $netwatch_created = false;
        $netwatch_error = null;
        if (!empty($user_data['remote-address'])) {
            try {
                error_log("[ADD PPP USER] Attempting to create netwatch for IP: " . $user_data['remote-address']);
                $netwatch_result = $mikrotik->createNetwatch($user_data['remote-address'], $input['name']);

                if ($netwatch_result !== false) {
                    $netwatch_created = true;
                    $message .= '. Netwatch monitoring enabled';
                    error_log("[ADD PPP USER] Netwatch created successfully");
                } else {
                    $netwatch_error = "Netwatch creation returned false";
                    error_log("[ADD PPP USER] Netwatch creation returned false");
                }
            } catch (Exception $e) {
                $netwatch_error = $e->getMessage();
                error_log("[ADD PPP USER] Netwatch exception: " . $e->getMessage());
                // Continue - netwatch is optional
            }
        } else {
            error_log("[ADD PPP USER] No remote address available for netwatch");
        }

        ob_clean();
        echo json_encode([
            'success' => true,
            'message' => $message,
            'nat_results' => $nat_results,
            'netwatch_created' => $netwatch_created,
            'netwatch_error' => $netwatch_error
        ]);

    } catch (Exception $e) {
        error_log("[ADD PPP USER ERROR] " . $e->getMessage());
        error_log("[ADD PPP USER TRACE] " . $e->getTraceAsString());

        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage(),
            'debug' => [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]
        ]);
    }
}

function editPPPUser($input) {
    
    try {
        $mikrotik = new MikroTikAPI();
        
        if (empty($input['user_id'])) {
            throw new Exception('User ID is required');
        }
        
        $user_id = $input['user_id'];
        $update_data = [];
        
        // Add fields to update
        if (!empty($input['name'])) {
            $update_data['name'] = $input['name'];
        }
        
        if (!empty($input['service'])) {
            $update_data['service'] = $input['service'];
        }
        
        if (isset($input['remote_address'])) {
            if (empty($input['remote_address'])) {
                $update_data['remote-address'] = '';
            } else {
                $update_data['remote-address'] = $input['remote_address'];
            }
        }
        
        if (empty($update_data)) {
            throw new Exception('No data to update');
        }
        
        // Update PPP user
        $result = $mikrotik->updatePPPSecret($user_id, $update_data);
        
        if (!$result) {
            throw new Exception('Failed to update PPP user');
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'PPP user updated successfully'
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

function deletePPPUser($input) {
    
    try {
        $mikrotik = new MikroTikAPI();
        
        if (empty($input['user_id'])) {
            throw new Exception('User ID is required');
        }
        
        $user_id = $input['user_id'];
        
        // Get user info first to find related NAT rules - use list-based approach for reliability
        $user = null;
        $username = '';
        $remote_address = '';
        
        try {
            // Get all PPP users and find the one we want
            $ppp_users = $mikrotik->getPPPSecrets();
            if (is_array($ppp_users)) {
                foreach ($ppp_users as $ppp_user) {
                    if (isset($ppp_user['.id']) && $ppp_user['.id'] === $user_id) {
                        $user = $ppp_user;
                        break;
                    }
                }
            }
            
            if (!$user) {
                throw new Exception('PPP user not found with ID: ' . $user_id);
            }
            
            $username = $user['name'] ?? '';
            $remote_address = $user['remote-address'] ?? '';
            
            
        } catch (Exception $e) {
            throw new Exception('PPP user not found: ' . $e->getMessage());
        }
        
        // STEP 1: Delete Netwatch entry FIRST
        $netwatch_deleted = false;
        if (!empty($username)) {
            try {
                $netwatch_deleted = $mikrotik->deleteNetwatchByComment($username);
            } catch (Exception $e) {
                // Continue even if netwatch deletion fails
            }
        }

        // If not deleted by comment, try by IP
        if (!$netwatch_deleted && !empty($remote_address)) {
            try {
                $netwatch_deleted = $mikrotik->deleteNetwatchByHost($remote_address);
            } catch (Exception $e) {
                // Continue even if netwatch deletion fails
            }
        }

        // STEP 2: Delete NAT rules SECOND
        $nat_deleted = 0;

        // Try method 1: Delete by comment (username)
        if (!empty($username)) {
            try {
                $nat_deleted = $mikrotik->deleteFirewallNATByComment($username);
            } catch (Exception $e) {
            }
        }

        // Try method 2: Delete by IP address if method 1 found nothing
        if ($nat_deleted == 0 && !empty($remote_address)) {
            try {
                $nat_deleted = $mikrotik->deleteFirewallNATByIP($remote_address);
            } catch (Exception $e) {
            }
        }

        // STEP 3: Delete PPP user LAST (after Netwatch and NAT rules are deleted)
        try {
            $result = $mikrotik->deletePPPSecret($user_id);

            if (!$result) {
                throw new Exception('Failed to delete PPP user');
            }
        } catch (Exception $e) {
            throw new Exception('Failed to delete PPP user: ' . $e->getMessage());
        }

        $message = 'PPP user deleted successfully';
        if ($nat_deleted > 0) {
            $message .= " and $nat_deleted NAT rule(s) removed";
        } elseif (!empty($username)) {
            $message .= ' (no NAT rules found to remove)';
        }

        if ($netwatch_deleted) {
            $message .= ', Netwatch entry removed';
        }

        echo json_encode([
            'success' => true,
            'message' => $message,
            'nat_deleted' => $nat_deleted,
            'netwatch_deleted' => $netwatch_deleted
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

function togglePPPUserStatus($input) {
    
    try {
        $mikrotik = new MikroTikAPI();
        
        if (empty($input['user_id'])) {
            throw new Exception('User ID is required');
        }
        
        $user_id = $input['user_id'];
        
        // Get current user status using list-based approach for reliability
        $ppp_users = $mikrotik->getPPPSecrets();
        $user = null;
        
        if (is_array($ppp_users)) {
            foreach ($ppp_users as $ppp_user) {
                if (isset($ppp_user['.id']) && $ppp_user['.id'] === $user_id) {
                    $user = $ppp_user;
                    break;
                }
            }
        }
        
        if (!$user) {
            throw new Exception('User not found');
        }
        
        // Check current disabled status
        // MikroTik API may return 'true'/'false' as strings or boolean values
        $current_disabled = false;
        if (isset($user['disabled'])) {
            $current_disabled = ($user['disabled'] === 'true' || $user['disabled'] === true);
        }
        
        // Toggle status - if currently disabled, enable it; if enabled, disable it
        $new_disabled = !$current_disabled;
        
        // Update user with proper boolean values for MikroTik API
        $update_data = ['disabled' => $new_disabled];
        $result = $mikrotik->updatePPPSecret($user_id, $update_data);
        
        if (!$result) {
            throw new Exception('Failed to toggle user status');
        }
        
        $status = $new_disabled ? 'disabled' : 'enabled';
        $username = $user['name'] ?? 'User';
        
        echo json_encode([
            'success' => true,
            'message' => "User {$username} {$status} successfully",
            'status' => $status,
            'disabled' => $new_disabled
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

function bulkDeletePPPUsers($input) {
    
    try {
        $mikrotik = new MikroTikAPI();
        
        if (empty($input['user_ids']) || !is_array($input['user_ids'])) {
            throw new Exception('User IDs array is required');
        }
        
        $user_ids = $input['user_ids'];
        $deleted_count = 0;
        $nat_deleted_count = 0;
        $errors = [];
        
        // Get all PPP users once for efficiency
        $ppp_users = $mikrotik->getPPPSecrets();
        
        foreach ($user_ids as $user_id) {
            try {
                // Find user in the list
                $user = null;
                if (is_array($ppp_users)) {
                    foreach ($ppp_users as $ppp_user) {
                        if (isset($ppp_user['.id']) && $ppp_user['.id'] === $user_id) {
                            $user = $ppp_user;
                            break;
                        }
                    }
                }
                
                if (!$user) {
                    $errors[] = "User ID $user_id not found";
                    continue;
                }
                
                $username = $user['name'] ?? '';
                $remote_address = $user['remote-address'] ?? '';

                // STEP 1: Delete Netwatch entry FIRST
                if (!empty($username)) {
                    try {
                        $mikrotik->deleteNetwatchByComment($username);
                    } catch (Exception $e) {
                        // Continue even if netwatch deletion fails
                    }
                }

                // If not deleted by comment, try by IP
                if (!empty($remote_address)) {
                    try {
                        $mikrotik->deleteNetwatchByHost($remote_address);
                    } catch (Exception $e) {
                        // Continue even if netwatch deletion fails
                    }
                }

                // STEP 2: Delete NAT rules SECOND
                $nat_deleted = 0;

                // Try method 1: Delete by comment (username)
                if (!empty($username)) {
                    try {
                        $nat_deleted = $mikrotik->deleteFirewallNATByComment($username);
                    } catch (Exception $e) {
                    }
                }

                // Try method 2: Delete by IP if method 1 found nothing
                if ($nat_deleted == 0 && !empty($remote_address)) {
                    try {
                        $nat_deleted = $mikrotik->deleteFirewallNATByIP($remote_address);
                    } catch (Exception $e) {
                    }
                }

                $nat_deleted_count += $nat_deleted;

                // STEP 3: Delete PPP user LAST (after Netwatch and NAT rules are deleted)
                $result = $mikrotik->deletePPPSecret($user_id);
                
                if ($result) {
                    $deleted_count++;
                } else {
                    $errors[] = "Failed to delete user: $username (ID: $user_id)";
                }
                
            } catch (Exception $e) {
                $errors[] = "Error deleting user ID $user_id: " . $e->getMessage();
            }
        }
        
        if ($deleted_count > 0) {
            $message = "$deleted_count user(s) deleted successfully";
            
            if ($nat_deleted_count > 0) {
                $message .= " and $nat_deleted_count NAT rule(s) removed";
            }
            
            if (!empty($errors)) {
                $message .= ". Some errors occurred: " . implode(', ', $errors);
            }
            
            echo json_encode([
                'success' => true,
                'message' => $message,
                'deleted_count' => $deleted_count,
                'nat_deleted_count' => $nat_deleted_count,
                'errors' => $errors
            ]);
        } else {
            throw new Exception('No users could be deleted. Errors: ' . implode(', ', $errors));
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

function bulkTogglePPPUsers($input) {
    
    try {
        $mikrotik = new MikroTikAPI();
        
        if (empty($input['user_ids']) || !is_array($input['user_ids'])) {
            throw new Exception('User IDs array is required');
        }
        
        $user_ids = $input['user_ids'];
        $updated_count = 0;
        $errors = [];
        
        // Get all PPP users once for efficiency
        $ppp_users = $mikrotik->getPPPSecrets();
        
        foreach ($user_ids as $user_id) {
            try {
                // Find user in the list
                $user = null;
                if (is_array($ppp_users)) {
                    foreach ($ppp_users as $ppp_user) {
                        if (isset($ppp_user['.id']) && $ppp_user['.id'] === $user_id) {
                            $user = $ppp_user;
                            break;
                        }
                    }
                }
                
                if (!$user) {
                    $errors[] = "User with ID $user_id not found";
                    continue;
                }
                
                // Check current disabled status
                $current_disabled = false;
                if (isset($user['disabled'])) {
                    $current_disabled = ($user['disabled'] === 'true' || $user['disabled'] === true);
                }
                
                // Toggle status
                $new_disabled = !$current_disabled;
                
                // Update user with proper boolean values
                $result = $mikrotik->updatePPPSecret($user_id, ['disabled' => $new_disabled]);
                
                if ($result) {
                    $updated_count++;
                } else {
                    $errors[] = "Failed to update user: " . ($user['name'] ?? $user_id);
                }
            } catch (Exception $e) {
                $errors[] = "Error updating user $user_id: " . $e->getMessage();
            }
        }
        
        if ($updated_count > 0) {
            $message = "$updated_count user(s) status updated successfully";
            if (!empty($errors)) {
                $message .= ". Some errors occurred: " . implode(', ', $errors);
            }
            
            echo json_encode([
                'success' => true,
                'message' => $message,
                'updated_count' => $updated_count,
                'errors' => $errors
            ]);
        } else {
            throw new Exception('No users could be updated. Errors: ' . implode(', ', $errors));
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

function getUserDetails($input) {
    
    try {
        if (empty($input['user_id'])) {
            throw new Exception('User ID is required');
        }
        
        $user_id = $input['user_id'];
        
        $mikrotik = new MikroTikAPI();
        
        // Get user details - use simpler approach like debug version
        $ppp_users = $mikrotik->getPPPSecrets();
        $user = null;
        
        if (is_array($ppp_users)) {
            foreach ($ppp_users as $ppp_user) {
                if (isset($ppp_user['.id']) && $ppp_user['.id'] === $user_id) {
                    $user = $ppp_user;
                    break;
                }
            }
        }
        
        if (!$user) {
            throw new Exception('User not found');
        }
        
        // Get related NAT rules - search by remote-address (IP) instead of comment
        $nat_rules = [];
        if (!empty($user['remote-address'])) {
            try {
                // Primary search: by remote-address (to-addresses field)
                $nat_rules = $mikrotik->getFirewallNATByIP($user['remote-address']);
                
                
                // Fallback: if no NAT rules found by IP, try by comment (username)
                if (empty($nat_rules) && !empty($user['name'])) {
                    $nat_rules = $mikrotik->getFirewallNATByComment($user['name']);
                }
            } catch (Exception $e) {
                // Don't throw, just log and continue
            }
        }
        
        // Get MikroTik server IP from config
        try {
            $mikrotik_config = getConfig('mikrotik');
            $server_ip = $mikrotik_config['host'] ?? '[server_ip]';
        } catch (Exception $e) {
            $server_ip = '[server_ip]';
        }
        
        $response_data = [
            'user' => $user,
            'nat_rules' => $nat_rules,
            'server_ip' => $server_ip
        ];
        
        
        echo json_encode([
            'success' => true,
            'data' => $response_data
        ]);
        
    } catch (Exception $e) {
        
        // If it's a connection error, return mock data
        if (strpos($e->getMessage(), 'Cannot connect to MikroTik') !== false || strpos($e->getMessage(), 'cURL error') !== false) {
            // Get server IP from config
            try {
                $mikrotik_config = getConfig('mikrotik');
                $server_ip = $mikrotik_config['host'] ?? '103.187.147.74';
            } catch (Exception $configError) {
                $server_ip = '103.187.147.74';
            }
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'user' => [
                        '.id' => $input['user_id'],
                        'name' => 'user5377',
                        'password' => 'pass123',
                        'service' => 'sstp',
                        'remote-address' => '10.51.0.100',
                        'disabled' => 'false'
                    ],
                    'nat_rules' => [
                        [
                            'dst-port' => '8291',
                            'to-addresses' => '10.51.0.100',
                            'comment' => 'user5377'
                        ],
                        [
                            'dst-port' => '8728', 
                            'to-addresses' => '10.51.0.100',
                            'comment' => 'user5377'
                        ]
                    ],
                    'server_ip' => $server_ip
                ],
                'mock_data' => true
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
}

function getUserPassword($input) {
    
    try {
        $mikrotik = new MikroTikAPI();
        
        if (empty($input['user_id'])) {
            throw new Exception('User ID is required');
        }
        
        $user_id = $input['user_id'];
        
        // Get user details
        $user = $mikrotik->getPPPSecret($user_id);
        if (!$user) {
            throw new Exception('User not found');
        }
        
        echo json_encode([
            'success' => true,
            'data' => [
                'password' => $user['password'] ?? 'N/A'
            ]
        ]);
        
    } catch (Exception $e) {
        
        // If it's a connection error, return mock data
        if (strpos($e->getMessage(), 'Cannot connect to MikroTik') !== false || strpos($e->getMessage(), 'cURL error') !== false) {
            echo json_encode([
                'success' => true,
                'data' => [
                    'password' => 'pass123'
                ],
                'mock_data' => true
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
}

function getAvailableIP($input) {
    
    try {
        $mikrotik = new MikroTikAPI();
        
        if (empty($input['service'])) {
            throw new Exception('Service is required');
        }
        
        $service = strtolower($input['service']);
        
        // Get available IP for the service
        $available_ip = $mikrotik->getNextAvailableIP($service);
        
        if (!$available_ip) {
            throw new Exception('No available IP found for service: ' . $service);
        }
        
        echo json_encode([
            'success' => true,
            'data' => [
                'ip' => $available_ip,
                'service' => $service
            ]
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * Get Netwatch entries
 */
function getNetwatch() {
    try {
        $mikrotik = new MikroTikAPI();
        $entries = $mikrotik->getNetwatchEntries();

        ob_clean();
        echo json_encode([
            'success' => true,
            'data' => $entries ?? []
        ]);
    } catch (Exception $e) {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * Add Netwatch entry
 */
function addNetwatch($input) {
    try {
        $host = $input['host'] ?? null;
        $name = $input['name'] ?? '';
        $interval = $input['interval'] ?? '10s';
        $timeout = $input['timeout'] ?? '5s';

        if (empty($host)) {
            throw new Exception('Host is required');
        }

        $mikrotik = new MikroTikAPI();

        $data = [
            'host' => $host,
            'interval' => $interval,
            'timeout' => $timeout
        ];

        if (!empty($name)) {
            $data['comment'] = $name;
        }

        $result = $mikrotik->addNetwatch($data);

        ob_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Netwatch host added successfully',
            'data' => $result
        ]);
    } catch (Exception $e) {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * Delete Netwatch entry
 */
function deleteNetwatch($input) {
    try {
        $id = $input['id'] ?? null;

        if (empty($id)) {
            throw new Exception('Netwatch ID is required');
        }

        $mikrotik = new MikroTikAPI();
        $mikrotik->deleteNetwatch($id);

        ob_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Netwatch host deleted successfully'
        ]);
    } catch (Exception $e) {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

function generateRandomPort($mikrotik) {
    $max_attempts = 100;
    $attempt = 0;

    do {
        $port = rand(1000, 9999);
        $attempt++;

        try {
            // Check if port is already used in NAT rules
            $existing_nats = $mikrotik->getFirewallNAT();
            $port_exists = false;

            if (is_array($existing_nats)) {
                foreach ($existing_nats as $nat) {
                    if (isset($nat['dst-port']) && $nat['dst-port'] == $port) {
                        $port_exists = true;
                        break;
                    }
                }
            }
            
            if (!$port_exists) {
                return $port;
            }
        } catch (Exception $e) {
            // If we can't check, just return the random port
            return $port;
        }
    } while ($attempt < $max_attempts);
    
    // If all attempts failed, return a random port anyway
    return rand(1000, 9999);
}


?>