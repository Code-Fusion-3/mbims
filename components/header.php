<?php
if (!function_exists('get_logged_user')) {
    $session_path = file_exists('../includes/session.php') ? '../includes/session.php' : 'includes/session.php';
    require_once $session_path;
}

$current_user = get_logged_user();
if (!$current_user) {
    header('Location: ../../index.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' . APP_NAME : APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body class="bg-gray-100">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <h1 class="text-xl font-bold text-gray-800"><?php echo APP_NAME; ?></h1>
                    </div>
                </div>

                <div class="flex items-center space-x-4">
                    <span class="text-gray-700">Welcome, <?php echo $current_user['first_name']; ?></span>
                    <span
                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo get_role_badge_class($current_user['role']); ?>">
                        <?php echo ucfirst($current_user['role']); ?>
                    </span>
                    <a href="../../api/auth/logout.php"
                        class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>