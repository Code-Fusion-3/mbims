<?php
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';
require_once '../../includes/transaction_functions.php';
require_once '../../config/database.php';

// Require login
require_login();

$page_title = 'Transactions';
$current_user = get_logged_user();

// Get database connection
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_transaction'])) {
        $business_id = (int)$_POST['business_id'];
        $category_id = (int)$_POST['category_id'];
        $amount = (float)$_POST['amount'];
        $type = sanitize_input($_POST['type']);
        $description = sanitize_input($_POST['description']);
        $transaction_date = sanitize_input($_POST['transaction_date']);
        
        $errors = [];
        
        // Validate inputs
        if ($business_id <= 0) {
            $errors[] = "Please select a business";
        }
        
        if ($category_id <= 0) {
            $errors[] = "Please select a category";
        }
        
        if ($amount <= 0) {
            $errors[] = "Amount must be greater than 0";
        }
        
        if (!in_array($type, ['income', 'expense'])) {
            $errors[] = "Invalid transaction type";
        }
        
        if (empty($transaction_date)) {
            $errors[] = "Transaction date is required";
        }
        
        // Check if user has permission to add transactions for this business
        if (!can_manage_business($current_user['id'], $business_id, $current_user['role'])) {
            $errors[] = "You don't have permission to add transactions for this business";
        }
        
        // Verify category type matches transaction type
        $category_check = mysqli_query($conn, "SELECT type FROM categories WHERE id = $category_id AND status = 'active'");
        if (mysqli_num_rows($category_check) > 0) {
            $category = mysqli_fetch_assoc($category_check);
            if ($category['type'] != $type) {
                $errors[] = "Category type doesn't match transaction type";
            }
        } else {
            $errors[] = "Invalid category selected";
        }
        
        if (empty($errors)) {
            $sql = "INSERT INTO transactions (business_id, category_id, user_id, amount, type, description, transaction_date) 
                    VALUES ($business_id, $category_id, {$current_user['id']}, $amount, '$type', '$description', '$transaction_date')";
            
            if (mysqli_query($conn, $sql)) {
                $success_message = "Transaction added successfully!";
            } else {
                $error_message = "Error adding transaction: " . mysqli_error($conn);
            }
        } else {
            $error_message = implode(', ', $errors);
        }
    }
    
    if (isset($_POST['update_transaction'])) {
        $transaction_id = (int)$_POST['transaction_id'];
        $category_id = (int)$_POST['category_id'];
        $amount = (float)$_POST['amount'];
        $type = sanitize_input($_POST['type']);
        $description = sanitize_input($_POST['description']);
        $transaction_date = sanitize_input($_POST['transaction_date']);
        
        $errors = [];
        
        // Check if user has permission to edit this transaction
        $transaction_check = mysqli_query($conn, "SELECT business_id, user_id FROM transactions WHERE id = $transaction_id AND status = 'active'");
        if (mysqli_num_rows($transaction_check) > 0) {
            $transaction = mysqli_fetch_assoc($transaction_check);
            if (!can_manage_business($current_user['id'], $transaction['business_id'], $current_user['role']) && 
                $transaction['user_id'] != $current_user['id']) {
                $errors[] = "You don't have permission to edit this transaction";
            }
        } else {
            $errors[] = "Transaction not found";
        }
        
        // Validate other inputs (same as add)
        if ($category_id <= 0) {
            $errors[] = "Please select a category";
        }
        
        if ($amount <= 0) {
            $errors[] = "Amount must be greater than 0";
        }
        
        if (!in_array($type, ['income', 'expense'])) {
            $errors[] = "Invalid transaction type";
        }
        
        if (empty($transaction_date)) {
            $errors[] = "Transaction date is required";
        }
        
        // Verify category type matches transaction type
        $category_check = mysqli_query($conn, "SELECT type FROM categories WHERE id = $category_id AND status = 'active'");
        if (mysqli_num_rows($category_check) > 0) {
            $category = mysqli_fetch_assoc($category_check);
            if ($category['type'] != $type) {
                $errors[] = "Category type doesn't match transaction type";
            }
        } else {
            $errors[] = "Invalid category selected";
        }
        
        if (empty($errors)) {
            $sql = "UPDATE transactions SET 
                    category_id = $category_id, 
                    amount = $amount, 
                    type = '$type', 
                    description = '$description', 
                    transaction_date = '$transaction_date',
                    updated_at = CURRENT_TIMESTAMP
                    WHERE id = $transaction_id";
            
            if (mysqli_query($conn, $sql)) {
                $success_message = "Transaction updated successfully!";
            } else {
                $error_message = "Error updating transaction: " . mysqli_error($conn);
            }
        } else {
            $error_message = implode(', ', $errors);
        }
    }
    
    if (isset($_POST['delete_transaction'])) {
        $transaction_id = (int)$_POST['transaction_id'];
        
        // Check if user has permission to delete this transaction
        $transaction_check = mysqli_query($conn, "SELECT business_id, user_id FROM transactions WHERE id = $transaction_id AND status = 'active'");
        if (mysqli_num_rows($transaction_check) > 0) {
            $transaction = mysqli_fetch_assoc($transaction_check);
            if (can_manage_business($current_user['id'], $transaction['business_id'], $current_user['role']) || 
                $transaction['user_id'] == $current_user['id'] || 
                $current_user['role'] == 'admin') {
                
                $sql = "UPDATE transactions SET status = 'deleted' WHERE id = $transaction_id";
                
                if (mysqli_query($conn, $sql)) {
                    $success_message = "Transaction deleted successfully!";
                } else {
                    $error_message = "Error deleting transaction: " . mysqli_error($conn);
                }
            } else {
                $error_message = "You don't have permission to delete this transaction";
            }
        } else {
            $error_message = "Transaction not found";
        }
    }
}

