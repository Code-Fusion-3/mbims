<?php
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';
require_once '../../config/database.php';

// Require partner role
require_role('partner');

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

// Get business details (ensure it belongs to current user)
$business_sql = "SELECT * FROM businesses WHERE id = $business_id AND owner_id = {$current_user['id']}";
$business_result = mysqli_query($conn, $business_sql);

if (mysqli_num_rows($business_result) == 0) {
    header('Location: businesses.php?error=business_not_found');
    exit();
}

$business = mysqli_fetch_assoc($business_result);

// Get business statistics
$stats_sql = "SELECT 
              COUNT(CASE WHEN t.type = 'income' THEN 1 END) as income_transactions,
              COUNT(CASE WHEN t.type = 'expense' THEN 1 END) as expense_transactions,
              SUM(CASE WHEN t.type = 'income' THEN t.amount ELSE 0 END) as total_income,
              SUM(CASE WHEN t.type = 'expense' THEN t.amount ELSE 0 END) as total_expenses,
              COUNT(*) as total_transactions
              FROM transactions t
              WHERE t.business_id = $business_id AND t.status = 'active'";
$stats_result = mysqli_query($conn, $stats_sql);
$stats = mysqli_fetch_assoc($stats_result);

// Get recent transactions
$recent_transactions_sql = "SELECT t.*, c.name as category_name, u.first_name, u.last_name
                           FROM transactions t
                           JOIN categories c ON t.category_id = c.id
                           JOIN users u ON t.user_id = u.id
                           WHERE t.business_id = $business_id AND t.status = 'active'
                           ORDER BY t.created_at DESC
                           LIMIT 10";
$recent_transactions_result = mysqli_query($conn, $recent_transactions_sql);

// Get assigned accountants
$accountants_sql = "SELECT u.*, uba.permission_level, uba.assigned_at
                   FROM user_business_assignments uba
                   JOIN users u ON uba.user_id = u.id
                   WHERE uba.business_id = $business_id AND u.status = 'active'
                   ORDER BY uba.assigned_at DESC";
$accountants_result = mysqli_query($conn, $accountants_sql);

// Get monthly performance data for chart (last 12 months)
$monthly_data_sql = "SELECT 
                     DATE_FORMAT(t.transaction_date, '%Y-%m') as month,
                     SUM(CASE WHEN t.type = 'income' THEN t.amount ELSE 0 END) as income,
                     SUM(CASE WHEN t.type = 'expense' THEN t.amount ELSE 0 END) as expenses
                     FROM transactions t
                     WHERE t.business_id = $business_id AND t.status = 'active'
                     AND t.transaction_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                     GROUP BY DATE_FORMAT(t.transaction_date, '%Y-%m')
                     ORDER BY month ASC";
$monthly_data_result = mysqli_query($conn, $monthly_data_sql);

// Get category breakdown
$category_breakdown_sql = "SELECT c.name, c.type,
                          COUNT(*) as transaction_count,
                          SUM(t.amount) as total_amount
                          FROM transactions t
                          JOIN categories c ON t.category_id = c.id
                          WHERE t.business_id = $business_id AND t.status = 'active'
                          GROUP BY c.id, c.name, c.type
                          ORDER BY total_amount DESC";
$category_breakdown_result = mysqli_query($conn, $category_breakdown_sql);

include '../../components/header.php';
include '../../components/sidebar.php';
?>

