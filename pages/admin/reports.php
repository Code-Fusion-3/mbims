<?php
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';
require_once '../../config/database.php';

// Require admin role
require_role('admin');

$page_title = 'Reports & Analytics';
$current_user = get_logged_user();

// Get database connection
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Get filter parameters
$date_from = isset($_GET['date_from']) ? sanitize_input($_GET['date_from']) : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? sanitize_input($_GET['date_to']) : date('Y-m-t');
$business_filter = isset($_GET['business']) ? (int)$_GET['business'] : 0;
$user_filter = isset($_GET['user']) ? (int)$_GET['user'] : 0;
$report_type = isset($_GET['report_type']) ? sanitize_input($_GET['report_type']) : 'overview';

// Build WHERE conditions
$where_conditions = ["t.status = 'active'"];

if (!empty($date_from)) {
    $where_conditions[] = "t.transaction_date >= '$date_from'";
}

if (!empty($date_to)) {
    $where_conditions[] = "t.transaction_date <= '$date_to'";
}

if ($business_filter > 0) {
    $where_conditions[] = "t.business_id = $business_filter";
}

if ($user_filter > 0) {
    $where_conditions[] = "t.user_id = $user_filter";
}

$where_clause = implode(' AND ', $where_conditions);

// Get overall statistics
$stats_sql = "SELECT 
              COUNT(*) as total_transactions,
              SUM(CASE WHEN t.type = 'income' THEN t.amount ELSE 0 END) as total_income,
              SUM(CASE WHEN t.type = 'expense' THEN t.amount ELSE 0 END) as total_expenses,
              COUNT(CASE WHEN t.type = 'income' THEN 1 END) as income_transactions,
              COUNT(CASE WHEN t.type = 'expense' THEN 1 END) as expense_transactions,
              COUNT(DISTINCT t.business_id) as active_businesses,
              COUNT(DISTINCT t.user_id) as active_users
              FROM transactions t
              WHERE $where_clause";

$stats_result = mysqli_query($conn, $stats_sql);
$stats = mysqli_fetch_assoc($stats_result);

// Calculate net profit/loss
$net_amount = $stats['total_income'] - $stats['total_expenses'];

// Get monthly trends (last 12 months)
$monthly_sql = "SELECT 
                DATE_FORMAT(t.transaction_date, '%Y-%m') as month,
                DATE_FORMAT(t.transaction_date, '%M %Y') as month_name,
                SUM(CASE WHEN t.type = 'income' THEN t.amount ELSE 0 END) as income,
                SUM(CASE WHEN t.type = 'expense' THEN t.amount ELSE 0 END) as expenses,
                COUNT(*) as transaction_count
                FROM transactions t
                WHERE t.status = 'active' 
                AND t.transaction_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                " . ($business_filter > 0 ? "AND t.business_id = $business_filter" : "") . "
                " . ($user_filter > 0 ? "AND t.user_id = $user_filter" : "") . "
                GROUP BY DATE_FORMAT(t.transaction_date, '%Y-%m')
                ORDER BY month DESC
                LIMIT 12";

$monthly_result = mysqli_query($conn, $monthly_sql);
$monthly_data = [];
while ($row = mysqli_fetch_assoc($monthly_result)) {
    $monthly_data[] = $row;
}

// Get top businesses by revenue
$top_businesses_sql = "SELECT 
                       b.name as business_name,
                       b.business_type,
                       SUM(CASE WHEN t.type = 'income' THEN t.amount ELSE 0 END) as total_income,
                       SUM(CASE WHEN t.type = 'expense' THEN t.amount ELSE 0 END) as total_expenses,
                       COUNT(*) as transaction_count
                       FROM transactions t
                       JOIN businesses b ON t.business_id = b.id
                       WHERE $where_clause
                       GROUP BY t.business_id, b.name, b.business_type
                       ORDER BY total_income DESC
                       LIMIT 10";

$top_businesses_result = mysqli_query($conn, $top_businesses_sql);

// Get category breakdown
$category_sql = "SELECT 
                 c.name as category_name,
                 c.type as category_type,
                 SUM(t.amount) as total_amount,
                 COUNT(*) as transaction_count,
                 ROUND((SUM(t.amount) / (SELECT SUM(amount) FROM transactions WHERE type = c.type AND status = 'active' AND $where_clause)) * 100, 2) as percentage
                 FROM transactions t
                 JOIN categories c ON t.category_id = c.id
                 WHERE $where_clause
                 GROUP BY c.id, c.name, c.type
                 ORDER BY c.type, total_amount DESC";

