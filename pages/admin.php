<?php
// Security configurations
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_strict_mode', 1);

session_start();

require_once '../includes/config.php';
require_once '../includes/auth.php';

// Constants
define('SESSION_TIMEOUT', 3600); // 60 minutes
define('MAX_FORM_ATTEMPTS', 10);
define('FORM_LOCKOUT_TIME', 300); // 5 minutes

// Security functions
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function sanitizeOutput($data, $context = 'html') {
    if (is_array($data)) {
        return array_map(function($item) use ($context) {
            return sanitizeOutput($item, $context);
        }, $data);
    }
    
    switch ($context) {
        case 'html':
            return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        case 'js':
            return json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        case 'url':
            return urlencode($data);
        default:
            return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }
}

// Check authentication
checkSession();

// Session security check
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
    session_unset();
    session_destroy();
    header('Location: ../index.php?timeout=1');
    exit;
}

// Regenerate session ID periodically for security
if (!isset($_SESSION['created']) || (time() - $_SESSION['created']) > 1800) {
    session_regenerate_id(true);
    $_SESSION['created'] = time();
}

// Update last activity
$_SESSION['last_activity'] = time();

// Page variables
$current_page = 'admin';
$page_title = 'Admin';
$page_subtitle = 'Manage MikroTik settings and system configuration';

