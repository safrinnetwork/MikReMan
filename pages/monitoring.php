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
$current_page = 'monitoring';
$page_title = 'Network Monitoring';
$page_subtitle = 'Monitor network hosts using Netwatch';

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Check if MikroTik configuration exists
$mikrotik_config = getConfig('mikrotik');
if (!$mikrotik_config || empty($mikrotik_config['host']) || empty($mikrotik_config['username'])) {
    // Redirect to admin with message
    $_SESSION['monitoring_error'] = 'MikroTik configuration is required. Please configure your router settings first.';
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
    $_SESSION['monitoring_error'] = 'Cannot connect to MikroTik router. Please check your credentials and network connection.';
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

    <style>
        .ppp-card {
            background: linear-gradient(135deg, #1a1d23 0%, #2d3748 100%);
            border: 1px solid #4a5568;
            border-radius: 12px;
            transition: all 0.3s ease;
        }

        .ppp-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
        }

        .stat-card {
            text-align: center;
            padding: 1.5rem;
        }

        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: #4fc3f7;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #4fc3f7;
        }

        .stat-label {
            color: #a0aec0;
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }

        .users-table {
            background: #1a1d23;
            border-radius: 12px;
            overflow: hidden;
        }

        .table-dark {
            --bs-table-bg: transparent;
        }

        .table-dark th {
            background: #2d3748;
            border-color: #4a5568;
            color: #e2e8f0;
            font-weight: 600;
        }

        .table-dark td {
            border-color: #4a5568;
            color: #cbd5e0;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .status-up {
            background: rgba(72, 187, 120, 0.2);
            color: #48bb78;
        }

        .status-down {
            background: rgba(245, 101, 101, 0.2);
            color: #f56565;
        }

        .status-unknown {
            background: rgba(237, 137, 54, 0.2);
            color: #ed8936;
        }

        .response-time {
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
        }
    </style>
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
                <!-- Page Header -->
                <div class="page-header">
                    <div class="header-content">
                        <div class="header-main">
                            <div class="header-icon">
                                <i class="bi bi-activity"></i>
                            </div>
                            <div class="header-text">
                                <h1 class="page-title"><?php echo sanitizeOutput($page_title); ?></h1>
                                <p class="page-subtitle"><?php echo sanitizeOutput($page_subtitle); ?></p>
                            </div>
                        </div>
                        <div class="header-actions">
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addNetwatchModal">
                                <i class="bi bi-plus-circle"></i> Add Host
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Alert Container -->
                <div id="alertContainer"></div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-lg-3 mb-3">
                        <div class="card ppp-card">
                            <div class="card-body">
                                <div class="stat-card">
                                    <div class="stat-icon">
                                        <i class="bi bi-hdd-network"></i>
                                    </div>
                                    <div class="stat-value" id="totalHosts">0</div>
                                    <div class="stat-label">Total Hosts</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 mb-3">
                        <div class="card ppp-card">
                            <div class="card-body">
                                <div class="stat-card">
                                    <div class="stat-icon text-success">
                                        <i class="bi bi-check-circle"></i>
                                    </div>
                                    <div class="stat-value text-success" id="hostsUp">0</div>
                                    <div class="stat-label">Hosts Up</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 mb-3">
                        <div class="card ppp-card">
                            <div class="card-body">
                                <div class="stat-card">
                                    <div class="stat-icon text-danger">
                                        <i class="bi bi-x-circle"></i>
                                    </div>
                                    <div class="stat-value text-danger" id="hostsDown">0</div>
                                    <div class="stat-label">Hosts Down</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 mb-3">
                        <div class="card ppp-card">
                            <div class="card-body">
                                <div class="stat-card">
                                    <div class="stat-icon text-info">
                                        <i class="bi bi-clock-history"></i>
                                    </div>
                                    <div class="stat-value" id="avgResponse">-</div>
                                    <div class="stat-label">Avg Response</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Netwatch Table -->
                <div class="card users-table">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0">Network Hosts</h5>
                            <small class="text-muted">Monitor your network hosts with Netwatch</small>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-dark table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Host</th>
                                <th>Name</th>
                                <th>Status</th>
                                <th>Response Time</th>
                                <th>Since</th>
                                <th>Interval</th>
                                <th>Timeout</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="netwatchTableBody">
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <p class="mt-2 mb-0 text-muted">Loading netwatch hosts...</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Add Netwatch Modal -->
    <div class="modal fade" id="addNetwatchModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content bg-dark">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Add Network Host</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addNetwatchForm">
                        <div class="mb-3">
                            <label class="form-label">Host IP/Domain</label>
                            <input type="text" class="form-control" id="netwatchHost" required placeholder="192.168.1.1 or google.com">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Name (Optional)</label>
                            <input type="text" class="form-control" id="netwatchName" placeholder="Gateway, DNS Server, etc.">
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Interval</label>
                                <input type="text" class="form-control" id="netwatchInterval" value="10s" placeholder="10s">
                                <small class="text-muted">e.g., 10s, 1m, 5m</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Timeout</label>
                                <input type="text" class="form-control" id="netwatchTimeout" value="5s" placeholder="5s">
                                <small class="text-muted">e.g., 1s, 5s, 10s</small>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="addNetwatch()">
                        <i class="bi bi-plus-circle"></i> Add Host
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        class MonitoringManager {
            constructor() {
                this.refreshInterval = null;
                this.init();
            }

            init() {
                this.loadNetwatch();
                this.startAutoRefresh();
            }

            async loadNetwatch() {
                try {
                    const response = await fetch('/api/mikrotik.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'get_netwatch' })
                    });

                    const result = await response.json();

                    if (result.success) {
                        this.renderNetwatch(result.data);
                        this.updateStats(result.data);
                    } else {
                        this.showError(result.message);
                    }
                } catch (error) {
                    console.error('Error loading netwatch:', error);
                    this.showError('Failed to load netwatch hosts');
                }
            }

            renderNetwatch(hosts) {
                const tbody = document.getElementById('netwatchTableBody');

                if (!hosts || hosts.length === 0) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="8" class="text-center py-4">
                                <i class="bi bi-inbox fs-1 text-muted"></i>
                                <p class="mt-2 mb-0 text-muted">No netwatch hosts configured</p>
                                <button class="btn btn-sm btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#addNetwatchModal">
                                    <i class="bi bi-plus-circle"></i> Add First Host
                                </button>
                            </td>
                        </tr>
                    `;
                    return;
                }

                tbody.innerHTML = hosts.map(host => {
                    const status = host.status || 'unknown';
                    const statusClass = status === 'up' ? 'status-up' :
                                      status === 'down' ? 'status-down' : 'status-unknown';
                    const statusIcon = status === 'up' ? 'check-circle' :
                                     status === 'down' ? 'x-circle' : 'question-circle';

                    return `
                        <tr>
                            <td><code>${this.escapeHtml(host.host)}</code></td>
                            <td>${this.escapeHtml(host.comment || host.name || '-')}</td>
                            <td>
                                <span class="status-badge ${statusClass}">
                                    <i class="bi bi-${statusIcon}"></i> ${status.toUpperCase()}
                                </span>
                            </td>
                            <td>
                                <span class="response-time ${host['done-tests'] > 0 ? 'text-info' : 'text-muted'}">
                                    ${host['done-tests'] > 0 ? (host['response-time'] || '-') : '-'}
                                </span>
                            </td>
                            <td class="text-muted">${host.since || '-'}</td>
                            <td class="text-muted">${host.interval || '10s'}</td>
                            <td class="text-muted">${host.timeout || '5s'}</td>
                            <td>
                                <button class="btn btn-sm btn-danger" onclick="monitoring.deleteNetwatch('${host['.id']}')">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                }).join('');
            }

            updateStats(hosts) {
                const total = hosts.length;
                const up = hosts.filter(h => h.status === 'up').length;
                const down = hosts.filter(h => h.status === 'down').length;

                document.getElementById('totalHosts').textContent = total;
                document.getElementById('hostsUp').textContent = up;
                document.getElementById('hostsDown').textContent = down;

                // Calculate average response time
                const responseTimes = hosts
                    .filter(h => h['response-time'] && h.status === 'up')
                    .map(h => {
                        const time = h['response-time'];
                        if (time.includes('ms')) {
                            return parseInt(time);
                        }
                        return 0;
                    })
                    .filter(t => t > 0);

                if (responseTimes.length > 0) {
                    const avg = Math.round(responseTimes.reduce((a, b) => a + b, 0) / responseTimes.length);
                    document.getElementById('avgResponse').textContent = avg + 'ms';
                } else {
                    document.getElementById('avgResponse').textContent = '-';
                }
            }

            async deleteNetwatch(id) {
                if (!confirm('Are you sure you want to delete this netwatch host?')) {
                    return;
                }

                try {
                    const response = await fetch('/api/mikrotik.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            action: 'delete_netwatch',
                            id: id
                        })
                    });

                    const result = await response.json();

                    if (result.success) {
                        this.showSuccess('Netwatch host deleted successfully');
                        this.loadNetwatch();
                    } else {
                        this.showError(result.message);
                    }
                } catch (error) {
                    console.error('Error deleting netwatch:', error);
                    this.showError('Failed to delete netwatch host');
                }
            }

            startAutoRefresh() {
                // Refresh every 10 seconds
                this.refreshInterval = setInterval(() => {
                    this.loadNetwatch();
                }, 10000);
            }

            escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            showSuccess(message) {
                this.showAlert(message, 'success');
            }

            showError(message) {
                this.showAlert(message, 'danger');
            }

            showAlert(message, type) {
                const alertHtml = `
                    <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                        ${message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                `;
                document.getElementById('alertContainer').innerHTML = alertHtml;
                setTimeout(() => {
                    document.getElementById('alertContainer').innerHTML = '';
                }, 5000);
            }
        }

        // Global functions
        async function addNetwatch() {
            const host = document.getElementById('netwatchHost').value.trim();
            const name = document.getElementById('netwatchName').value.trim();
            const interval = document.getElementById('netwatchInterval').value.trim();
            const timeout = document.getElementById('netwatchTimeout').value.trim();

            if (!host) {
                alert('Please enter a host IP or domain');
                return;
            }

            try {
                const response = await fetch('/api/mikrotik.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'add_netwatch',
                        host: host,
                        name: name,
                        interval: interval,
                        timeout: timeout
                    })
                });

                const result = await response.json();

                if (result.success) {
                    bootstrap.Modal.getInstance(document.getElementById('addNetwatchModal')).hide();
                    document.getElementById('addNetwatchForm').reset();
                    monitoring.showSuccess('Netwatch host added successfully');
                    monitoring.loadNetwatch();
                } else {
                    alert(result.message);
                }
            } catch (error) {
                console.error('Error adding netwatch:', error);
                alert('Failed to add netwatch host');
            }
        }

        // Initialize
        const monitoring = new MonitoringManager();
    </script>
            </div>
        </div>
    </div>
</body>
</html>
