<?php
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';
require_once '../../config/database.php';

// Get current user data
$current_user = get_logged_user();
// Require admin role
require_role('admin');

$page_title = 'Admin Dashboard';

// Get dashboard statistics
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Get total users
$users_result = mysqli_query($conn, "SELECT COUNT(*) as total FROM users WHERE status = 'active'");
$total_users = mysqli_fetch_assoc($users_result)['total'];

// Get total businesses
$businesses_result = mysqli_query($conn, "SELECT COUNT(*) as total FROM businesses WHERE status = 'active'");
$total_businesses = mysqli_fetch_assoc($businesses_result)['total'];

// Get total transactions
$transactions_result = mysqli_query($conn, "SELECT COUNT(*) as total FROM transactions WHERE status = 'active'");
$total_transactions = mysqli_fetch_assoc($transactions_result)['total'];

// Get total income
$income_result = mysqli_query($conn, "SELECT SUM(amount) as total FROM transactions WHERE type = 'income' AND status = 'active'");
$total_income = mysqli_fetch_assoc($income_result)['total'] ?? 0;

// Get total expenses
$expense_result = mysqli_query($conn, "SELECT SUM(amount) as total FROM transactions WHERE type = 'expense' AND status = 'active'");
$total_expenses = mysqli_fetch_assoc($expense_result)['total'] ?? 0;

// Get recent transactions
$recent_transactions = mysqli_query($conn, "
    SELECT t.*, b.name as business_name, c.name as category_name, u.first_name, u.last_name 
    FROM transactions t 
    JOIN businesses b ON t.business_id = b.id 
    JOIN categories c ON t.category_id = c.id 
    JOIN users u ON t.user_id = u.id 
    WHERE t.status = 'active' 
    ORDER BY t.created_at DESC 
    LIMIT 10
");

// Get recent users
$recent_users = mysqli_query($conn, "
    SELECT * FROM users 
    WHERE status = 'active' 
    ORDER BY created_at DESC 
    LIMIT 5
");

include '../../components/header.php';
include '../../components/sidebar.php';
?>

<div class="space-y-6">
    <!-- Page Header -->
    <div class="bg-white rounded-lg shadow p-6">
        <h1 class="text-2xl font-bold text-gray-900">Admin Dashboard</h1>
        <p class="text-gray-600">Welcome back, <?php echo $current_user['first_name']; ?>! Here's what's happening with
            your system.</p>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6">
        <!-- Total Users -->
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z">
                                </path>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Total Users</dt>
                            <dd class="text-lg font-medium text-gray-900"><?php echo $total_users; ?></dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Businesses -->
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-500 rounded-md flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4">
                                </path>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Total Businesses</dt>
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

    <!-- Charts and Recent Activity -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Recent Transactions -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Recent Transactions</h3>
                <div class="overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Business</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Amount</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Type</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Date</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (mysqli_num_rows($recent_transactions) > 0): ?>
                            <?php while ($transaction = mysqli_fetch_assoc($recent_transactions)): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo htmlspecialchars($transaction['business_name']); ?>
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
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo format_date($transaction['transaction_date']); ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                            <?php else: ?>
                            <tr>
                                <td colspan="4" class="px-6 py-4 text-center text-gray-500">No transactions found</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Recent Users -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Recent Users</h3>
                <div class="space-y-4">
                    <?php if (mysqli_num_rows($recent_users) > 0): ?>
                    <?php while ($user = mysqli_fetch_assoc($recent_users)): ?>
                    <div class="flex items-center space-x-4">
                        <div class="flex-shrink-0">
                            <div class="w-10 h-10 bg-gray-300 rounded-full flex items-center justify-center">
                                <span class="text-sm font-medium text-gray-700">
                                    <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                                </span>
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900">
                                <a href="user-profile.php?id=<?php echo $user['id']; ?>"
                                    class="hover:text-blue-600 hover:underline">
                                    <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                </a>
                            </p>
                            <p class="text-sm text-gray-500"><?php echo htmlspecialchars($user['email']); ?></p>
                        </div>

                        <div class="flex-shrink-0">
                            <span
                                class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo get_role_badge_class($user['role']); ?>">
                                <?php echo ucfirst($user['role']); ?>
                            </span>
                        </div>
                    </div>
                    <?php endwhile; ?>
                    <?php else: ?>
                    <p class="text-center text-gray-500">No users found</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="bg-white shadow rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Quick Actions</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <a href="users.php"
                    class="bg-blue-50 hover:bg-blue-100 p-4 rounded-lg border border-blue-200 transition-colors">
                    <div class="flex items-center">
                        <svg class="w-6 h-6 text-blue-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        <span class="text-blue-700 font-medium">Add New User</span>
                    </div>
                </a>

                <a href="businesses.php"
                    class="bg-green-50 hover:bg-green-100 p-4 rounded-lg border border-green-200 transition-colors">
                    <div class="flex items-center">
                        <svg class="w-6 h-6 text-green-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4">
                            </path>
                        </svg>
                        <span class="text-green-700 font-medium">Manage Businesses</span>
                    </div>
                </a>

                <a href="categories.php"
                    class="bg-yellow-50 hover:bg-yellow-100 p-4 rounded-lg border border-yellow-200 transition-colors">
                    <div class="flex items-center">
                        <svg class="w-6 h-6 text-yellow-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z">
                            </path>
                        </svg>
                        <span class="text-yellow-700 font-medium">Manage Categories</span>
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
</div>

<?php
mysqli_close($conn);
include '../../components/footer.php';
?>