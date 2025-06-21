<?php
require_once 'config/config.php';
require_once 'includes/session.php';
require_once 'includes/auth.php';
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

// Handle login form submission
$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $email = sanitize_input($_POST['email']);
    $password = $_POST['password'];
    
    // Server-side validation
    if (empty($email) || empty($password)) {
        $error_message = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please enter a valid email address.';
    } else {
        $auth = new Auth();
        $result = $auth->login($email, $password);
        
        if ($result['success']) {
            header('Location: ' . $result['redirect']);
            exit();
        } else {
            $error_message = $result['message'];
            header('Location: index.php?error=login_failed');
        }
    }
}


// Handle URL parameters for messages
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'session_expired':
            $error_message = 'Your session has expired. Please log in again.';
            break;
        case 'unauthorized':
            $error_message = 'You are not authorized to access that page.';
            break;
        case 'logout':
        case '1':
        case  1:
            $success_message = 'You have been successfully logged out.';
            break;
        case 'login_failed':
            $error_message = 'Invalid email or password. Please try again.';
            break;
        default:
            $error_message = 'An error occurred. Please try again.';
    }
}

if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'password_reset':
            $success_message = 'Your password has been reset successfully. Please log in with your new password.';
            break;
        case 'account_created':
            $success_message = 'Your account has been created successfully. Please log in.';
            break;
      
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MBIMS - Multi-Business Income Management System</title>
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

    .login-animation {
        animation: slideInUp 0.6s ease-out;
    }

    @keyframes slideInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .feature-card:hover {
        transform: translateY(-5px);
        transition: all 0.3s ease;
    }
    </style>
</head>

