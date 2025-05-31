<?php
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';
require_once '../../config/database.php';

// Require accountant role
require_role('accountant');

$page_title = 'Accountant Profile';

// Redirect to common profile page
header('Location: ../common/profile.php');
exit();
?>