<div class="space-y-6">
    <!-- Back Button -->
    <div>
        <a href="businesses.php" class="inline-flex items-center text-blue-600 hover:text-blue-800">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
            Back to My Businesses
        </a>
    </div>

    <!-- Business Profile Header -->
    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <div class="h-16 w-16 rounded-full bg-blue-500 flex items-center justify-center mr-4">
                        <span class="text-xl font-medium text-white">
                            <?php echo strtoupper(substr($business['name'], 0, 2)); ?>
                        </span>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900"><?php echo htmlspecialchars($business['name']); ?></h1>
                        <p class="text-gray-600"><?php echo htmlspecialchars($business['business_type']); ?></p>
                        <div class="flex items-center mt-2">
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo get_status_badge_class($business['status']); ?>">
                                <?php echo ucfirst($business['status']); ?>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="flex space-x-3">
                    <a href="../common/transactions.php?business_id=<?php echo $business['id']; ?>"
                       class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg">
                        Add Transaction
                    </a>
                    <button onclick="showEditBusinessModal(<?php echo htmlspecialchars(json_encode($business)); ?>)"
                            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                        Edit Business
                    </button>
                </div>
            </div>
        </div>

        <!-- Business Details -->
        <div class="px-6 py-4">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-500">Created</label>
                    <p class="text-sm text-gray-900"><?php echo format_date($business['created_at']); ?></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500">Last Updated</label>
                    <p class="text-sm text-gray-900"><?php echo format_date($business['updated_at']); ?></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500">Total Transactions</label>
                    <p class="text-sm text-gray-900"><?php echo $stats['total_transactions']; ?></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500">Assigned Accountants</label>
                    <p class="text-sm text-gray-900"><?php echo mysqli_num_rows($accountants_result); ?></p>
                </div>
            </div>
            
            <?php if (!empty($business['description'])): ?>
            <div class="mt-4">
                <label class="block text-sm font-medium text-gray-500">Description</label>
                <p class="text-sm text-gray-900"><?php echo htmlspecialchars($business['description']); ?></p>
            </div>
            <?php endif; ?>
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
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Total Income</dt>
                            <dd class="text-lg font-medium text-green-600"><?php echo format_currency($stats['total_income']); ?></dd>
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
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 13l-5 5m0 0l-5-5m5 5V6"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Total Expenses</dt>
                            <dd class="text-lg font-medium text-red-600"><?php echo format_currency($stats['total_expenses']); ?></dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 <?php echo ($stats['total_income'] - $stats['total_expenses']) >= 0 ? 'bg-blue-500' : 'bg-orange-500'; ?> rounded-md flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Net Profit</dt>
                            <dd class="text-lg font-medium <?php echo ($stats['total_income'] - $stats['total_expenses']) >= 0 ? 'text-blue-600' : 'text-orange-600'; ?>">
                                <?php echo format_currency($stats['total_income'] - $stats['total_expenses']); ?>
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
                        <div class="w-8 h-8 bg-purple-500 rounded-md flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Total Transactions</dt>
                            <dd class="text-lg font-medium text-gray-900"><?php echo $stats['total_transactions']; ?></dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts and Data -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Monthly Performance Chart -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Monthly Performance (Last 12 Months)</h3>
            </div>
            <div class="p-6">
                <canvas id="monthlyChart" width="400" height="200"></canvas>
            </div>
        </div>

        <!-- Category Breakdown -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Category Breakdown</h3>
            </div>
            <div class="p-6">
                <?php if (mysqli_num_rows($category_breakdown_result) > 0): ?>
                <div class="space-y-4">
                    <?php while ($category = mysqli_fetch_assoc($category_breakdown_result)): ?>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="w-3 h-3 rounded-full <?php echo $category['type'] == 'income' ? 'bg-green-500' : 'bg-red-500'; ?> mr-3"></div>
                            <div>
                                <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($category['name']); ?></p>
                                                         <div>
                                <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($category['name']); ?></p>
                                <p class="text-xs text-gray-500"><?php echo $category['transaction_count']; ?> transactions</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-medium <?php echo $category['type'] == 'income' ? 'text-green-600' : 'text-red-600'; ?>">
                                <?php echo format_currency($category['total_amount']); ?>
                            </p>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
                <?php else: ?>
                <p class="text-center text-gray-500">No transactions found</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent Transactions and Assigned Accountants -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Recent Transactions -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg font-medium text-gray-900">Recent Transactions</h3>
                    <a href="../common/transactions.php?business_id=<?php echo $business['id']; ?>" 
                       class="text-blue-600 hover:text-blue-800 text-sm">View All</a>
                </div>
            </div>
            <div class="overflow-hidden">
                <?php if (mysqli_num_rows($recent_transactions_result) > 0): ?>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php while ($transaction = mysqli_fetch_assoc($recent_transactions_result)): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo format_date($transaction['transaction_date']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo htmlspecialchars($transaction['category_name']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium <?php echo $transaction['type'] == 'income' ? 'text-green-600' : 'text-red-600'; ?>">
                                <?php echo format_currency($transaction['amount']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $transaction['type'] == 'income' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                    <?php echo ucfirst($transaction['type']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="text-center py-8">
                    <p class="text-gray-500">No transactions found</p>
                    <a href="../common/transactions.php?business_id=<?php echo $business['id']; ?>" 
                       class="mt-2 inline-flex items-center text-blue-600 hover:text-blue-800">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        Add First Transaction
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Assigned Accountants -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Assigned Accountants</h3>
            </div>
            <div class="p-6">
                <?php if (mysqli_num_rows($accountants_result) > 0): ?>
                <div class="space-y-4">
                    <?php while ($accountant = mysqli_fetch_assoc($accountants_result)): ?>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="h-10 w-10 rounded-full bg-gray-300 flex items-center justify-center mr-3">
                                <span class="text-sm font-medium text-gray-700">
                                    <?php echo strtoupper(substr($accountant['first_name'], 0, 1) . substr($accountant['last_name'], 0, 1)); ?>
                                </span>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($accountant['first_name'] . ' ' . $accountant['last_name']); ?>
                                </p>
                                <p class="text-xs text-gray-500"><?php echo htmlspecialchars($accountant['email']); ?></p>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo get_permission_badge_class($accountant['permission_level']); ?>">
                                <?php echo ucfirst($accountant['permission_level']); ?>
                            </span>
                            <p class="text-xs text-gray-500 mt-1">
                                Assigned: <?php echo format_date($accountant['assigned_at']); ?>
                            </p>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
                <?php else: ?>
                <div class="text-center py-8">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No Accountants Assigned</h3>
                    <p class="mt-1 text-sm text-gray-500">Contact your administrator to assign accountants to this business.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="bg-white shadow rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Quick Actions</h3>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <a href="../common/transactions.php?business_id=<?php echo $business['id']; ?>&action=add&type=income"
                   class="bg-green-50 hover:bg-green-100 p-4 rounded-lg border border-green-200 transition-colors">
                    <div class="flex items-center">
                        <svg class="w-6 h-6 text-green-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        <span class="text-green-700 font-medium">Add Income</span>
                    </div>
                </a>

                <a href="../common/transactions.php?business_id=<?php echo $business['id']; ?>&action=add&type=expense"
                   class="bg-red-50 hover:bg-red-100 p-4 rounded-lg border border-red-200 transition-colors">
                    <div class="flex items-center">
                        <svg class="w-6 h-6 text-red-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"></path>
                        </svg>
                        <span class="text-red-700 font-medium">Add Expense</span>
                    </div>
                </a>

                <a href="../common/transactions.php?business_id=<?php echo $business['id']; ?>"
                   class="bg-blue-50 hover:bg-blue-100 p-4 rounded-lg border border-blue-200 transition-colors">
                    <div class="flex items-center">
                        <svg class="w-6 h-6 text-blue-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                        </svg>
                        <span class="text-blue-700 font-medium">View All Transactions</span>
                    </div>
                </a>

                <a href="reports.php?business_id=<?php echo $business['id']; ?>"
                   class="bg-purple-50 hover:bg-purple-100 p-4 rounded-lg border border-purple-200 transition-colors">
                    <div class="flex items-center">
                        <svg class="w-6 h-6 text-purple-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                        <span class="text-purple-700 font-medium">Generate Report</span>
                    </div>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Edit Business Modal (reuse from businesses.php) -->
<div id="editBusinessModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900">Edit Business</h3>
                <button onclick="hideEditBusinessModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <form method="POST" action="businesses.php" class="space-y-4">
                <input type="hidden" name="business_id" id="edit_business_id">

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Business Name *</label>
                    <input type="text" name="name" id="edit_business_name" required 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Business Type *</label>
                                       <select name="business_type" id="edit_business_type" required 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Select Business Type</option>
                        <option value="Retail">Retail</option>
                        <option value="Restaurant">Restaurant</option>
                        <option value="Service">Service</option>
                        <option value="Manufacturing">Manufacturing</option>
                        <option value="Technology">Technology</option>
                        <option value="Healthcare">Healthcare</option>
                        <option value="Education">Education</option>
                        <option value="Real Estate">Real Estate</option>
                        <option value="Construction">Construction</option>
                        <option value="Transportation">Transportation</option>
                        <option value="Agriculture">Agriculture</option>
                        <option value="Entertainment">Entertainment</option>
                        <option value="Consulting">Consulting</option>
                        <option value="E-commerce">E-commerce</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" id="edit_business_description" rows="3" 
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                              placeholder="Brief description of your business..."></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select name="status" id="edit_business_status" required 
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

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Edit Business Modal Functions
function showEditBusinessModal(business) {
    document.getElementById('edit_business_id').value = business.id;
    document.getElementById('edit_business_name').value = business.name;
    document.getElementById('edit_business_type').value = business.business_type;
    document.getElementById('edit_business_description').value = business.description || '';
    document.getElementById('edit_business_status').value = business.status;
    document.getElementById('editBusinessModal').classList.remove('hidden');
}

function hideEditBusinessModal() {
    document.getElementById('editBusinessModal').classList.add('hidden');
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('editBusinessModal');
    if (event.target === modal) {
        modal.classList.add('hidden');
    }
}

// Monthly Performance Chart
const monthlyData = [
    <?php 
    mysqli_data_seek($monthly_data_result, 0);
    $chart_data = [];
    while ($row = mysqli_fetch_assoc($monthly_data_result)) {
        $chart_data[] = [
            'month' => $row['month'],
            'income' => floatval($row['income']),
            'expenses' => floatval($row['expenses'])
        ];
    }
    echo json_encode($chart_data);
    ?>
];

const ctx = document.getElementById('monthlyChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: monthlyData.map(item => {
            const date = new Date(item.month + '-01');
            return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
        }),
        datasets: [{
            label: 'Income',
            data: monthlyData.map(item => item.income),
            borderColor: 'rgb(34, 197, 94)',
            backgroundColor: 'rgba(34, 197, 94, 0.1)',
            tension: 0.1
        }, {
            label: 'Expenses',
            data: monthlyData.map(item => item.expenses),
            borderColor: 'rgb(239, 68, 68)',
            backgroundColor: 'rgba(239, 68, 68, 0.1)',
            tension: 0.1
        }]
    },
    options: {
        responsive: true,
        plugins: {
            title: {
                display: true,
                text: 'Monthly Income vs Expenses'
            },
            legend: {
                position: 'top',
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value, index, values) {
                        return '$' + value.toLocaleString();
                    }
                }
            }
        }
    }
});
</script>

<?php
mysqli_close($conn);
include '../../components/footer.php';
?>