$category_result = mysqli_query($conn, $category_sql);

// Get user activity
$user_activity_sql = "SELECT 
                      u.first_name,
                      u.last_name,
                      u.role,
                      COUNT(*) as transaction_count,
                      SUM(CASE WHEN t.type = 'income' THEN t.amount ELSE 0 END) as total_income,
                      SUM(CASE WHEN t.type = 'expense' THEN t.amount ELSE 0 END) as total_expenses,
                      MAX(t.created_at) as last_activity
                      FROM transactions t
                      JOIN users u ON t.user_id = u.id
                      WHERE $where_clause
                      GROUP BY u.id, u.first_name, u.last_name, u.role
                      ORDER BY transaction_count DESC
                      LIMIT 10";

$user_activity_result = mysqli_query($conn, $user_activity_sql);

// Get businesses for filter dropdown
$businesses_result = mysqli_query($conn, "SELECT id, name FROM businesses WHERE status = 'active' ORDER BY name");

// Get users for filter dropdown
$users_result = mysqli_query($conn, "SELECT id, first_name, last_name FROM users WHERE status = 'active' ORDER BY first_name, last_name");

include '../../components/header.php';
include '../../components/sidebar.php';
?>

<div class="space-y-6">
    <!-- Page Header -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Reports & Analytics</h1>
                <p class="text-gray-600">Comprehensive business insights and financial reports</p>
            </div>
            <div class="flex space-x-3">
                <button onclick="exportReport('pdf')" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg">
                    Export PDF
                </button>
                <button onclick="exportReport('excel')" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg">
                    Export Excel
                </button>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white shadow rounded-lg p-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Report Filters</h3>
        <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">From Date</label>
                <input type="date" name="date_from" value="<?php echo $date_from; ?>" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">To Date</label>
                <input type="date" name="date_to" value="<?php echo $date_to; ?>" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Business</label>
                <select name="business" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">All Businesses</option>
                    <?php while ($business = mysqli_fetch_assoc($businesses_result)): ?>
                    <option value="<?php echo $business['id']; ?>" <?php echo $business_filter == $business['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($business['name']); ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">User</label>
                <select name="user" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">All Users</option>
                    <?php while ($user = mysqli_fetch_assoc($users_result)): ?>
                    <option value="<?php echo $user['id']; ?>" <?php echo $user_filter == $user['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Report Type</label>
                <select name="report_type" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="overview" <?php echo $report_type == 'overview' ? 'selected' : ''; ?>>Overview</option>
                    <option value="detailed" <?php echo $report_type == 'detailed' ? 'selected' : ''; ?>>Detailed</option>
                    <option value="trends" <?php echo $report_type == 'trends' ? 'selected' : ''; ?>>Trends</option>
                    <option value="comparison" <?php echo $report_type == 'comparison' ? 'selected' : ''; ?>>Comparison</option>
                </select>
            </div>

            <div class="flex items-end">
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                    Generate Report
                </button>
            </div>
        </form>
    </div>

    <!-- Key Metrics -->
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
                        <div class="w-8 h-8 <?php echo $net_amount >= 0 ? 'bg-green-500' : 'bg-red-500'; ?> rounded-md flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Net Profit/Loss</dt>
                            <dd class="text-lg font-medium <?php echo $net_amount >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                <?php echo format_currency($net_amount); ?>
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
                        <div class="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Total Transactions</dt>
                            <dd class="text-lg font-medium text-gray-900"><?php echo number_format($stats['total_transactions']); ?></dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Monthly Trends Chart -->
        <div class="bg-white shadow rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Monthly Trends (Last 12 Months)</h3>
            <div class="h-80">
                <canvas id="monthlyTrendsChart"></canvas>
            </div>
        </div>

        <!-- Income vs Expenses Pie Chart -->
        <div class="bg-white shadow rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Income vs Expenses</h3>
            <div class="h-80">
                <canvas id="incomeExpenseChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Top Businesses Performance -->
    <div class="bg-white shadow rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Top Performing Businesses</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Business</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Income</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expenses</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Net Profit</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transactions</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Margin</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (mysqli_num_rows($top_businesses_result) > 0): ?>
                        <?php while ($business = mysqli_fetch_assoc($top_businesses_result)): ?>
                        <?php 
                        $net_profit = $business['total_income'] - $business['total_expenses'];
                        $margin = $business['total_income'] > 0 ? ($net_profit / $business['total_income']) * 100 : 0;
                        ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($business['business_name']); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo htmlspecialchars($business['business_type']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600">
                                <?php echo format_currency($business['total_income']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-red-600">
                                <?php echo format_currency($business['total_expenses']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm <?php echo $net_profit >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                <?php echo format_currency($net_profit); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo number_format($business['transaction_count']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm <?php echo $margin >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                <?php echo number_format($margin, 1); ?>%
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="px-6 py-4 text-center text-gray-500">No business data found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Category Breakdown -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Income Categories -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Income Categories</h3>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    <?php 
                    mysqli_data_seek($category_result, 0);
                    while ($category = mysqli_fetch_assoc($category_result)): 
                        if ($category['category_type'] == 'income'):
                    ?>
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($category['category_name']); ?>
                                </span>
                                <span class="text-sm text-gray-500">
                                    <?php echo $category['percentage']; ?>%
                                </span>
                            </div>
                            <div class="mt-1 w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-green-500 h-2 rounded-full" style="width: <?php echo $category['percentage']; ?>%"></div>
                            </div>
                            <div class="flex justify-between mt-1">
                                <span class="text-xs text-gray-500">
                                    <?php echo $category['transaction_count']; ?> transactions
                                </span>
                                <span class="text-xs font-medium text-green-600">
                                    <?php echo format_currency($category['total_amount']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <?php 
                        endif;
                    endwhile; 
                    ?>
                </div>
            </div>
        </div>

        <!-- Expense Categories -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Expense Categories</h3>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    <?php 
                    mysqli_data_seek($category_result, 0);
                    while ($category = mysqli_fetch_assoc($category_result)): 
                        if ($category['category_type'] == 'expense'):
                    ?>
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($category['category_name']); ?>
                                </span>
                                <span class="text-sm text-gray-500">
                                    <?php echo $category['percentage']; ?>%
                                </span>
                            </div>
                            <div class="mt-1 w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-red-500 h-2 rounded-full" style="width: <?php echo $category['percentage']; ?>%"></div>
                            </div>
                            <div class="flex justify-between mt-1">
                                <span class="text-xs text-gray-500">
                                    <?php echo $category['transaction_count']; ?> transactions
                                </span>
                                <span class="text-xs font-medium text-red-600">
                                    <?php echo format_currency($category['total_amount']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <?php 
                        endif;
                    endwhile; 
                    ?>
                </div>
            </div>
        </div>
    </div>

    <!-- User Activity -->
    <div class="bg-white shadow rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">User Activity</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transactions</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Income</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expenses</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Activity</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (mysqli_num_rows($user_activity_result) > 0): ?>
                        <?php while ($user = mysqli_fetch_assoc($user_activity_result)): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo get_role_badge_class($user['role']); ?>">
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo number_format($user['transaction_count']); ?>
                            </td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600">
                                <?php echo format_currency($user['total_income']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-red-600">
                                <?php echo format_currency($user['total_expenses']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo format_datetime($user['last_activity']); ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="px-6 py-4 text-center text-gray-500">No user activity found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Additional Analytics based on Report Type -->
    <?php if ($report_type == 'detailed'): ?>
    <!-- Detailed Report Section -->
    <div class="bg-white shadow rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Detailed Transaction Analysis</h3>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="text-center">
                    <div class="text-2xl font-bold text-blue-600"><?php echo number_format($stats['active_businesses']); ?></div>
                    <div class="text-sm text-gray-500">Active Businesses</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-purple-600"><?php echo number_format($stats['active_users']); ?></div>
                    <div class="text-sm text-gray-500">Active Users</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-orange-600">
                        <?php echo $stats['total_transactions'] > 0 ? number_format($stats['total_income'] / $stats['income_transactions'], 2) : '0'; ?>
                    </div>
                    <div class="text-sm text-gray-500">Avg Income per Transaction</div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($report_type == 'trends'): ?>
    <!-- Trends Analysis -->
    <div class="bg-white shadow rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Trend Analysis</h3>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div>
                    <h4 class="text-md font-medium text-gray-900 mb-4">Growth Trends</h4>
                    <div class="space-y-3">
                        <?php 
                        $monthly_data_reversed = array_reverse($monthly_data);
                        for ($i = 1; $i < count($monthly_data_reversed); $i++):
                            $current = $monthly_data_reversed[$i];
                            $previous = $monthly_data_reversed[$i-1];
                            $income_growth = $previous['income'] > 0 ? (($current['income'] - $previous['income']) / $previous['income']) * 100 : 0;
                            $expense_growth = $previous['expenses'] > 0 ? (($current['expenses'] - $previous['expenses']) / $previous['expenses']) * 100 : 0;
                        ?>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600"><?php echo $current['month_name']; ?></span>
                            <div class="flex space-x-4">
                                <span class="text-sm <?php echo $income_growth >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                    Income: <?php echo number_format($income_growth, 1); ?>%
                                </span>
                                <span class="text-sm <?php echo $expense_growth <= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                    Expenses: <?php echo number_format($expense_growth, 1); ?>%
                                </span>
                            </div>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>
                <div>
                    <h4 class="text-md font-medium text-gray-900 mb-4">Performance Indicators</h4>
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600">Profit Margin</span>
                            <span class="text-sm font-medium <?php echo $net_amount >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                <?php echo $stats['total_income'] > 0 ? number_format(($net_amount / $stats['total_income']) * 100, 1) : '0'; ?>%
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600">Expense Ratio</span>
                            <span class="text-sm font-medium text-gray-900">
                                <?php echo $stats['total_income'] > 0 ? number_format(($stats['total_expenses'] / $stats['total_income']) * 100, 1) : '0'; ?>%
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600">Avg Transaction Value</span>
                            <span class="text-sm font-medium text-gray-900">
                                <?php echo format_currency(($stats['total_income'] + $stats['total_expenses']) / max($stats['total_transactions'], 1)); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Chart.js Scripts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Monthly Trends Chart
const monthlyCtx = document.getElementById('monthlyTrendsChart').getContext('2d');
const monthlyData = <?php echo json_encode(array_reverse($monthly_data)); ?>;

new Chart(monthlyCtx, {
    type: 'line',
    data: {
        labels: monthlyData.map(item => item.month_name),
        datasets: [{
            label: 'Income',
            data: monthlyData.map(item => parseFloat(item.income)),
            borderColor: 'rgb(34, 197, 94)',
            backgroundColor: 'rgba(34, 197, 94, 0.1)',
            tension: 0.1
        }, {
            label: 'Expenses',
            data: monthlyData.map(item => parseFloat(item.expenses)),
            borderColor: 'rgb(239, 68, 68)',
            backgroundColor: 'rgba(239, 68, 68, 0.1)',
            tension: 0.1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '$' + value.toLocaleString();
                    }
                }
            }
        },
        plugins: {
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.dataset.label + ': $' + context.parsed.y.toLocaleString();
                    }
                }
            }
        }
    }
});

// Income vs Expenses Pie Chart
const pieCtx = document.getElementById('incomeExpenseChart').getContext('2d');
new Chart(pieCtx, {
    type: 'doughnut',
    data: {
        labels: ['Income', 'Expenses'],
        datasets: [{
            data: [<?php echo $stats['total_income']; ?>, <?php echo $stats['total_expenses']; ?>],
            backgroundColor: ['rgb(34, 197, 94)', 'rgb(239, 68, 68)'],
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = ((context.parsed / total) * 100).toFixed(1);
                        return context.label + ': $' + context.parsed.toLocaleString() + ' (' + percentage + '%)';
                    }
                }
            }
        }
    }
});

// Export functions
function exportReport(format) {
    const params = new URLSearchParams(window.location.search);
    params.set('export', format);
    window.open('export-report.php?' + params.toString(), '_blank');
}

// Print report
function printReport() {
    window.print();
}
</script>

<!-- Print Styles -->
<style media="print">
    .no-print {
        display: none !important;
    }
    
    .print-break {
        page-break-before: always;
    }
    
    body {
        font-size: 12px;
    }
    
    .bg-white {
        background: white !important;
    }
    
    .shadow {
        box-shadow: none !important;
    }
</style>

<?php
mysqli_close($conn);
include '../../components/footer.php';
?>
