<?php
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';
require_once '../../config/database.php';

// Require accountant role
require_role('accountant');

$page_title = 'Accountant Dashboard';
$current_user = get_logged_user();

// Get dashboard statistics for assigned businesses
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Get assigned businesses
$assigned_businesses = mysqli_query($conn, "
    SELECT COUNT(*) as total FROM user_business_assignments uba
    JOIN businesses b ON uba.business_id = b.id
    WHERE uba.user_id = {$current_user['id']} AND b.status = 'active'
");
$total_assigned_businesses = mysqli_fetch_assoc($assigned_businesses)['total'];

// Get transactions for assigned businesses
$transactions_result = mysqli_query($conn, "
    SELECT COUNT(*) as total FROM transactions t 
    JOIN user_business_assignments uba ON t.business_id = uba.business_id
    WHERE uba.user_id = {$current_user['id']} AND t.status = 'active'
");
$total_transactions = mysqli_fetch_assoc($transactions_result)['total'];

// Get total income for assigned businesses
$income_result = mysqli_query($conn, "
    SELECT SUM(t.amount) as total FROM transactions t 
    JOIN user_business_assignments uba ON t.business_id = uba.business_id
    WHERE uba.user_id = {$current_user['id']} AND t.type = 'income' AND t.status = 'active'
");
$total_income = mysqli_fetch_assoc($income_result)['total'] ?? 0;

// Get total expenses for assigned businesses
$expense_result = mysqli_query($conn, "
    SELECT SUM(t.amount) as total FROM transactions t 
    JOIN user_business_assignments uba ON t.business_id = uba.business_id
    WHERE uba.user_id = {$current_user['id']} AND t.type = 'expense' AND t.status = 'active'
");
$total_expenses = mysqli_fetch_assoc($expense_result)['total'] ?? 0;

// Get assigned businesses details
$my_assigned_businesses = mysqli_query($conn, "
    SELECT b.*, u.first_name, u.last_name, uba.permission_level
    FROM businesses b
    JOIN user_business_assignments uba ON b.id = uba.business_id
    JOIN users u ON b.owner_id = u.id
    WHERE uba.user_id = {$current_user['id']} AND b.status = 'active'
    ORDER BY b.created_at DESC 
    LIMIT 5
");

// Get recent transactions for assigned businesses
$recent_transactions = mysqli_query($conn, "
    SELECT t.*, b.name as business_name, c.name as category_name, u.first_name, u.last_name
    FROM transactions t 
    JOIN businesses b ON t.business_id = b.id 
    JOIN categories c ON t.category_id = c.id 
    JOIN users u ON b.owner_id = u.id
    JOIN user_business_assignments uba ON t.business_id = uba.business_id
    WHERE uba.user_id = {$current_user['id']} AND t.status = 'active' 
    ORDER BY t.created_at DESC 
    LIMIT 10
");

include '../../components/header.php';
include '../../components/sidebar.php';
?>

<div class="space-y-6">
    <!-- Page Header -->
    <div class="bg-white rounded-lg shadow p-6">
        <h1 class="text-2xl font-bold text-gray-900">Accountant Dashboard</h1>
        <p class="text-gray-600">Welcome back, <?php echo $current_user['first_name']; ?>! Here's an overview of your
            assigned businesses.</p>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Assigned Businesses -->
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4">
                                </path>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Assigned Businesses</dt>
                            <dd class="text-lg font-medium text-gray-900"><?php echo $total_assigned_businesses; ?></dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Transactions -->
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

        <!-- Total Income -->
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-600 rounded-md flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M7 11l5-5m0 0l5 5m-5-5v12"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Total Income</dt>
                            <dd class="text-lg font-medium text-green-600"><?php echo format_currency($total_income); ?>
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Expenses -->
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
                            <dd class="text-lg font-medium text-red-600"><?php echo format_currency($total_expenses); ?>
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Assigned Businesses and Recent Transactions -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Assigned Businesses -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Assigned Businesses</h3>
                <div class="space-y-4">
                    <?php if (mysqli_num_rows($my_assigned_businesses) > 0): ?>
                    <?php while ($business = mysqli_fetch_assoc($my_assigned_businesses)): ?>
                    <div class="border border-gray-200 rounded-lg p-4">
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <h4 class="text-md font-medium text-gray-900">
                                    <?php echo htmlspecialchars($business['name']); ?></h4>
                                <p class="text-sm text-gray-500">
                                    <?php echo htmlspecialchars($business['business_type']); ?></p>
                                <p class="text-xs text-gray-400 mt-1">Owner:
                                    <?php echo htmlspecialchars($business['first_name'] . ' ' . $business['last_name']); ?>
                                </p>
                            </div>
                            <div class="text-right">
                                <span
                                    class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo get_permission_badge_class($business['permission_level']); ?>">
                                    <?php echo ucfirst($business['permission_level']); ?>
                                </span>
                            </div>
                        </div>
                        <div class="mt-3 flex space-x-2">
                            <a href="businesses.php?view=<?php echo $business['id']; ?>"
                                class="text-blue-600 hover:text-blue-800 text-sm">View Details</a>
                            <a href="transactions.php?business_id=<?php echo $business['id']; ?>"
                                class="text-green-600 hover:text-green-800 text-sm">View Transactions</a>
                        </div>
                    </div>
                    <?php endwhile; ?>
                    <?php else: ?>
                    <div class="text-center py-8">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4">
                            </path>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">No assigned businesses</h3>
                        <p class="mt-1 text-sm text-gray-500">You haven't been assigned to any businesses yet.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent Transactions -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Recent Transactions</h3>
                <div class="space-y-4">
                    <?php if (mysqli_num_rows($recent_transactions) > 0): ?>
                    <?php while ($transaction = mysqli_fetch_assoc($recent_transactions)): ?>
                    <div class="border border-gray-200 rounded-lg p-4">
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <h4 class="text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($transaction['business_name']); ?></h4>
                                <p class="text-sm text-gray-500">
                                    <?php echo htmlspecialchars($transaction['category_name']); ?></p>
                                <?php if ($transaction['description']): ?>
                                <p class="text-xs text-gray-400 mt-1">
                                    <?php echo htmlspecialchars($transaction['description']); ?></p>
                                <?php endif; ?>
                                <p class="text-xs text-gray-400 mt-1">By:
                                    <?php echo htmlspecialchars($transaction['first_name'] . ' ' . $transaction['last_name']); ?>
                                </p>
                            </div>
                            <div class="text-right">
                                <p
                                    class="text-sm font-medium <?php echo $transaction['type'] == 'income' ? 'text-green-600' : 'text-red-600'; ?>">
                                    <?php echo ($transaction['type'] == 'income' ? '+' : '-') . format_currency($transaction['amount']); ?>
                                </p>
                                <p class="text-xs text-gray-400">
                                    <?php echo format_date($transaction['transaction_date']); ?></p>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                    <?php else: ?>
                    <div class="text-center py-8">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z">
                            </path>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">No transactions</h3>
                        <p class="mt-1 text-sm text-gray-500">No transactions found for assigned businesses.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="bg-white shadow rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Quick Actions</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <a href="transactions.php?action=create"
                    class="bg-green-50 hover:bg-green-100 p-4 rounded-lg border border-green-200 transition-colors">
                    <div class="flex items-center">
                        <svg class="w-6 h-6 text-green-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        <span class="text-green-700 font-medium">Add Transaction</span>
                    </div>
                </a>

                <a href="businesses.php"
                    class="bg-blue-50 hover:bg-blue-100 p-4 rounded-lg border border-blue-200 transition-colors">
                    <div class="flex items-center">
                        <svg class="w-6 h-6 text-blue-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4">
                            </path>
                        </svg>
                        <span class="text-blue-700 font-medium">View All Businesses</span>
                    </div>
                </a>

                <a href="reports.php"
                    class="bg-purple-50 hover:bg-purple-100 p-4 rounded-lg border border-purple-200 transition-colors">
                    <div class="flex items-center">
                        <svg class="w-6 h-6 text-purple-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z">
                            </path>
                        </svg>
                        <span class="text-purple-700 font-medium">Generate Reports</span>
                    </div>
                </a>
            </div>
        </div>
    </div>

    <!-- Financial Summary for Assigned Businesses -->
    <div class="bg-white shadow rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Financial Summary (Assigned Businesses)</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="text-center">
                    <p class="text-sm font-medium text-gray-500">Total Income</p>
                    <p class="text-2xl font-bold text-green-600"><?php echo format_currency($total_income); ?></p>
                </div>
                <div class="text-center">
                    <p class="text-sm font-medium text-gray-500">Total Expenses</p>
                    <p class="text-2xl font-bold text-red-600"><?php echo format_currency($total_expenses); ?></p>
                </div>
                <div class="text-center">
                    <p class="text-sm font-medium text-gray-500">Net Profit/Loss</p>
                    <?php 
                    $net_profit = $total_income - $total_expenses;
                    $profit_class = $net_profit >= 0 ? 'text-green-600' : 'text-red-600';
                    ?>
                    <p class="text-2xl font-bold <?php echo $profit_class; ?>">
                        <?php echo format_currency($net_profit); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Business Performance Overview -->
    <?php if ($total_assigned_businesses > 0): ?>
    <div class="bg-white shadow rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Business Performance Overview</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Business</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Owner</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Income</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Expenses</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Net</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php
                        // Reset the result pointer
                        mysqli_data_seek($my_assigned_businesses, 0);
                        while ($business = mysqli_fetch_assoc($my_assigned_businesses)):
                            // Get financial data for this business
                            $business_income_result = mysqli_query($conn, "
                                SELECT SUM(amount) as total FROM transactions 
                                WHERE business_id = {$business['id']} AND type = 'income' AND status = 'active'
                            ");
                            $business_income = mysqli_fetch_assoc($business_income_result)['total'] ?? 0;
                            
                            $business_expense_result = mysqli_query($conn, "
                                SELECT SUM(amount) as total FROM transactions 
                                WHERE business_id = {$business['id']} AND type = 'expense' AND status = 'active'
                            ");
                            $business_expenses = mysqli_fetch_assoc($business_expense_result)['total'] ?? 0;
                            
                            $business_net = $business_income - $business_expenses;
                        ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($business['name']); ?></div>
                                <div class="text-sm text-gray-500">
                                    <?php echo htmlspecialchars($business['business_type']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo htmlspecialchars($business['first_name'] . ' ' . $business['last_name']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-green-600">
                                <?php echo format_currency($business_income); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-red-600">
                                <?php echo format_currency($business_expenses); ?>
                            </td>
                            <td
                                class="px-6 py-4 whitespace-nowrap text-sm font-medium <?php echo $business_net >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                <?php echo format_currency($business_net); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <a href="businesses.php?view=<?php echo $business['id']; ?>"
                                    class="text-blue-600 hover:text-blue-900 mr-3">View</a>
                                <a href="transactions.php?business_id=<?php echo $business['id']; ?>"
                                    class="text-green-600 hover:text-green-900">Transactions</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
mysqli_close($conn);
include '../../components/footer.php';
?>