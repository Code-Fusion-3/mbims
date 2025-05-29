<?php
// Determine the correct path to database config based on current location
$db_path = '';
if (strpos($_SERVER['PHP_SELF'], '/pages/') !== false) {
    // We're in a pages subdirectory
    $db_path = '../../config/database.php';
} else {
    // We're in the root or includes directory
    $db_path = '../config/database.php';
}

// Check if database config file exists, if not try alternative paths
if (!file_exists($db_path)) {
    if (file_exists('config/database.php')) {
        $db_path = 'config/database.php';
    } elseif (file_exists('../config/database.php')) {
        $db_path = '../config/database.php';
    } elseif (file_exists('../../config/database.php')) {
        $db_path = '../../config/database.php';
    }
}

require_once $db_path;

require_once 'session.php';
class Auth {
    private $connection;
    
    public function __construct() {
        $this->connection = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if (!$this->connection) {
            die("Connection failed: " . mysqli_connect_error());
        }
    }
    
    // Login user
    public function login($email, $password) {
        // Sanitize input
        $email = mysqli_real_escape_string($this->connection, trim($email));
        
        // Get user from database
        $sql = "SELECT * FROM users WHERE email = '$email' AND status = 'active'";
        $result = mysqli_query($this->connection, $sql);
        
        if ($result && mysqli_num_rows($result) == 1) {
            $user = mysqli_fetch_assoc($result);
            
            // Verify password
            if (password_verify($password, $user['password'])) {
                // Set session
                set_user_session($user);
                return [
                    'success' => true,
                    'user' => $user,
                    'redirect' => $this->getRedirectUrl($user['role'])
                ];
            }
        }
        
        return [
            'success' => false,
            'message' => 'Invalid email or password'
        ];
    }
    
    // Register new user (Admin only)
    public function register($email, $password, $first_name, $last_name, $role) {
        // Sanitize input
        $email = mysqli_real_escape_string($this->connection, trim($email));
        $first_name = mysqli_real_escape_string($this->connection, trim($first_name));
        $last_name = mysqli_real_escape_string($this->connection, trim($last_name));
        $role = mysqli_real_escape_string($this->connection, $role);
        
        // Check if email already exists
        $sql = "SELECT id FROM users WHERE email = '$email'";
        $result = mysqli_query($this->connection, $sql);
        
        if (mysqli_num_rows($result) > 0) {
            return [
                'success' => false,
                'message' => 'Email already exists'
            ];
        }
        
        // Validate password
        if (strlen($password) < PASSWORD_MIN_LENGTH) {
            return [
                'success' => false,
                'message' => 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters'
            ];
        }
        
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert user
        $sql = "INSERT INTO users (email, password, first_name, last_name, role) 
                VALUES ('$email', '$hashed_password', '$first_name', '$last_name', '$role')";
        
        if (mysqli_query($this->connection, $sql)) {
            return [
                'success' => true,
                'message' => 'User created successfully',
                'user_id' => mysqli_insert_id($this->connection)
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Error creating user: ' . mysqli_error($this->connection)
            ];
        }
    }
    
    // Logout user
    public function logout() {
        destroy_user_session();
        return [
            'success' => true,
            'redirect' => '../index.php'
        ];
    }
    
    // Get redirect URL based on role
    private function getRedirectUrl($role) {
        switch ($role) {
            case 'admin':
                return '../pages/admin/dashboard.php';
            case 'partner':
                return '../pages/partner/dashboard.php';
            case 'accountant':
                return '../pages/accountant/dashboard.php';
            default:
                return '../index.php';
        }
    }
    
    // Get user by ID
    public function getUserById($user_id) {
        $user_id = (int)$user_id;
        $sql = "SELECT * FROM users WHERE id = $user_id";
        $result = mysqli_query($this->connection, $sql);
        
        if ($result && mysqli_num_rows($result) == 1) {
            return mysqli_fetch_assoc($result);
        }
        return null;
    }
    
    // Update user password
    public function updatePassword($user_id, $old_password, $new_password) {
        $user = $this->getUserById($user_id);
        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }
        
        // Verify old password
        if (!password_verify($old_password, $user['password'])) {
            return ['success' => false, 'message' => 'Current password is incorrect'];
        }
        
        // Validate new password
        if (strlen($new_password) < PASSWORD_MIN_LENGTH) {
            return [
                'success' => false,
                'message' => 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters'
            ];
        }
        
        // Update password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $sql = "UPDATE users SET password = '$hashed_password' WHERE id = $user_id";
        
        if (mysqli_query($this->connection, $sql)) {
            return ['success' => true, 'message' => 'Password updated successfully'];
        } else {
            return ['success' => false, 'message' => 'Error updating password'];
        }
    }
    
    public function __destruct() {
        if ($this->connection) {
            mysqli_close($this->connection);
        }
    }
}
?>