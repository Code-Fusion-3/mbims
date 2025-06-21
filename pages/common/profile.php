<?php
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';
require_once '../../config/database.php';

// Require login
require_login();


$page_title = 'My Profile';
$current_user = get_logged_user();

// Get database connection
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Handle form submissions
$success_message = '';
$error_message = '';

// Update profile information
if (isset($_POST['update_profile']) && $current_user['role'] !== 'accountant') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);

    // Validate input
    if (empty($first_name) || empty($last_name) || empty($email)) {
        $error_message = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address.";
    } else {
        // Check if email is already taken by another user
        $email_check_sql = "SELECT id FROM users WHERE email = ? AND id != ?";
        $stmt = mysqli_prepare($conn, $email_check_sql);
        mysqli_stmt_bind_param($stmt, "si", $email, $current_user['id']);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($result) > 0) {
            $error_message = "Email address is already in use by another user.";
        } else {
            // Update user profile
            $update_sql = "UPDATE users SET first_name = ?, last_name = ?, email = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            $stmt = mysqli_prepare($conn, $update_sql);
            mysqli_stmt_bind_param($stmt, "sssi", $first_name, $last_name, $email, $current_user['id']);

            if (mysqli_stmt_execute($stmt)) {
                // Update session data
                $_SESSION['user_first_name'] = $first_name;
                $_SESSION['user_last_name'] = $last_name;
                $_SESSION['user_email'] = $email;

                $success_message = "Profile updated successfully!";
                $current_user = get_logged_user(); // Refresh current user data
            } else {
                $error_message = "Error updating profile: " . mysqli_error($conn);
            }
        }
    }
}

// Change password
if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate input
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error_message = "All password fields are required.";
    } elseif (strlen($new_password) < 6) {
        $error_message = "New password must be at least 6 characters long.";
    } elseif ($new_password !== $confirm_password) {
        $error_message = "New passwords do not match.";
    } else {
        // Verify current password
        $user_sql = "SELECT password FROM users WHERE id = ?";
        $stmt = mysqli_prepare($conn, $user_sql);
        mysqli_stmt_bind_param($stmt, "i", $current_user['id']);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user_data = mysqli_fetch_assoc($result);

        if (!password_verify($current_password, $user_data['password'])) {
            $error_message = "Current password is incorrect.";
        } else {
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_sql = "UPDATE users SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            $stmt = mysqli_prepare($conn, $update_sql);
            mysqli_stmt_bind_param($stmt, "si", $hashed_password, $current_user['id']);

            if (mysqli_stmt_execute($stmt)) {
                $success_message = "Password changed successfully!";
            } else {
                $error_message = "Error changing password: " . mysqli_error($conn);
            }
        }
    }
}

// Get user statistics based on role
$user_stats = [];