// Generate CSRF token
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-Frame-Options" content="DENY">
    <meta http-equiv="X-XSS-Protection" content="1; mode=block">
    <meta name="referrer" content="strict-origin-when-cross-origin">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo sanitizeOutput($page_title); ?> - Management</title>
    <link rel="icon" href="../favicon.ico" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body class="admin-body">
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar" id="sidebar">
                <div class="sidebar-header">
                    <div class="brand-container">
                        <div class="brand-icon">
                            <i class="bi bi-shield-lock-fill"></i>
                        </div>
                        <a href="admin.php" class="sidebar-brand">
                            <span class="brand-text">VPN</span>
                            <small class="brand-subtitle">Remote</small>
                        </a>
                    </div>
                </div>
                
                <!-- Mobile menu toggle -->
                <button class="btn btn-outline-secondary d-md-none mb-3" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarNav" aria-expanded="false">
                    <i class="bi bi-list"></i> Menu
                </button>
                
                <nav class="sidebar-nav collapse d-md-block" id="sidebarNav">
                    <ul class="nav nav-pills flex-column">
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current_page === 'admin' ? 'active' : ''; ?>" href="admin.php">
                                <i class="bi bi-gear-fill"></i>
                                <span>Configuration</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current_page === 'dashboard' ? 'active' : ''; ?>" href="dashboard.php">
                                <i class="bi bi-speedometer2"></i>
                                <span>Dashboard</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current_page === 'ppp' ? 'active' : ''; ?>" href="ppp.php">
                                <i class="bi bi-people-fill"></i>
                                <span>PPP Users</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current_page === 'monitoring' ? 'active' : ''; ?>" href="monitoring.php">
                                <i class="bi bi-activity"></i>
                                <span>Monitoring</span>
                            </a>
                        </li>

                        <li class="nav-divider"></li>
                        
                        <li class="nav-item">
                            <a class="nav-link logout-link" href="logout.php">
                                <i class="bi bi-box-arrow-right"></i>
                                <span>Logout</span>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <!-- Enhanced Page Header -->
                <div class="page-header">
                    <div class="header-content">
                        <div class="header-main">
                            <div class="header-icon">
                                <i class="bi bi-gear-fill"></i>
                            </div>
                            <div class="header-text">
                                <h1 class="page-title"><?php echo sanitizeOutput($page_title); ?></h1>
                                <p class="page-subtitle"><?php echo sanitizeOutput($page_subtitle); ?></p>
                            </div>
                        </div>
                        <div class="header-actions">
                            <!-- Connection status removed -->
                        </div>
                    </div>
                </div>
                
                <div id="alerts-container">
                    <?php if (isset($_SESSION['dashboard_error'])): ?>
                        <div class="alert alert-warning alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <strong>Dashboard Access:</strong> <?php echo sanitizeOutput($_SESSION['dashboard_error']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['dashboard_error']); ?>
                    <?php endif; ?>
                </div>
                
                <div class="row">
                    <!-- MikroTik Configuration -->
                    <div class="col-lg-6 mb-4">
                        <div class="card enhanced-card">
                            <div class="card-header">
                                <div class="card-header-content">
                                    <div class="card-icon">
                                        <i class="bi bi-router"></i>
                                    </div>
                                    <div class="card-title-group">
                                        <h5 class="card-title mb-0">MikroTik Configuration</h5>
                                        <small class="card-subtitle">Router connection settings</small>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <form id="mikrotik-form">
                                    <!-- CSRF Token -->
                                    <input type="hidden" name="csrf_token" value="<?php echo sanitizeOutput($csrf_token); ?>">
                                    <div class="mb-3">
                                        <label for="mt_host" class="form-label">IP MikroTik</label>
                                        <input type="text" class="form-control" id="mt_host" name="host" placeholder="192.168.1.1" required autocomplete="off" maxlength="253">
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="mt_username" class="form-label">Username</label>
                                            <input type="text" class="form-control" id="mt_username" name="username" placeholder="admin" required autocomplete="username" maxlength="50">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="mt_password" class="form-label">Password</label>
                                            <div class="input-group">
                                                <input type="password" class="form-control" id="mt_password" name="password" placeholder="Password" autocomplete="current-password" maxlength="255">
                                                <button class="btn btn-outline-secondary" type="button" id="toggleMtPassword" onclick="toggleMikrotikPasswordVisibility()">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="mt_port" class="form-label">Port</label>
                                        <input type="number" class="form-control" id="mt_port" name="port" value="443" min="1" max="65535" autocomplete="off">
                                    </div>
                                    
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-check-lg me-2"></i>
                                            Save
                                        </button>
                                        <button type="button" class="btn btn-outline-info" id="test-connection">
                                            <i class="bi bi-plug me-2"></i>
                                            Test
                                        </button>
                                        <button type="button" class="btn btn-success" id="connect-mikrotik" onclick="handleConnectClick()">
                                            <i class="bi bi-link-45deg me-2"></i>
                                            <span id="connect-text">Connect</span>
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" id="ssl-toggle" data-ssl="true">
                                            <i class="bi bi-shield-lock me-2"></i>
                                            HTTPS/SSL
                                        </button>
                                    </div>

                                    <!-- Connection Status Indicator -->
                                    <div class="mt-3" id="connection-status" style="display: none;">
                                        <div class="alert alert-success d-flex align-items-center mb-0" role="alert">
                                            <i class="bi bi-check-circle-fill me-2"></i>
                                            <div>
                                                <strong>Connected to MikroTik</strong>
                                                <br>
                                                <small id="connection-info">Router: Loading...</small>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Hidden input to maintain form compatibility -->
                                    <input type="hidden" id="mt_use_ssl" name="use_ssl" value="true">
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Authentication Settings -->
                    <div class="col-lg-6 mb-4">
                        <div class="card enhanced-card">
                            <div class="card-header">
                                <div class="card-header-content">
                                    <div class="card-icon">
                                        <i class="bi bi-shield-lock-fill"></i>
                                    </div>
                                    <div class="card-title-group">
                                        <h5 class="card-title mb-0">Login Settings</h5>
                                        <small class="card-subtitle">Web interface credentials</small>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <form id="auth-form">
                                    <!-- CSRF Token -->
                                    <input type="hidden" name="csrf_token" value="<?php echo sanitizeOutput($csrf_token); ?>">
                                    <div class="mb-3">
                                        <label for="auth_username" class="form-label">Username</label>
                                        <input type="text" class="form-control" id="auth_username" name="username" placeholder="Enter new username or leave empty to keep current" autocomplete="username" maxlength="50">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="auth_password" class="form-label">Password</label>
                                        <div class="input-group">
                                            <input type="password" class="form-control" id="auth_password" name="password" placeholder="" autocomplete="new-password" maxlength="255">
                                            <button class="btn btn-outline-secondary" type="button" id="toggleAuthPassword" onclick="togglePasswordVisibility()">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-warning">
                                        <i class="bi bi-shield-check me-2"></i>
                                        Update Login
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Telegram Bot Configuration -->
                    <div class="col-lg-6 mb-4">
                        <div class="card enhanced-card">
                            <div class="card-header">
                                <div class="card-header-content">
                                    <div class="card-icon telegram-icon">
                                        <i class="bi bi-telegram"></i>
                                    </div>
                                    <div class="card-title-group">
                                        <h5 class="card-title mb-0">Telegram Bot</h5>
                                        <small class="card-subtitle">Backup notification settings</small>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <form id="telegram-form">
                                    <!-- CSRF Token -->
                                    <input type="hidden" name="csrf_token" value="<?php echo sanitizeOutput($csrf_token); ?>">
                                    <div class="mb-3">
                                        <label for="bot_token" class="form-label">Bot Token</label>
                                        <div class="input-group">
                                            <input type="password" class="form-control" id="bot_token" name="bot_token" placeholder="123456789:ABC-DEF1234ghIkl-zyx57W2v1u123ew11" autocomplete="off" maxlength="500">
                                            <button class="btn btn-outline-secondary" type="button" id="toggleBotToken">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="chat_id" class="form-label">Chat ID</label>
                                        <input type="text" class="form-control" id="chat_id" name="chat_id" placeholder="-123456789" autocomplete="off" maxlength="20">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="telegram_enabled" name="enabled">
                                            <label class="form-check-label" for="telegram_enabled">
                                                Enable
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-success">
                                            <i class="bi bi-check-lg me-2"></i>
                                            Save
                                        </button>
                                        <button type="button" class="btn btn-outline-info" id="test-telegram">
                                            <i class="bi bi-send me-2"></i>
                                            Test Bot
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- VPN Services Control -->
                    <div class="col-lg-6 mb-4">
                        <div class="card enhanced-card">
                            <div class="card-header">
                                <div class="card-header-content">
                                    <div class="card-icon">
                                        <i class="bi bi-hdd-network-fill"></i>
                                    </div>
                                    <div class="card-title-group">
                                        <h5 class="card-title mb-0">VPN Services</h5>
                                        <small class="card-subtitle">Server management & control</small>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <!-- Service Toggle Cards -->
                                <div class="row mb-4">
                                    <div class="col-md-4 mb-3">
                                        <div class="service-card">
                                            <div class="service-header">
                                                <i class="bi bi-shield-fill-check service-icon l2tp"></i>
                                                <h6 class="service-name">L2TP Server</h6>
                                            </div>
                                            <button class="btn btn-outline-success service-btn" id="toggle-l2tp" data-service="l2tp">
                                                <i class="bi bi-power"></i>
                                                <span>Enable</span>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <div class="service-card">
                                            <div class="service-header">
                                                <i class="bi bi-shield-fill-plus service-icon pptp"></i>
                                                <h6 class="service-name">PPTP Server</h6>
                                            </div>
                                            <button class="btn btn-outline-success service-btn" id="toggle-pptp" data-service="pptp">
                                                <i class="bi bi-power"></i>
                                                <span>Enable</span>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <div class="service-card">
                                            <div class="service-header">
                                                <i class="bi bi-shield-fill-exclamation service-icon sstp"></i>
                                                <h6 class="service-name">SSTP Server</h6>
                                            </div>
                                            <button class="btn btn-outline-success service-btn" id="toggle-sstp" data-service="sstp">
                                                <i class="bi bi-power"></i>
                                                <span>Enable</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <hr>
                                
                                <!-- Profile Management -->
                                <div class="profile-section mb-4">
                                    <div class="section-header">
                                        <h6 class="section-title">Service Profiles</h6>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-4 mb-2">
                                            <button class="btn btn-outline-primary btn-sm w-100 profile-btn" id="create-l2tp-profile" data-service="l2tp">
                                                <i class="bi bi-plus-circle me-1"></i>
                                                L2TP Profile
                                            </button>
                                        </div>
                                        <div class="col-md-4 mb-2">
                                            <button class="btn btn-outline-primary btn-sm w-100 profile-btn" id="create-pptp-profile" data-service="pptp">
                                                <i class="bi bi-plus-circle me-1"></i>
                                                PPTP Profile
                                            </button>
                                        </div>
                                        <div class="col-md-4 mb-2">
                                            <button class="btn btn-outline-primary btn-sm w-100 profile-btn" id="create-sstp-profile" data-service="sstp">
                                                <i class="bi bi-plus-circle me-1"></i>
                                                SSTP Profile
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- NAT Management -->
                                <div class="nat-section mb-4">
                                    <div class="section-header">
                                        <h6 class="section-title">NAT Configuration</h6>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-2">
                                            <button class="btn btn-outline-warning btn-sm w-100" id="create-nat-masquerade">
                                                <i class="bi bi-router me-1"></i>
                                                NAT Masquerade
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Quick Actions -->
                                <div class="quick-actions">
                                    <div class="section-header">
                                        <h6 class="section-title">Quick Actions</h6>
                                    </div>
                                    <div class="d-flex gap-2 flex-wrap">
                                        <button class="btn btn-info flex-fill" id="backup-config">
                                            <i class="bi bi-cloud-download me-2"></i>
                                            Backup Config
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/admin.js"></script>

    <script>
        // Security configurations
        window.APP_CONFIG = {
            CSRF_TOKEN: <?php echo sanitizeOutput($csrf_token, 'js'); ?>,
            MAX_RETRIES: 3,
            TIMEOUT: 30000
        };

        // Global function to handle Connect button click
        async function handleConnectClick() {
            // Try to initialize if not already done
            if (!window.adminPanelInstance && typeof window.initializeAdminPanel === 'function') {
                window.initializeAdminPanel();
            }

            // Wait for AdminPanel to be initialized
            if (window.adminPanelInstance) {
                window.adminPanelInstance.connectMikrotik();
            } else {
                setTimeout(() => {
                    if (window.adminPanelInstance) {
                        window.adminPanelInstance.connectMikrotik();
                    } else {
                        alert('Error: AdminPanel not initialized.\n\nPlease refresh the page (Ctrl+Shift+R)');
                    }
                }, 200);
            }
        }

        // Global function to toggle password visibility
        async function togglePasswordVisibility() {
            const passwordInput = document.getElementById('auth_password');
            const usernameInput = document.getElementById('auth_username');
            const toggleBtn = document.getElementById('toggleAuthPassword');
            const eyeIcon = toggleBtn.querySelector('i');
            
            if (!passwordInput || !toggleBtn || !eyeIcon) {
                console.error('Missing elements');
                return;
            }
            
            // If password field shows bullets, fetch current password first
            if (passwordInput.value === '••••••••') {
                const originalBtnContent = toggleBtn.innerHTML;
                toggleBtn.disabled = true;
                toggleBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
                
                try {
                    const response = await fetch('../api/config.php?action=get_auth_credentials');
                    
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}`);
                    }
                    
                    const result = await response.json();
                    
                    if (result.success && result.credentials) {
                        // Fill the fields with current credentials
                        if (usernameInput && !usernameInput.value) {
                            usernameInput.value = result.credentials.username;
                        }
                        passwordInput.value = result.credentials.password;
                        
                        // Update JavaScript userPasswords object
                        if (window.adminPanel && window.adminPanel.userPasswords) {
                            window.adminPanel.userPasswords.auth = result.credentials.password;
                        }
                        
                        // Show password
                        passwordInput.type = 'text';
                        eyeIcon.className = 'bi bi-eye-slash';
                    } else {
                        alert('❌ Failed to load credentials: ' + (result.message || 'Unknown error'));
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('Error: ' + error.message);
                } finally {
                    toggleBtn.disabled = false;
                    toggleBtn.innerHTML = originalBtnContent;
                }
            } else {
                // Toggle visibility of existing password
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    eyeIcon.className = 'bi bi-eye-slash';
                } else {
                    // Hide password - change back to bullets and password type
                    passwordInput.value = '';  // Clear first
                    passwordInput.type = 'password';
                    // Use setTimeout to ensure type change is processed first
                    setTimeout(() => {
                        passwordInput.value = '••••••••';
                    }, 10);
                    eyeIcon.className = 'bi bi-eye';
                }
            }
        }
        
        // Global function to toggle MikroTik password visibility
        async function toggleMikrotikPasswordVisibility() {
            
            const passwordInput = document.getElementById('mt_password');
            const toggleBtn = document.getElementById('toggleMtPassword');
            const eyeIcon = toggleBtn.querySelector('i');
            
            if (!passwordInput || !toggleBtn || !eyeIcon) {
                console.error('Missing elements');
                return;
            }
            
            // If password field is empty or shows bullets, fetch current password first
            if (!passwordInput.value || passwordInput.value === '••••••••') {
                const originalBtnContent = toggleBtn.innerHTML;
                toggleBtn.disabled = true;
                toggleBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
                
                try {
                    const response = await fetch('../api/config.php?action=get_mikrotik_credentials');
                    
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}`);
                    }
                    
                    const result = await response.json();
                    
                    if (result.success && result.credentials) {
                        if (result.credentials.password && !result.credentials.password_masked) {
                            // Set actual password value
                            passwordInput.value = result.credentials.password;
                            
                            // Show password
                            passwordInput.type = 'text';
                            eyeIcon.className = 'bi bi-eye-slash';
                            
                        } else {
                            // Password is masked or empty - let user enter new password
                            passwordInput.value = '';
                            passwordInput.type = 'text';
                            passwordInput.placeholder = 'Enter your MikroTik router password';
                            passwordInput.focus();
                            eyeIcon.className = 'bi bi-eye-slash';
                            
                        }
                    } else {
                        // API failed - let user enter password
                        passwordInput.value = '';
                        passwordInput.type = 'text';
                        passwordInput.placeholder = 'Enter your MikroTik router password';
                        passwordInput.focus();
                        eyeIcon.className = 'bi bi-eye-slash';
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('Error: ' + error.message);
                } finally {
                    toggleBtn.disabled = false;
                    toggleBtn.innerHTML = originalBtnContent;
                }
            } else {
                // Toggle visibility of existing password
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    eyeIcon.className = 'bi bi-eye-slash';
                } else {
                    // Hide password - change back to bullets and password type
                    passwordInput.value = '••••••••';
                    passwordInput.type = 'password';
                    eyeIcon.className = 'bi bi-eye';
                }
            }
        }
    </script>
</body>
</html>