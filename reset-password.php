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

$email = isset($_GET['email']) ? sanitize_input($_GET['email']) : '';
$token = isset($_GET['token']) ? sanitize_input($_GET['token']) : '';

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reset_password'])) {
    $email = sanitize_input($_POST['email']);
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Server-side validation
    if (empty($email) || empty($new_password) || empty($confirm_password)) {
        $message = 'Please fill in all fields.';
        $message_type = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
        $message_type = 'error';
    } elseif (strlen($new_password) < 6) {
        $message = 'Password must be at least 6 characters long.';
        $message_type = 'error';
    } elseif ($new_password !== $confirm_password) {
        $message = 'Passwords do not match.';
        $message_type = 'error';
    } else {
        // Update password
        $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if (!$conn) {
            $message = 'Database connection failed. Please try again.';
            $message_type = 'error';
        } else {
            $email_escaped = mysqli_real_escape_string($conn, $email);
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            $result = mysqli_query($conn, "UPDATE users SET password = '$hashed_password' WHERE email = '$email_escaped' AND status = 'active'");
            
            if (mysqli_affected_rows($conn) > 0) {
                mysqli_close($conn);
                header('Location: index.php?success=password_reset');
                exit();
            } else {
                $message = 'Invalid email address or account not found.';
                $message_type = 'error';
            }
            
            mysqli_close($conn);
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - MBIMS</title>
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
                    <h2 class="text-2xl font-bold text-white mb-2">Reset Password</h2>
                    <p class="text-indigo-200">Enter your new password below.</p>
                </div>

                <!-- Messages -->
                <?php if (!empty($message)): ?>
                <div class="mb-6 <?php echo $message_type == 'error' ? 'bg-red-100 border-red-400 text-red-700' : 'bg-green-100 border-green-400 text-green-700'; ?> border px-4 py-3 rounded-lg" role="alert">
                    <div class="flex items-center">
                        <i class="fas <?php echo $message_type == 'error' ? 'fa-exclamation-circle' : 'fa-check-circle'; ?> mr-2"></i>
                        <span><?php echo htmlspecialchars($message); ?></span>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Reset Form -->
                <form method="POST" action="" class="space-y-6">
                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                    
                    <div>
                        <label for="email_display" class="block text-sm font-medium text-white mb-2">
                            <i class="fas fa-envelope mr-2"></i>Email Address
                        </label>
                        <input type="email" 
                               id="email_display" 
                               value="<?php echo htmlspecialchars($email); ?>"
                               disabled
                               class="w-full px-4 py-3 bg-white bg-opacity-10 border border-white border-opacity-30 rounded-lg text-indigo-200 cursor-not-allowed">
                    </div>

                    <div>
                        <label for="new_password" class="block text-sm font-medium text-white mb-2">
                            <i class="fas fa-lock mr-2"></i>New Password
                        </label>
                        <div class="relative">
                            <input type="password" 
                                   id="new_password" 
                                   name="new_password" 
                                   required
                                   minlength="6"
                                   class="w-full px-4 py-3 bg-white bg-opacity-20 border border-white border-opacity-30 rounded-lg text-white placeholder-indigo-200 focus:outline-none focus:ring-2 focus:ring-white focus:ring-opacity-50 focus:border-transparent transition duration-200"
                                   placeholder="Enter new password">
                            <button type="button" 
                                    onclick="togglePassword('new_password', 'new_password_icon')" 
                                    class="absolute right-3 top-1/2 transform -translate-y-1/2 text-indigo-200 hover:text-white transition duration-200">
                                <i id="new_password_icon" class="fas fa-eye"></i>
                            </button>
                        </div>
                        <p class="text-indigo-200 text-xs mt-1">Minimum 6 characters</p>
                    </div>

                    <div>
                        <label for="confirm_password" class="block text-sm font-medium text-white mb-2">
                            <i class="fas fa-lock mr-2"></i>Confirm New Password
                        </label>
                        <div class="relative">
                            <input type="password" 
                                   id="confirm_password" 
                                   name="confirm_password" 
                                   required
                                   minlength="6"
                                   class="w-full px-4 py-3 bg-white bg-opacity-20 border border-white border-opacity-30 rounded-lg text-white placeholder-indigo-200 focus:outline-none focus:ring-2 focus:ring-white focus:ring-opacity-50 focus:border-transparent transition duration-200"
                                   placeholder="Confirm new password">
                            <button type="button" 
                                    onclick="togglePassword('confirm_password', 'confirm_password_icon')" 
                                    class="absolute right-3 top-1/2 transform -translate-y-1/2 text-indigo-200 hover:text-white transition duration-200">
                                <i id="confirm_password_icon" class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Password Strength Indicator -->
                    <div id="password_strength" class="hidden">
                        <div class="text-sm text-white mb-2">Password Strength:</div>
                        <div class="w-full bg-white bg-opacity-20 rounded-full h-2">
                            <div id="strength_bar" class="h-2 rounded-full transition-all duration-300"></div>
                        </div>
                        <div id="strength_text" class="text-xs text-indigo-200 mt-1"></div>
                    </div>

                    <button type="submit" 
                            name="reset_password"
                            class="w-full bg-white text-indigo-600 font-semibold py-3 px-4 rounded-lg hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-white focus:ring-opacity-50 transition duration-200 transform hover:scale-105">
                        <i class="fas fa-key mr-2"></i>Reset Password
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
function togglePassword(inputId, iconId) {
    const passwordInput = document.getElementById(inputId);
    const passwordIcon = document.getElementById(iconId);
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        passwordIcon.classList.remove('fa-eye');
        passwordIcon.classList.add('fa-eye-slash');
    } else {
        passwordInput.type = 'password';
        passwordIcon.classList.remove('fa-eye-slash');
        passwordIcon.classList.add('fa-eye');
    }
}

// Password strength checker (visual feedback only)
document.getElementById('new_password').addEventListener('input', function() {
    const password = this.value;
    const strengthDiv = document.getElementById('password_strength');
    const strengthBar = document.getElementById('strength_bar');
    const strengthText = document.getElementById('strength_text');
    
    if (password.length === 0) {
        strengthDiv.classList.add('hidden');
        return;
    }
    
    strengthDiv.classList.remove('hidden');
    
    let strength = 0;
    let feedback = [];
    
    if (password.length >= 8) strength += 25;
    else feedback.push('At least 8 characters');
    
    if (/[A-Z]/.test(password)) strength += 25;
    else feedback.push('One uppercase letter');
    
    if (/[a-z]/.test(password)) strength += 25;
    else feedback.push('One lowercase letter');
    
    if (/[\d\W]/.test(password)) strength += 25;
    else feedback.push('One number or special character');
    
    strengthBar.style.width = strength + '%';
    
    if (strength < 50) {
        strengthBar.className = 'h-2 rounded-full transition-all duration-300 bg-red-500';
        strengthText.textContent = 'Weak - ' + feedback.join(', ');
        strengthText.className = 'text-xs text-red-300 mt-1';
    } else if (strength < 75) {
        strengthBar.className = 'h-2 rounded-full transition-all duration-300 bg-yellow-500';
        strengthText.textContent = 'Medium - ' + feedback.join(', ');
        strengthText.className = 'text-xs text-yellow-300 mt-1';
    } else {
        strengthBar.className = 'h-2 rounded-full transition-all duration-300 bg-green-500';
        strengthText.textContent = 'Strong';
        strengthText.className = 'text-xs text-green-300 mt-1';
    }
});

// Visual password confirmation feedback
document.getElementById('confirm_password').addEventListener('input', function() {
    const password = document.getElementById('new_password').value;
    const confirmPassword = this.value;
    
    if (confirmPassword.length > 0) {
        if (password === confirmPassword) {
            this.classList.remove('border-red-500');
            this.classList.add('border-green-500');
        } else {
            this.classList.remove('border-green-500');
            this.classList.add('border-red-500');
        }
    } else {
        this.classList.remove('border-red-500', 'border-green-500');
    }
});

// // Simple form submission with loading state
// document.querySelector('form').addEventListener('submit', function(e) {
//     const submitButton = document.querySelector('button[type="submit"]');
//     const originalText = submitButton.innerHTML;
    
//     // Show loading state
//     submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Resetting Password...';
//     submitButton.disabled = true;
    
//     // Let PHP handle all validation - form submits normally
// });

// Auto-focus
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('new_password').focus();
});
</script>

</body>
</html>
