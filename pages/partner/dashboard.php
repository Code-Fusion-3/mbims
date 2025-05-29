<?php
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';
require_once '../../config/database.php';

// Require partner role
require_role('partner');

$page_title = 'Partner Dashboard';
$current_user = get_logged_user();

// Get dashboard statistics for this partner
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Get partner's businesses
$businesses_result = mysqli_query($conn, "SELECT COUNT(*) as total FROM businesses WHERE owner_id = {$current_user['id']} AND status = 'active'");
$total_businesses = mysqli_fetch_assoc($businesses_result)['total'];

// Get partner's transactions
$transactions_result = mysqli_query($conn, "
    SELECT COUNT(*) as total FROM transactions t 
    JOIN businesses b ON t.business_id = b.id 
    WHERE b.owner_id = {$current_user['id']} AND t.status = 'active'
");
$total_transactions = mysqli_fetch_assoc($transactions_result)['total'];

// Get partner's total income
$income_result = mysqli_query($conn, "
    SELECT SUM(t.amount) as total FROM transactions t 
    JOIN businesses b ON t.business_id = b.id 
    WHERE b.owner_id = {$current_user['id']} AND t.type = 'income' AND t.status = 'active'
");
$total_income = mysqli_fetch_assoc($income_result)['total'] ?? 0;

// Get partner's total expenses
$expense_result = mysqli_query($conn, "
    SELECT SUM(t.amount) as total FROM transactions t 
    JOIN businesses b ON t.business_id = b.id 
    WHERE b.owner_id = {$current_user['id']} AND t.type = 'expense' AND t.status = 'active'
");
$total_expenses = mysqli_fetch_assoc($expense_result)['total'] ?? 0;

// Get partner's businesses
$my_businesses = mysqli_query($conn, "
    SELECT * FROM businesses 
    WHERE owner_id = {$current_user['id']} AND status = 'active' 
    ORDER BY created_at DESC 
    LIMIT 5
");

// Get recent transactions for partner's businesses
$recent_transactions = mysqli_query($conn, "
    SELECT t.*, b.name as business_name, c.name as category_name 
    FROM transactions t 
    JOIN businesses b ON t.business_id = b.id 
    JOIN categories c ON t.category_id = c.id 
    WHERE b.owner_id = {$current_user['id']} AND t.status = 'active' 
    ORDER BY t.created_at DESC 
    LIMIT 10
");

include '../../components/header.php';
include '../../components/sidebar.php';
?>

<div class="space-y-6">
    <!-- Page Header -->
    <div class="bg-white rounded-lg shadow p-6">
        <h1 class="text-2xl font-bold text-gray-900">Partner Dashboard</h1>
        <p class="text-gray-600">Welcome back, <?php echo $current_user['first_name']; ?>! Here's an overview of your
            businesses.</p>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- My Businesses -->
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
                            <dt class="text-sm font-medium text-gray-500 truncate">My Businesses</dt>
                            <dd class="text-lg font-medium text-gray-900"><?php echo $total_businesses; ?></dd>
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

    <!-- My Businesses and Recent Transactions -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- My Businesses -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">My Businesses</h3>
                <div class="space-y-4">
                    <?php if (mysqli_num_rows($my_businesses) > 0): ?>
                    <?php while ($business = mysqli_fetch_assoc($my_businesses)): ?>
                    <div class="border border-gray-200 rounded-lg p-4">
                        <h4 class="text-md font-medium text-gray-900"><?php echo htmlspecialchars($business['name']); ?>
                        </h4>
                        <p class="text-sm text-gray-500"><?php echo htmlspecialchars($business['business_type']); ?></p>
                        <p class="text-xs text-gray-400 mt-2">Created:
                            <?php echo format_date($business['created_at']); ?></p>
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
                        <h3 class="mt-2 text-sm font-medium text-gray-900">No businesses</h3>
                        <p class="mt-1 text-sm text-gray-500">Get started by creating your first business.</p>
                        <div class="mt-6">
                            <a href="businesses.php?action=create"
                                class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                                Create Business
                            </a>
                        </div>
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
                        <p class="mt-1 text-sm text-gray-500">Start by adding your first transaction.</p>
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
                <a href="businesses.php?action=create"
                    class="bg-blue-50 hover:bg-blue-100 p-4 rounded-lg border border-blue-200 transition-colors">
                    <div class="flex items-center">
                        <svg class="w-6 h-6 text-blue-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        <span class="text-blue-700 font-medium">Add New Business</span>
                    </div>
                </a>

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

                <a href="reports.php"
                    class="bg-purple-50 hover:bg-purple-100 p-4 rounded-lg border border-purple-200 transition-colors">
                    <div class="flex items-center">
                        <svg class="w-6 h-6 text-purple-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z">
                            </path>
                        </svg>
                        <span class="text-purple-700 font-medium">View Reports</span>
                    </div>
                </a>
            </div>
        </div>
    </div>

    <!-- Net Profit/Loss Summary -->
    <div class="bg-white shadow rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Financial Summary</h3>
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
</div>

<?php
mysqli_close($conn);
include '../../components/footer.php';
?>