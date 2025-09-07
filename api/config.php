<?php
header('Content-Type: application/json');
session_start();

require_once '../includes/auth.php';
require_once '../includes/config.php';

// Check authentication for all API calls
requireAuth();

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($method) {
        case 'GET':
            handleGetRequest($action);
            break;
            
        case 'POST':
            handlePostRequest();
            break;
            
        default:
            throw new Exception('Method not allowed');
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function handleGetRequest($action) {
    switch ($action) {
        case 'get_all':
            getAllConfig();
            break;
            
        case 'get_section':
            $section = $_GET['section'] ?? '';
            if (empty($section)) {
                throw new Exception('Section parameter required');
            }
            getConfigSection($section);
            break;
            
        case 'get_password':
            $section = $_GET['section'] ?? '';
            $key = $_GET['key'] ?? '';
            if (empty($section) || empty($key)) {
                throw new Exception('Section and key parameters required');
            }
            getPassword($section, $key);
            break;
            
        case 'get_auth_credentials':
            getAuthCredentials();
            break;
            
        case 'get_mikrotik_credentials':
            getMikrotikCredentials();
            break;
            
        default:
            throw new Exception('Invalid action');
    }
}

function handlePostRequest() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON input');
    }
    
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'update_section':
            updateConfigSectionHandler($input);
            break;
            
        case 'update_key':
            updateConfigKeyHandler($input);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
}

function getAllConfig() {
    try {
        $config = getConfig();
        
        if ($config === null) {
            // If config doesn't exist, create default and return it
            global $config_manager;
            $config = $config_manager->loadConfig();
        }
        
        // Remove sensitive data before sending
        $safe_config = $config;
        if (isset($safe_config['auth']['password'])) {
            $safe_config['auth']['password'] = !empty($config['auth']['password']) ? '••••••••' : '';
        }
        if (isset($safe_config['mikrotik']['password'])) {
            $safe_config['mikrotik']['password'] = !empty($config['mikrotik']['password']) ? '••••••••' : '';
        }
        if (isset($safe_config['telegram']['bot_token'])) {
            $safe_config['telegram']['bot_token'] = !empty($config['telegram']['bot_token']) ? 
                substr($config['telegram']['bot_token'], 0, 10) . '••••••••' : '';
        }
        
        echo json_encode([
            'success' => true,
            'config' => $safe_config
        ]);
    } catch (Exception $e) {
        throw new Exception('Failed to load configuration: ' . $e->getMessage());
    }
}

function getConfigSection($section) {
    try {
        $config = getConfig($section);
        
        if ($config === null) {
            throw new Exception('Configuration section not found');
        }
        
        echo json_encode([
            'success' => true,
            'data' => $config
        ]);
    } catch (Exception $e) {
        throw new Exception('Failed to load configuration section: ' . $e->getMessage());
    }
}

function getPassword($section, $key) {
    try {
        $config = getConfig($section);
        
        if ($config === null) {
            throw new Exception('Configuration section not found');
        }
        
        if (!isset($config[$key])) {
            throw new Exception('Password key not found');
        }
        
        echo json_encode([
            'success' => true,
            'password' => $config[$key]
        ]);
    } catch (Exception $e) {
        throw new Exception('Failed to get password: ' . $e->getMessage());
    }
}

function updateConfigSectionHandler($input) {
    try {
        $section = $input['section'] ?? '';
        $data = $input['data'] ?? [];
        
        if (empty($section)) {
            throw new Exception('Section parameter required');
        }
        
        if (empty($data)) {
            throw new Exception('Data parameter required');
        }
        
        // Special handling for auth section - selective update
        if ($section === 'auth') {
            $existing_auth = getConfig('auth');
            
            // If username is not provided, keep existing
            if (!isset($data['username']) || empty($data['username'])) {
                if ($existing_auth && isset($existing_auth['username'])) {
                    $data['username'] = $existing_auth['username'];
                }
            }
            
            // If password is not provided, keep existing
            if (!isset($data['password']) || empty($data['password'])) {
                if ($existing_auth && isset($existing_auth['password'])) {
                    $data['password'] = $existing_auth['password'];
                    $data['password_hash'] = $existing_auth['password_hash'];
                }
            } else {
                // Store both plaintext and hash for new password
                $data['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
                // $data['password'] already contains plaintext
            }
        }
        
        $result = updateConfigSection($section, $data);
        
        if (!$result) {
            throw new Exception('Failed to save configuration');
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Configuration updated successfully'
        ]);
    } catch (Exception $e) {
        throw new Exception('Failed to update configuration: ' . $e->getMessage());
    }
}

function getAuthCredentials() {
    try {
        
        $auth_config = getConfig('auth');
        
        if (!$auth_config) {
            // Return default credentials if no config exists
            $credentials = [
                'username' => 'user1234',
                'password' => 'mostech' // Default password
            ];
        } else {
            $credentials = [
                'username' => $auth_config['username'] ?? 'user1234',
                'password' => $auth_config['password'] ?? 'mostech' // Return actual password
            ];
        }
        
        
        echo json_encode([
            'success' => true,
            'credentials' => $credentials
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to get auth credentials: ' . $e->getMessage());
    }
}

function getMikrotikCredentials() {
    try {
        $mikrotik_config = getConfig('mikrotik');
        
        if (!$mikrotik_config) {
            // Return empty credentials if no config exists
            $credentials = [
                'host' => '',
                'username' => '',
                'password' => '',
                'port' => '443',
                'use_ssl' => true
            ];
        } else {
            $credentials = [
                'host' => $mikrotik_config['host'] ?? '',
                'username' => $mikrotik_config['username'] ?? '',
                'password' => $mikrotik_config['password'] ?? '', // Return actual password
                'port' => $mikrotik_config['port'] ?? '443',
                'use_ssl' => $mikrotik_config['use_ssl'] ?? true
            ];
            
            // If password is already masked with bullets, we can't retrieve original
            // In this case, return empty so user knows to enter new password
            if ($credentials['password'] === '••••••••') {
                $credentials['password'] = '';
                $credentials['password_masked'] = true;
            } else {
                $credentials['password_masked'] = false;
            }
        }
        
        echo json_encode([
            'success' => true,
            'credentials' => $credentials
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to get MikroTik credentials: ' . $e->getMessage());
    }
}

function updateConfigKeyHandler($input) {
    try {
        $section = $input['section'] ?? '';
        $key = $input['key'] ?? '';
        $value = $input['value'] ?? '';
        
        if (empty($section) || empty($key)) {
            throw new Exception('Section and key parameters required');
        }
        
        $result = updateConfig($section, $key, $value);
        
        if (!$result) {
            throw new Exception('Failed to save configuration');
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Configuration updated successfully'
        ]);
    } catch (Exception $e) {
        throw new Exception('Failed to update configuration: ' . $e->getMessage());
    }
}
?>