<?php
// Security configurations
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_strict_mode', 1);

session_start();

// Constants
define('SESSION_TIMEOUT', 3600); // 60 minutes
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes
define('MAX_USERNAME_LENGTH', 50);
define('MAX_PASSWORD_LENGTH', 255);

// Check if user is already logged in
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    // Check session timeout
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        header('Location: index.php?timeout=1');
        exit;
    }
    
    // Regenerate session ID periodically for security
    if (!isset($_SESSION['created']) || (time() - $_SESSION['created']) > 1800) {
        session_regenerate_id(true);
        $_SESSION['created'] = time();
    }
    
    // Update last activity
    $_SESSION['last_activity'] = time();
    
    // Redirect to admin panel
    header('Location: pages/admin.php');
    exit;
}

require_once 'includes/config.php';
require_once 'includes/auth.php';

// Initialize variables
$error = '';
$timeout_msg = '';
$client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

// Rate limiting functions
function getRateLimitKey($ip) {
    return 'login_attempts_' . md5($ip);
}

function isRateLimited($ip) {
    $key = getRateLimitKey($ip);
    $attempts = $_SESSION[$key] ?? ['count' => 0, 'last_attempt' => 0];
    
    // Reset counter if lockout time has passed
    if (time() - $attempts['last_attempt'] > LOGIN_LOCKOUT_TIME) {
        unset($_SESSION[$key]);
        return false;
    }
    
    return $attempts['count'] >= MAX_LOGIN_ATTEMPTS;
}

function recordFailedAttempt($ip) {
    $key = getRateLimitKey($ip);
    $attempts = $_SESSION[$key] ?? ['count' => 0, 'last_attempt' => 0];
    
    $attempts['count']++;
    $attempts['last_attempt'] = time();
    $_SESSION[$key] = $attempts;
}

function resetLoginAttempts($ip) {
    $key = getRateLimitKey($ip);
    unset($_SESSION[$key]);
}

// Handle timeout message
if (isset($_GET['timeout']) && $_GET['timeout'] === '1') {
    $timeout_msg = 'Session expired due to inactivity. Please login again.';
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF protection
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } elseif (isRateLimited($client_ip)) {
        $error = 'Too many failed attempts. Please try again in ' . ceil(LOGIN_LOCKOUT_TIME / 60) . ' minutes.';
    } else {
        // Input validation
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        // Validate input lengths
        if (strlen($username) > MAX_USERNAME_LENGTH || strlen($password) > MAX_PASSWORD_LENGTH) {
            $error = 'Invalid input length.';
            recordFailedAttempt($client_ip);
        } elseif (empty($username) || empty($password)) {
            $error = 'Please enter both username and password.';
            recordFailedAttempt($client_ip);
        } elseif (authenticate($username, $password)) {
            // Successful login
            resetLoginAttempts($client_ip);
            
            // Regenerate session ID for security
            session_regenerate_id(true);
            
            $_SESSION['logged_in'] = true;
            $_SESSION['username'] = $username;
            $_SESSION['last_activity'] = time();
            $_SESSION['login_time'] = time();
            $_SESSION['created'] = time();
            
            header('Location: pages/admin.php');
            exit;
        } else {
            $error = 'Invalid username or password.';
            recordFailedAttempt($client_ip);
        }
    }
}

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
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
    <title>VPN Remote - Login</title>
    <link rel="icon" href="favicon.ico" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="login-body">
    <div class="container-fluid vh-100">
        <div class="row h-100">
            <div class="col-md-6 d-flex align-items-center justify-content-center">
                <div class="login-container">
                    <div class="text-center mb-4">
                        <div class="login-logo mb-3">
                            <div class="logo-circle">
                                <i class="bi bi-shield-lock-fill"></i>
                            </div>
                        </div>
                        <h2 class="login-title">
                            VPN Remote
                        </h2>
                        <p class="login-subtitle">MikroTik VPN Remote Manager</p>
                        <div class="login-divider"></div>
                    </div>
                    
                    <?php if ($timeout_msg): ?>
                    <div class="alert alert-warning" role="alert">
                        <i class="bi bi-clock-history me-2"></i>
                        <?php echo htmlspecialchars($timeout_msg); ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" class="login-form" autocomplete="on">
                        <!-- CSRF Token -->
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        
                        <div class="mb-3">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="username" name="username" 
                                       value="<?php echo htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
                                       placeholder=" " required autofocus autocomplete="username"
                                       maxlength="<?php echo MAX_USERNAME_LENGTH; ?>">
                                <label for="username">
                                    <i class="bi bi-person me-2"></i>Username
                                </label>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <div class="input-group has-validation">
                                <div class="form-floating flex-grow-1">
                                    <input type="password" class="form-control border-end-0" id="password" name="password" 
                                           placeholder=" " required autocomplete="current-password"
                                           maxlength="<?php echo MAX_PASSWORD_LENGTH; ?>">
                                    <label for="password">
                                        <i class="bi bi-lock me-2"></i>Password
                                    </label>
                                </div>
                                <button class="btn btn-outline-secondary border-start-0" type="button" id="togglePassword" tabindex="-1">
                                    <i class="bi bi-eye" id="toggleIcon"></i>
                                </button>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100 login-btn">
                            <span class="btn-text">
                                <i class="bi bi-box-arrow-in-right me-2"></i>
                                Login
                            </span>
                            <span class="btn-loading d-none">
                                <i class="bi bi-arrow-clockwise spin me-2"></i>
                                Logging in...
                            </span>
                        </button>
                    </form>
                    
                </div>
            </div>
            
            <div class="col-md-6 d-none d-md-flex align-items-center justify-content-center login-bg">
                <div class="text-center text-white">
                    <div class="hero-animation">
                        <div class="floating-element">
                            <i class="bi bi-router display-1 mb-4"></i>
                        </div>
                        <div class="connection-lines">
                            <div class="line line-1"></div>
                            <div class="line line-2"></div>
                            <div class="line line-3"></div>
                        </div>
                    </div>
                    <h3 class="hero-title">MikReMan V.1.69</h3>
                    <p class="hero-subtitle">Manage your MikroTik VPN users with ease</p>
                    <div class="feature-list mt-4">
                        <div class="feature-item">
                            <i class="bi bi-check-circle me-2"></i>
                            Real-time monitoring
                        </div>
                        <div class="feature-item">
                            <i class="bi bi-check-circle me-2"></i>
                            Easy service management
                        </div>
                        <div class="feature-item">
                            <i class="bi bi-check-circle me-2"></i>
                            Secure configuration
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <footer class="footer">
        <div class="container text-center">
            <small class="text-muted">Made by Mostech</small>
        </div>
    </footer>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/login.js"></script>
</body>
</html>