<?php

function authenticate($username, $password) {
    $auth_config = getConfig('auth');
    
    if (!$auth_config) {
        return false;
    }
    
    // Check username and verify password using hash
    if ($username === $auth_config['username']) {
        // Use password_hash if available, fallback to password for backward compatibility
        $password_to_verify = $auth_config['password_hash'] ?? $auth_config['password'];
        
        if (isset($auth_config['password_hash'])) {
            // New system: use password_hash for verification
            return password_verify($password, $password_to_verify);
        } else {
            // Old system: direct password comparison (for migration)
            return $password === $password_to_verify;
        }
    }
    
    return false;
}

function updateAuthCredentials($username, $password) {
    $auth_data = [
        'username' => $username,
        'password' => $password, // Store plaintext for admin retrieval
        'password_hash' => password_hash($password, PASSWORD_DEFAULT) // Store hash for verification
    ];
    
    return updateConfigSection('auth', $auth_data);
}

function isLoggedIn() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

function checkSession() {
    if (!isLoggedIn()) {
        header('Location: ../index.php');
        exit;
    }
    
    // Check session timeout
    $timeout = getConfig('system', 'session_timeout') ?? 3600;
    
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
        session_unset();
        session_destroy();
        header('Location: ../index.php?timeout=1');
        exit;
    }
    
    // Update last activity
    $_SESSION['last_activity'] = time();
}

function logout() {
    session_unset();
    session_destroy();
    header('Location: ../index.php');
    exit;
}

function requireAuth() {
    if (!isLoggedIn()) {
        if (ob_get_level() > 0) ob_clean(); // Clear any output buffer before sending JSON
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Authentication required']);
        exit;
    }

    // Check session timeout
    $timeout = getConfig('system', 'session_timeout') ?? 3600;

    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
        session_unset();
        session_destroy();
        if (ob_get_level() > 0) ob_clean(); // Clear any output buffer before sending JSON
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Session timeout']);
        exit;
    }

    // Update last activity
    $_SESSION['last_activity'] = time();
}
?>