// Get filter parameters
$business_filter = isset($_GET['business_id']) ? (int)$_GET['business_id'] : 0;
$type_filter = isset($_GET['type']) ? sanitize_input($_GET['type']) : '';
$category_filter = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$date_from = isset($_GET['date_from']) ? sanitize_input($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? sanitize_input($_GET['date_to']) : '';
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';

// Build WHERE clause based on user role and filters
$where_conditions = ["t.status = 'active'"];

// Role-based access control
if ($current_user['role'] == 'partner') {
    $where_conditions[] = "b.owner_id = {$current_user['id']}";
} elseif ($current_user['role'] == 'accountant') {
    $where_conditions[] = "(b.owner_id = {$current_user['id']} OR EXISTS (
        SELECT 1 FROM user_business_assignments uba 
        WHERE uba.user_id = {$current_user['id']} AND uba.business_id = b.id
    ))";
}

// Apply filters
if ($business_filter > 0) {
    $where_conditions[] = "t.business_id = $business_filter";
}

if (!empty($type_filter) && in_array($type_filter, ['income', 'expense'])) {
    $where_conditions[] = "t.type = '$type_filter'";
}

if ($category_filter > 0) {
    $where_conditions[] = "t.category_id = $category_filter";
}

if (!empty($date_from)) {
    $where_conditions[] = "t.transaction_date >= '$date_from'";
}

if (!empty($date_to)) {
    $where_conditions[] = "t.transaction_date <= '$date_to'";
}

if (!empty($search)) {
    $where_conditions[] = "(t.description LIKE '%$search%' OR b.name LIKE '%$search%' OR c.name LIKE '%$search%')";
}

$where_clause = implode(' AND ', $where_conditions);

// Get transactions with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

$transactions_sql = "SELECT t.*, b.name as business_name, c.name as category_name, 
                     u.first_name, u.last_name
                     FROM transactions t
                     JOIN businesses b ON t.business_id = b.id
                     JOIN categories c ON t.category_id = c.id
                     JOIN users u ON t.user_id = u.id
                     WHERE $where_clause
                     ORDER BY t.transaction_date DESC, t.created_at DESC
                     LIMIT $per_page OFFSET $offset";

