<?php

class ConfigManager {
    private $config_file;
    private $key_file;
    private $encryption_key;
    
    public function __construct() {
        $this->config_file = __DIR__ . '/../config/config.json.enc';
        $this->key_file = __DIR__ . '/../config/encryption.key';
        $this->initializeEncryption();
    }
    
    private function initializeEncryption() {
        // Create config directory if it doesn't exist
        $config_dir = dirname($this->config_file);
        if (!file_exists($config_dir)) {
            mkdir($config_dir, 0755, true);
        }
        
        // Generate or load encryption key
        if (!file_exists($this->key_file)) {
            $this->encryption_key = random_bytes(32); // 256-bit key for AES-256
            file_put_contents($this->key_file, base64_encode($this->encryption_key));
            chmod($this->key_file, 0600); // Read/write for owner only
        } else {
            $this->encryption_key = base64_decode(file_get_contents($this->key_file));
        }
        
        // Initialize default config if doesn't exist
        if (!file_exists($this->config_file)) {
            $this->createDefaultConfig();
        }
    }
    
    private function createDefaultConfig() {
        $default_config = [
            'auth' => [
                'username' => 'user1234',
                'password' => 'mostech', // Store plaintext for admin retrieval
                'password_hash' => password_hash('mostech', PASSWORD_DEFAULT) // Keep hash for verification
            ],
            'mikrotik' => [
                'host' => '',
                'username' => '',
                'password' => '',
                'port' => '443',
                'use_ssl' => true
            ],
            'telegram' => [
                'bot_token' => '',
                'chat_id' => '',
                'enabled' => false
            ],
            'system' => [
                'session_timeout' => 3600, // 60 minutes
                'app_version' => '1.69',
                'app_name' => 'MikReMan'
            ],
            'services' => [
                'l2tp' => false,
                'pptp' => false,
                'sstp' => false
            ]
        ];
        
        $result = $this->saveConfig($default_config);
        if (!$result) {
            throw new Exception('Failed to create default configuration file');
        }
        
        return $default_config;
    }
    
    public function encrypt($data) {
        $iv = random_bytes(16); // AES block size
        $encrypted = openssl_encrypt(json_encode($data), 'AES-256-CBC', $this->encryption_key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }
    
    public function decrypt($encrypted_data) {
        $data = base64_decode($encrypted_data);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $this->encryption_key, 0, $iv);
        return json_decode($decrypted, true);
    }
    
    public function loadConfig() {
        if (!file_exists($this->config_file)) {
            // Create default config if file doesn't exist
            return $this->createDefaultConfig();
        }
        
        try {
            $encrypted_data = file_get_contents($this->config_file);
            if ($encrypted_data === false) {
                throw new Exception('Failed to read configuration file');
            }
            
            if (empty($encrypted_data)) {
                // If file is empty, recreate default config
                return $this->createDefaultConfig();
            }
            
            $decrypted = $this->decrypt($encrypted_data);
            if ($decrypted === null || $decrypted === false) {
                throw new Exception('Failed to decrypt configuration file');
            }
            
            return $decrypted;
        } catch (Exception $e) {
            // If decryption fails, backup old file and create new default config
            if (file_exists($this->config_file)) {
                rename($this->config_file, $this->config_file . '.backup.' . time());
            }
            return $this->createDefaultConfig();
        }
    }
    
    public function saveConfig($config) {
        $encrypted_data = $this->encrypt($config);
        $result = file_put_contents($this->config_file, $encrypted_data);
        if ($result !== false) {
            chmod($this->config_file, 0600); // Read/write for owner only
        }
        return $result !== false;
    }
    
    public function getConfig($section = null, $key = null) {
        $config = $this->loadConfig();
        
        if ($section === null) {
            return $config;
        }
        
        if (!isset($config[$section])) {
            return null;
        }
        
        if ($key === null) {
            return $config[$section];
        }
        
        return $config[$section][$key] ?? null;
    }
    
    public function updateConfig($section, $key, $value) {
        $config = $this->loadConfig();
        
        if ($config === null) {
            return false;
        }
        
        if (!isset($config[$section])) {
            $config[$section] = [];
        }
        
        $config[$section][$key] = $value;
        
        return $this->saveConfig($config);
    }
    
    public function updateSection($section, $data) {
        $config = $this->loadConfig();
        
        if ($config === null) {
            return false;
        }
        
        $config[$section] = array_merge($config[$section] ?? [], $data);
        
        return $this->saveConfig($config);
    }
}

// Global config instance
$config_manager = new ConfigManager();

// Helper functions
function getConfig($section = null, $key = null) {
    global $config_manager;
    return $config_manager->getConfig($section, $key);
}

function updateConfig($section, $key, $value) {
    global $config_manager;
    return $config_manager->updateConfig($section, $key, $value);
}

function updateConfigSection($section, $data) {
    global $config_manager;
    return $config_manager->updateSection($section, $data);
}
?>