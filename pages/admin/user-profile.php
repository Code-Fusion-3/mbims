                       <?php
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';
require_once '../../config/database.php';

// Require admin role
require_role('admin');

$page_title = 'User Profile';
$current_user = get_logged_user();

// Get user ID from URL
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$user_id) {
    header('Location: users.php');
    exit();
}

// Get database connection
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Get user details
$user_sql = "SELECT * FROM users WHERE id = $user_id";
$user_result = mysqli_query($conn, $user_sql);

if (mysqli_num_rows($user_result) == 0) {
    header('Location: users.php?error=user_not_found');
    exit();
}

$user = mysqli_fetch_assoc($user_result);

// Get user's businesses (if partner)
$businesses_sql = "SELECT b.*, 
                   (SELECT COUNT(*) FROM transactions t WHERE t.business_id = b.id AND t.status = 'active') as transaction_count,
                   (SELECT SUM(amount) FROM transactions t WHERE t.business_id = b.id AND t.type = 'income' AND t.status = 'active') as total_income,
                   (SELECT SUM(amount) FROM transactions t WHERE t.business_id = b.id AND t.type = 'expense' AND t.status = 'active') as total_expenses
                   FROM businesses b 
                   WHERE b.owner_id = $user_id AND b.status = 'active'
                   ORDER BY b.created_at DESC";
$businesses_result = mysqli_query($conn, $businesses_sql);

// Get user's assigned businesses (if accountant)
$assigned_businesses_sql = "SELECT b.*, uba.permission_level, uba.assigned_at,
                           u.first_name as owner_first_name, u.last_name as owner_last_name,
                           (SELECT COUNT(*) FROM transactions t WHERE t.business_id = b.id AND t.status = 'active') as transaction_count
                           FROM user_business_assignments uba
                           JOIN businesses b ON uba.business_id = b.id
                           JOIN users u ON b.owner_id = u.id
                           WHERE uba.user_id = $user_id AND b.status = 'active'
                           ORDER BY uba.assigned_at DESC";
$assigned_businesses_result = mysqli_query($conn, $assigned_businesses_sql);

// Get user's recent transactions
$transactions_sql = "SELECT t.*, b.name as business_name, c.name as category_name
                     FROM transactions t
                     JOIN businesses b ON t.business_id = b.id
                     JOIN categories c ON t.category_id = c.id
                     WHERE t.user_id = $user_id AND t.status = 'active'
                     ORDER BY t.created_at DESC
                     LIMIT 10";
$transactions_result = mysqli_query($conn, $transactions_sql);

// Get user statistics
$stats_sql = "SELECT 
              COUNT(CASE WHEN t.type = 'income' THEN 1 END) as income_transactions,
              COUNT(CASE WHEN t.type = 'expense' THEN 1 END) as expense_transactions,
              SUM(CASE WHEN t.type = 'income' THEN t.amount ELSE 0 END) as total_income,
              SUM(CASE WHEN t.type = 'expense' THEN t.amount ELSE 0 END) as total_expenses,
              COUNT(*) as total_transactions
              FROM transactions t
              WHERE t.user_id = $user_id AND t.status = 'active'";
$stats_result = mysqli_query($conn, $stats_sql);
$stats = mysqli_fetch_assoc($stats_result);

