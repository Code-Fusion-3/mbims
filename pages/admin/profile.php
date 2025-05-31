<?php
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';
require_once '../../config/database.php';

// Require admin role
require_role('admin');

$page_title = 'Admin Profile';

// Redirect to common profile page
header('Location: ../common/profile.php');
exit();
?>