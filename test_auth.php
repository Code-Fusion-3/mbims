<?php
require_once 'config/config.php';
require_once 'includes/session.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

echo "<h1>Testing Authentication System</h1>";

// Test login with default admin credentials
$auth = new Auth();
$result = $auth->login('admin@mbims.com', 'admin123');

if ($result['success']) {
    echo "<p style='color: green;'>✓ Login test successful!</p>";
    echo "<p>User: " . $result['user']['first_name'] . " " . $result['user']['last_name'] . "</p>";
    echo "<p>Role: " . $result['user']['role'] . "</p>";
    echo "<p>Redirect URL: " . $result['redirect'] . "</p>";
    
    // Test session functions
    if (is_logged_in()) {
        echo "<p style='color: green;'>✓ Session management working!</p>";
        $current_user = get_logged_user();
        echo "<p>Current user: " . $current_user['full_name'] . "</p>";
    }
    
} else {
    echo "<p style='color: red;'>✗ Login test failed: " . $result['message'] . "</p>";
}
?>