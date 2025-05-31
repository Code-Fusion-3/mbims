<?php
require_once 'config/config.php';
require_once 'includes/session.php';

// Destroy the session
destroy_user_session();

// Redirect to login page with success message
header('Location: index.php?error=logout');
exit();
?>