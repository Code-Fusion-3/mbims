<?php
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';
require_once '../../config/database.php';

// Require admin role
require_role('admin');

// Get database connection
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $business_id = (int)$_POST['business_id'];
    $accountant_id = (int)$_POST['accountant_id'];
    $permission_level = sanitize_input($_POST['permission_level']);
    
    $errors = [];
    
    // Validate inputs
    if ($business_id <= 0) {
        $errors[] = "Invalid business selected";
    }
    
    if ($accountant_id <= 0) {
        $errors[] = "Invalid accountant selected";
    }
    
    if (!validate_permission_level($permission_level)) {
        $errors[] = "Invalid permission level";
    }
    
    // Check if business exists and is active
    $business_check = mysqli_query($conn, "SELECT id FROM businesses WHERE id = $business_id AND status = 'active'");
    if (mysqli_num_rows($business_check) == 0) {
        $errors[] = "Business not found or inactive";
    }
    
    // Check if accountant exists and is active
    $accountant_check = mysqli_query($conn, "SELECT id FROM users WHERE id = $accountant_id AND role = 'accountant' AND status = 'active'");
    if (mysqli_num_rows($accountant_check) == 0) {
        $errors[] = "Accountant not found or inactive";
    }
    
    // Check if assignment already exists
    $existing_check = mysqli_query($conn, "SELECT id FROM user_business_assignments WHERE user_id = $accountant_id AND business_id = $business_id");
    if (mysqli_num_rows($existing_check) > 0) {
        // Update existing assignment
        $sql = "UPDATE user_business_assignments SET permission_level = '$permission_level' WHERE user_id = $accountant_id AND business_id = $business_id";
        $action = "updated";
    } else {
        // Create new assignment
        $sql = "INSERT INTO user_business_assignments (user_id, business_id, permission_level) VALUES ($accountant_id, $business_id, '$permission_level')";
        $action = "assigned";
    }
    
    if (empty($errors)) {
        if (mysqli_query($conn, $sql)) {
            header('Location: businesses.php?success=accountant_' . $action);
            $success_message = "Accountant $action successfully!";
            $_SESSION['success_message'] = $success_message;
            exit();
        } else {
            header('Location: businesses.php?error=assignment_failed');
            exit();
        }
    } else {
        $error_message = implode(', ', $errors);
        header('Location: businesses.php?error=' . urlencode($error_message));
        $_SESSION['error_message'] = $error_message;
        exit();
    }
} else {
    header('Location: businesses.php');
    exit();
}

mysqli_close($conn);
?>