$transactions_result = mysqli_query($conn, $transactions_sql);

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total 
              FROM transactions t
              JOIN businesses b ON t.business_id = b.id
              JOIN categories c ON t.category_id = c.id
              JOIN users u ON t.user_id = u.id
              WHERE $where_clause";
$count_result = mysqli_query($conn, $count_sql);
$total_transactions = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_transactions / $per_page);

// Get summary statistics
$summary_sql = "SELECT 
                SUM(CASE WHEN t.type = 'income' THEN t.amount ELSE 0 END) as total_income,
                SUM(CASE WHEN t.type = 'expense' THEN t.amount ELSE 0 END) as total_expenses,
                COUNT(CASE WHEN t.type = 'income' THEN 1 END) as income_count,
                COUNT(CASE WHEN t.type = 'expense' THEN 1 END) as expense_count
                FROM transactions t
                JOIN businesses b ON t.business_id = b.id
                JOIN categories c ON t.category_id = c.id
                JOIN users u ON t.user_id = u.id
                WHERE $where_clause";
$summary_result = mysqli_query($conn, $summary_sql);
$summary = mysqli_fetch_assoc($summary_result);

// Get businesses for dropdown (based on user role)
$businesses_where = "b.status = 'active'";
if ($current_user['role'] == 'partner') {
    $businesses_where .= " AND b.owner_id = {$current_user['id']}";
} elseif ($current_user['role'] == 'accountant') {
    $businesses_where .= " AND (b.owner_id = {$current_user['id']} OR EXISTS (
        SELECT 1 FROM user_business_assignments uba 
        WHERE uba.user_id = {$current_user['id']} AND uba.business_id = b.id
    ))";
}

$businesses_sql = "SELECT id, name FROM businesses b WHERE $businesses_where ORDER BY name";
$businesses_result = mysqli_query($conn, $businesses_sql);

// Get categories for dropdown
$categories_sql = "SELECT id, name, type FROM categories WHERE status = 'active' ORDER BY type, name";
$categories_result = mysqli_query($conn, $categories_sql);

include '../../components/header.php';
include '../../components/sidebar.php';
?>

