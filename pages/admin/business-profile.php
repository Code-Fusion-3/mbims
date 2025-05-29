<?php
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';
require_once '../../config/database.php';

// Require admin role
require_role('admin');

$page_title = 'Business Profile';
$current_user = get_logged_user();

// Get business ID from URL
$business_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$business_id) {
    header('Location: businesses.php');
    exit();
}

// Get database connection
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['remove_accountant'])) {
        $assignment_id = (int)$_POST['assignment_id'];
        $sql = "DELETE FROM user_business_assignments WHERE id = $assignment_id";
        
        if (mysqli_query($conn, $sql)) {
            $success_message = "Accountant removed successfully!";
        } else {
            $error_message = "Error removing accountant: " . mysqli_error($conn);
        }
    }
}

// Get business details with owner information
$business_sql = "SELECT b.*, 
                 u.first_name as owner_first_name, u.last_name as owner_last_name, u.email as owner_email,
                 (SELECT COUNT(*) FROM transactions t WHERE t.business_id = b.id AND t.status = 'active') as transaction_count,
                 (SELECT SUM(amount) FROM transactions t WHERE t.business_id = b.id AND t.type = 'income' AND t.status = 'active') as total_income,
                 (SELECT SUM(amount) FROM transactions t WHERE t.business_id = b.id AND t.type = 'expense' AND t.status = 'active') as total_expenses,
                 (SELECT COUNT(*) FROM user_business_assignments uba WHERE uba.business_id = b.id) as assigned_accountants_count
                 FROM businesses b
                 LEFT JOIN users u ON b.owner_id = u.id
                 WHERE b.id = $business_id";

$business_result = mysqli_query($conn, $business_sql);

if (mysqli_num_rows($business_result) == 0) {
    header('Location: businesses.php?error=business_not_found');
    exit();
}

$business = mysqli_fetch_assoc($business_result);

// Get assigned accountants
$accountants_sql = "SELECT uba.*, u.first_name, u.last_name, u.email, uba.assigned_at, uba.permission_level
                    FROM user_business_assignments uba
                    JOIN users u ON uba.user_id = u.id
                    WHERE uba.business_id = $business_id AND u.status = 'active'
                    ORDER BY uba.assigned_at DESC";
$accountants_result = mysqli_query($conn, $accountants_sql);

// Get recent transactions
$transactions_sql = "SELECT t.*, c.name as category_name, u.first_name, u.last_name
                     FROM transactions t
                     JOIN categories c ON t.category_id = c.id
                     JOIN users u ON t.user_id = u.id
                     WHERE t.business_id = $business_id AND t.status = 'active'
                     ORDER BY t.created_at DESC
                     LIMIT 20";
$transactions_result = mysqli_query($conn, $transactions_sql);

// Get monthly transaction summary for the last 12 months
$monthly_sql = "SELECT 
                DATE_FORMAT(transaction_date, '%Y-%m') as month,
                SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as monthly_income,
                SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as monthly_expenses,
                COUNT(*) as monthly_transactions
                FROM transactions 
                WHERE business_id = $business_id AND status = 'active' 
                AND transaction_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                GROUP BY DATE_FORMAT(transaction_date, '%Y-%m')
                ORDER BY month DESC";
$monthly_result = mysqli_query($conn, $monthly_sql);

// Get category breakdown
$category_sql = "SELECT c.name, c.type,
                 SUM(t.amount) as total_amount,
                 COUNT(t.id) as transaction_count
                 FROM transactions t
                 JOIN categories c ON t.category_id = c.id
                 WHERE t.business_id = $business_id AND t.status = 'active'
                 GROUP BY c.id, c.name, c.type
                 ORDER BY total_amount DESC";
$category_result = mysqli_query($conn, $category_sql);

// Business types for display
$business_types = [
    'retail' => 'Retail',
    'restaurant' => 'Restaurant',
    'service' => 'Service',
    'manufacturing' => 'Manufacturing',
    'technology' => 'Technology',
    'healthcare' => 'Healthcare',
    'education' => 'Education',
    'consulting' => 'Consulting',
    'real_estate' => 'Real Estate',
    'construction' => 'Construction',
    'transportation' => 'Transportation',
    'other' => 'Other'
];

include '../../components/header.php';
include '../../components/sidebar.php';
?>

