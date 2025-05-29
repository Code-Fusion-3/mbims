<?php
require_once 'config/config.php';
require_once 'includes/session.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// If user is already logged in, redirect to dashboard
if (is_logged_in()) {
    $user = get_logged_user();
    switch ($user['role']) {
        case 'admin':
            redirect('pages/admin/dashboard.php');
            break;
        case 'partner':
            redirect('pages/partner/dashboard.php');
            break;
        case 'accountant':
            redirect('pages/accountant/dashboard.php');
            break;
    }
}

$error_message = '';
$success_message = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $email = sanitize_input($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $error_message = 'Please fill in all fields';
    } else {
        $auth = new Auth();
        $result = $auth->login($email, $password);
        
        if ($result['success']) {
            redirect($result['redirect']);
        } else {
            $error_message = $result['message'];
        }
    }
}

// Handle logout message
if (isset($_GET['logout'])) {
    $success_message = 'You have been logged out successfully';
}

// Handle session expired message
if (isset($_GET['error']) && $_GET['error'] == 'session_expired') {
    $error_message = 'Your session has expired. Please login again.';
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full space-y-8">
        <div>
            <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                <?php echo APP_NAME; ?>
            </h2>
            <p class="mt-2 text-center text-sm text-gray-600">
                Sign in to your account
            </p>
        </div>

        <form class="mt-8 space-y-6 bg-white p-8 rounded-lg shadow-md" method="POST">
            <?php if ($error_message): ?>
            <?php echo show_alert($error_message, 'error'); ?>
            <?php endif; ?>

            <?php if ($success_message): ?>
            <?php echo show_alert($success_message, 'success'); ?>
            <?php endif; ?>

            <div class="space-y-4">
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">
                        Email Address
                    </label>
                    <input id="email" name="email" type="email" required
                        class="mt-1 appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm"
                        placeholder="Enter your email"
                        value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700">
                        Password
                    </label>
                    <input id="password" name="password" type="password" required
                        class="mt-1 appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm"
                        placeholder="Enter your password">
                </div>
            </div>

            <div>
                <button type="submit" name="login"
                    class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Sign In
                </button>
            </div>

            <div class="text-center">
                <div class="text-sm text-gray-600">
                    <strong>Default Admin Login:</strong><br>
                    Email: admin@mbims.com<br>
                    Password: admin123
                </div>
            </div>
        </form>
    </div>
</body>

</html>