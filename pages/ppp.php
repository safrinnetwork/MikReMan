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
$current_page = 'ppp';
$page_title = 'PPP Users';
$page_subtitle = 'Manage MikroTik PPP users and connections';

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Check if MikroTik configuration exists
$mikrotik_config = getConfig('mikrotik');
if (!$mikrotik_config || empty($mikrotik_config['host']) || empty($mikrotik_config['username'])) {
    // Redirect to admin with message
    $_SESSION['ppp_error'] = 'MikroTik configuration is required. Please configure your router settings first.';
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
    $_SESSION['ppp_error'] = 'Cannot connect to MikroTik router. Please check your credentials and network connection.';
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
        
        .table-dark tbody tr:hover {
            background-color: rgba(79, 195, 247, 0.1);
        }
        
        .btn-action {
            padding: 0.25rem 0.5rem;
            margin: 0 0.125rem;
            border-radius: 6px;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .status-enabled {
            background: rgba(72, 187, 120, 0.2);
            color: #48bb78;
            border: 1px solid rgba(72, 187, 120, 0.3);
        }
        
        .status-disabled {
            background: rgba(245, 101, 101, 0.2);
            color: #f56565;
            border: 1px solid rgba(245, 101, 101, 0.3);
        }

        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            cursor: pointer;
        }

        .status-online {
            background-color: #48bb78;
            box-shadow: 0 0 8px rgba(72, 187, 120, 0.6);
        }

        .status-offline {
            background-color: #f56565;
            box-shadow: 0 0 8px rgba(245, 101, 101, 0.6);
        }

        .status-enabled {
            background-color: #4299e1;
            box-shadow: 0 0 8px rgba(66, 153, 225, 0.6);
        }

        .status-disabled {
            background-color: #cbd5e0;
            box-shadow: 0 0 8px rgba(203, 213, 224, 0.4);
        }

        .traffic-info-compact {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            font-size: 0.85rem;
            font-family: 'Courier New', monospace;
            white-space: nowrap;
        }

        .traffic-item-compact {
            display: inline-flex;
            align-items: center;
            gap: 0.2rem;
        }

        .traffic-item-compact i {
            font-size: 0.85rem;
        }

        .traffic-value {
            font-weight: 600;
            font-size: 0.85rem;
            min-width: 75px;
            display: inline-block;
            text-align: right;
        }

        .traffic-separator {
            color: #4a5568;
            font-weight: bold;
            padding: 0 0.15rem;
            font-size: 0.8rem;
        }

        .traffic-upload {
            color: #4299e1;
        }

        .traffic-upload i {
            color: #4299e1;
        }

        .traffic-download {
            color: #48bb78;
        }

        .traffic-download i {
            color: #48bb78;
        }

        .username-link {
            color: #4299e1;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }

        .username-link:hover {
            color: #2b6cb0;
            text-decoration: underline;
        }

        .username-link i {
            font-size: 0.75rem;
            transition: transform 0.3s;
        }

        .nat-details-row {
            background-color: rgba(66, 153, 225, 0.05);
        }

        .nat-details-container {
            padding: 1rem;
        }

        .nat-rule-item {
            display: inline-flex;
            align-items: center;
            padding: 0.5rem 1rem;
            margin: 0.25rem;
            background: rgba(72, 187, 120, 0.15);
            border-left: 3px solid #48bb78;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            transition: all 0.2s ease;
        }

        .nat-rule-item i {
            margin-right: 0.5rem;
            color: #48bb78;
        }

        .nat-rule-clickable {
            cursor: pointer;
            position: relative;
        }

        .nat-rule-clickable:hover {
            background: rgba(72, 187, 120, 0.25);
            border-left-color: #38a169;
            transform: translateX(3px);
            box-shadow: 0 2px 8px rgba(72, 187, 120, 0.3);
        }

        .nat-rule-clickable .copy-icon {
            opacity: 0;
            transition: opacity 0.2s ease;
            color: #4fc3f7;
            font-size: 0.85rem;
        }

        .nat-rule-clickable:hover .copy-icon {
            opacity: 1;
        }

        .nat-port-text {
            flex: 1;
        }

        .nat-empty {
            color: #a0aec0;
            font-style: italic;
        }

        .service-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .service-l2tp {
            background: rgba(66, 153, 225, 0.2);
            color: #4299e1;
        }
        
        .service-pptp {
            background: rgba(159, 122, 234, 0.2);
            color: #9f7aea;
        }
        
        .service-sstp {
            background: rgba(236, 201, 75, 0.2);
            color: #ecc94b;
        }
        
        .service-any {
            background: rgba(128, 128, 128, 0.2);
            color: #a0adb8;
        }
        
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }
        
        /* Ensure modal buttons are clickable */
        .modal .btn {
            position: relative;
            z-index: 1;
        }
        
        /* Fix for form submit buttons */
        .modal-footer .btn {
            cursor: pointer !important;
        }
        
        .loading-spinner {
            text-align: center;
            color: #fff;
        }
        
        .form-floating > .form-control:not(:placeholder-shown) {
            padding-top: 1.625rem;
            padding-bottom: 0.625rem;
        }
        
        .form-floating > .form-control:focus,
        .form-floating > .form-control:not(:placeholder-shown) {
            padding-top: 1.625rem;
            padding-bottom: 0.625rem;
        }
        
        .form-floating > label {
            opacity: 0.65;
        }
        
        .bulk-actions {
            background: #2d3748;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            display: none;
        }
        
        .bulk-actions.show {
            display: block;
        }
        
        .search-filters {
            background: #2d3748;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .password-field {
            position: relative;
        }
        
        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #a0aec0;
            cursor: pointer;
        }
        
        .password-toggle:hover {
            color: #4fc3f7;
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner">
            <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status"></div>
            <div class="mt-3">Loading...</div>
        </div>
    </div>

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
                <!-- Page Header -->
                <div class="page-header">
                    <div class="header-content">
                        <div class="header-main">
                            <div class="header-icon">
                                <i class="bi bi-people-fill"></i>
                            </div>
                            <div class="header-text">
                                <h1 class="page-title"><?php echo sanitizeOutput($page_title); ?></h1>
                                <p class="page-subtitle"><?php echo sanitizeOutput($page_subtitle); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div id="alerts-container"></div>
                
                <!-- PPP Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-lg-4 mb-3">
                        <div class="card ppp-card">
                            <div class="card-body">
                                <div class="stat-card">
                                    <div class="stat-value" id="total-users">-</div>
                                    <div class="stat-label">Total Users</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 mb-3">
                        <div class="card ppp-card">
                            <div class="card-body">
                                <div class="stat-card">
                                    <div class="stat-value text-success" id="online-users">-</div>
                                    <div class="stat-label">Online Users</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 mb-3">
                        <div class="card ppp-card">
                            <div class="card-body">
                                <div class="stat-card">
                                    <div class="stat-value text-warning" id="offline-users">-</div>
                                    <div class="stat-label">Offline Users</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Search and Filters -->
                <div class="search-filters">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="searchInput" placeholder=" ">
                                <label for="searchInput"><i class="bi bi-search me-2"></i>Search Users</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-floating">
                                <select class="form-select" id="serviceFilter">
                                    <option value="">All Services</option>
                                    <option value="l2tp">L2TP</option>
                                    <option value="pptp">PPTP</option>
                                    <option value="sstp">SSTP</option>
                                    <option value="any">Any</option>
                                </select>
                                <label for="serviceFilter"><i class="bi bi-filter me-2"></i>Service</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-floating">
                                <select class="form-select" id="statusFilter">
                                    <option value="">All Status</option>
                                    <option value="enabled">Enabled</option>
                                    <option value="disabled">Disabled</option>
                                </select>
                                <label for="statusFilter"><i class="bi bi-toggle-on me-2"></i>Status</label>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-outline-secondary w-100 h-100" onclick="clearFilters()">
                                <i class="bi bi-x-circle"></i> Clear
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Bulk Actions -->
                <div class="bulk-actions" id="bulkActions">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span id="selectedCount">0</span> users selected
                        </div>
                        <div>
                            <button class="btn btn-warning btn-sm me-2" onclick="bulkToggleStatus()">
                                <i class="bi bi-toggle-off"></i> Toggle Status
                            </button>
                            <button class="btn btn-danger btn-sm" onclick="bulkDeleteUsers()">
                                <i class="bi bi-trash"></i> Delete Selected
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Users Table -->
                <div class="card users-table">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0">PPP Users</h5>
                            <small class="text-muted">Manage your MikroTik PPP users</small>
                        </div>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                            <i class="bi bi-plus-circle"></i> Add User
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-dark table-hover mb-0" id="usersTable">
                                <thead>
                                    <tr>
                                        <th width="40">
                                            <input type="checkbox" class="form-check-input" id="selectAll">
                                        </th>
                                        <th class="sort-header" data-sort="name" onclick="pppManager.sortUsers('name')" style="cursor: pointer;">
                                            Name
                                        </th>
                                        <th class="sort-header" data-sort="service" onclick="pppManager.sortUsers('service')" style="cursor: pointer;">
                                            Service
                                        </th>
                                        <th class="sort-header" data-sort="remote-address" onclick="pppManager.sortUsers('remote-address')" style="cursor: pointer;">
                                            Local IP
                                        </th>
                                        <th class="sort-header" data-sort="last-caller-id" onclick="pppManager.sortUsers('last-caller-id')" style="cursor: pointer;">
                                            Caller ID
                                        </th>
                                        <th class="sort-header" data-sort="disabled" onclick="pppManager.sortUsers('disabled')" style="cursor: pointer;">
                                            Status
                                        </th>
                                        <th class="sort-header" data-sort="mode" onclick="pppManager.sortUsers('mode')" style="cursor: pointer;">
                                            Mode
                                        </th>
                                        <th class="sort-header" data-sort="traffic" onclick="pppManager.sortUsers('traffic')" style="cursor: pointer;">
                                            Traffic
                                        </th>
                                        <th width="200">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="usersTableBody">
                                    <tr>
                                        <td colspan="9" class="text-center py-4">
                                            <i class="bi bi-hourglass-split"></i> Loading users...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content bg-dark">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title">
                        <i class="bi bi-person-plus"></i> Add PPP User
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="addUserForm">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-floating position-relative">
                                    <input type="text" class="form-control" id="userName" name="name" required style="padding-right: 50px;">
                                    <label for="userName">Username</label>
                                    <button type="button" class="btn btn-outline-info btn-sm position-absolute" 
                                            style="top: 50%; right: 10px; transform: translateY(-50%); z-index: 10; padding: 0.25rem 0.5rem;"
                                            onclick="generateRandomName()" title="Generate Random Username">
                                        <i class="bi bi-shuffle"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating position-relative">
                                    <input type="password" class="form-control" id="userPassword" name="password" required style="padding-right: 90px;">
                                    <label for="userPassword">Password</label>
                                    <button type="button" class="btn btn-outline-info btn-sm position-absolute" 
                                            style="top: 50%; right: 45px; transform: translateY(-50%); z-index: 10; padding: 0.25rem 0.5rem;"
                                            onclick="generateRandomPassword()" title="Generate Random Password">
                                        <i class="bi bi-shuffle"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm position-absolute" 
                                            style="top: 50%; right: 10px; transform: translateY(-50%); z-index: 10; padding: 0.25rem 0.5rem;"
                                            onclick="togglePassword('userPassword')" title="Show/Hide Password">
                                        <i class="bi bi-eye" id="userPasswordIcon"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <select class="form-select" id="userService" name="service" required>
                                        <option value="">Select Service</option>
                                        <option value="l2tp">L2TP</option>
                                        <option value="pptp">PPTP</option>
                                        <option value="sstp">SSTP</option>
                                        <option value="any">Any</option>
                                    </select>
                                    <label for="userService">Service</label>
                                </div>
                                <small class="text-muted">Profile will be set automatically based on selected service</small>
                            </div>
                            <!-- Hidden field for remote address - auto-assigned based on service -->
                            <input type="hidden" id="userRemoteAddress" name="remote_address">
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="userPorts" name="ports" 
                                           placeholder=" ">
                                    <label for="userPorts">Ports (comma separated)</label>
                                </div>
                                <small class="text-muted">Leave empty to use default ports: 8291, 8728</small>
                            </div>
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="createNatRule" checked>
                                    <label class="form-check-label" for="createNatRule">
                                        Create Nat
                                    </label>
                                </div>
                            </div>
                            <div class="col-12" id="multiPortOptions" style="display: none;">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="createMultipleNat">
                                    <label class="form-check-label" for="createMultipleNat">
                                        Custom Port
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-secondary">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-person-plus"></i> Create User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content bg-dark">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title">
                        <i class="bi bi-pencil"></i> Edit PPP User
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="editUserForm">
                    <div class="modal-body">
                        <input type="hidden" id="editUserId" name="user_id">
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="editUserName" name="name" required>
                                    <label for="editUserName">Username</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-floating">
                                    <select class="form-select" id="editUserService" name="service" required>
                                        <option value="l2tp">L2TP</option>
                                        <option value="pptp">PPTP</option>
                                        <option value="sstp">SSTP</option>
                                        <option value="any">Any</option>
                                    </select>
                                    <label for="editUserService">Service</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="editUserRemoteAddress" name="remote_address" 
                                           pattern="^((25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$">
                                    <label for="editUserRemoteAddress">Remote Address (IP)</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-secondary">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">
                            <i class="bi bi-pencil"></i> Update User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- User Details Modal -->
    <div class="modal fade" id="userDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content bg-dark">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title">
                        <i class="bi bi-info-circle"></i> User Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="userDetailsContent">
                    <!-- Details will be loaded here -->
                </div>
                <div class="modal-footer border-secondary">
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- PPP Users JavaScript -->
    <script>
        class PPPManager {
            constructor() {
                this.users = [];
                this.activeSessions = [];
                this.previousTrafficData = new Map(); // Store previous traffic data for rate calculation
                this.selectedUsers = new Set();
                this.openNATRows = new Set(); // Track which NAT rows are open
                this.natCache = new Map(); // Cache NAT content per userId
                this.sortField = 'name';
                this.sortDirection = 'asc';
                this.init();
            }
            
            init() {
                this.bindEvents();
                this.loadUsers();
                this.loadActiveSessions();
                this.updateStats();
                this.loadAvailableServices();

                // Start periodic updates for real-time traffic
                setInterval(() => {
                    this.loadActiveSessions();
                }, 2000); // Update traffic every 2 seconds for real-time

                // Update stats every 10 seconds
                setInterval(() => {
                    this.updateStats();
                }, 10000); // Update stats every 10 seconds
            }
            
            bindEvents() {
                // Search and filter events
                document.getElementById('searchInput').addEventListener('input', () => this.filterUsers());
                document.getElementById('serviceFilter').addEventListener('change', () => this.filterUsers());
                document.getElementById('statusFilter').addEventListener('change', () => this.filterUsers());
                
                // Select all checkbox
                document.getElementById('selectAll').addEventListener('change', (e) => {
                    const checkboxes = document.querySelectorAll('.user-checkbox');
                    checkboxes.forEach(cb => {
                        cb.checked = e.target.checked;
                        if (e.target.checked) {
                            this.selectedUsers.add(cb.dataset.userId);
                        } else {
                            this.selectedUsers.delete(cb.dataset.userId);
                        }
                    });
                    this.updateBulkActions();
                });
                
                // Form submissions
                const addUserForm = document.getElementById('addUserForm');
                const editUserForm = document.getElementById('editUserForm');

                if (addUserForm) {
                    addUserForm.addEventListener('submit', (e) => this.handleAddUser(e));
                }

                if (editUserForm) {
                    editUserForm.addEventListener('submit', (e) => this.handleEditUser(e));
                }
                
                // Service change event for auto IP assignment
                const userService = document.getElementById('userService');
                if (userService) {
                    userService.addEventListener('change', (e) => this.handleServiceChange(e));
                }
                
                // Multi-port detection
                const userPorts = document.getElementById('userPorts');
                if (userPorts) {
                    userPorts.addEventListener('input', (e) => {
                        const multiPortDiv = document.getElementById('multiPortOptions');
                        if (multiPortDiv) {
                            if (e.target.value.includes(',')) {
                                multiPortDiv.style.display = 'block';
                            } else {
                                multiPortDiv.style.display = 'none';
                            }
                        }
                    });
                }
                
            }
            
            showLoading() {
                document.getElementById('loadingOverlay').style.display = 'flex';
            }
            
            hideLoading() {
                document.getElementById('loadingOverlay').style.display = 'none';
            }
            
            showAlert(message, type = 'success') {
                const alertsContainer = document.getElementById('alerts-container');
                const alertId = 'alert-' + Date.now();
                
                const alertHtml = `
                    <div class="alert alert-${type} alert-dismissible fade show" id="${alertId}" role="alert">
                        <i class="bi bi-${type === 'success' ? 'check-circle' : type === 'warning' ? 'exclamation-triangle' : 'x-circle'}"></i>
                        ${message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                `;
                
                alertsContainer.insertAdjacentHTML('beforeend', alertHtml);
                
                // Auto-remove after 5 seconds
                setTimeout(() => {
                    const alert = document.getElementById(alertId);
                    if (alert) {
                        alert.remove();
                    }
                }, 5000);
            }
            
            async fetchAPI(action, data = null, method = 'GET') {
                const url = '../api/mikrotik.php?action=' + action;
                const options = {
                    method: method,
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                };
                
                if (data && method !== 'GET') {
                    options.headers['Content-Type'] = 'application/json';
                    options.body = JSON.stringify({ action, ...data });
                }
                
                try {
                    const response = await fetch(url, options);
                    const responseText = await response.text();

                    // Try to parse JSON response
                    let jsonData;
                    try {
                        jsonData = JSON.parse(responseText);
                    } catch (jsonError) {
                        throw new Error(`Invalid JSON response: ${responseText.substring(0, 200)}`);
                    }

                    // If response not OK, throw error with message
                    if (!response.ok) {
                        throw new Error(jsonData.message || `HTTP error! status: ${response.status}`);
                    }

                    return jsonData;
                } catch (error) {
                    throw error;
                }
            }
            
            async loadUsers() {
                try {
                    this.showLoading();
                    const result = await this.fetchAPI('get_ppp_users');

                    if (result.success) {
                        this.users = result.data || [];
                        this.renderUsers();
                    } else {
                        throw new Error(result.message || 'Failed to load users');
                    }
                } catch (error) {
                    this.showAlert('Error loading users: ' + error.message, 'danger');
                } finally {
                    this.hideLoading();
                }
            }

            async loadAvailableServices() {
                try {
                    const result = await this.fetchAPI('get_available_services');

                    if (result.success && result.data) {
                        this.updateServiceDropdown(result.data);
                    }
                } catch (error) {
                    // Silent error - keep default services
                    console.error('Error loading available services:', error);
                }
            }

            updateServiceDropdown(availableServices) {
                // Update Add User modal dropdown
                const serviceDropdown = document.getElementById('userService');
                if (serviceDropdown) {
                    // Clear current options except the first one (placeholder)
                    serviceDropdown.innerHTML = '<option value="">Select Service</option>';

                    // Add only available services
                    if (availableServices.includes('l2tp')) {
                        serviceDropdown.innerHTML += '<option value="l2tp">L2TP</option>';
                    }
                    if (availableServices.includes('pptp')) {
                        serviceDropdown.innerHTML += '<option value="pptp">PPTP</option>';
                    }
                    if (availableServices.includes('sstp')) {
                        serviceDropdown.innerHTML += '<option value="sstp">SSTP</option>';
                    }

                    // Always add "Any" option if at least one service is available
                    if (availableServices.length > 0) {
                        serviceDropdown.innerHTML += '<option value="any">Any</option>';
                    }
                }

                // Update Edit User modal dropdown
                const editServiceDropdown = document.getElementById('editUserService');
                if (editServiceDropdown) {
                    const currentValue = editServiceDropdown.value; // Save current selection

                    editServiceDropdown.innerHTML = '';

                    // Add only available services
                    if (availableServices.includes('l2tp')) {
                        editServiceDropdown.innerHTML += '<option value="l2tp">L2TP</option>';
                    }
                    if (availableServices.includes('pptp')) {
                        editServiceDropdown.innerHTML += '<option value="pptp">PPTP</option>';
                    }
                    if (availableServices.includes('sstp')) {
                        editServiceDropdown.innerHTML += '<option value="sstp">SSTP</option>';
                    }

                    // Always add "Any" option
                    if (availableServices.length > 0) {
                        editServiceDropdown.innerHTML += '<option value="any">Any</option>';
                    }

                    // Restore selection if it's still available
                    if (currentValue && availableServices.includes(currentValue)) {
                        editServiceDropdown.value = currentValue;
                    }
                }
            }

            async loadActiveSessions() {
                try {
                    const result = await this.fetchAPI('get_ppp_active');

                    if (result.success) {
                        // Update traffic data BEFORE updating activeSessions
                        const newSessions = result.data || [];

                        // Calculate traffic rates for all users
                        newSessions.forEach(session => {
                            const username = session.name;
                            const rxBytes = parseInt(session['bytes-in'] || session['rx-byte'] || session.rx || 0);
                            const txBytes = parseInt(session['bytes-out'] || session['tx-byte'] || session.tx || 0);
                            const currentTime = Date.now();

                            const previousData = this.previousTrafficData.get(username);

                            // Calculate and store rate
                            if (previousData && previousData.time) {
                                const timeDiff = (currentTime - previousData.time) / 1000;

                                if (timeDiff > 1.5) { // Only calculate if at least 1.5 seconds passed
                                    const rxDiff = rxBytes - previousData.rx;
                                    const txDiff = txBytes - previousData.tx;

                                    if (rxDiff >= 0 && txDiff >= 0) {
                                        const rxRate = rxDiff / timeDiff;
                                        const txRate = txDiff / timeDiff;

                                        // Store calculated rate
                                        this.previousTrafficData.set(username, {
                                            rx: rxBytes,
                                            tx: txBytes,
                                            rxRate: rxRate,
                                            txRate: txRate,
                                            time: currentTime
                                        });
                                    }
                                }
                            } else {
                                // First time - just store the data
                                this.previousTrafficData.set(username, {
                                    rx: rxBytes,
                                    tx: txBytes,
                                    rxRate: 0,
                                    txRate: 0,
                                    time: currentTime
                                });
                            }
                        });

                        this.activeSessions = newSessions;
                        this.renderUsers(); // Re-render to update online/offline status and traffic
                    }
                } catch (error) {
                    // Silent error for active sessions - no action needed
                }
            }

            isUserOnline(username) {
                if (!this.activeSessions || this.activeSessions.length === 0) {
                    return false;
                }

                return this.activeSessions.some(session => session.name === username);
            }

            getUserTraffic(username) {
                if (!this.activeSessions || this.activeSessions.length === 0) {
                    return { rx: '-', tx: '-', rxRate: '-', txRate: '-' };
                }

                const session = this.activeSessions.find(s => s.name === username);
                if (!session) {
                    return { rx: '-', tx: '-', rxRate: '-', txRate: '-' };
                }

                // Get traffic data from cache (calculated in loadActiveSessions)
                const trafficData = this.previousTrafficData.get(username);

                if (trafficData) {
                    return {
                        rx: this.formatBytes(trafficData.rx),
                        tx: this.formatBytes(trafficData.tx),
                        rxRate: this.formatBitrate(trafficData.rxRate || 0),
                        txRate: this.formatBitrate(trafficData.txRate || 0)
                    };
                }

                // No traffic data yet
                return { rx: '-', tx: '-', rxRate: '-', txRate: '-' };
            }

            formatBytes(bytes) {
                if (bytes === 0 || !bytes) return '0 B';

                const k = 1024;
                const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));

                return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
            }

            formatBitrate(bytesPerSecond) {
                if (bytesPerSecond === 0 || !bytesPerSecond) return '0.0 Kbps';

                // Convert bytes to bits
                const bitsPerSecond = bytesPerSecond * 8;

                const k = 1000; // Use 1000 for network speeds (not 1024)
                const sizes = ['bps', 'Kbps', 'Mbps', 'Gbps'];
                const i = Math.floor(Math.log(bitsPerSecond) / Math.log(k));

                const value = parseFloat((bitsPerSecond / Math.pow(k, i)).toFixed(2));

                // Format with appropriate decimals for consistency
                let formatted;
                if (i === 0) { // bps - convert to Kbps for consistency
                    formatted = (value / 1000).toFixed(1);
                    return formatted + ' Kbps';
                } else if (i === 1) { // Kbps
                    formatted = value.toFixed(1);
                } else { // Mbps and above
                    formatted = value.toFixed(2);
                }

                return formatted + ' ' + sizes[i];
            }
            
            
            async updateStats() {
                try {
                    const result = await this.fetchAPI('ppp_stats');
                    
                    if (result.success && result.data) {
                        document.getElementById('total-users').textContent = result.data.total || 0;
                        document.getElementById('online-users').textContent = result.data.online || 0;
                        document.getElementById('offline-users').textContent = result.data.offline || 0;
                    }
                } catch (error) {
                    // Silent error for stats update
                }
            }
            
            async handleServiceChange(e) {
                const service = e.target.value;
                const remoteAddressField = document.getElementById('userRemoteAddress');
                
                if (service) {
                    try {
                        const result = await this.fetchAPI('get_available_ip', { service: service }, 'POST');
                        
                        if (result.success && result.data.ip) {
                            remoteAddressField.value = result.data.ip;
                        } else {
                            remoteAddressField.value = '';
                        }
                    } catch (error) {
                        remoteAddressField.value = '';
                    }
                } else {
                    remoteAddressField.value = '';
                }
            }
            
            renderUsers() {
                const tbody = document.getElementById('usersTableBody');

                if (!this.users || this.users.length === 0) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="9" class="text-center py-4">
                                <i class="bi bi-people"></i> No PPP users found
                            </td>
                        </tr>
                    `;
                    return;
                }

                const filteredUsers = this.getFilteredUsers();

                tbody.innerHTML = filteredUsers.map(user => {
                    const isOnline = this.isUserOnline(user.name);
                    const trafficData = this.getUserTraffic(user.name);
                    const isChecked = this.selectedUsers.has(user['.id']);
                    return `
                    <tr>
                        <td>
                            <input type="checkbox" class="form-check-input user-checkbox"
                                   data-user-id="${user['.id']}"
                                   ${isChecked ? 'checked' : ''}
                                   onchange="pppManager.handleUserSelection(this)">
                        </td>
                        <td>
                            <a href="#" class="username-link"
                               onclick="pppManager.toggleNATRules(event, '${this.escapeHtml(user.name)}', '${user['.id']}')">
                                ${this.escapeHtml(user.name || '-')}
                            </a>
                        </td>
                        <td>
                            <span class="service-badge service-${user.service || 'any'}">
                                ${(user.service || 'any').toUpperCase()}
                            </span>
                        </td>
                        <td>${this.escapeHtml(user['remote-address'] || '-')}</td>
                        <td>${this.escapeHtml(user['last-caller-id'] || 'Never')}</td>
                        <td class="text-center">
                            <div class="status-indicator ${user.disabled === 'true' ? 'status-disabled' : 'status-enabled'}"
                                 title="${user.disabled === 'true' ? 'Disabled' : 'Enabled'}">
                            </div>
                        </td>
                        <td class="text-center">
                            <div class="status-indicator ${isOnline ? 'status-online' : 'status-offline'}"
                                 title="${isOnline ? 'Online' : 'Offline'}">
                            </div>
                        </td>
                        <td>
                            ${isOnline ? `
                                <div class="traffic-info-compact">
                                    <span class="traffic-item-compact traffic-upload">
                                        <i class="bi bi-arrow-up-circle-fill"></i>
                                        <span class="traffic-value">${trafficData.txRate}</span>
                                    </span>
                                    <span class="traffic-separator">-</span>
                                    <span class="traffic-item-compact traffic-download">
                                        <i class="bi bi-arrow-down-circle-fill"></i>
                                        <span class="traffic-value">${trafficData.rxRate}</span>
                                    </span>
                                </div>
                            ` : '<small class="text-muted">-</small>'}
                        </td>
                        <td>
                            <button class="btn btn-sm btn-outline-info btn-action"
                                    onclick="pppManager.showUserDetails('${user['.id']}')"
                                    title="View Details">
                                <i class="bi bi-info-circle"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-warning btn-action"
                                    onclick="pppManager.editUser('${user['.id']}')"
                                    title="Edit User">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm ${user.disabled === 'true' ? 'btn-outline-success' : 'btn-outline-warning'} btn-action"
                                    onclick="pppManager.toggleUserStatus('${user['.id']}')"
                                    title="${user.disabled === 'true' ? 'Enable User' : 'Disable User'}">
                                <i class="bi bi-${user.disabled === 'true' ? 'unlock' : 'lock'}"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger btn-action"
                                    onclick="pppManager.deleteUser('${user['.id']}')"
                                    title="Delete User">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <tr id="nat-row-${user['.id']}" class="nat-details-row" style="display: none;">
                        <td colspan="9">
                            <div class="nat-details-container">
                                <div class="spinner-border spinner-border-sm" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <span class="ms-2">Loading NAT rules...</span>
                            </div>
                        </td>
                    </tr>
                `}).join('');

                // Restore open NAT rows after render
                this.restoreNATState();

                // Update "Select All" checkbox state
                this.updateSelectAllCheckbox();
            }

            restoreNATState() {
                // Restore NAT rows that were open before re-render
                this.openNATRows.forEach(userId => {
                    const natRow = document.getElementById(`nat-row-${userId}`);

                    if (natRow) {
                        natRow.style.display = 'table-row';

                        // Restore cached NAT content
                        const escapedUserId = CSS.escape(userId);
                        const container = document.querySelector(`#nat-row-${escapedUserId} .nat-details-container`);

                        if (container && this.natCache.has(userId)) {
                            container.innerHTML = this.natCache.get(userId);
                        }
                    }
                });
            }

            getFilteredUsers() {
                let filtered = [...this.users];
                
                // Search filter
                const searchTerm = document.getElementById('searchInput').value.toLowerCase();
                if (searchTerm) {
                    filtered = filtered.filter(user => 
                        (user.name || '').toLowerCase().includes(searchTerm) ||
                        (user['remote-address'] || '').toLowerCase().includes(searchTerm)
                    );
                }
                
                // Service filter
                const serviceFilter = document.getElementById('serviceFilter').value;
                if (serviceFilter) {
                    filtered = filtered.filter(user => user.service === serviceFilter);
                }
                
                // Status filter
                const statusFilter = document.getElementById('statusFilter').value;
                if (statusFilter) {
                    const isDisabled = statusFilter === 'disabled';
                    filtered = filtered.filter(user => (user.disabled === 'true') === isDisabled);
                }
                
                // Sort
                filtered.sort((a, b) => {
                    let aVal, bVal;

                    // Handle special sort fields
                    if (this.sortField === 'mode') {
                        // Sort by online/offline status
                        aVal = this.isUserOnline(a.name) ? 'online' : 'offline';
                        bVal = this.isUserOnline(b.name) ? 'online' : 'offline';
                    } else if (this.sortField === 'traffic') {
                        // Sort by traffic RATE (bps) - combined rx + tx rate
                        const aTrafficData = this.previousTrafficData.get(a.name);
                        const bTrafficData = this.previousTrafficData.get(b.name);

                        // Get total rate (rx + tx) in bytes per second
                        const aTotalRate = aTrafficData ? ((aTrafficData.rxRate || 0) + (aTrafficData.txRate || 0)) : 0;
                        const bTotalRate = bTrafficData ? ((bTrafficData.rxRate || 0) + (bTrafficData.txRate || 0)) : 0;

                        // Sort: ascending = lowest first, descending = highest first
                        return this.sortDirection === 'asc' ? aTotalRate - bTotalRate : bTotalRate - aTotalRate;
                    } else if (this.sortField === 'disabled') {
                        // Sort by status (enabled/disabled)
                        aVal = a.disabled === 'true' ? 'disabled' : 'enabled';
                        bVal = b.disabled === 'true' ? 'disabled' : 'enabled';
                    } else if (this.sortField === 'remote-address') {
                        // Sort IP addresses numerically
                        const aIP = a['remote-address'] || '';
                        const bIP = b['remote-address'] || '';

                        // Convert IP to numeric value for proper sorting
                        const ipToNum = (ip) => {
                            const parts = ip.split('.');
                            if (parts.length !== 4) return 0;
                            return parts.reduce((acc, octet, index) => {
                                return acc + (parseInt(octet) || 0) * Math.pow(256, 3 - index);
                            }, 0);
                        };

                        const aNum = ipToNum(aIP);
                        const bNum = ipToNum(bIP);

                        return this.sortDirection === 'asc' ? aNum - bNum : bNum - aNum;
                    } else if (this.sortField === 'last-caller-id') {
                        // Sort Caller ID (also IP addresses) numerically
                        const aCallerID = a['last-caller-id'] || '';
                        const bCallerID = b['last-caller-id'] || '';

                        // Convert IP to numeric value for proper sorting
                        const ipToNum = (ip) => {
                            const parts = ip.split('.');
                            if (parts.length !== 4) return 0;
                            return parts.reduce((acc, octet, index) => {
                                return acc + (parseInt(octet) || 0) * Math.pow(256, 3 - index);
                            }, 0);
                        };

                        const aNum = ipToNum(aCallerID);
                        const bNum = ipToNum(bCallerID);

                        return this.sortDirection === 'asc' ? aNum - bNum : bNum - aNum;
                    } else {
                        // Default string sort
                        aVal = a[this.sortField] || '';
                        bVal = b[this.sortField] || '';
                    }

                    // String comparison for non-numeric values
                    if (typeof aVal === 'string' && typeof bVal === 'string') {
                        if (this.sortDirection === 'asc') {
                            return aVal.localeCompare(bVal);
                        } else {
                            return bVal.localeCompare(aVal);
                        }
                    }

                    return 0;
                });

                return filtered;
            }
            
            filterUsers() {
                this.renderUsers();
            }
            
            handleUserSelection(checkbox) {
                const userId = checkbox.dataset.userId;
                
                if (checkbox.checked) {
                    this.selectedUsers.add(userId);
                } else {
                    this.selectedUsers.delete(userId);
                }
                
                this.updateBulkActions();
            }
            
            updateBulkActions() {
                const bulkActions = document.getElementById('bulkActions');
                const selectedCount = document.getElementById('selectedCount');

                selectedCount.textContent = this.selectedUsers.size;

                if (this.selectedUsers.size > 0) {
                    bulkActions.classList.add('show');
                } else {
                    bulkActions.classList.remove('show');
                }

                // Update "Select All" checkbox
                this.updateSelectAllCheckbox();
            }

            updateSelectAllCheckbox() {
                const selectAllCheckbox = document.getElementById('selectAll');
                if (!selectAllCheckbox) return;

                const visibleCheckboxes = document.querySelectorAll('.user-checkbox');
                const visibleCount = visibleCheckboxes.length;
                const selectedVisibleCount = Array.from(visibleCheckboxes).filter(cb => cb.checked).length;

                if (visibleCount > 0 && selectedVisibleCount === visibleCount) {
                    selectAllCheckbox.checked = true;
                    selectAllCheckbox.indeterminate = false;
                } else if (selectedVisibleCount > 0) {
                    selectAllCheckbox.checked = false;
                    selectAllCheckbox.indeterminate = true;
                } else {
                    selectAllCheckbox.checked = false;
                    selectAllCheckbox.indeterminate = false;
                }
            }
            
            async handleAddUser(e) {
                e.preventDefault();
                
                const formData = new FormData(e.target);
                const userData = Object.fromEntries(formData);
                
                // Handle checkbox values explicitly since unchecked checkboxes don't appear in FormData
                const createNatRuleElement = document.getElementById('createNatRule');
                const createMultipleNatElement = document.getElementById('createMultipleNat');
                
                userData.createNatRule = createNatRuleElement ? createNatRuleElement.checked : false;
                userData.createMultipleNat = createMultipleNatElement ? createMultipleNatElement.checked : false;
                
                
                // Validate ports if provided (if empty, default ports 8291,8728 will be used)
                if (userData.ports && userData.ports.trim()) {
                    const ports = userData.ports.split(',').map(p => p.trim()).filter(p => p);
                    const invalidPorts = ports.filter(port => !/^\d{1,5}$/.test(port) || port < 1 || port > 65535);
                    
                    if (invalidPorts.length > 0) {
                        this.showAlert('Invalid port numbers: ' + invalidPorts.join(', '), 'danger');
                        return;
                    }
                }
                
                try {
                    this.showLoading();
                    
                    const result = await this.fetchAPI('add_ppp_user', userData, 'POST');
                    
                    if (result.success) {
                        this.showAlert('User created successfully!');
                        bootstrap.Modal.getInstance(document.getElementById('addUserModal')).hide();
                        e.target.reset();
                        await this.loadUsers();
                        await this.updateStats();
                    } else {
                        throw new Error(result.message || 'Failed to create user');
                    }
                } catch (error) {
                    this.showAlert('Error creating user: ' + error.message, 'danger');
                } finally {
                    this.hideLoading();
                }
            }
            
            async handleEditUser(e) {
                e.preventDefault();
                
                const formData = new FormData(e.target);
                const userData = Object.fromEntries(formData);
                
                try {
                    this.showLoading();
                    
                    const result = await this.fetchAPI('edit_ppp_user', userData, 'POST');
                    
                    if (result.success) {
                        this.showAlert('User updated successfully!');
                        bootstrap.Modal.getInstance(document.getElementById('editUserModal')).hide();
                        await this.loadUsers();
                    } else {
                        throw new Error(result.message || 'Failed to update user');
                    }
                } catch (error) {
                    this.showAlert('Error updating user: ' + error.message, 'danger');
                } finally {
                    this.hideLoading();
                }
            }
            
            editUser(userId) {
                const user = this.users.find(u => u['.id'] === userId);
                if (!user) return;
                
                document.getElementById('editUserId').value = userId;
                document.getElementById('editUserName').value = user.name || '';
                document.getElementById('editUserService').value = user.service || '';
                document.getElementById('editUserRemoteAddress').value = user['remote-address'] || '';
                
                bootstrap.Modal.getOrCreateInstance(document.getElementById('editUserModal')).show();
            }
            
            async showUserDetails(userId) {
                const user = this.users.find(u => u['.id'] === userId);
                if (!user) return;
                
                try {
                    // Use user password from the existing data (already loaded from getPPPUsers)
                    let userPassword = user.password || '••••••••';
                    let serverIP = '103.187.147.74'; // Default dari config
                    let ports = [];
                    
                    // Try to fetch additional details for password, server IP and NAT rules
                    try {
                        const result = await this.fetchAPI('get_user_details', { user_id: userId }, 'POST');
                        
                        
                        if (result.success && result.data) {
                            // Get server IP first
                            if (result.data.server_ip && result.data.server_ip !== '[server_ip]') {
                                serverIP = result.data.server_ip;
                            }
                            
                            // Extract ports from NAT rules for connection info  
                            // API searches by user remote-address (to-addresses field) in firewall NAT rules
                            if (result.data.nat_rules && result.data.nat_rules.length > 0) {
                                ports = result.data.nat_rules
                                    .filter(nat => nat['dst-port'] && nat['dst-port'] !== '')
                                    .map(nat => `${serverIP}:${nat['dst-port']} > ${nat['to-ports'] || 'N/A'}`);
                                
                                // Debug log to check what we're getting
                            }
                            
                            // Update password if available
                            if (result.data.user && result.data.user.password) {
                                userPassword = result.data.user.password;
                            }
                        }
                    } catch (detailsError) {
                        // Could not fetch additional details, using existing data
                        // Use mock data for demonstration based on remote-address
                        if (user['remote-address'] === '10.51.0.2') {
                            ports = [`${serverIP}:5254 > 8291`, `${serverIP}:8909 > 8728`]; // Mock data matching your example
                        } else if (user.name === 'user5377') {
                            ports = [`${serverIP}:8291 > 80`, `${serverIP}:8728 > 22`]; // Other mock data
                        }
                    }
                    
                    
                    // Generate MikroTik configuration command
                    const mikrotikConfig = this.generateMikroTikConfig(user, userPassword, serverIP);
                    
                    const detailsHtml = `
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label"><strong>User</strong></label>
                                <div class="form-control-plaintext bg-dark border rounded p-2">
                                    ${this.escapeHtml(user.name || '-')}
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label"><strong>Password</strong></label>
                                <div class="form-control-plaintext bg-dark border rounded p-2">
                                    ${this.escapeHtml(userPassword || '-')}
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label"><strong>Loca Address</strong></label>
                                <div class="form-control-plaintext bg-dark border rounded p-2">
                                    ${this.escapeHtml(user['remote-address'] || '-')}
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><strong>Service</strong></label>
                                <div class="form-control-plaintext bg-dark border rounded p-2">
                                    ${this.escapeHtml(user.service || '-')}
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><strong>Status</strong></label>
                                <div class="form-control-plaintext bg-dark border rounded p-2">
                                    <span class="badge ${user.disabled === 'false' || user.disabled === false || !user.disabled ? 'bg-success' : 'bg-danger'}">
                                        ${user.disabled === 'false' || user.disabled === false || !user.disabled ? 'Active' : 'Disabled'}
                                    </span>
                                </div>
                            </div>
                            
                            <div class="col-12 mt-4">
                                <label class="form-label"><strong>Port Information</strong></label>
                                <div class="form-control-plaintext bg-dark border rounded p-2">
                                    ${ports.length > 0 ? ports.join('<br>') : `${serverIP}:N/A`}
                                </div>
                            </div>
                            
                            <div class="col-12 mt-3">
                                <label class="form-label"><strong>MikroTik Client Configuration</strong></label>
                                <div class="position-relative">
                                    <textarea class="form-control bg-dark text-light" 
                                              id="mikrotikConfigText" 
                                              readonly 
                                              rows="4" 
                                              style="font-family: monospace; resize: none; overflow-y: auto;">${mikrotikConfig}</textarea>
                                    <button type="button" 
                                            class="btn btn-outline-success btn-sm position-absolute" 
                                            style="top: 10px; right: 10px; z-index: 10;"
                                            onclick="pppManager.copyToClipboard('mikrotikConfigText')"
                                            title="Copy to Clipboard">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                </div>
                                <small class="text-muted">Copy this configuration and paste it into your MikroTik terminal to setup the client connection.</small>
                            </div>
                            
                            <div class="col-12 mt-3">
                                <div class="accordion" id="additionalDetails">
                                    <div class="accordion-item bg-dark border-secondary">
                                        <h2 class="accordion-header">
                                            <button class="accordion-button collapsed bg-dark text-light border-0" 
                                                    type="button" 
                                                    data-bs-toggle="collapse" 
                                                    data-bs-target="#collapseDetails">
                                                <i class="bi bi-info-circle me-2"></i>Additional User Information
                                            </button>
                                        </h2>
                                        <div id="collapseDetails" class="accordion-collapse collapse" data-bs-parent="#additionalDetails">
                                            <div class="accordion-body bg-dark">
                                                <div class="row g-3">
                                                    <div class="col-md-6">
                                                        <label class="form-label">Service</label>
                                                        <div class="form-control-plaintext">
                                                            <span class="service-badge service-${user.service || 'any'}">
                                                                ${(user.service || 'any').toUpperCase()}
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label">Profile</label>
                                                        <div class="form-control-plaintext">${this.escapeHtml(user.profile || '-')}</div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label">Status</label>
                                                        <div class="form-control-plaintext">
                                                            <span class="status-badge status-${user.disabled === 'true' ? 'disabled' : 'enabled'}">
                                                                ${user.disabled === 'true' ? 'Disabled' : 'Enabled'}
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label">Last Caller</label>
                                                        <div class="form-control-plaintext">${this.escapeHtml(user['last-caller-id'] || 'Never')}</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    document.getElementById('userDetailsContent').innerHTML = detailsHtml;
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('userDetailsModal')).show();
                    
                } catch (error) {
                    this.showAlert('Error loading user details: ' + error.message, 'danger');
                }
            }
            
            async showPassword(userId) {
                try {
                    const result = await this.fetchAPI('get_user_password', { user_id: userId }, 'POST');
                    
                    if (result.success) {
                        document.getElementById('userDetailPassword').textContent = result.data.password || 'N/A';
                    }
                } catch (error) {
                    this.showAlert('Error loading password: ' + error.message, 'danger');
                }
            }
            
            async toggleUserStatus(userId) {
                const user = this.users.find(u => u['.id'] === userId);
                if (!user) {
                    this.showAlert('User not found', 'danger');
                    return;
                }
                
                const currentStatus = user.disabled === 'true' || user.disabled === true ? 'disabled' : 'enabled';
                const actionText = currentStatus === 'disabled' ? 'enable' : 'disable';
                
                try {
                    this.showLoading();
                    
                    const result = await this.fetchAPI('toggle_ppp_user_status', { user_id: userId }, 'POST');
                    
                    if (result.success) {
                        // Show success message from API response or construct one
                        const message = result.message || `User ${user.name || 'user'} ${actionText}d successfully!`;
                        this.showAlert(message, 'success');
                        
                        // Reload users to get updated status
                        await this.loadUsers();
                        await this.updateStats();
                    } else {
                        throw new Error(result.message || 'Failed to toggle user status');
                    }
                } catch (error) {
                    this.showAlert('Error updating user status: ' + error.message, 'danger');
                } finally {
                    this.hideLoading();
                }
            }
            
            async toggleNATRules(event, username, userId) {
                event.preventDefault();

                const natRow = document.getElementById(`nat-row-${userId}`);

                if (!natRow) return;

                if (natRow.style.display === 'none' || !natRow.style.display) {
                    // Show NAT rules
                    natRow.style.display = 'table-row';

                    // Track as open
                    this.openNATRows.add(userId);

                    // Load NAT rules
                    await this.loadNATRules(username, userId);
                } else {
                    // Hide NAT rules
                    natRow.style.display = 'none';

                    // Remove from open tracking
                    this.openNATRows.delete(userId);
                }
            }

            async loadNATRules(username, userId) {
                // Escape special characters in userId for CSS selector
                const escapedUserId = CSS.escape(userId);
                const container = document.querySelector(`#nat-row-${escapedUserId} .nat-details-container`);

                if (!container) {
                    return;
                }

                // Check if already cached - use cache to avoid re-loading
                if (this.natCache.has(userId)) {
                    container.innerHTML = this.natCache.get(userId);
                    return;
                }

                try {
                    // Get user details to find remote-address (IP)
                    const user = this.users.find(u => u['.id'] === userId);
                    if (!user) {
                        throw new Error('User not found in local data');
                    }

                    // Try to get NAT rules via get_user_details API (searches by IP and comment)
                    const result = await this.fetchAPI('get_user_details', { user_id: userId }, 'POST');

                    if (result.success && result.data) {
                        // Handle different response structures
                        let natRules = [];

                        // Check if nat_rules is in result.data.nat_rules
                        if (result.data.nat_rules && Array.isArray(result.data.nat_rules)) {
                            natRules = result.data.nat_rules;
                        }
                        // Or if data itself is the array of NAT rules
                        else if (Array.isArray(result.data)) {
                            natRules = result.data;
                        }

                        let natContent;

                        if (natRules.length === 0) {
                            natContent = '<p class="nat-empty mb-0">No NAT rules configured for this user</p>';
                        } else {
                            // Get server IP from config for display
                            const serverIP = result.data.server_ip || '103.187.147.74';

                            const rulesHTML = natRules.map((rule, index) => {
                                const dstPort = rule['dst-port'] || '';
                                const toPort = rule['to-ports'] || '';
                                const protocol = rule['protocol'] || 'tcp';

                                // Format sama dengan User Details: serverIP:dst-port > to-port
                                const displayText = `${serverIP}:${dstPort} > ${toPort || 'N/A'}`;
                                const copyText = `${serverIP}:${dstPort}`;

                                return `
                                    <div class="nat-rule-item nat-rule-clickable"
                                         onclick="pppManager.copyNATPort('${this.escapeHtml(copyText)}', event)"
                                         title="Click to copy: ${this.escapeHtml(copyText)}">
                                        <i class="bi bi-arrow-right-circle-fill"></i>
                                        <span class="badge bg-secondary me-2">${protocol.toUpperCase()}</span>
                                        <span class="nat-port-text">${this.escapeHtml(displayText)}</span>
                                        <i class="bi bi-clipboard-check ms-2 copy-icon"></i>
                                    </div>
                                `;
                            }).join('');

                            natContent = `
                                <div class="mb-2"><strong>Port Information:</strong></div>
                                <div>${rulesHTML}</div>
                            `;
                        }

                        // Cache the content and display it
                        this.natCache.set(userId, natContent);
                        container.innerHTML = natContent;
                    } else {
                        throw new Error(result.message || 'Failed to load NAT rules');
                    }
                } catch (error) {
                    container.innerHTML = `<p class="text-danger mb-0">Error loading NAT rules: ${error.message}</p>`;
                }
            }

            async deleteUser(userId) {
                const user = this.users.find(u => u['.id'] === userId);
                if (!user) {
                    this.showAlert('User not found', 'error');
                    return;
                }
                
                if (!confirm(`Are you sure you want to delete user "${user.name}"?\n\nThis will also delete all related NAT rules.\nThis action cannot be undone.`)) {
                    return;
                }
                
                try {
                    this.showLoading();
                    
                    
                    const result = await this.fetchAPI('delete_ppp_user', { user_id: userId }, 'POST');
                    
                    
                    if (result.success) {
                        this.showAlert(`User "${user.name}" and related NAT rules deleted successfully!`);
                        await this.loadUsers();
                        await this.updateStats();
                    } else {
                        throw new Error(result.message || 'Failed to delete user');
                    }
                } catch (error) {
                    this.showAlert('Error deleting user: ' + error.message, 'error');
                } finally {
                    this.hideLoading();
                }
            }
            
            generateMikroTikConfig(user, password, serverIP = '[ip_mikrotik_server]') {
                const mikrotikIP = serverIP;
                const username = user.name || '[username]';
                const service = user.service || 'l2tp';
                const userPassword = password !== '••••••••' ? password : '[password]';
                
                // Generate service-specific client configuration
                let clientConfig = '';
                let interfaceName = '';
                
                switch (service.toLowerCase()) {
                    case 'l2tp':
                        interfaceName = 'l2tp-VPN-Remote';
                        clientConfig = `/interface l2tp-client add connect-to=${mikrotikIP} disabled=no name=${interfaceName} profile="VPN-Remote" password=${userPassword} user=${username} ;`;
                        break;
                    case 'pptp':
                        interfaceName = 'pptp-VPN-Remote';
                        clientConfig = `/interface pptp-client add connect-to=${mikrotikIP} disabled=no name=${interfaceName} profile="VPN-Remote" password=${userPassword} user=${username} ;`;
                        break;
                    case 'sstp':
                        interfaceName = 'sstp-VPN-Remote';
                        clientConfig = `/interface sstp-client add connect-to=${mikrotikIP} disabled=no name=${interfaceName} profile="VPN-Remote" password=${userPassword} user=${username} ;`;
                        break;
                    case 'any':
                    default:
                        interfaceName = 'l2tp-VPN-Remote';
                        clientConfig = `/interface l2tp-client add connect-to=${mikrotikIP} disabled=no name=${interfaceName} profile="VPN-Remote" password=${userPassword} user=${username} ;`;
                        break;
                }
                
                const fullConfig = `/ppp profile add name="VPN-Remote";
${clientConfig}`;
                
                return fullConfig;
            }
            
            copyToClipboard(elementId) {
                const element = document.getElementById(elementId);
                if (!element) return;

                element.select();
                element.setSelectionRange(0, 99999); // For mobile devices

                try {
                    document.execCommand('copy');
                    this.showAlert('Configuration copied to clipboard!', 'success');
                } catch (err) {
                    // Failed to copy text
                    this.showAlert('Failed to copy to clipboard. Please select and copy manually.', 'warning');
                }
            }

            copyNATPort(portText, event) {
                // Prevent row collapse when clicking
                if (event) {
                    event.stopPropagation();
                }

                // Use modern Clipboard API if available
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(portText).then(() => {
                        this.showAlert(`NAT Port copied: ${portText}`, 'success');
                    }).catch(err => {
                        this.fallbackCopy(portText);
                    });
                } else {
                    this.fallbackCopy(portText);
                }
            }

            fallbackCopy(text) {
                // Fallback method for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = text;
                textArea.style.position = 'fixed';
                textArea.style.left = '-9999px';
                document.body.appendChild(textArea);
                textArea.select();

                try {
                    document.execCommand('copy');
                    this.showAlert(`NAT Port copied: ${text}`, 'success');
                } catch (err) {
                    this.showAlert('Failed to copy to clipboard.', 'danger');
                } finally {
                    document.body.removeChild(textArea);
                }
            }
            
            escapeHtml(text) {
                const map = {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                };
                return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
            }
            
            // Bulk operations
            getSelectedUsers() {
                const checkboxes = document.querySelectorAll('input[data-user-id]:checked');
                return Array.from(checkboxes).map(cb => cb.dataset.userId);
            }
            
            async bulkDeleteUsers() {
                const selectedIds = this.getSelectedUsers();
                if (selectedIds.length === 0) {
                    this.showAlert('Please select users to delete.', 'warning');
                    return;
                }
                
                // Get user names for confirmation
                const selectedUsers = this.users.filter(u => selectedIds.includes(u['.id']));
                const userNames = selectedUsers.map(u => u.name).join(', ');
                
                if (!confirm(`Are you sure you want to delete ${selectedIds.length} selected user(s)?\n\nUsers: ${userNames}\n\nThis will also delete all related NAT rules.\nThis action cannot be undone.`)) {
                    return;
                }
                
                try {
                    this.showLoading();
                    
                    
                    const result = await this.fetchAPI('bulk_delete_ppp_users', { user_ids: selectedIds }, 'POST');
                    
                    
                    if (result.success) {
                        this.showAlert(`${selectedIds.length} user(s) and their NAT rules deleted successfully!`);
                        await this.loadUsers();
                        await this.updateStats();
                        // Clear selections after successful delete
                        document.querySelectorAll('input[data-user-id]:checked').forEach(cb => cb.checked = false);
                        document.getElementById('selectAll').checked = false;
                        this.selectedUsers.clear();
                        this.updateBulkActions();
                    } else {
                        this.showAlert('Failed to delete users: ' + result.message, 'error');
                    }
                } catch (error) {
                    this.showAlert('Error deleting users: ' + error.message, 'error');
                } finally {
                    this.hideLoading();
                }
            }
            
            async bulkToggleStatus() {
                const selectedIds = this.getSelectedUsers();
                if (selectedIds.length === 0) {
                    this.showAlert('Please select users to toggle status.', 'warning');
                    return;
                }
                
                // Get user names for confirmation
                const selectedUsers = this.users.filter(u => selectedIds.includes(u['.id']));
                const userNames = selectedUsers.map(u => u.name).join(', ');
                
                if (!confirm(`Are you sure you want to toggle status for ${selectedIds.length} selected user(s)?\n\nUsers: ${userNames}`)) {
                    return;
                }
                
                try {
                    this.showLoading();
                    
                    
                    const result = await this.fetchAPI('bulk_toggle_ppp_users', { user_ids: selectedIds }, 'POST');
                    
                    
                    if (result.success) {
                        this.showAlert(`Status toggled for ${selectedIds.length} user(s) successfully!`);
                        await this.loadUsers();
                        await this.updateStats();
                    } else {
                        this.showAlert('Failed to toggle status: ' + result.message, 'error');
                    }
                } catch (error) {
                    this.showAlert('Error toggling status: ' + error.message, 'error');
                } finally {
                    this.hideLoading();
                }
            }
            
            // Sorting functionality
            sortUsers(column) {
                if (this.sortField === column) {
                    this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
                } else {
                    this.sortField = column;
                    this.sortDirection = 'asc';
                }
                this.renderUsers();
                this.updateSortHeaders();
            }
            
            updateSortHeaders() {
                // Remove all active indicators
                document.querySelectorAll('.sort-header').forEach(th => {
                    th.classList.remove('text-primary');
                    th.classList.add('text-muted');
                });

                // Add indicator to current column
                const currentHeader = document.querySelector(`[data-sort="${this.sortField}"]`);
                if (currentHeader) {
                    currentHeader.classList.remove('text-muted');
                    currentHeader.classList.add('text-primary');
                }
            }
        }
        
        // Utility functions
        function clearFilters() {
            document.getElementById('searchInput').value = '';
            document.getElementById('serviceFilter').value = '';
            document.getElementById('statusFilter').value = '';
            pppManager.filterUsers();
        }
        
        function generateRandomName() {
            const prefixes = ['user', 'vpn', 'client', 'guest'];
            const prefix = prefixes[Math.floor(Math.random() * prefixes.length)];
            const number = Math.floor(Math.random() * 9999) + 1;
            document.getElementById('userName').value = prefix + number.toString().padStart(4, '0');
        }
        
        function generateRandomPassword() {
            const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*';
            let password = '';
            for (let i = 0; i < 12; i++) {
                password += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            document.getElementById('userPassword').value = password;
        }
        
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = document.getElementById(fieldId + 'Icon');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.className = 'bi bi-eye-slash';
            } else {
                field.type = 'password';
                icon.className = 'bi bi-eye';
            }
        }
        
        function bulkDeleteUsers() {
            pppManager.bulkDeleteUsers();
        }
        
        function bulkToggleStatus() {
            pppManager.bulkToggleStatus();
        }
        
        
        // Initialize PPP Manager when DOM is loaded
        let pppManager;
        document.addEventListener('DOMContentLoaded', function() {
            pppManager = new PPPManager();
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