<?php
// General utility functions

// Sanitize input data
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Format currency
function format_currency($amount) {
    return '$' . number_format($amount, 2);
}

// Format date
function format_date($date, $format = DATE_FORMAT) {
    return date($format, strtotime($date));
}
// Helper function to format datetime
function format_datetime($datetime) {
    return date('M j, Y g:i A', strtotime($datetime));
}
// Generate random string
function generate_random_string($length = 10) {
    return substr(str_shuffle(str_repeat($x='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length/strlen($x)) )),1,$length);
}

// Validate email
function is_valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Get user role badge class
function get_role_badge_class($role) {
    switch ($role) {
        case 'admin':
            return 'bg-red-100 text-red-800';
        case 'partner':
            return 'bg-blue-100 text-blue-800';
        case 'accountant':
            return 'bg-green-100 text-green-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

// Get status badge class
function get_status_badge_class($status) {
  switch ($status) {
        case 'active':
            return 'bg-green-100 text-green-800';
        case 'inactive':
            return 'bg-red-100 text-red-800';
        case 'deleted':
            return 'bg-gray-100 text-gray-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

// Redirect function
function redirect($url) {
    header("Location: $url");
    exit();
}

// Show alert message
function show_alert($message, $type = 'info') {
    $alertClass = '';
    switch ($type) {
        case 'success':
            $alertClass = 'bg-green-100 border-green-400 text-green-700';
            break;
        case 'error':
            $alertClass = 'bg-red-100 border-red-400 text-red-700';
            break;
        case 'warning':
            $alertClass = 'bg-yellow-100 border-yellow-400 text-yellow-700';
            break;
        default:
            $alertClass = 'bg-blue-100 border-blue-400 text-blue-700';
    }
    
    return "<div class='border px-4 py-3 rounded mb-4 $alertClass' role='alert'>
                <span class='block sm:inline'>$message</span>
            </div>";
}

// Pagination function
function paginate($total_records, $current_page, $records_per_page = RECORDS_PER_PAGE) {
    $total_pages = ceil($total_records / $records_per_page);
    $offset = ($current_page - 1) * $records_per_page;
    
    return [
        'total_pages' => $total_pages,
        'current_page' => $current_page,
        'offset' => $offset,
        'limit' => $records_per_page,
        'has_previous' => $current_page > 1,
        'has_next' => $current_page < $total_pages
    ];
}
// Helper function to get permission badge class
function get_permission_badge_class($permission) {
    switch ($permission) {
        case 'admin':
            return 'bg-red-100 text-red-800';
        case 'write':
            return 'bg-blue-100 text-blue-800';
        case 'read':
            return 'bg-gray-100 text-gray-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}
// Helper function to truncate text
function truncate_text($text, $length = 50) {
    if (strlen($text) > $length) {
        return substr($text, 0, $length) . '...';
    }
    return $text;
}
// Helper function to generate random password
function generate_password($length = 8) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $password;
}
// Add these validation functions to the existing functions.php file

/**
 * Validate email address
 */
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate password strength
 */
function validate_password($password) {
    // At least 6 characters
    if (strlen($password) < 6) {
        return false;
    }
    return true;
}

/**
 * Validate required field
 */
function validate_required($value) {
    return !empty(trim($value));
}

/**
 * Validate name (letters, spaces, hyphens only)
 */
function validate_name($name) {
    return preg_match('/^[a-zA-Z\s\-]+$/', $name) && strlen(trim($name)) >= 2;
}

/**
 * Validate phone number
 */
function validate_phone($phone) {
    // Remove all non-digit characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    // Check if it's 10 digits
    return strlen($phone) >= 10 && strlen($phone) <= 15;
}

/**
 * Validate decimal amount
 */
function validate_amount($amount) {
    return is_numeric($amount) && $amount >= 0;
}

/**
 * Validate date format (YYYY-MM-DD)
 */
function validate_date($date) {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}



/**
 * Validate business type
 */
function validate_business_type($type) {
    $allowed_types = [
        'retail', 'restaurant', 'service', 'manufacturing', 
        'technology', 'healthcare', 'education', 'consulting', 
        'real_estate', 'construction', 'transportation', 'other'
    ];
    return in_array($type, $allowed_types);
}

/**
 * Validate user role
 */
function validate_user_role($role) {
    $allowed_roles = ['admin', 'partner', 'accountant'];
    return in_array($role, $allowed_roles);
}

/**
 * Validate status
 */
function validate_status($status) {
    $allowed_statuses = ['active', 'inactive'];
    return in_array($status, $allowed_statuses);
}

/**
 * Validate transaction type
 */
function validate_transaction_type($type) {
    $allowed_types = ['income', 'expense'];
    return in_array($type, $allowed_types);
}

/**
 * Validate permission level
 */
function validate_permission_level($level) {
    $allowed_levels = ['read', 'write', 'admin'];
    return in_array($level, $allowed_levels);
}

/**
 * Generate validation errors array
 */
function validate_user_data($data, $is_update = false) {
    $errors = [];
    
    // Validate first name
    if (!validate_required($data['first_name'])) {
        $errors[] = "First name is required";
    } elseif (!validate_name($data['first_name'])) {
        $errors[] = "First name must contain only letters, spaces, and hyphens";
    }
    
    // Validate last name
    if (!validate_required($data['last_name'])) {
        $errors[] = "Last name is required";
    } elseif (!validate_name($data['last_name'])) {
        $errors[] = "Last name must contain only letters, spaces, and hyphens";
    }
    
    // Validate email
    if (!validate_required($data['email'])) {
        $errors[] = "Email is required";
    } elseif (!validate_email($data['email'])) {
        $errors[] = "Please enter a valid email address";
    }
    
    // Validate password (only for new users or if password is provided)
    if (!$is_update && !validate_required($data['password'])) {
        $errors[] = "Password is required";
    } elseif (isset($data['password']) && !empty($data['password']) && !validate_password($data['password'])) {
        $errors[] = "Password must be at least 6 characters long";
    }
    
    // Validate role
    if (!validate_required($data['role'])) {
        $errors[] = "Role is required";
    } elseif (!validate_user_role($data['role'])) {
        $errors[] = "Invalid role selected";
    }
    
    // Validate status (for updates)
    if ($is_update && isset($data['status']) && !validate_status($data['status'])) {
        $errors[] = "Invalid status selected";
    }
    
    return $errors;
}

/**
 * Validate business data
 */
function validate_business_data($data) {
    $errors = [];
    
    // Validate business name
    if (!validate_required($data['name'])) {
        $errors[] = "Business name is required";
    } elseif (strlen(trim($data['name'])) < 2) {
        $errors[] = "Business name must be at least 2 characters long";
    }
    
    // Validate business type
    if (!validate_required($data['business_type'])) {
        $errors[] = "Business type is required";
    } elseif (!validate_business_type($data['business_type'])) {
        $errors[] = "Invalid business type selected";
    }
    
    return $errors;
}

/**
 * Validate transaction data
 */
function validate_transaction_data($data) {
    $errors = [];
    
    // Validate amount
    if (!validate_required($data['amount'])) {
        $errors[] = "Amount is required";
    } elseif (!validate_amount($data['amount'])) {
        $errors[] = "Amount must be a valid positive number";
    }
    
    // Validate type
    if (!validate_required($data['type'])) {
        $errors[] = "Transaction type is required";
    } elseif (!validate_transaction_type($data['type'])) {
        $errors[] = "Invalid transaction type";
    }
    
    // Validate date
    if (!validate_required($data['transaction_date'])) {
        $errors[] = "Transaction date is required";
    } elseif (!validate_date($data['transaction_date'])) {
        $errors[] = "Invalid date format";
    }
    
    // Validate business ID
    if (!validate_required($data['business_id'])) {
        $errors[] = "Business is required";
    } elseif (!is_numeric($data['business_id'])) {
        $errors[] = "Invalid business selected";
    }
    
    // Validate category ID
    if (!validate_required($data['category_id'])) {
        $errors[] = "Category is required";
    } elseif (!is_numeric($data['category_id'])) {
        $errors[] = "Invalid category selected";
    }
    
    return $errors;
}

/**
 * Check if email exists in database
 */
function email_exists($email, $exclude_user_id = null) {
    $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    $email = mysqli_real_escape_string($conn, $email);
    $sql = "SELECT id FROM users WHERE email = '$email'";
    
    if ($exclude_user_id) {
        $sql .= " AND id != " . (int)$exclude_user_id;
    }
    
    $result = mysqli_query($conn, $sql);
    $exists = mysqli_num_rows($result) > 0;
    
    mysqli_close($conn);
    return $exists;
}

/**
 * Display validation errors
 */
function display_errors($errors) {
    if (!empty($errors)) {
        echo '<div class="bg-red-50 border border-red-200 rounded-md p-4 mb-4">';
        echo '<div class="flex">';
        echo '<div class="flex-shrink-0">';
        echo '<svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">';
        echo '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />';
        echo '</svg>';
        echo '</div>';
        echo '<div class="ml-3">';
        echo '<h3 class="text-sm font-medium text-red-800">Please correct the following errors:</h3>';
        echo '<div class="mt-2 text-sm text-red-700">';
        echo '<ul class="list-disc pl-5 space-y-1">';
        foreach ($errors as $error) {
            echo '<li>' . htmlspecialchars($error) . '</li>';
        }
        echo '</ul>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
}

/**
 * Display success message
 */
function display_success($message) {
    echo '<div class="bg-green-50 border border-green-200 rounded-md p-4 mb-4">';
    echo '<div class="flex">';
    echo '<div class="flex-shrink-0">';
    echo '<svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">';
    echo '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />';
    echo '</svg>';
    echo '</div>';
    echo '<div class="ml-3">';
    echo '<p class="text-sm font-medium text-green-800">' . htmlspecialchars($message) . '</p>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
}

?>