<body class="gradient-bg min-h-screen">
    <div class="min-h-screen flex">
        <!-- Left Side - Branding & Features -->
        <div class="hidden lg:flex lg:w-1/2 flex-col justify-center px-12">
            <div class="max-w-md">
                <!-- Logo and Title -->
                <div class="mb-8">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-white rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-chart-line text-2xl text-indigo-600"></i>
                        </div>
                        <div>
                            <h1 class="text-3xl font-bold text-white">MBIMS</h1>
                            <p class="text-indigo-200">Multi-Business Income Management</p>
                        </div>
                    </div>
                    <p class="text-white text-lg leading-relaxed">
                        Streamline your business finances with our comprehensive management platform designed for modern
                        entrepreneurs.
                    </p>
                </div>

                <!-- Features -->
                <div class="space-y-4">
                    <div class="feature-card glass-effect rounded-lg p-4">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-green-500 rounded-lg flex items-center justify-center mr-4">
                                <i class="fas fa-building text-white"></i>
                            </div>
                            <div>
                                <h3 class="text-white font-semibold">Multi-Business Support</h3>
                                <p class="text-indigo-200 text-sm">Manage multiple businesses from one dashboard</p>
                            </div>
                        </div>
                    </div>

                    <div class="feature-card glass-effect rounded-lg p-4">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-blue-500 rounded-lg flex items-center justify-center mr-4">
                                <i class="fas fa-users text-white"></i>
                            </div>
                            <div>
                                <h3 class="text-white font-semibold">Role-Based Access</h3>
                                <p class="text-indigo-200 text-sm">Secure access for partners and accountants</p>
                            </div>
                        </div>
                    </div>

                    <div class="feature-card glass-effect rounded-lg p-4">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-purple-500 rounded-lg flex items-center justify-center mr-4">
                                <i class="fas fa-chart-bar text-white"></i>
                            </div>
                            <div>
                                <h3 class="text-white font-semibold">Advanced Reporting</h3>
                                <p class="text-indigo-200 text-sm">Detailed financial insights and analytics</p>
                            </div>
                        </div>
                    </div>

                    <div class="feature-card glass-effect rounded-lg p-4">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-yellow-500 rounded-lg flex items-center justify-center mr-4">
                                <i class="fas fa-shield-alt text-white"></i>
                            </div>
                            <div>
                                <h3 class="text-white font-semibold">Secure & Reliable</h3>
                                <p class="text-indigo-200 text-sm">Enterprise-grade security for your data</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Side - Login Form -->
        <div class="w-full lg:w-1/2 flex items-center justify-center px-6 py-12">
            <div class="login-animation w-full max-w-md">
                <!-- Mobile Logo -->
                <div class="lg:hidden text-center mb-8">
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

                <!-- Login Card -->
                <div class="glass-effect rounded-2xl p-8 shadow-2xl">
                    <div class="text-center mb-8">
                        <h2 class="text-2xl font-bold text-white mb-2">Welcome Back</h2>
                        <p class="text-indigo-200">Sign in to your account to continue</p>
                    </div>

                    <!-- Error/Success Messages -->
                    <?php if (!empty($error_message)): ?>
                    <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg" role="alert">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-circle mr-2"></i>
                            <span><?php echo htmlspecialchars($error_message); ?></span>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($success_message)): ?>
                    <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg"
                        role="alert">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle mr-2"></i>
                            <span><?php echo htmlspecialchars($success_message); ?></span>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Login Form -->
                    <form method="POST" action="" class="space-y-6">
                        <div>
                            <label for="email" class="block text-sm font-medium text-white mb-2">
                                <i class="fas fa-envelope mr-2"></i>Email Address
                            </label>
                            <input type="email" id="email" name="email" required
                                value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                                class="w-full px-4 py-3 bg-white bg-opacity-20 border border-white border-opacity-30 rounded-lg text-white placeholder-indigo-200 focus:outline-none focus:ring-2 focus:ring-white focus:ring-opacity-50 focus:border-transparent transition duration-200"
                                placeholder="Enter your email address">
                        </div>

                        <div>
                            <label for="password" class="block text-sm font-medium text-white mb-2">
                                <i class="fas fa-lock mr-2"></i>Password
                            </label>
                            <div class="relative">
                                <input type="password" id="password" name="password" required
                                    class="w-full px-4 py-3 bg-white bg-opacity-20 border border-white border-opacity-30 rounded-lg text-white placeholder-indigo-200 focus:outline-none focus:ring-2 focus:ring-white focus:ring-opacity-50 focus:border-transparent transition duration-200"
                                    placeholder="Enter your password">
                                <button type="button" onclick="togglePassword()"
                                    class="absolute right-3 top-1/2 transform -translate-y-1/2 text-indigo-200 hover:text-white transition duration-200">
                                    <i id="password-icon" class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <input type="checkbox" id="remember" name="remember"
                                    class="w-4 h-4 text-indigo-600 bg-white bg-opacity-20 border-white border-opacity-30 rounded focus:ring-indigo-500 focus:ring-2">
                                <label for="remember" class="ml-2 text-sm text-indigo-200">Remember me</label>
                            </div>
                            <a href="forgot-password.php"
                                class="text-sm text-white hover:text-indigo-200 transition duration-200">
                                Forgot password?
                            </a>
                        </div>

                        <button type="submit" name="login"
                            class="w-full bg-white text-indigo-600 font-semibold py-3 px-4 rounded-lg hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-white focus:ring-opacity-50 transition duration-200 transform hover:scale-105">
                            <i class="fas fa-sign-in-alt mr-2"></i>Sign In
                        </button>
                    </form>

                    <!-- Demo Credentials -->
                    <div class="mt-8 p-4 bg-white bg-opacity-10 rounded-lg">
                        <h4 class="text-white font-semibold mb-2">Demo Credentials:</h4>
                        <div class="text-sm text-indigo-200 space-y-1">
                            <p><strong>Admin:</strong> admin@mbims.com / admin123</p>
                            <p><strong>Partner:</strong> Create via admin panel</p>
                            <p><strong>Accountant:</strong> Create via admin panel</p>
                        </div>
                    </div>

                    <!-- Footer Links -->
                    <div class="mt-6 text-center">
                        <p class="text-indigo-200 text-sm">
                            Don't have an account?
                            <a href="#" class="text-white hover:text-indigo-200 font-medium transition duration-200">
                                Contact Administrator
                            </a>
                        </p>
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
    </div>

    <script>
    function togglePassword() {
        const passwordInput = document.getElementById('password');
        const passwordIcon = document.getElementById('password-icon');

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
    // Auto-focus on email field when page loads
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('email').focus();
    });

    // // Form validation
    // document.querySelector('form').addEventListener('submit', function(e) {
    //     const email = document.getElementById('email').value.trim();
    //     const password = document.getElementById('password').value;

    //     if (!email || !password) {
    //         e.preventDefault();
    //         alert('Please fill in all fields.');
    //         return false;
    //     }

    //     if (!isValidEmail(email)) {
    //         e.preventDefault();
    //         alert('Please enter a valid email address.');
    //         return false;
    //     }

    //     // Show loading state
    //     const submitButton = document.querySelector('button[type="submit"]');
    //     const originalText = submitButton.innerHTML;
    //     submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Signing In...';
    //     submitButton.disabled = true;

    //     // Re-enable button after 5 seconds (in case of slow response)
    //     setTimeout(function() {
    //         submitButton.innerHTML = originalText;
    //         submitButton.disabled = false;
    //     }, 5000);
    // });

    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    // Clear URL parameters after showing messages
    if (window.location.search) {
        setTimeout(function() {
            const url = window.location.protocol + "//" + window.location.host + window.location.pathname;
            window.history.replaceState({
                path: url
            }, '', url);
        }, 3000);
    }

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Alt + L to focus on email
        if (e.altKey && e.key === 'l') {
            e.preventDefault();
            document.getElementById('email').focus();
        }
        // Alt + P to focus on password
        if (e.altKey && e.key === 'p') {
            e.preventDefault();
            document.getElementById('password').focus();
        }
    });

    // Add smooth animations
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    });

    document.querySelectorAll('.feature-card').forEach((card) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        observer.observe(card);
    });
    </script>
</body>

</html>