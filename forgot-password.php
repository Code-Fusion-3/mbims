<?php
require_once 'config/config.php';
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'includes/functions.php';
// Redirect if already logged in
if (is_logged_in() && !is_session_expired()) {
    $user = get_logged_user();
    switch ($user['role']) {
        case 'admin':
            header('Location: pages/admin/dashboard.php');
            break;
        case 'partner':
            header('Location: pages/partner/dashboard.php');
            break;
        case 'accountant':
            header('Location: pages/accountant/dashboard.php');
            break;
    }
    exit();
}

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reset_request'])) {
    $email = sanitize_input($_POST['email']);
    
    if (empty($email)) {
        $message = 'Please enter your email address.';
        $message_type = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
        $message_type = 'error';
    } else {
        // Check if email exists
        $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        $email_escaped = mysqli_real_escape_string($conn, $email);
        $result = mysqli_query($conn, "SELECT id, first_name, last_name FROM users WHERE email = '$email_escaped' AND status = 'active'");
        
        if (mysqli_num_rows($result) > 0) {
            $user = mysqli_fetch_assoc($result);
            
            // Generate reset token (in a real app, you'd store this in database and send email)
            $reset_token = bin2hex(random_bytes(32));
            $reset_link = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset-password.php?token=" . $reset_token;
            
            // For demo purposes, we'll show the reset link instead of sending email
            $message = "Click link for Password reset instructions <a href='reset-password.php?email=" . urlencode($email) . "' class='text-blue-600 hover:text-blue-800'>Reset Password link</a>";
            $message_type = 'success';
        } else {
            $message = 'Email not found.';
  
            $message_type = 'error';
        }
        
        mysqli_close($conn);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - MBIMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .glass-effect {
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.18);
        }
    </style>
</head>
<body class="gradient-bg min-h-screen">
    <div class="min-h-screen flex items-center justify-center px-6 py-12">
        <div class="w-full max-w-md">
            <!-- Logo -->
            <div class="text-center mb-8">
                <div class="inline-flex items-center">
                    <div class="w-12 h-12 bg-white rounded-lg flex items-center justify-center mr-4">
                        <i class="fas fa-chart-line text-2xl text-indigo-600"></i>
                    </div>
                    <div class="text-left">
                        <h1 class="text-3xl font-bold text-white">MBIMS</h1>
                        <p class="text-indigo-200">Multi-Business Income Management</p>
                    </div>
                </div>
            </div>

            <!-- Reset Card -->
            <div class="glass-effect rounded-2xl p-8 shadow-2xl">
                <div class="text-center mb-8">
                    <h2 class="text-2xl font-bold text-white mb-2">Forgot Password?</h2>
                    <p class="text-indigo-200">Enter your email address and we'll send you instructions to reset your password.</p>
                </div>

                <!-- Messages -->
                <?php if (!empty($message)): ?>
                <div class="mb-6 <?php echo $message_type == 'error' ? 'bg-red-100 border-red-400 text-red-700' : 'bg-green-100 border-green-400 text-green-700'; ?> border px-4 py-3 rounded-lg" role="alert">
                    <div class="flex items-center">
                        <i class="fas <?php echo $message_type == 'error' ? 'fa-exclamation-circle' : 'fa-check-circle'; ?> mr-2"></i>
                        <span><?php echo $message; ?></span>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Reset Form -->
                <form method="POST" action="" class="space-y-6">
                    <div>
                        <label for="email" class="block text-sm font-medium text-white mb-2">
                            <i class="fas fa-envelope mr-2"></i>Email Address
                        </label>
                        <input type="email" 
                               id="email" 
                               name="email" 
                               required 
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                               class="w-full px-4 py-3 bg-white bg-opacity-20 border border-white border-opacity-30 rounded-lg text-white placeholder-indigo-200 focus:outline-none focus:ring-2 focus:ring-white focus:ring-opacity-50 focus:border-transparent transition duration-200"
                               placeholder="Enter your email address">
                    </div>

                    <button type="submit" 
                            name="reset_request"
                            class="w-full bg-white text-indigo-600 font-semibold py-3 px-4 rounded-lg hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-white focus:ring-opacity-50 transition duration-200 transform hover:scale-105">
                        <i class="fas fa-paper-plane mr-2"></i>Send Reset Instructions
                    </button>
                </form>

                <!-- Back to Login -->
                <div class="mt-6 text-center">
                    <a href="index.php" class="text-white hover:text-indigo-200 font-medium transition duration-200">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Login
                    </a>
                </div>
            </div>

            <!-- Copyright -->
            <div class="text-center mt-8">
                <p class="text-indigo-200 text-sm">
                    © <?php echo date('Y'); ?> MBIMS. All rights reserved.
                </p>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('email').focus();
        });
    </script>
</body>
</html>