<div class="space-y-6">
    <!-- Back Button -->
    <div>
        <a href="businesses.php" class="inline-flex items-center text-blue-600 hover:text-blue-800">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18">
                </path>
            </svg>
            Back to Businesses
        </a>
    </div>

    <!-- Display Messages -->
    <?php if (isset($success_message)): ?>
    <?php display_success($success_message); ?>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
    <div class="bg-red-50 border border-red-200 rounded-md p-4 mb-4">
        <p class="text-red-800"><?php echo $error_message; ?></p>
    </div>
    <?php endif; ?>

    <!-- Business Profile Header -->
    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <div class="h-16 w-16 rounded-lg bg-blue-100 flex items-center justify-center mr-4">
                        <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4">
                            </path>
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">
                            <?php echo htmlspecialchars($business['name']); ?>
                        </h1>
                        <p class="text-gray-600"><?php echo htmlspecialchars($business['description']); ?></p>
                        <div class="flex items-center mt-2">
                            <span
                                class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                <?php echo htmlspecialchars($business_types[$business['business_type']] ?? ucfirst($business['business_type'])); ?>
                            </span>
                            <span
                                class="ml-2 inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo get_status_badge_class($business['status']); ?>">
                                <?php echo ucfirst($business['status']); ?>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="flex space-x-3">
                    <button onclick="showEditBusinessModal(<?php echo htmlspecialchars(json_encode($business)); ?>)"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                        Edit Business
                    </button>
                    <button
                        onclick="showAssignAccountantModal(<?php echo $business['id']; ?>, '<?php echo htmlspecialchars($business['name']); ?>')"
                        class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg">
                        Assign Accountant
                    </button>
                </div>
            </div>
        </div>

        <!-- Business Details -->
        <div class="px-6 py-4">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-500">Owner</label>
                    <?php if ($business['owner_first_name']): ?>
                    <p class="text-sm text-gray-900">
                        <a href="user-profile.php?id=<?php echo $business['owner_id']; ?>"
                            class="hover:text-blue-600 hover:underline">
                            <?php echo htmlspecialchars($business['owner_first_name'] . ' ' . $business['owner_last_name']); ?>
                        </a>
                    </p>
                    <p class="text-xs text-gray-500"><?php echo htmlspecialchars($business['owner_email']); ?></p>
                    <?php else: ?>
                    <p class="text-sm text-gray-400">No owner assigned</p>
                    <?php endif; ?>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500">Created</label>
                    <p class="text-sm text-gray-900"><?php echo format_date($business['created_at']); ?></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500">Last Updated</label>
                    <p class="text-sm text-gray-900"><?php echo format_date($business['updated_at']); ?></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500">Assigned Accountants</label>
                    <p class="text-sm text-gray-900"><?php echo $business['assigned_accountants_count']; ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
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
                                <?php echo format_currency($business['total_income'] ?? 0); ?></dd>
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
                                <?php echo format_currency($business['total_expenses'] ?? 0); ?></dd>
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
                                class="text-lg font-medium <?php echo (($business['total_income'] ?? 0) - ($business['total_expenses'] ?? 0)) >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                <?php echo format_currency(($business['total_income'] ?? 0) - ($business['total_expenses'] ?? 0)); ?>
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
                            <dd class="text-lg font-medium text-gray-900"><?php echo $business['transaction_count']; ?>
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Content Sections -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Assigned Accountants -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Assigned Accountants</h3>
            </div>
            <div class="p-6">
                <?php if (mysqli_num_rows($accountants_result) > 0): ?>
                <div class="space-y-4">
                    <?php while ($accountant = mysqli_fetch_assoc($accountants_result)): ?>
                    <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                        <div class="flex items-center">
                            <div class="h-10 w-10 rounded-full bg-gray-300 flex items-center justify-center mr-3">
                                <span class="text-sm font-medium text-gray-700">
                                    <?php echo strtoupper(substr($accountant['first_name'], 0, 1) . substr($accountant['last_name'], 0, 1)); ?>
                                </span>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-900">
                                    <a href="user-profile.php?id=<?php echo $accountant['user_id']; ?>"
                                        class="hover:text-blue-600 hover:underline">
                                        <?php echo htmlspecialchars($accountant['first_name'] . ' ' . $accountant['last_name']); ?>
                                    </a>
                                </p>
                                <p class="text-sm text-gray-500"><?php echo htmlspecialchars($accountant['email']); ?>
                                </p>
                                <div class="flex items-center mt-1">
                                    <span
                                        class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo get_permission_badge_class($accountant['permission_level']); ?>">
                                        <?php echo ucfirst($accountant['permission_level']); ?>
                                    </span>
                                    <span class="ml-2 text-xs text-gray-400">
                                        Assigned: <?php echo format_date($accountant['assigned_at']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <button
                            onclick="showRemoveAccountantModal(<?php echo $accountant['id']; ?>, '<?php echo htmlspecialchars($accountant['first_name'] . ' ' . $accountant['last_name']); ?>')"
                            class="text-red-600 hover:text-red-800">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                </path>
                            </svg>
                        </button>
                    </div>
                    <?php endwhile; ?>
                </div>
                <?php else: ?>
                <p class="text-center text-gray-500">No accountants assigned to this business</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Monthly Summary -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Monthly Summary (Last 12 Months)</h3>
            </div>
            <div class="p-6">
                <?php if (mysqli_num_rows($monthly_result) > 0): ?>
                <div class="space-y-3">
                    <?php while ($month = mysqli_fetch_assoc($monthly_result)): ?>
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <div>
                            <p class="text-sm font-medium text-gray-900">
                                <?php echo date('F Y', strtotime($month['month'] . '-01')); ?>
                            </p>
                            <p class="text-xs text-gray-500"><?php echo $month['monthly_transactions']; ?> transactions
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm text-green-600">+<?php echo format_currency($month['monthly_income']); ?>
                            </p>
                            <p class="text-sm text-red-600">-<?php echo format_currency($month['monthly_expenses']); ?>
                            </p>
                            <p
                                class="text-sm font-medium <?php echo ($month['monthly_income'] - $month['monthly_expenses']) >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                <?php echo format_currency($month['monthly_income'] - $month['monthly_expenses']); ?>
                            </p>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
                <?php else: ?>
                <p class="text-center text-gray-500">No transaction data available</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Category Breakdown -->
    <div class="bg-white shadow rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Category Breakdown</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Category</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total
                            Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Transactions</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Average</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (mysqli_num_rows($category_result) > 0): ?>
                    <?php while ($category = mysqli_fetch_assoc($category_result)): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            <?php echo htmlspecialchars($category['name']); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span
                                class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $category['type'] == 'income' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                <?php echo ucfirst($category['type']); ?>
                            </span>
                        </td>
                        <td
                            class="px-6 py-4 whitespace-nowrap text-sm <?php echo $category['type'] == 'income' ? 'text-green-600' : 'text-red-600'; ?>">
                            <?php echo format_currency($category['total_amount']); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?php echo $category['transaction_count']; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo format_currency($category['total_amount'] / $category['transaction_count']); ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php else: ?>
                    <tr>
                        <td colspan="5" class="px-6 py-4 text-center text-gray-500">No category data available</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent Transactions -->
    <div class="bg-white shadow rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-medium text-gray-900">Recent Transactions</h3>
                <a href="../common/transactions.php?business_id=<?php echo $business_id; ?>"
                    class="text-blue-600 hover:text-blue-800 text-sm">View All</a>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Category</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Description</th>
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
                            <?php echo htmlspecialchars($transaction['category_name']); ?>
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
                            <a href="user-profile.php?id=<?php echo $transaction['user_id']; ?>"
                                class="hover:text-blue-600 hover:underline">
                                <?php echo htmlspecialchars($transaction['first_name'] . ' ' . $transaction['last_name']); ?>
                            </a>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">
                            <?php echo htmlspecialchars(truncate_text($transaction['description'], 50)); ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php else: ?>
                    <tr>
                        <td colspan="6" class="px-6 py-4 text-center text-gray-500">No transactions found</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Edit Business Modal -->
<div id="editBusinessModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900">Edit Business</h3>
                <button onclick="hideEditBusinessModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                        </path>
                    </svg>
                </button>
            </div>

            <form method="POST" action="businesses.php" class="space-y-4">
                <input type="hidden" name="business_id" id="edit_business_id">

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Business Name</label>
                    <input type="text" name="name" id="edit_name" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" id="edit_description" rows="3"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Business Type</label>
                    <select name="business_type" id="edit_business_type" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <?php foreach ($business_types as $value => $label): ?>
                        <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Owner (Partner)</label>
                    <select name="owner_id" id="edit_owner_id" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <?php 
                        $partners_result = mysqli_query($conn, "SELECT id, first_name, last_name, email FROM users WHERE role = 'partner' AND status = 'active' ORDER BY first_name, last_name");
                        while ($partner = mysqli_fetch_assoc($partners_result)): 
                        ?>
                        <option value="<?php echo $partner['id']; ?>">
                            <?php echo htmlspecialchars($partner['first_name'] . ' ' . $partner['last_name'] . ' (' . $partner['email'] . ')'); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select name="status" id="edit_status" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>

                <div class="flex justify-end space-x-3 pt-4">
                    <button type="button" onclick="hideEditBusinessModal()"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300">
                        Cancel
                    </button>
                    <button type="submit" name="update_business"
                        class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700">
                        Update Business
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Assign Accountant Modal -->
<div id="assignAccountantModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900">Assign Accountant</h3>
                <button onclick="hideAssignAccountantModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                        </path>
                    </svg>
                </button>
            </div>

            <p class="text-sm text-gray-600 mb-4">Assign accountant to: <span id="assign_business_name"
                    class="font-medium"></span></p>

            <form method="POST" action="assign-accountant.php" class="space-y-4">
                <input type="hidden" name="business_id" id="assign_business_id">

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Accountant</label>
                    <select name="accountant_id" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Select Accountant</option>
                        <?php 
                        $accountants_result = mysqli_query($conn, "SELECT id, first_name, last_name, email FROM users WHERE role = 'accountant' AND status = 'active' ORDER BY first_name, last_name");
                        while ($accountant = mysqli_fetch_assoc($accountants_result)): 
                        ?>
                        <option value="<?php echo $accountant['id']; ?>">
                            <?php echo htmlspecialchars($accountant['first_name'] . ' ' . $accountant['last_name'] . ' (' . $accountant['email'] . ')'); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Permission Level</label>
                    <select name="permission_level" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="read">Read Only</option>
                        <option value="write">Read & Write</option>
                        <option value="admin">Full Access</option>
                    </select>
                </div>

                <div class="flex justify-end space-x-3 pt-4">
                    <button type="button" onclick="hideAssignAccountantModal()"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300">
                        Cancel
                    </button>
                    <button type="submit"
                        class="px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-md hover:bg-green-700">
                        Assign Accountant
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Remove Accountant Modal -->
<div id="removeAccountantModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900">Remove Accountant</h3>
                <button onclick="hideRemoveAccountantModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                        </path>
                    </svg>
                </button>
            </div>

            <p class="text-sm text-gray-600 mb-4">Are you sure you want to remove: <span id="remove_accountant_name"
                    class="font-medium"></span>?</p>
            <p class="text-xs text-gray-500 mb-4">This will remove their access to this business.</p>

            <form method="POST" class="space-y-4">
                <input type="hidden" name="assignment_id" id="remove_assignment_id">

                <div class="flex justify-end space-x-3 pt-4">
                    <button type="button" onclick="hideRemoveAccountantModal()"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300">
                        Cancel
                    </button>
                    <button type="submit" name="remove_accountant"
                        class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-md hover:bg-red-700">
                        Remove Accountant
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Edit Business Modal Functions
function showEditBusinessModal(business) {
    document.getElementById('edit_business_id').value = business.id;
    document.getElementById('edit_name').value = business.name;
    document.getElementById('edit_description').value = business.description || '';
    document.getElementById('edit_business_type').value = business.business_type;
    document.getElementById('edit_owner_id').value = business.owner_id;
    document.getElementById('edit_status').value = business.status;
    document.getElementById('editBusinessModal').classList.remove('hidden');
}

function hideEditBusinessModal() {
    document.getElementById('editBusinessModal').classList.add('hidden');
}

// Assign Accountant Modal Functions
function showAssignAccountantModal(businessId, businessName) {
    document.getElementById('assign_business_id').value = businessId;
    document.getElementById('assign_business_name').textContent = businessName;
    document.getElementById('assignAccountantModal').classList.remove('hidden');
}

function hideAssignAccountantModal() {
    document.getElementById('assignAccountantModal').classList.add('hidden');
}

// Remove Accountant Modal Functions
function showRemoveAccountantModal(assignmentId, accountantName) {
    document.getElementById('remove_assignment_id').value = assignmentId;
    document.getElementById('remove_accountant_name').textContent = accountantName;
    document.getElementById('removeAccountantModal').classList.remove('hidden');
}

function hideRemoveAccountantModal() {
    document.getElementById('removeAccountantModal').classList.add('hidden');
}

// Close modals when clicking outside
window.onclick = function(event) {
    const modals = ['editBusinessModal', 'assignAccountantModal', 'removeAccountantModal'];
    modals.forEach(modalId => {
        const modal = document.getElementById(modalId);
        if (event.target === modal) {
            modal.classList.add('hidden');
        }
    });
}
</script>

<?php
mysqli_close($conn);
include '../../components/footer.php';
?>