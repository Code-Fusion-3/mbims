<?php

require_once '../../includes/session.php';
require_once '../../includes/auth.php';

// Check if user is logged in
if (!is_logged_in()) {
    header('Location: ../../index.php');
    exit();
}

// Logout user
$auth = new Auth();
$result = $auth->logout();

// Redirect to login page with success message
header('Location: ../../index.php?logout=1');
exit();

?>