include '../../components/header.php';
include '../../components/sidebar.php';
?>

                       <div class="space-y-6">
                           <!-- Back Button -->
                           <div>
                               <a href="users.php" class="inline-flex items-center text-blue-600 hover:text-blue-800">
                                   <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                       <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                           d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                                   </svg>
                                   Back to Users
                               </a>
                           </div>

                           <!-- User Profile Header -->
                           <div class="bg-white shadow rounded-lg overflow-hidden">
                               <div class="px-6 py-4 border-b border-gray-200">
                                   <div class="flex items-center justify-between">
                                       <div class="flex items-center">
                                           <div
                                               class="h-16 w-16 rounded-full bg-gray-300 flex items-center justify-center mr-4">
                                               <span class="text-xl font-medium text-gray-700">
                                                   <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                                               </span>
                                           </div>
                                           <div>
                                               <h1 class="text-2xl font-bold text-gray-900">
                                                   <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                               </h1>
                                               <p class="text-gray-600"><?php echo htmlspecialchars($user['email']); ?>
                                               </p>
                                               <div class="flex items-center mt-2">
                                                   <span
                                                       class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo get_role_badge_class($user['role']); ?>">
                                                       <?php echo ucfirst($user['role']); ?>
                                                   </span>
                                                   <span
                                                       class="ml-2 inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo get_status_badge_class($user['status']); ?>">
                                                       <?php echo ucfirst($user['status']); ?>
                                                   </span>
                                               </div>
                                           </div>
                                       </div>
                                       <div class="flex space-x-3">
                                           <button
                                               onclick="showEditUserModal(<?php echo htmlspecialchars(json_encode($user)); ?>)"
                                               class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                                               Edit User
                                           </button>
                                           <button
                                               onclick="showResetPasswordModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>')"
                                               class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg">
                                               Reset Password
                                           </button>
                                       </div>
                                   </div>
                               </div>

                               <!-- User Details -->
                               <div class="px-6 py-4">
                                   <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                                       <div>
                                           <label class="block text-sm font-medium text-gray-500">Member Since</label>
                                           <p class="text-sm text-gray-900">
                                               <?php echo format_date($user['created_at']); ?></p>
                                       </div>
                                       <div>
                                           <label class="block text-sm font-medium text-gray-500">Last Updated</label>
                                           <p class="text-sm text-gray-900">
                                               <?php echo format_date($user['updated_at']); ?></p>
                                       </div>
                                       <div>
                                           <label class="block text-sm font-medium text-gray-500">Total
                                               Transactions</label>
                                           <p class="text-sm text-gray-900"><?php echo $stats['total_transactions']; ?>
                                           </p>
                                       </div>
                                       <div>
                                           <label class="block text-sm font-medium text-gray-500">Net Activity</label>
                                           <p
                                               class="text-sm <?php echo ($stats['total_income'] - $stats['total_expenses']) >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                               <?php echo format_currency($stats['total_income'] - $stats['total_expenses']); ?>
                                           </p>
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
                                               <div
                                                   class="w-8 h-8 bg-green-500 rounded-md flex items-center justify-center">
                                                   <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor"
                                                       viewBox="0 0 24 24">
                                                       <path stroke-linecap="round" stroke-linejoin="round"
                                                           stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12"></path>
                                                   </svg>
                                               </div>
                                           </div>
                                           <div class="ml-5 w-0 flex-1">
                                               <dl>
                                                   <dt class="text-sm font-medium text-gray-500 truncate">Total Income
                                                   </dt>
                                                   <dd class="text-lg font-medium text-green-600">
                                                       <?php echo format_currency($stats['total_income'] ?? 0); ?></dd>
                                               </dl>
                                           </div>
                                       </div>
                                   </div>
                               </div>

                               <div class="bg-white overflow-hidden shadow rounded-lg">
                                   <div class="p-5">
                                       <div class="flex items-center">
                                           <div class="flex-shrink-0">
                                               <div
                                                   class="w-8 h-8 bg-red-500 rounded-md flex items-center justify-center">
                                                   <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor"
                                                       viewBox="0 0 24 24">
                                                       <path stroke-linecap="round" stroke-linejoin="round"
                                                           stroke-width="2" d="M17 13l-5 5m0 0l-5-5m5 5V6"></path>
                                                   </svg>
                                               </div>
                                           </div>
                                           <div class="ml-5 w-0 flex-1">
                                               <dl>
                                                   <dt class="text-sm font-medium text-gray-500 truncate">Total Expenses
                                                   </dt>
                                                   <dd class="text-lg font-medium text-red-600">
                                                       <?php echo format_currency($stats['total_expenses'] ?? 0); ?>
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
                                               <div
                                                   class="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center">
                                                   <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor"
                                                       viewBox="0 0 24 24">
                                                       <path stroke-linecap="round" stroke-linejoin="round"
                                                           stroke-width="2"
                                                           d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z">
                                                       </path>
                                                   </svg>
                                               </div>
                                           </div>
                                           <div class="ml-5 w-0 flex-1">
                                               <dl>
                                                   <dt class="text-sm font-medium text-gray-500 truncate">Income
                                                       Transactions</dt>
                                                   <dd class="text-lg font-medium text-gray-900">
                                                       <?php echo $stats['income_transactions']; ?></dd>
                                               </dl>
                                           </div>
                                       </div>
                                   </div>
                               </div>

                               <div class="bg-white overflow-hidden shadow rounded-lg">
                                   <div class="p-5">
                                       <div class="flex items-center">
                                           <div class="flex-shrink-0">
                                               <div
                                                   class="w-8 h-8 bg-yellow-500 rounded-md flex items-center justify-center">
                                                   <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor"
                                                       viewBox="0 0 24 24">
                                                       <path stroke-linecap="round" stroke-linejoin="round"
                                                           stroke-width="2"
                                                           d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z">
                                                       </path>
                                                   </svg>
                                               </div>
                                           </div>
                                           <div class="ml-5 w-0 flex-1">
                                               <dl>
                                                   <dt class="text-sm font-medium text-gray-500 truncate">Expense
                                                       Transactions</dt>
                                                   <dd class="text-lg font-medium text-gray-900">
                                                       <?php echo $stats['expense_transactions']; ?></dd>
                                               </dl>
                                           </div>
                                       </div>
                                   </div>
                               </div>
                           </div>

                           <!-- Content based on user role -->
                           <?php if ($user['role'] == 'partner'): ?>
                           <!-- Partner's Businesses -->
                           <div class="bg-white shadow rounded-lg">
                               <div class="px-6 py-4 border-b border-gray-200">
                                   <h3 class="text-lg font-medium text-gray-900">Owned Businesses</h3>
                               </div>
                               <div class="overflow-x-auto">
                                   <table class="min-w-full divide-y divide-gray-200">
                                       <thead class="bg-gray-50">
                                           <tr>
                                               <th
                                                   class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                   Business</th>


                                               <th
                                                   class=" px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                   Type
                                               </th>
                                               <th
                                                   class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                   Transactions</th>
                                               <th
                                                   class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                   Income</th>
                                               <th
                                                   class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                   Expenses</th>
                                               <th
                                                   class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                   Net
                                               </th>
                                               <th
                                                   class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                   Created</th>
                                           </tr>
                                       </thead>
                                       <tbody class="bg-white divide-y divide-gray-200">
                                           <?php if (mysqli_num_rows($businesses_result) > 0): ?>
                                           <?php while ($business = mysqli_fetch_assoc($businesses_result)): ?>
                                           <tr>
                                               <td class="px-6 py-4 whitespace-nowrap">
                                                   <div>
                                                       <div class="text-sm font-medium text-gray-900">
                                                           <?php echo htmlspecialchars($business['name']); ?>
                                                       </div>
                                                       <div class="text-sm text-gray-500">
                                                           <?php echo htmlspecialchars(truncate_text($business['description'], 50)); ?>
                                                       </div>
                                                   </div>
                                               </td>
                                               <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                   <?php echo htmlspecialchars($business['business_type']); ?>
                                               </td>
                                               <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                   <?php echo $business['transaction_count']; ?>
                                               </td>
                                               <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600">
                                                   <?php echo format_currency($business['total_income'] ?? 0); ?>
                                               </td>
                                               <td class="px-6 py-4 whitespace-nowrap text-sm text-red-600">
                                                   <?php echo format_currency($business['total_expenses'] ?? 0); ?>
                                               </td>
                                               <td
                                                   class="px-6 py-4 whitespace-nowrap text-sm <?php echo (($business['total_income'] ?? 0) - ($business['total_expenses'] ?? 0)) >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                                   <?php echo format_currency(($business['total_income'] ?? 0) - ($business['total_expenses'] ?? 0)); ?>
                                               </td>
                                               <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                   <?php echo format_date($business['created_at']); ?>
                                               </td>
                                           </tr>
                                           <?php endwhile; ?>
                                           <?php else: ?>
                                           <tr>
                                               <td colspan="7" class="px-6 py-4 text-center text-gray-500">No businesses
                                                   found</td>
                                           </tr>
                                           <?php endif; ?>
                                       </tbody>
                                   </table>
                               </div>
                           </div>
                           <?php endif; ?>

                           <?php if ($user['role'] == 'accountant'): ?>
                           <!-- Accountant's Assigned Businesses -->
                           <div class="bg-white shadow rounded-lg">
                               <div class="px-6 py-4 border-b border-gray-200">
                                   <h3 class="text-lg font-medium text-gray-900">Assigned Businesses</h3>
                               </div>
                               <div class="overflow-x-auto">
                                   <table class="min-w-full divide-y divide-gray-200">
                                       <thead class="bg-gray-50">
                                           <tr>
                                               <th
                                                   class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                   Business</th>
                                               <th
                                                   class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                   Owner</th>
                                               <th
                                                   class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                   Permission</th>
                                               <th
                                                   class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                   Transactions</th>
                                               <th
                                                   class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                   Assigned</th>
                                           </tr>
                                       </thead>
                                       <tbody class="bg-white divide-y divide-gray-200">
                                           <?php if (mysqli_num_rows($assigned_businesses_result) > 0): ?>
                                           <?php while ($business = mysqli_fetch_assoc($assigned_businesses_result)): ?>
                                           <tr>
                                               <td class="px-6 py-4 whitespace-nowrap">
                                                   <div>
                                                       <div class="text-sm font-medium text-gray-900">
                                                           <?php echo htmlspecialchars($business['name']); ?>
                                                       </div>
                                                       <div class="text-sm text-gray-500">
                                                           <?php echo htmlspecialchars($business['business_type']); ?>
                                                       </div>
                                                   </div>
                                               </td>
                                               <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                   <?php echo htmlspecialchars($business['owner_first_name'] . ' ' . $business['owner_last_name']); ?>
                                               </td>
                                               <td class="px-6 py-4 whitespace-nowrap">
                                                   <span
                                                       class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo get_permission_badge_class($business['permission_level']); ?>">
                                                       <?php echo ucfirst($business['permission_level']); ?>
                                                   </span>
                                               </td>
                                               <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                   <?php echo $business['transaction_count']; ?>
                                               </td>
                                               <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                   <?php echo format_date($business['assigned_at']); ?>
                                               </td>
                                           </tr>
                                           <?php endwhile; ?>
                                           <?php else: ?>
                                           <tr>
                                               <td colspan="5" class="px-6 py-4 text-center text-gray-500">No assigned
                                                   businesses found</td>
                                           </tr>
                                           <?php endif; ?>
                                       </tbody>
                                   </table>
                               </div>
                           </div>
                           <?php endif; ?>

                           <!-- Recent Transactions -->
                           <div class="bg-white shadow rounded-lg">
                               <div class="px-6 py-4 border-b border-gray-200">
                                   <h3 class="text-lg font-medium text-gray-900">Recent Transactions</h3>
                               </div>
                               <div class="overflow-x-auto">
                                   <table class="min-w-full divide-y divide-gray-200">
                                       <thead class="bg-gray-50">
                                           <tr>
                                               <th
                                                   class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                   Business</th>
                                               <th
                                                   class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                   Category</th>
                                               <th
                                                   class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                   Amount</th>
                                               <th
                                                   class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                   Type</th>
                                               <th
                                                   class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                   Date</th>
                                               <th
                                                   class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                   Description</th>
                                           </tr>
                                       </thead>
                                       <tbody class="bg-white divide-y divide-gray-200">
                                           <?php if (mysqli_num_rows($transactions_result) > 0): ?>
                                           <?php while ($transaction = mysqli_fetch_assoc($transactions_result)): ?>
                                           <tr>
                                               <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                   <?php echo htmlspecialchars($transaction['business_name']); ?>
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
                                               <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                   <?php echo format_date($transaction['transaction_date']); ?>
                                               </td>
                                               <td class="px-6 py-4 text-sm text-gray-500">
                                                   <?php echo htmlspecialchars(truncate_text($transaction['description'], 50)); ?>
                                               </td>
                                           </tr>
                                           <?php endwhile; ?>
                                           <?php else: ?>
                                           <tr>
                                               <td colspan="6" class="px-6 py-4 text-center text-gray-500">No
                                                   transactions
                                                   found</td>
                                           </tr>
                                           <?php endif; ?>
                                       </tbody>
                                   </table>
                               </div>
                           </div>
                       </div>

                       <!-- Edit User Modal (reuse from users.php) -->
                       <div id="editUserModal"
                           class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
                           <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                               <div class="mt-3">
                                   <div class="flex items-center justify-between mb-4">
                                       <h3 class="text-lg font-medium text-gray-900">Edit User</h3>
                                       <button onclick="hideEditUserModal()" class="text-gray-400 hover:text-gray-600">
                                           <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                               <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                   d="M6 18L18 6M6 6l12 12"></path>
                                           </svg>
                                       </button>
                                   </div>

                                   <form method="POST" action="users.php" class="space-y-4">
                                       <input type="hidden" name="user_id" id="edit_user_id">

                                       <div>
                                           <label class="block text-sm font-medium text-gray-700 mb-1">First
                                               Name</label>
                                           <input type="text" name="first_name" id="edit_first_name" required
                                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                       </div>

                                       <div>
                                           <label class="block text-sm font-medium text-gray-700 mb-1">Last
                                               Name</label>
                                           <input type="text" name="last_name" id="edit_last_name" required
                                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                       </div>

                                       <div>
                                           <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                           <input type="email" name="email" id="edit_email" required
                                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                       </div>

                                       <div>
                                           <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                                           <select name="role" id="edit_role" required
                                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                               <option value="admin">Admin</option>
                                               <option value="partner">Partner</option>
                                               <option value="accountant">Accountant</option>
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
                                           <button type="button" onclick="hideEditUserModal()"
                                               class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300">
                                               Cancel
                                           </button>
                                           <button type="submit" name="update_user"
                                               class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700">
                                               Update User
                                           </button>
                                       </div>
                                   </form>
                               </div>
                           </div>
                       </div>

                       <!-- Reset Password Modal (reuse from users.php) -->
                       <div id="resetPasswordModal"
                           class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
                           <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                               <div class="mt-3">
                                   <div class="flex items-center justify-between mb-4">
                                       <h3 class="text-lg font-medium text-gray-900">Reset Password</h3>
                                       <button onclick="hideResetPasswordModal()"
                                           class="text-gray-400 hover:text-gray-600">
                                           <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                               <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                   d="M6 18L18 6M6 6l12 12">
                                               </path>
                                           </svg>
                                       </button>
                                   </div>

                                   <p class="text-sm text-gray-600 mb-4">Reset password for: <span id="reset_user_name"
                                           class="font-medium"></span></p>

                                   <form method="POST" action="users.php" class="space-y-4">
                                       <input type="hidden" name="user_id" id="reset_user_id">

                                       <div>
                                           <label class="block text-sm font-medium text-gray-700 mb-1">New
                                               Password</label>
                                           <input type="password" name="new_password" required minlength="6"
                                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                           <p class="text-xs text-gray-500 mt-1">Minimum 6 characters</p>
                                       </div>

                                       <div class="flex justify-end space-x-3 pt-4">
                                           <button type="button" onclick="hideResetPasswordModal()"
                                               class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300">
                                               Cancel
                                           </button>
                                           <button type="submit" name="reset_password"
                                               class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-md hover:bg-red-700">
                                               Reset Password
                                           </button>
                                       </div>
                                   </form>
                               </div>
                           </div>
                       </div>

                       <script>
// Edit User Modal Functions
function showEditUserModal(user) {
    document.getElementById('edit_user_id').value = user.id;
    document.getElementById('edit_first_name').value = user.first_name;
    document.getElementById('edit_last_name').value = user.last_name;
    document.getElementById('edit_email').value = user.email;
    document.getElementById('edit_role').value = user.role;
    document.getElementById('edit_status').value = user.status;
    document.getElementById('editUserModal').classList.remove('hidden');
}

function hideEditUserModal() {
    document.getElementById('editUserModal').classList.add('hidden');
}

// Reset Password Modal Functions
function showResetPasswordModal(userId, userName) {
    document.getElementById('reset_user_id').value = userId;
    document.getElementById('reset_user_name').textContent = userName;
    document.getElementById('resetPasswordModal').classList.remove('hidden');
}

function hideResetPasswordModal() {
    document.getElementById('resetPasswordModal').classList.add('hidden');
}

// Close modals when clicking outside
window.onclick = function(event) {
    const modals = ['editUserModal', 'resetPasswordModal'];
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