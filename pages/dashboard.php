<?php
// Security configurations
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_strict_mode', 1);

session_start();

require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/mikrotik.php';

// Constants
define('SESSION_TIMEOUT', 3600); // 60 minutes

// Check authentication
checkSession();

// Page info
$current_page = 'dashboard';
$page_title = 'Dashboard';
$page_subtitle = 'Real-time monitoring and system overview';

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Check if MikroTik configuration exists
$mikrotik_config = getConfig('mikrotik');
if (!$mikrotik_config || empty($mikrotik_config['host']) || empty($mikrotik_config['username'])) {
    // Redirect to admin with message
    $_SESSION['dashboard_error'] = 'MikroTik configuration is required. Please configure your router settings first.';
    header('Location: admin.php');
    exit;
}

// Test MikroTik connection
try {
    $mikrotik = new MikroTikAPI($mikrotik_config);
    $test_result = $mikrotik->getSystemResource();
    if (!$test_result) {
        throw new Exception('Cannot connect to MikroTik router');
    }
} catch (Exception $e) {
    $_SESSION['dashboard_error'] = 'Cannot connect to MikroTik router. Please check your credentials and network connection.';
    header('Location: admin.php');
    exit;
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
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo sanitizeOutput($page_title); ?> - VPN Remote</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="../assets/css/style.css" rel="stylesheet">
    
    <style>
        .dashboard-card {
            background: linear-gradient(135deg, #1a1d23 0%, #2d3748 100%);
            border: 1px solid #4a5568;
            border-radius: 12px;
            transition: all 0.3s ease;
        }
        
        .dashboard-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
        }
        
        .stat-card {
            text-align: center;
            padding: 1.5rem;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #4fc3f7;
            white-space: nowrap;
            display: inline-block;
        }
        
        .stat-label {
            color: #a0aec0;
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }
        
        .resource-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #4a5568;
        }
        
        .resource-item:last-child {
            border-bottom: none;
        }
        
        .resource-label {
            color: #a0aec0;
            font-weight: 500;
        }
        
        .resource-value {
            color: #e2e8f0;
            font-weight: 600;
        }
        
        .log-container {
            height: 300px;
            overflow-y: auto;
            background: #1a1d23;
            border-radius: 8px;
            padding: 1rem;
            border: 1px solid #4a5568;
        }
        
        .log-entry {
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
            margin-bottom: 0.5rem;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            background: #2d3748;
        }
        
        .log-time {
            color: #4fc3f7;
        }
        
        .log-message {
            color: #e2e8f0;
        }
        
        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }
        
        .status-online {
            background-color: #48bb78;
            animation: pulse 2s infinite;
        }
        
        .status-offline {
            background-color: #f56565;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        .connection-status {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
        }
        
        .update-time {
            font-size: 0.8rem;
            color: #a0aec0;
            text-align: center;
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar">
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
                                <i class="bi bi-speedometer2"></i>
                            </div>
                            <div class="header-text">
                                <h1 class="page-title"><?php echo sanitizeOutput($page_title); ?></h1>
                                <p class="page-subtitle"><?php echo sanitizeOutput($page_subtitle); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Connection Status -->
                <div class="connection-status">
                    <div class="alert alert-success d-flex align-items-center" id="connection-alert">
                        <span class="status-indicator status-online"></span>
                        <span>Connected to MikroTik</span>
                    </div>
                </div>
                
                <div id="alerts-container"></div>
                
                <div class="row">
                    <!-- Card 1: System Resources -->
                    <div class="col-lg-4 mb-4">
                        <div class="card dashboard-card">
                            <div class="card-header">
                                <div class="card-header-content">
                                    <div class="card-icon">
                                        <i class="bi bi-cpu"></i>
                                    </div>
                                    <div class="card-title-group">
                                        <h5 class="card-title mb-0">System Resources</h5>
                                        <small class="card-subtitle">Router performance metrics</small>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div id="system-resources">
                                    <div class="resource-item">
                                        <span class="resource-label">CPU Usage</span>
                                        <span class="resource-value" id="cpu-load">-</span>
                                    </div>
                                    <div class="resource-item">
                                        <span class="resource-label">Memory</span>
                                        <span class="resource-value" id="memory-usage">-</span>
                                    </div>
                                    <div class="resource-item">
                                        <span class="resource-label">Storage</span>
                                        <span class="resource-value" id="storage-usage">-</span>
                                    </div>
                                    <div class="resource-item">
                                        <span class="resource-label">RouterOS Version</span>
                                        <span class="resource-value" id="router-version">-</span>
                                    </div>
                                    <div class="resource-item">
                                        <span class="resource-label">Timezone</span>
                                        <span class="resource-value" id="timezone">-</span>
                                    </div>
                                    <div class="resource-item">
                                        <span class="resource-label">Uptime</span>
                                        <span class="resource-value" id="uptime">-</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Card 2: PPP Statistics -->
                    <div class="col-lg-4 mb-4">
                        <div class="card dashboard-card">
                            <div class="card-header">
                                <div class="card-header-content">
                                    <div class="card-icon">
                                        <i class="bi bi-people"></i>
                                    </div>
                                    <div class="card-title-group">
                                        <h5 class="card-title mb-0">PPP Users</h5>
                                        <small class="card-subtitle">User connection statistics</small>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-4">
                                        <div class="stat-card">
                                            <div class="stat-value" id="total-users">-</div>
                                            <div class="stat-label">Total</div>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="stat-card">
                                            <div class="stat-value text-success" id="online-users">-</div>
                                            <div class="stat-label">On</div>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="stat-card">
                                            <div class="stat-value text-warning" id="offline-users">-</div>
                                            <div class="stat-label">Off</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Card 3: Selected MikroTik -->
                    <div class="col-lg-4 mb-4">
                        <div class="card dashboard-card">
                            <div class="card-header">
                                <div class="card-header-content">
                                    <div class="card-icon">
                                        <i class="bi bi-router"></i>
                                    </div>
                                    <div class="card-title-group">
                                        <h5 class="card-title mb-0">Router Information</h5>
                                        <small class="card-subtitle">Connected MikroTik device</small>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div id="router-info">
                                    <div class="resource-item">
                                        <span class="resource-label">Host</span>
                                        <span class="resource-value" id="router-host"><?php echo sanitizeOutput($mikrotik_config['host'] ?? '-'); ?></span>
                                    </div>
                                    <div class="resource-item">
                                        <span class="resource-label">Username</span>
                                        <span class="resource-value" id="router-username"><?php echo sanitizeOutput($mikrotik_config['username'] ?? '-'); ?></span>
                                    </div>
                                    <div class="resource-item">
                                        <span class="resource-label">Port</span>
                                        <span class="resource-value" id="router-port"><?php echo sanitizeOutput($mikrotik_config['port'] ?? '443'); ?></span>
                                    </div>
                                    <div class="resource-item">
                                        <span class="resource-label">SSL</span>
                                        <span class="resource-value" id="router-ssl"><?php echo ($mikrotik_config['use_ssl'] ?? true) ? 'Enabled' : 'Disabled'; ?></span>
                                    </div>
                                    <div class="resource-item">
                                        <span class="resource-label">Board Name</span>
                                        <span class="resource-value" id="board-name">-</span>
                                    </div>
                                    <div class="resource-item">
                                        <span class="resource-label">Architecture</span>
                                        <span class="resource-value" id="architecture">-</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Card 4: PPP Logs -->
                <div class="row">
                    <div class="col-12">
                        <div class="card dashboard-card">
                            <div class="card-header">
                                <div class="card-header-content">
                                    <div class="card-icon">
                                        <i class="bi bi-file-text"></i>
                                    </div>
                                    <div class="card-title-group">
                                        <h5 class="card-title mb-0">PPP Connection Logs</h5>
                                        <small class="card-subtitle">Real-time connection activity</small>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="log-container" id="ppp-logs">
                                    <div class="text-center text-muted">
                                        <i class="bi bi-hourglass-split"></i> Loading logs...
                                    </div>
                                </div>
                                <div class="update-time" id="last-update">
                                    Last updated: -
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Dashboard JavaScript -->
    <script>
        class Dashboard {
            constructor() {
                this.updateInterval = null;
                this.connectionStatus = true;
                this.init();
            }
            
            init() {
                this.startUpdates();
                this.bindEvents();
            }
            
            bindEvents() {
                // Handle page visibility change to pause/resume updates
                document.addEventListener('visibilitychange', () => {
                    if (document.visibilityState === 'visible') {
                        this.startUpdates();
                    } else {
                        this.stopUpdates();
                    }
                });
                
                // Handle window unload
                window.addEventListener('beforeunload', () => {
                    this.stopUpdates();
                });
            }
            
            startUpdates() {
                this.stopUpdates(); // Clear any existing interval
                this.updateData(); // Initial update
                this.updateInterval = setInterval(() => {
                    this.updateData();
                }, 1000); // Update every 1 second
            }
            
            stopUpdates() {
                if (this.updateInterval) {
                    clearInterval(this.updateInterval);
                    this.updateInterval = null;
                }
            }
            
            async updateData() {
                try {
                    // Fetch system resources
                    const systemData = await this.fetchData('../api/mikrotik.php?action=system_resource');
                    if (systemData.success) {
                        this.updateSystemResources(systemData.data);
                    }
                    
                    // Fetch PPP statistics
                    const pppStats = await this.fetchData('../api/mikrotik.php?action=ppp_stats');
                    if (pppStats.success) {
                        this.updatePPPStats(pppStats.data);
                    }
                    
                    // Fetch PPP logs
                    const pppLogs = await this.fetchData('../api/mikrotik.php?action=ppp_logs');
                    if (pppLogs.success) {
                        this.updatePPPLogs(pppLogs.data);
                    }
                    
                    this.updateConnectionStatus(true);
                    this.updateLastUpdateTime();
                    
                } catch (error) {
                    console.error('Error updating dashboard:', error);
                    this.updateConnectionStatus(false);
                }
            }
            
            async fetchData(url) {
                const response = await fetch(url, {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                return await response.json();
            }
            
            updateSystemResources(data) {
                if (!data) return;
                
                document.getElementById('cpu-load').textContent = data['cpu-load'] + '%' || '-';
                
                // Memory calculation
                const totalMem = parseInt(data['total-memory']) || 0;
                const freeMem = parseInt(data['free-memory']) || 0;
                const usedMem = totalMem - freeMem;
                const memPercent = totalMem > 0 ? Math.round((usedMem / totalMem) * 100) : 0;
                document.getElementById('memory-usage').textContent = `${this.formatBytes(usedMem)} / ${this.formatBytes(totalMem)} (${memPercent}%)`;
                
                // Storage calculation
                const totalHdd = parseInt(data['total-hdd-space']) || 0;
                const freeHdd = parseInt(data['free-hdd-space']) || 0;
                const usedHdd = totalHdd - freeHdd;
                const hddPercent = totalHdd > 0 ? Math.round((usedHdd / totalHdd) * 100) : 0;
                document.getElementById('storage-usage').textContent = `${this.formatBytes(usedHdd)} / ${this.formatBytes(totalHdd)} (${hddPercent}%)`;
                
                document.getElementById('router-version').textContent = data.version || '-';
                document.getElementById('uptime').textContent = data.uptime || '-';
                document.getElementById('board-name').textContent = data['board-name'] || '-';
                document.getElementById('architecture').textContent = data['architecture-name'] || '-';
                
                // Update timezone from system clock if available
                const timezone = data.timezone || new Date().toString().match(/\((.+)\)$/)?.[1] || 'Local Time';
                document.getElementById('timezone').textContent = timezone;
            }
            
            updatePPPStats(data) {
                if (!data) return;
                
                document.getElementById('total-users').textContent = data.total || 0;
                document.getElementById('online-users').textContent = data.online || 0;
                document.getElementById('offline-users').textContent = data.offline || 0;
            }
            
            updatePPPLogs(logs) {
                if (!logs || !Array.isArray(logs)) return;
                
                const logContainer = document.getElementById('ppp-logs');
                
                if (logs.length === 0) {
                    logContainer.innerHTML = '<div class="text-center text-muted">No recent PPP logs found</div>';
                    return;
                }
                
                // Limit to last 50 entries
                const recentLogs = logs.slice(-50);
                
                logContainer.innerHTML = recentLogs.map(log => `
                    <div class="log-entry">
                        <span class="log-time">${log.time || new Date().toLocaleTimeString()}</span>
                        <span class="log-message"> - ${this.escapeHtml(log.message || log.topics || 'No message')}</span>
                    </div>
                `).join('');
                
                // Auto-scroll to bottom
                logContainer.scrollTop = logContainer.scrollHeight;
            }
            
            updateConnectionStatus(isConnected) {
                const alert = document.getElementById('connection-alert');
                const indicator = alert.querySelector('.status-indicator');
                
                if (isConnected !== this.connectionStatus) {
                    this.connectionStatus = isConnected;
                    
                    if (isConnected) {
                        alert.className = 'alert alert-success d-flex align-items-center';
                        indicator.className = 'status-indicator status-online';
                        alert.innerHTML = '<span class="status-indicator status-online"></span><span>Connected to MikroTik</span>';
                    } else {
                        alert.className = 'alert alert-danger d-flex align-items-center';
                        indicator.className = 'status-indicator status-offline';
                        alert.innerHTML = '<span class="status-indicator status-offline"></span><span>Connection Lost</span>';
                    }
                }
            }
            
            updateLastUpdateTime() {
                const now = new Date();
                const timeString = now.toLocaleTimeString();
                document.getElementById('last-update').textContent = `Last updated: ${timeString}`;
            }
            
            formatBytes(bytes) {
                if (bytes === 0) return '0 B';
                const k = 1024;
                const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
            }
            
            escapeHtml(text) {
                const map = {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                };
                return text.replace(/[&<>"']/g, function(m) { return map[m]; });
            }
        }
        
        // Initialize dashboard when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            new Dashboard();
        });
        
        // Session timeout handler
        let sessionTimeout;
        function resetSessionTimeout() {
            clearTimeout(sessionTimeout);
            sessionTimeout = setTimeout(() => {
                alert('Session expired. Redirecting to login page.');
                window.location.href = '../index.php?timeout=1';
            }, <?php echo SESSION_TIMEOUT * 1000; ?>);
        }
        
        // Reset timeout on user activity
        ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart'].forEach(event => {
            document.addEventListener(event, resetSessionTimeout, { passive: true });
        });
        
        resetSessionTimeout();
    </script>
</body>
</html>