<div class="space-y-6">
    <!-- Page Header -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Transactions</h1>
                <p class="text-gray-600">Manage income and expense transactions</p>
            </div>
            <?php if ($current_user['role'] != 'accountant' || has_write_permission($current_user['id'])): ?>
            <button onclick="showAddTransactionModal()"
                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                Add Transaction
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Display Messages -->
    <?php if (isset($success_message)): ?>
    <?php display_success($success_message); ?>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
    <div class="bg-red-50 border border-red-200 rounded-md p-4">
        <p class="text-red-800"><?php echo $error_message; ?></p>
    </div>
    <?php endif; ?>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
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
                                <?php echo format_currency($summary['total_income'] ?? 0); ?></dd>
                        </dl>
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Total Income</dt>
                            <dd class="text-lg font-medium text-green-600">
                                <?php echo format_currency($summary['total_income'] ?? 0); ?></dd>
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
                                <?php echo format_currency($summary['total_expenses'] ?? 0); ?></dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1">
                                </path>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Net Profit</dt>
                            <dd
                                class="text-lg font-medium <?php echo (($summary['total_income'] ?? 0) - ($summary['total_expenses'] ?? 0)) >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                <?php echo format_currency(($summary['total_income'] ?? 0) - ($summary['total_expenses'] ?? 0)); ?>
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
                            <dd class="text-lg font-medium text-gray-900"><?php echo $total_transactions; ?></dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white shadow rounded-lg p-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Filters</h3>
        <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Business</label>
                <select name="business_id"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">All Businesses</option>
                    <?php while ($business = mysqli_fetch_assoc($businesses_result)): ?>
                    <option value="<?php echo $business['id']; ?>"
                        <?php echo $business_filter == $business['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($business['name']); ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                <select name="type"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">All Types</option>
                    <option value="income" <?php echo $type_filter == 'income' ? 'selected' : ''; ?>>Income</option>
                    <option value="expense" <?php echo $type_filter == 'expense' ? 'selected' : ''; ?>>Expense</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                <select name="category_id"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">All Categories</option>
                    <?php 
                    mysqli_data_seek($categories_result, 0);
                    while ($category = mysqli_fetch_assoc($categories_result)): 
                    ?>
                    <option value="<?php echo $category['id']; ?>"
                        <?php echo $category_filter == $category['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($category['name']) . ' (' . ucfirst($category['type']) . ')'; ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
                <input type="date" name="date_from" value="<?php echo $date_from; ?>"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
                <input type="date" name="date_to" value="<?php echo $date_to; ?>"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                    placeholder="Description, business, category..."
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            <div class="md:col-span-2 lg:col-span-6 flex space-x-3">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                    Apply Filters
                </button>
                <a href="transactions.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg">
                    Clear Filters
                </a>
            </div>
        </form>
    </div>

    <!-- Transactions Table -->
    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">
                Transactions
                <?php if ($total_transactions > 0): ?>
                (<?php echo number_format($total_transactions); ?> total)
                <?php endif; ?>
            </h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Business</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Category</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Description</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (mysqli_num_rows($transactions_result) > 0): ?>
                    <?php while ($transaction = mysqli_fetch_assoc($transactions_result)): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo format_date($transaction['transaction_date']); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?php echo htmlspecialchars($transaction['business_name']); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?php echo htmlspecialchars($transaction['category_name']); ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-900">
                            <?php echo htmlspecialchars(truncate_text($transaction['description'], 50)); ?>
                        </td>
                        <td
                            class="px-6 py-4 whitespace-nowrap text-sm font-medium <?php echo $transaction['type'] == 'income' ? 'text-green-600' : 'text-red-600'; ?>">
                            <?php echo format_currency($transaction['amount']); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span
                                class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $transaction['type'] == 'income' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                <?php echo ucfirst($transaction['type']); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?php echo htmlspecialchars($transaction['first_name'] . ' ' . $transaction['last_name']); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <div class="flex space-x-2">
                                <?php if (can_edit_transaction($current_user, $transaction)): ?>
                                <button
                                    onclick="showEditTransactionModal(<?php echo htmlspecialchars(json_encode($transaction)); ?>)"
                                    class="text-blue-600 hover:text-blue-900">Edit</button>
                                <button
                                    onclick="showDeleteTransactionModal(<?php echo $transaction['id']; ?>, '<?php echo htmlspecialchars($transaction['description']); ?>')"
                                    class="text-red-600 hover:text-red-900">Delete</button>
                                <?php else: ?>
                                <span class="text-gray-400">View Only</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php else: ?>
                    <tr>
                        <td colspan="8" class="px-6 py-4 text-center text-gray-500">No transactions found</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
            <div class="flex-1 flex justify-between sm:hidden">
                <?php if ($page > 1): ?>
                <a href="?page=<?php echo ($page - 1); ?>&<?php echo http_build_query(array_filter($_GET, function($key) { return $key != 'page'; }, ARRAY_FILTER_USE_KEY)); ?>"
                    class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                    Previous
                </a>
                <?php endif; ?>

                <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo ($page + 1); ?>&<?php echo http_build_query(array_filter($_GET, function($key) { return $key != 'page'; }, ARRAY_FILTER_USE_KEY)); ?>"
                    class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                    Next
                </a>
                <?php endif; ?>
            </div>

            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm text-gray-700">
                        Showing <span class="font-medium"><?php echo (($page - 1) * $per_page) + 1; ?></span>
                        to <span class="font-medium"><?php echo min($page * $per_page, $total_transactions); ?></span>
                        of <span class="font-medium"><?php echo $total_transactions; ?></span> results
                    </p>
                </div>
                <div>
                    <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                        <?php if ($page > 1): ?>
                        <a href="?page=<?php echo ($page - 1); ?>&<?php echo http_build_query(array_filter($_GET, function($key) { return $key != 'page'; }, ARRAY_FILTER_USE_KEY)); ?>"
                            class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z"
                                    clip-rule="evenodd" />
                            </svg>
                        </a>
                        <?php endif; ?>

                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        for ($i = $start_page; $i <= $end_page; $i++):
                        ?>
                        <a href="?page=<?php echo $i; ?>&<?php echo http_build_query(array_filter($_GET, function($key) { return $key != 'page'; }, ARRAY_FILTER_USE_KEY)); ?>"
                            class="relative inline-flex items-center px-4 py-2 border text-sm font-medium <?php echo $i == $page ? 'z-10 bg-blue-50 border-blue-500 text-blue-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'; ?>">
                            <?php echo $i; ?>
                        </a>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo ($page + 1); ?>&<?php echo http_build_query(array_filter($_GET, function($key) { return $key != 'page'; }, ARRAY_FILTER_USE_KEY)); ?>"
                            class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"
                                    clip-rule="evenodd" />
                            </svg>
                        </a>
                        <?php endif; ?>
                    </nav>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Transaction Modal -->
<div id="addTransactionModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900">Add Transaction</h3>
                <button onclick="hideAddTransactionModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                        </path>
                    </svg>
                </button>
            </div>

            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Business</label>
                    <select name="business_id" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Select Business</option>
                        <?php 
                        mysqli_data_seek($businesses_result, 0);
                        while ($business = mysqli_fetch_assoc($businesses_result)): 
                        ?>
                        <option value="<?php echo $business['id']; ?>">
                            <?php echo htmlspecialchars($business['name']); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                    <select name="type" id="add_type" required onchange="filterCategories('add')"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Select Type</option>
                        <option value="income">Income</option>
                        <option value="expense">Expense</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                    <select name="category_id" id="add_category" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option selected disabled>Select Category</option>
                        <?php 
                        mysqli_data_seek($categories_result, 0);
                        while ($category = mysqli_fetch_assoc($categories_result)): 
                        ?>
                        <option value="<?php echo $category['id']; ?>" data-type="<?php echo $category['type']; ?>">
                            <?php echo htmlspecialchars($category['name']); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Amount</label>
                    <input type="number" name="amount" step="0.01" min="0.01" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Transaction Date</label>
                    <input type="date" name="transaction_date" value="<?php echo date('Y-m-d'); ?>" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" rows="3" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                </div>

                <div class="flex justify-end space-x-3 pt-4">
                    <button type="button" onclick="hideAddTransactionModal()"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300">
                        Cancel
                    </button>
                    <button type="submit" name="add_transaction"
                        class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700">
                        Add Transaction
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Transaction Modal -->
<div id="editTransactionModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900">Edit Transaction</h3>
                <button onclick="hideEditTransactionModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                        </path>
                    </svg>
                </button>
            </div>

            <form method="POST" class="space-y-4">
                <input type="hidden" name="transaction_id" id="edit_transaction_id">

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                    <select name="type" id="edit_type" required onchange="filterCategories('edit')"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="income">Income</option>
                        <option value="expense">Expense</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                    <select name="category_id" id="edit_category" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <?php 
                        mysqli_data_seek($categories_result, 0);
                        while ($category = mysqli_fetch_assoc($categories_result)): 
                        ?>
                        <option value="<?php echo $category['id']; ?>" data-type="<?php echo $category['type']; ?>">
                            <?php echo htmlspecialchars($category['name']); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Amount</label>
                    <input type="number" name="amount" id="edit_amount" step="0.01" min="0.01" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Transaction Date</label>
                    <input type="date" name="transaction_date" id="edit_transaction_date" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" id="edit_description" rows="3" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                </div>

                <div class="flex justify-end space-x-3 pt-4">
                    <button type="button" onclick="hideEditTransactionModal()"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300">
                        Cancel
                    </button>
                    <button type="submit" name="update_transaction"
                        class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700">
                        Update Transaction
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Transaction Modal -->
<div id="deleteTransactionModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900">Delete Transaction</h3>
                <button onclick="hideDeleteTransactionModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                        </path>
                    </svg>
                </button>
            </div>

            <p class="text-sm text-gray-600 mb-4">Are you sure you want to delete this transaction?</p>
            <p class="text-sm font-medium text-gray-900 mb-4" id="delete_transaction_description"></p>
            <p class="text-xs text-gray-500 mb-4">This action cannot be undone.</p>

            <form method="POST" class="space-y-4">
                <input type="hidden" name="transaction_id" id="delete_transaction_id">

                <div class="flex justify-end space-x-3 pt-4">
                    <button type="button" onclick="hideDeleteTransactionModal()"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300">
                        Cancel
                    </button>
                    <button type="submit" name="delete_transaction"
                        class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-md hover:bg-red-700">
                        Delete Transaction
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Add Transaction Modal Functions
function showAddTransactionModal() {
    document.getElementById('addTransactionModal').classList.remove('hidden');
}

function hideAddTransactionModal() {
    document.getElementById('addTransactionModal').classList.add('hidden');
}

// Edit Transaction Modal Functions
function showEditTransactionModal(transaction) {
    document.getElementById('edit_transaction_id').value = transaction.id;
    document.getElementById('edit_type').value = transaction.type;
    document.getElementById('edit_category').value = transaction.category_id;
    document.getElementById('edit_amount').value = transaction.amount;
    document.getElementById('edit_transaction_date').value = transaction.transaction_date;
    document.getElementById('edit_description').value = transaction.description;

    // Filter categories based on type
    filterCategories('edit');

    document.getElementById('editTransactionModal').classList.remove('hidden');
}

function hideEditTransactionModal() {
    document.getElementById('editTransactionModal').classList.add('hidden');
}

// Delete Transaction Modal Functions
function showDeleteTransactionModal(transactionId, description) {
    document.getElementById('delete_transaction_id').value = transactionId;
    document.getElementById('delete_transaction_description').textContent = description;
    document.getElementById('deleteTransactionModal').classList.remove('hidden');
}

function hideDeleteTransactionModal() {
    document.getElementById('deleteTransactionModal').classList.add('hidden');
}

// Filter categories based on transaction type
function filterCategories(prefix) {
    const typeSelect = document.getElementById(prefix + '_type');
    const categorySelect = document.getElementById(prefix + '_category');
    const selectedType = typeSelect.value;

    // Show/hide options based on type
    Array.from(categorySelect.options).forEach(option => {
        if (option.value === '') {
            option.style.display = 'block';
            return;
        }

        const optionType = option.getAttribute('data-type');
        if (selectedType === '' || optionType === selectedType) {
            option.style.display = 'block';
        } else {
            option.style.display = 'none';
        }
    });

    // Reset category selection if current selection doesn't match type
    if (categorySelect.value) {
        const currentOption = categorySelect.options[categorySelect.selectedIndex];
        const currentType = currentOption.getAttribute('data-type');
        if (currentType !== selectedType) {
            categorySelect.value = '';
        }
    }
}

// Close modals when clicking outside
window.onclick = function(event) {
    const modals = ['addTransactionModal', 'editTransactionModal', 'deleteTransactionModal'];
    modals.forEach(modalId => {
        const modal = document.getElementById(modalId);
        if (event.target === modal) {
            modal.classList.add('hidden');
        }
    });
}

// Initialize category filtering on page load
document.addEventListener('DOMContentLoaded', function() {
    // Set up event listeners for type changes
    const addTypeSelect = document.getElementById('add_type');
    if (addTypeSelect) {
        addTypeSelect.addEventListener('change', () => filterCategories('add'));
    }

    const editTypeSelect = document.getElementById('edit_type');
    if (editTypeSelect) {
        editTypeSelect.addEventListener('change', () => filterCategories('edit'));
    }
});
</script>

<?php
mysqli_close($conn);
include '../../components/footer.php';
?>