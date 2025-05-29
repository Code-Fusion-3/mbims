<?php
// Determine the correct path to config based on current location
$config_path = '';
if (strpos($_SERVER['PHP_SELF'], '/pages/') !== false) {
    // We're in a pages subdirectory
    $config_path = '../../config/config.php';
} else {
    // We're in the root or includes directory
    $config_path = '../config/config.php';
}

// Check if config file exists, if not try alternative paths
if (!file_exists($config_path)) {
    if (file_exists('config/config.php')) {
        $config_path = 'config/config.php';
    } elseif (file_exists('../config/config.php')) {
        $config_path = '../config/config.php';
    } elseif (file_exists('../../config/config.php')) {
        $config_path = '../../config/config.php';
    }
}

require_once $config_path;
// Start session if not already started
function start_session() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
}

// Check if user is logged in
function is_logged_in() {
    start_session();
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
}

// Get current user data (renamed to avoid PHP built-in function conflict)
function get_logged_user() {
    start_session();
    if (is_logged_in()) {
        return [
            'id' => $_SESSION['user_id'],
            'email' => $_SESSION['user_email'],
            'first_name' => $_SESSION['user_first_name'],
            'last_name' => $_SESSION['user_last_name'],
            'role' => $_SESSION['user_role'],
            'full_name' => $_SESSION['user_first_name'] . ' ' . $_SESSION['user_last_name']
        ];
    }
    return null;
}

// Set user session data
function set_user_session($user_data) {
    start_session();
    $_SESSION['user_id'] = $user_data['id'];
    $_SESSION['user_email'] = $user_data['email'];
    $_SESSION['user_first_name'] = $user_data['first_name'];
    $_SESSION['user_last_name'] = $user_data['last_name'];
    $_SESSION['user_role'] = $user_data['role'];
    $_SESSION['login_time'] = time();
}

// Destroy user session
function destroy_user_session() {
    start_session();
    session_unset();
    session_destroy();
}

// Check if session is expired
function is_session_expired() {
    start_session();
    if (isset($_SESSION['login_time'])) {
        return (time() - $_SESSION['login_time']) > SESSION_TIMEOUT;
    }
    return true;
}

// Require login (redirect if not logged in)
function require_login() {
    if (!is_logged_in() || is_session_expired()) {
        destroy_user_session();
        header('Location: ../index.php?error=session_expired');
        exit();
    }
}

// Check user role
function has_role($required_role) {
    $user = get_logged_user();
    if (!$user) return false;
    
    // Admin has access to everything
    if ($user['role'] == 'admin') return true;
    
    // Check specific role
    return $user['role'] == $required_role;
}

// Require specific role
function require_role($required_role) {
    require_login();
    if (!has_role($required_role)) {
        header('Location: ../pages/common/unauthorized.php');
        exit();
    }
}
?>