if ($current_user['role'] == 'partner') {
    // Get partner statistics
    $stats_sql = "SELECT 
                  (SELECT COUNT(*) FROM businesses WHERE owner_id = ? AND status = 'active') as owned_businesses,
                  (SELECT COUNT(*) FROM transactions WHERE user_id = ? AND status = 'active') as total_transactions,
                  (SELECT SUM(amount) FROM transactions WHERE user_id = ? AND type = 'income' AND status = 'active') as total_income,
                  (SELECT SUM(amount) FROM transactions WHERE user_id = ? AND type = 'expense' AND status = 'active') as total_expenses";
    $stmt = mysqli_prepare($conn, $stats_sql);
    mysqli_stmt_bind_param($stmt, "iiii", $current_user['id'], $current_user['id'], $current_user['id'], $current_user['id']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user_stats = mysqli_fetch_assoc($result);
} elseif ($current_user['role'] == 'accountant') {
    // Get accountant statistics
    $stats_sql = "SELECT 
                  (SELECT COUNT(*) FROM user_business_assignments WHERE user_id = ?) as assigned_businesses,
                  (SELECT COUNT(*) FROM transactions WHERE user_id = ? AND status = 'active') as total_transactions,
                  (SELECT COUNT(DISTINCT business_id) FROM transactions WHERE user_id = ? AND status = 'active') as businesses_worked_on";
    $stmt = mysqli_prepare($conn, $stats_sql);
    mysqli_stmt_bind_param($stmt, "iii", $current_user['id'], $current_user['id'], $current_user['id']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user_stats = mysqli_fetch_assoc($result);
} elseif ($current_user['role'] == 'admin') {
    // Get admin statistics
    $stats_sql = "SELECT 
                  (SELECT COUNT(*) FROM users WHERE status = 'active') as total_users,
                  (SELECT COUNT(*) FROM businesses WHERE status = 'active') as total_businesses,
                  (SELECT COUNT(*) FROM transactions WHERE status = 'active') as total_transactions";
    $result = mysqli_query($conn, $stats_sql);
    $user_stats = mysqli_fetch_assoc($result);
}

// Get recent activity based on role
$recent_activity = [];

if ($current_user['role'] == 'partner') {
    $activity_sql = "SELECT t.*, b.name as business_name, c.name as category_name
                     FROM transactions t
                     JOIN businesses b ON t.business_id = b.id
                     JOIN categories c ON t.category_id = c.id
                     WHERE t.user_id = ? AND t.status = 'active'
                     ORDER BY t.created_at DESC
                     LIMIT 5";
    $stmt = mysqli_prepare($conn, $activity_sql);
    mysqli_stmt_bind_param($stmt, "i", $current_user['id']);
    mysqli_stmt_execute($stmt);
    $recent_activity = mysqli_stmt_get_result($stmt);
} elseif ($current_user['role'] == 'accountant') {
    $activity_sql = "SELECT t.*, b.name as business_name, c.name as category_name
                     FROM transactions t
                     JOIN businesses b ON t.business_id = b.id
                     JOIN categories c ON t.category_id = c.id
                     JOIN user_business_assignments uba ON uba.business_id = b.id
                     WHERE uba.user_id = ? AND t.status = 'active'
                     ORDER BY t.created_at DESC
                     LIMIT 5";
    $stmt = mysqli_prepare($conn, $activity_sql);
    mysqli_stmt_bind_param($stmt, "i", $current_user['id']);
    mysqli_stmt_execute($stmt);
    $recent_activity = mysqli_stmt_get_result($stmt);
}

include '../../components/header.php';
include '../../components/sidebar.php';
?>

<div class="space-y-6">
    <!-- Page Header -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="h-20 w-20 rounded-full bg-gray-300 flex items-center justify-center mr-6">
                <span class="text-2xl font-medium text-gray-700">
                    <?php echo strtoupper(substr($current_user['first_name'], 0, 1) . substr($current_user['last_name'], 0, 1)); ?>
                </span>
            </div>
            <div>
                <h1 class="text-3xl font-bold text-gray-900">
                    <?php echo htmlspecialchars($current_user['full_name']); ?>
                </h1>
                <p class="text-gray-600"><?php echo htmlspecialchars($current_user['email']); ?></p>
                <div class="mt-2">
                    <span
                        class="inline-flex px-3 py-1 text-sm font-semibold rounded-full <?php echo get_role_badge_class($current_user['role']); ?>">
                        <?php echo ucfirst($current_user['role']); ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Success/Error Messages -->
    <?php if (!empty($success_message)): ?>
        <div class="bg-green-50 border border-green-200 rounded-md p-4">
            <div class="flex">
                <svg class="w-5 h-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd"
                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                        clip-rule="evenodd"></path>
                </svg>
                <div class="ml-3">
                    <p class="text-sm text-green-800"><?php echo $success_message; ?></p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
        <div class="bg-red-50 border border-red-200 rounded-md p-4">
            <div class="flex">
                <svg class="w-5 h-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd"
                        d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                        clip-rule="evenodd"></path>
                </svg>
                <div class="ml-3">
                    <p class="text-sm text-red-800"><?php echo $error_message; ?></p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <?php if (!empty($user_stats)): ?>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <?php if ($current_user['role'] == 'partner'): ?>
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center">
                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4">
                                        </path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">My Businesses</dt>
                                    <dd class="text-lg font-medium text-gray-900"><?php echo $user_stats['owned_businesses']; ?>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-green-500 rounded-md flex items-center justify-center">
                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M7 11l5-5m0 0l5 5m-5-5v12"></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Total Income</dt>
                                    <dd class="text-lg font-medium text-green-600">
                                        <?php echo format_currency($user_stats['total_income'] ?? 0); ?>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-red-500 rounded-md flex items-center justify-center">
                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M17 13l-5 5m0 0l-5-5m5 5V6"></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Total Expenses</dt>
                                    <dd class="text-lg font-medium text-red-600">
                                        <?php echo format_currency($user_stats['total_expenses'] ?? 0); ?>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

            <?php elseif ($current_user['role'] == 'accountant'): ?>
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center">
                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4">
                                        </path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Assigned Businesses</dt>
                                    <dd class="text-lg font-medium text-gray-900">
                                        <?php echo $user_stats['assigned_businesses']; ?>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-green-500 rounded-md flex items-center justify-center">
                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z">
                                        </path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Transactions Added</dt>
                                    <dd class="text-lg font-medium text-gray-900">
                                        <?php echo $user_stats['total_transactions']; ?>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-purple-500 rounded-md flex items-center justify-center">
                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z">
                                        </path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Businesses Worked On</dt>
                                    <dd class="text-lg font-medium text-gray-900">
                                        <?php echo $user_stats['businesses_worked_on']; ?>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

            <?php elseif ($current_user['role'] == 'admin'): ?>
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center">
                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z">
                                        </path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Total Users</dt>
                                    <dd class="text-lg font-medium text-gray-900"><?php echo $user_stats['total_users']; ?></dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-green-500 rounded-md flex items-center justify-center">
                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4">
                                        </path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Total Businesses</dt>
                                    <dd class="text-lg font-medium text-gray-900"><?php echo $user_stats['total_businesses']; ?>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-yellow-500 rounded-md flex items-center justify-center">
                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z">
                                        </path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Total Transactions</dt>
                                    <dd class="text-lg font-medium text-gray-900">
                                        <?php echo $user_stats['total_transactions']; ?>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Profile Management Tabs -->
    <div class="bg-white shadow rounded-lg">
        <div class="border-b border-gray-200">
            <nav class="-mb-px flex space-x-8 px-6" aria-label="Tabs">
                <button onclick="showTab('profile')" id="profile-tab"
                    class="tab-button border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                    Profile Information
                </button>
                <button onclick="showTab('security')" id="security-tab"
                    class="tab-button border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                    Security
                </button>
                <button onclick="showTab('activity')" id="activity-tab"
                    class="tab-button border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                    Recent Activity
                </button>
            </nav>
        </div>

        <!-- Profile Information Tab -->
        <div id="profile-content" class="tab-content p-6">
            <div class="max-w-lg">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Profile Information</h3>
                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                        <input type="text" name="first_name"
                            value="<?php echo htmlspecialchars($current_user['first_name']); ?>" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 <?php if ($current_user['role'] === 'accountant')
                                echo 'bg-gray-100'; ?>"
                            <?php if ($current_user['role'] === 'accountant')
                                echo 'readonly'; ?>>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                        <input type="text" name="last_name"
                            value="<?php echo htmlspecialchars($current_user['last_name']); ?>" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 <?php if ($current_user['role'] === 'accountant')
                                echo 'bg-gray-100'; ?>"
                            <?php if ($current_user['role'] === 'accountant')
                                echo 'readonly'; ?>>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($current_user['email']); ?>"
                            required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 <?php if ($current_user['role'] === 'accountant')
                                echo 'bg-gray-100'; ?>"
                            <?php if ($current_user['role'] === 'accountant')
                                echo 'readonly'; ?>>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                        <input type="text" value="<?php echo ucfirst($current_user['role']); ?>" readonly
                            class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50 text-gray-500">
                        <p class="text-xs text-gray-500 mt-1">Contact your administrator to change your role.</p>
                    </div>
                    <div class="pt-4">
                        <?php if ($current_user['role'] !== 'accountant'): ?>
                            <button type="submit" name="update_profile"
                                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                                Update Profile
                            </button>
                        <?php else: ?>
                            <p class="text-sm text-gray-500">As an accountant, you do not have permission to edit your
                                profile.</p>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Security Tab -->
        <div id="security-content" class="tab-content p-6 hidden">
            <div class="max-w-lg">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Change Password</h3>
                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Current Password</label>
                        <input type="password" name="current_password" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                        <input type="password" name="new_password" required minlength="6"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <p class="text-xs text-gray-500 mt-1">Minimum 6 characters</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
                        <input type="password" name="confirm_password" required minlength="6"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div class="pt-4">
                        <button type="submit" name="change_password"
                            class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg">
                            Change Password
                        </button>
                    </div>
                </form>

                <!-- Security Information -->
                <div class="mt-8 p-4 bg-gray-50 rounded-lg">
                    <h4 class="text-sm font-medium text-gray-900 mb-2">Security Tips</h4>
                    <ul class="text-xs text-gray-600 space-y-1">
                        <li>• Use a strong password with at least 8 characters</li>
                        <li>• Include uppercase, lowercase, numbers, and special characters</li>
                        <li>• Don't reuse passwords from other accounts</li>
                        <li>• Change your password regularly</li>
                        <li>• Never share your password with others</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Recent Activity Tab -->
        <div id="activity-content" class="tab-content p-6 hidden">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Recent Activity</h3>

            <?php if ($recent_activity && mysqli_num_rows($recent_activity) > 0): ?>
                <div class="space-y-4">
                    <?php while ($activity = mysqli_fetch_assoc($recent_activity)): ?>
                        <div class="border border-gray-200 rounded-lg p-4">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-3">
                                    <div class="flex-shrink-0">
                                        <div
                                            class="w-8 h-8 <?php echo $activity['type'] == 'income' ? 'bg-green-500' : 'bg-red-500'; ?> rounded-full flex items-center justify-center">
                                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <?php if ($activity['type'] == 'income'): ?>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M7 11l5-5m0 0l5 5m-5-5v12"></path>
                                                <?php else: ?>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M17 13l-5 5m0 0l-5-5m5 5V6"></path>
                                                <?php endif; ?>
                                            </svg>
                                        </div>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">
                                            <?php echo ucfirst($activity['type']); ?> -
                                            <?php echo htmlspecialchars($activity['category_name']); ?>
                                        </p>
                                        <p class="text-xs text-gray-500">
                                            <?php echo htmlspecialchars($activity['business_name']); ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p
                                        class="text-sm font-medium <?php echo $activity['type'] == 'income' ? 'text-green-600' : 'text-red-600'; ?>">
                                        <?php echo format_currency($activity['amount']); ?>
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        <?php echo format_date($activity['transaction_date']); ?>
                                    </p>
                                </div>
                            </div>
                            <?php if (!empty($activity['description'])): ?>
                                <div class="mt-2">
                                    <p class="text-xs text-gray-600"><?php echo htmlspecialchars($activity['description']); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                </div>

                <div class="mt-6">
                    <a href="../common/transactions.php"
                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-blue-600 bg-blue-50 hover:bg-blue-100">
                        View All Transactions
                        <svg class="ml-2 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </a>
                </div>

            <?php else: ?>
                <div class="text-center py-8">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z">
                        </path>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No recent activity</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        <?php if ($current_user['role'] == 'partner'): ?>
                            Start by adding transactions to your businesses.
                        <?php elseif ($current_user['role'] == 'accountant'): ?>
                            You haven't worked on any transactions yet.
                        <?php else: ?>
                            No recent system activity to display.
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Account Information -->
    <div class="bg-white shadow rounded-lg p-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Account Information</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-500">User ID</label>
                <p class="text-sm text-gray-900"><?php echo $current_user['id']; ?></p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-500">Account Status</label>
                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                    Active
                </span>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-500">Member Since</label>
                <p class="text-sm text-gray-900">
                    <?php
                    // Get user creation date
                    $user_sql = "SELECT created_at FROM users WHERE id = ?";
                    $stmt = mysqli_prepare($conn, $user_sql);
                    mysqli_stmt_bind_param($stmt, "i", $current_user['id']);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    $user_data = mysqli_fetch_assoc($result);
                    echo format_date($user_data['created_at']);
                    ?>
                </p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-500">Last Login</label>
                <p class="text-sm text-gray-900">
                    <?php echo isset($_SESSION['login_time']) ? date('M j, Y g:i A', $_SESSION['login_time']) : 'Unknown'; ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="bg-white shadow rounded-lg p-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Quick Actions</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php if ($current_user['role'] == 'partner'): ?>
                <a href="../partner/businesses.php"
                    class="bg-blue-50 hover:bg-blue-100 p-4 rounded-lg border border-blue-200 transition-colors">
                    <div class="flex items-center">
                        <svg class="w-6 h-6 text-blue-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4">
                            </path>
                        </svg>
                        <span class="text-blue-700 font-medium">Manage Businesses</span>
                    </div>
                </a>

                <a href="../common/transactions.php"
                    class="bg-green-50 hover:bg-green-100 p-4 rounded-lg border border-green-200 transition-colors">
                    <div class="flex items-center">
                        <svg class="w-6 h-6 text-green-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        <span class="text-green-700 font-medium">Add Transaction</span>
                    </div>
                </a>

                <a href="../partner/reports.php"
                    class="bg-purple-50 hover:bg-purple-100 p-4 rounded-lg border border-purple-200 transition-colors">
                    <div class="flex items-center">
                        <svg class="w-6 h-6 text-purple-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z">
                            </path>
                        </svg>
                        <span class="text-purple-700 font-medium">View Reports</span>
                    </div>
                </a>

            <?php elseif ($current_user['role'] == 'accountant'): ?>
                <a href="../accountant/businesses.php"
                    class="bg-blue-50 hover:bg-blue-100 p-4 rounded-lg border border-blue-200 transition-colors">
                    <div class="flex items-center">
                        <svg class="w-6 h-6 text-blue-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4">
                            </path>
                        </svg>
                        <span class="text-blue-700 font-medium">Assigned Businesses</span>
                    </div>
                </a>

                <a href="../common/transactions.php"
                    class="bg-green-50 hover:bg-green-100 p-4 rounded-lg border border-green-200 transition-colors">
                    <div class="flex items-center">
                        <svg class="w-6 h-6 text-green-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        <span class="text-green-700 font-medium">Add Transaction</span>
                    </div>
                </a>

                <a href="../accountant/reports.php"
                    class="bg-purple-50 hover:bg-purple-100 p-4 rounded-lg border border-purple-200 transition-colors">
                    <div class="flex items-center">
                        <svg class="w-6 h-6 text-purple-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z">
                            </path>
                        </svg>
                        <span class="text-purple-700 font-medium">View Reports</span>
                    </div>
                </a>

            <?php elseif ($current_user['role'] == 'admin'): ?>
                <a href="../admin/users.php"
                    class="bg-blue-50 hover:bg-blue-100 p-4 rounded-lg border border-blue-200 transition-colors">
                    <div class="flex items-center">
                        <svg class="w-6 h-6 text-blue-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z">
                            </path>
                        </svg>
                        <span class="text-blue-700 font-medium">Manage Users</span>
                    </div>
                </a>

                <a href="../admin/businesses.php"
                    class="bg-green-50 hover:bg-green-100 p-4 rounded-lg border border-green-200 transition-colors">
                    <div class="flex items-center">
                        <svg class="w-6 h-6 text-green-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4">
                            </path>
                        </svg>
                        <span class="text-green-700 font-medium">Manage Businesses</span>
                    </div>
                </a>

                <a href="../admin/reports.php"
                    class="bg-purple-50 hover:bg-purple-100 p-4 rounded-lg border border-purple-200 transition-colors">
                    <div class="flex items-center">
                        <svg class="w-6 h-6 text-purple-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z">
                            </path>
                        </svg>
                        <span class="text-purple-700 font-medium">System Reports</span>
                    </div>
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    // Tab functionality
    function showTab(tabName) {
        // Hide all tab contents
        const tabContents = document.querySelectorAll('.tab-content');
        tabContents.forEach(content => {
            content.classList.add('hidden');
        });

        // Remove active class from all tab buttons
        const tabButtons = document.querySelectorAll('.tab-button');
        tabButtons.forEach(button => {
            button.classList.remove('border-blue-500', 'text-blue-600');
            button.classList.add('border-transparent', 'text-gray-500');
        });

        // Show selected tab content
        document.getElementById(tabName + '-content').classList.remove('hidden');

        // Add active class to selected tab button
        const activeButton = document.getElementById(tabName + '-tab');
        activeButton.classList.remove('border-transparent', 'text-gray-500');
        activeButton.classList.add('border-blue-500', 'text-blue-600');
    }

    // Initialize with profile tab active
    document.addEventListener('DOMContentLoaded', function () {
        showTab('profile');
    });

    // Form validation for password change
    document.addEventListener('DOMContentLoaded', function () {
        const passwordForm = document.querySelector('form[method="POST"]');
        if (passwordForm) {
            const newPassword = passwordForm.querySelector('input[name="new_password"]');
            const confirmPassword = passwordForm.querySelector('input[name="confirm_password"]');

            if (newPassword && confirmPassword) {
                confirmPassword.addEventListener('input', function () {
                    if (newPassword.value !== confirmPassword.value) {
                        confirmPassword.setCustomValidity('Passwords do not match');
                    } else {
                        confirmPassword.setCustomValidity('');
                    }
                });
            }
        }
    });

    // Auto-hide success/error messages after 5 seconds
    document.addEventListener('DOMContentLoaded', function () {
        const messages = document.querySelectorAll('.bg-green-50, .bg-red-50');
        messages.forEach(message => {
            setTimeout(() => {
                message.style.transition = 'opacity 0.5s';
                message.style.opacity = '0';
                setTimeout(() => {
                    message.remove();
                }, 500);
            }, 5000);
        });
    });
</script>

<?php
mysqli_close($conn);
include '../../components/footer.php';
?>