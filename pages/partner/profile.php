<?php
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';
require_once '../../config/database.php';

// Require partner role
require_role('partner');

$page_title = 'Partner Profile';

// Redirect to common profile page
header('Location: ../common/profile.php');
exit();
?>