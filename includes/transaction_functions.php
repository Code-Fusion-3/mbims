<?php
// Transaction-related helper functions

// Check if user can manage a specific business
function can_manage_business($user_id, $business_id, $user_role) {
    global $conn;
    
    // Admin can manage all businesses
    if ($user_role == 'admin') {
        return true;
    }
    
    // Check if user owns the business
    $owner_check = mysqli_query($conn, "SELECT id FROM businesses WHERE id = $business_id AND owner_id = $user_id");
    if (mysqli_num_rows($owner_check) > 0) {
        return true;
    }
    
    // Check if user is assigned to the business (for accountants)
    if ($user_role == 'accountant') {
        $assignment_check = mysqli_query($conn, "SELECT id FROM user_business_assignments WHERE user_id = $user_id AND business_id = $business_id");
        if (mysqli_num_rows($assignment_check) > 0) {
            return true;
        }
    }
    
    return false;
}

// Check if user can edit a specific transaction
function can_edit_transaction($current_user, $transaction) {
    global $conn;
    
    // Admin can edit all transactions
    if ($current_user['role'] == 'admin') {
        return true;
    }
    
    // User can edit their own transactions
    if ($transaction['user_id'] == $current_user['id']) {
        return true;
    }
    
    // Business owner can edit all transactions for their business
    if (can_manage_business($current_user['id'], $transaction['business_id'], $current_user['role'])) {
        return true;
    }
    
    // Accountants with write permission can edit transactions
    if ($current_user['role'] == 'accountant') {
        $permission_check = mysqli_query($conn, "SELECT permission_level FROM user_business_assignments 
                                                WHERE user_id = {$current_user['id']} AND business_id = {$transaction['business_id']}");
        if (mysqli_num_rows($permission_check) > 0) {
            $permission = mysqli_fetch_assoc($permission_check);
            return in_array($permission['permission_level'], ['write', 'admin']);
        }
    }
    
    return false;
}

// Check if accountant has write permission for any business
function has_write_permission($user_id) {
    global $conn;
    
    $permission_check = mysqli_query($conn, "SELECT id FROM user_business_assignments 
                                            WHERE user_id = $user_id AND permission_level IN ('write', 'admin')");
    return mysqli_num_rows($permission_check) > 0;
}

// Get transaction statistics for a business
function get_business_transaction_stats($business_id, $date_from = null, $date_to = null) {
    global $conn;
    
    $where_conditions = ["business_id = $business_id", "status = 'active'"];
    
    if ($date_from) {
        $where_conditions[] = "transaction_date >= '$date_from'";
    }
    
    if ($date_to) {
        $where_conditions[] = "transaction_date <= '$date_to'";
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    $sql = "SELECT 
            SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as total_income,
            SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as total_expenses,
            COUNT(CASE WHEN type = 'income' THEN 1 END) as income_count,
            COUNT(CASE WHEN type = 'expense' THEN 1 END) as expense_count,
            COUNT(*) as total_transactions
            FROM transactions 
            WHERE $where_clause";
    
    $result = mysqli_query($conn, $sql);
    return mysqli_fetch_assoc($result);
}

// Get monthly transaction summary
function get_monthly_transaction_summary($business_id = null, $months = 12) {
    global $conn;
    
    $where_conditions = ["status = 'active'"];
    
    if ($business_id) {
        $where_conditions[] = "business_id = $business_id";
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    $sql = "SELECT 
            DATE_FORMAT(transaction_date, '%Y-%m') as month,
            SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as income,
            SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as expenses
            FROM transactions 
            WHERE $where_clause 
            AND transaction_date >= DATE_SUB(CURDATE(), INTERVAL $months MONTH)
            GROUP BY DATE_FORMAT(transaction_date, '%Y-%m')
            ORDER BY month DESC";
    
    $result = mysqli_query($conn, $sql);
    $summary = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $summary[] = $row;
    }
    
    return $summary;
}

// Get category-wise transaction summary
function get_category_transaction_summary($business_id = null, $type = null, $date_from = null, $date_to = null) {
    global $conn;
    
    $where_conditions = ["t.status = 'active'"];
    
    if ($business_id) {
        $where_conditions[] = "t.business_id = $business_id";
    }
    
    if ($type && in_array($type, ['income', 'expense'])) {
        $where_conditions[] = "t.type = '$type'";
    }
    
    if ($date_from) {
        $where_conditions[] = "t.transaction_date >= '$date_from'";
    }
    
    if ($date_to) {
        $where_conditions[] = "t.transaction_date <= '$date_to'";
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    $sql = "SELECT 
            c.name as category_name,
            c.type as category_type,
            SUM(t.amount) as total_amount,
            COUNT(t.id) as transaction_count
            FROM transactions t
            JOIN categories c ON t.category_id = c.id
            WHERE $where_clause
            GROUP BY c.id, c.name, c.type
            ORDER BY total_amount DESC";
    
    $result = mysqli_query($conn, $sql);
    $summary = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $summary[] = $row;
    }
    
    return $summary;
}

// Export transactions to CSV
function export_transactions_csv($transactions_sql, $filename = 'transactions.csv') {
    global $conn;
    
    $result = mysqli_query($conn, $transactions_sql);
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // Write CSV headers
    fputcsv($output, [
        'Date', 'Business', 'Category', 'Type', 'Amount', 'Description', 'User', 'Created At'
    ]);
    
    // Write data rows
    while ($row = mysqli_fetch_assoc($result)) {
        fputcsv($output, [
            $row['transaction_date'],
            $row['business_name'],
            $row['category_name'],
            ucfirst($row['type']),
            $row['amount'],
            $row['description'],
            $row['first_name'] . ' ' . $row['last_name'],
            $row['created_at']
        ]);
    }
    
    fclose($output);
    exit();
}
?>