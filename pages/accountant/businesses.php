<?php
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';
require_once '../../config/database.php';

// Require accountant role
require_role('accountant');

$page_title = 'Assigned Businesses';
$current_user = get_logged_user();

// Get database connection
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Get filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$business_type = isset($_GET['business_type']) ? $_GET['business_type'] : '';
$permission_level = isset($_GET['permission_level']) ? $_GET['permission_level'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';

// Build WHERE clause for filters
$where_conditions = ["uba.user_id = {$current_user['id']}"];

if (!empty($search)) {
    $search_escaped = mysqli_real_escape_string($conn, $search);
    $where_conditions[] = "(b.name LIKE '%$search_escaped%' OR b.description LIKE '%$search_escaped%' OR CONCAT(u.first_name, ' ', u.last_name) LIKE '%$search_escaped%')";
}

if (!empty($business_type)) {
    $business_type_escaped = mysqli_real_escape_string($conn, $business_type);
    $where_conditions[] = "b.business_type = '$business_type_escaped'";
}

if (!empty($permission_level)) {
    $permission_escaped = mysqli_real_escape_string($conn, $permission_level);
    $where_conditions[] = "uba.permission_level = '$permission_escaped'";
}

if (!empty($status)) {
    $status_escaped = mysqli_real_escape_string($conn, $status);
    $where_conditions[] = "b.status = '$status_escaped'";
}

$where_clause = implode(' AND ', $where_conditions);

// Get assigned businesses with statistics
$businesses_sql = "SELECT b.*, 
                   uba.permission_level, 
                   uba.assigned_at,
                   u.first_name as owner_first_name, 
                   u.last_name as owner_last_name,
                   u.email as owner_email,
                   (SELECT COUNT(*) FROM transactions t WHERE t.business_id = b.id AND t.status = 'active') as transaction_count,
                   (SELECT SUM(amount) FROM transactions t WHERE t.business_id = b.id AND t.type = 'income' AND t.status = 'active') as total_income,
                   (SELECT SUM(amount) FROM transactions t WHERE t.business_id = b.id AND t.type = 'expense' AND t.status = 'active') as total_expenses,
                   (SELECT COUNT(*) FROM transactions t WHERE t.business_id = b.id AND t.user_id = {$current_user['id']} AND t.status = 'active') as my_transactions
                   FROM user_business_assignments uba
                   JOIN businesses b ON uba.business_id = b.id
                   JOIN users u ON b.owner_id = u.id
                   WHERE $where_clause
                   ORDER BY uba.assigned_at DESC";

$businesses_result = mysqli_query($conn, $businesses_sql);

// Get summary statistics
$stats_sql = "SELECT 
              COUNT(DISTINCT b.id) as assigned_businesses,
              COUNT(DISTINCT CASE WHEN b.status = 'active' THEN b.id END) as active_businesses,
              SUM(CASE WHEN uba.permission_level = 'admin' THEN 1 ELSE 0 END) as admin_permissions,
              SUM(CASE WHEN uba.permission_level = 'write' THEN 1 ELSE 0 END) as write_permissions,
              SUM(CASE WHEN uba.permission_level = 'read' THEN 1 ELSE 0 END) as read_permissions
              FROM user_business_assignments uba
              JOIN businesses b ON uba.business_id = b.id
              WHERE uba.user_id = {$current_user['id']}";
$stats_result = mysqli_query($conn, $stats_sql);
$stats = mysqli_fetch_assoc($stats_result);

// Get business types for filter
$business_types_sql = "SELECT DISTINCT b.business_type 
                      FROM user_business_assignments uba
                      JOIN businesses b ON uba.business_id = b.id
                      WHERE uba.user_id = {$current_user['id']} AND b.business_type IS NOT NULL AND b.business_type != ''
                      ORDER BY b.business_type";
$business_types_result = mysqli_query($conn, $business_types_sql);

include '../../components/header.php';
include '../../components/sidebar.php';
?>

<div class="space-y-6">
    <!-- Page Header -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Assigned Businesses</h1>
                <p class="text-gray-600">Manage businesses you have been assigned to work with</p>
            </div>
            <div class="text-right">
                <p class="text-sm text-gray-500">Total Assigned</p>
                <p class="text-2xl font-bold text-blue-600"><?php echo $stats['assigned_businesses']; ?></p>
            </div>
        </div>
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
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Total Assigned</dt>
                            <dd class="text-lg font-medium text-gray-900"><?php echo $stats['assigned_businesses']; ?></dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <!-- Active Businesses -->
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-500 rounded-md flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Active Businesses</dt>
                            <dd class="text-lg font-medium text-gray-900"><?php echo $stats['active_businesses']; ?></dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <!-- Admin Permissions -->
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-purple-500 rounded-md flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Admin Access</dt>
                            <dd class="text-lg font-medium text-gray-900"><?php echo $stats['admin_permissions']; ?></dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <!-- Write Permissions -->
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-yellow-500 rounded-md flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Write Access</dt>
                            <dd class="text-lg font-medium text-gray-900"><?php echo $stats['write_permissions']; ?></dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white shadow rounded-lg p-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Filters</h3>
        <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Business name, owner..."
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Business Type</label>
                <select name="business_type" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">All Types</option>
                    <?php while ($type = mysqli_fetch_assoc($business_types_result)): ?>
                    <option value="<?php echo htmlspecialchars($type['business_type']); ?>" 
                            <?php echo $business_type == $type['business_type'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($type['business_type']); ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Permission Level</label>
                <select name="permission_level" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">All Permissions</option>
                    <option value="read" <?php echo $permission_level == 'read' ? 'selected' : ''; ?>>Read Only</option>
                    <option value="write" <?php echo $permission_level == 'write' ? 'selected' : ''; ?>>Read & Write</option>
                    <option value="admin" <?php echo $permission_level == 'admin' ? 'selected' : ''; ?>>Admin</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">All Status</option>
                    <option value="active" <?php echo $status == 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $status == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>

            <div class="flex items-end">
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md">
                    Apply Filters
                </button>
            </div>
        </form>

               <?php if (!empty($search) || !empty($business_type) || !empty($permission_level) || !empty($status)): ?>
        <div class="mt-4">
            <a href="businesses.php" class="text-blue-600 hover:text-blue-800 text-sm">
                <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
                Clear Filters
            </a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Businesses List -->
    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">
                Assigned Businesses 
                <span class="text-sm font-normal text-gray-500">(<?php echo mysqli_num_rows($businesses_result); ?> found)</span>
            </h3>
        </div>

        <?php if (mysqli_num_rows($businesses_result) > 0): ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Business</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Owner</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Permission</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transactions</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Revenue</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expenses</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Net</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assigned</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php while ($business = mysqli_fetch_assoc($businesses_result)): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="h-10 w-10 flex-shrink-0">
                                    <div class="h-10 w-10 rounded-full bg-blue-500 flex items-center justify-center">
                                        <span class="text-sm font-medium text-white">
                                            <?php echo strtoupper(substr($business['name'], 0, 2)); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-gray-900">
                                        <a href="#?id=<?php echo $business['id']; ?>" 
                                           class="hover:text-blue-600">
                                            <?php echo htmlspecialchars($business['name']); ?>
                                        </a>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        <?php echo htmlspecialchars($business['business_type']); ?>
                                    </div>
                                    <div class="text-xs text-gray-400">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo get_status_badge_class($business['status']); ?>">
                                            <?php echo ucfirst($business['status']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900">
                                <?php echo htmlspecialchars($business['owner_first_name'] . ' ' . $business['owner_last_name']); ?>
                            </div>
                            <div class="text-sm text-gray-500">
                                <?php echo htmlspecialchars($business['owner_email']); ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo get_permission_badge_class($business['permission_level']); ?>">
                                <?php echo ucfirst($business['permission_level']); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <div class="flex flex-col">
                                <span class="font-medium"><?php echo $business['transaction_count']; ?> total</span>
                                <span class="text-xs text-gray-500"><?php echo $business['my_transactions']; ?> by me</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600 font-medium">
                            <?php echo format_currency($business['total_income'] ?? 0); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-red-600 font-medium">
                            <?php echo format_currency($business['total_expenses'] ?? 0); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <?php 
                            $net = ($business['total_income'] ?? 0) - ($business['total_expenses'] ?? 0);
                            $net_class = $net >= 0 ? 'text-green-600' : 'text-red-600';
                            ?>
                            <span class="<?php echo $net_class; ?>">
                                <?php echo format_currency($net); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo format_date($business['assigned_at']); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <div class="flex space-x-2">
                                <a href="#?id=<?php echo $business['id']; ?>" 
                                   class="text-blue-600 hover:text-blue-900" title="View Details">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                    </svg>
                                </a>
                                
                                <?php if ($business['permission_level'] != 'read'): ?>
                                <a href="../common/transactions.php?business_id=<?php echo $business['id']; ?>" 
                                   class="text-green-600 hover:text-green-900" title="Add Transaction">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                    </svg>
                                </a>
                                <?php endif; ?>
                                
                                <a href="../common/transactions.php?business_id=<?php echo $business['id']; ?>" 
                                   class="text-purple-600 hover:text-purple-900" title="View Transactions">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                    </svg>
                                </a>
                                
                                <a href="reports.php?business_id=<?php echo $business['id']; ?>" 
                                   class="text-indigo-600 hover:text-indigo-900" title="Generate Report">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                    </svg>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="text-center py-12">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900">No businesses assigned</h3>
            <p class="mt-1 text-sm text-gray-500">
                <?php if (!empty($search) || !empty($business_type) || !empty($permission_level) || !empty($status)): ?>
                    No businesses match your current filters.
                <?php else: ?>
                    You haven't been assigned to any businesses yet. Contact your administrator.
                <?php endif; ?>
            </p>
            <?php if (!empty($search) || !empty($business_type) || !empty($permission_level) || !empty($status)): ?>
            <div class="mt-6">
                <a href="businesses.php" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                    Clear Filters
                </a>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Quick Actions -->
    <div class="bg-white shadow rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Quick Actions</h3>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <a href="../common/transactions.php"
                   class="bg-green-50 hover:bg-green-100 p-4 rounded-lg border border-green-200 transition-colors">
                    <div class="flex items-center">
                        <svg class="w-6 h-6 text-green-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        <span class="text-green-700 font-medium">Add Transaction</span>
                    </div>
                </a>

                             <a href="../common/transactions.php"
                   class="bg-blue-50 hover:bg-blue-100 p-4 rounded-lg border border-blue-200 transition-colors">
                    <div class="flex items-center">
                        <svg class="w-6 h-6 text-blue-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                        </svg>
                        <span class="text-blue-700 font-medium">View All Transactions</span>
                    </div>
                </a>

                <a href="../common/categories.php"
                   class="bg-yellow-50 hover:bg-yellow-100 p-4 rounded-lg border border-yellow-200 transition-colors">
                    <div class="flex items-center">
                        <svg class="w-6 h-6 text-yellow-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                        </svg>
                        <span class="text-yellow-700 font-medium">View Categories</span>
                    </div>
                </a>

                <a href="reports.php"
                   class="bg-purple-50 hover:bg-purple-100 p-4 rounded-lg border border-purple-200 transition-colors">
                    <div class="flex items-center">
                        <svg class="w-6 h-6 text-purple-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                        <span class="text-purple-700 font-medium">Generate Reports</span>
                    </div>
                </a>
            </div>
        </div>
    </div>

    <!-- Recent Activity Summary -->
    <?php
    // Get recent activity across all assigned businesses
    $recent_activity_sql = "SELECT t.*, b.name as business_name, c.name as category_name, u.first_name, u.last_name
                           FROM transactions t
                           JOIN businesses b ON t.business_id = b.id
                           JOIN categories c ON t.category_id = c.id
                           JOIN users u ON t.user_id = u.id
                           JOIN user_business_assignments uba ON uba.business_id = b.id
                           WHERE uba.user_id = {$current_user['id']} AND t.status = 'active'
                           ORDER BY t.created_at DESC
                           LIMIT 10";
    $recent_activity_result = mysqli_query($conn, $recent_activity_sql);
    ?>

    <?php if (mysqli_num_rows($recent_activity_result) > 0): ?>
    <div class="bg-white shadow rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-medium text-gray-900">Recent Activity</h3>
                <a href="../common/transactions.php" class="text-blue-600 hover:text-blue-800 text-sm">View All</a>
            </div>
        </div>
        <div class="overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Business</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Added By</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php while ($activity = mysqli_fetch_assoc($recent_activity_result)): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo format_date($activity['transaction_date']); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                           <?php echo htmlspecialchars($activity['business_name']); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?php echo htmlspecialchars($activity['category_name']); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium <?php echo $activity['type'] == 'income' ? 'text-green-600' : 'text-red-600'; ?>">
                            <?php echo format_currency($activity['amount']); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $activity['type'] == 'income' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                <?php echo ucfirst($activity['type']); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']); ?>
                            <?php if ($activity['user_id'] == $current_user['id']): ?>
                                <span class="text-blue-600">(You)</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Performance Overview -->
    <?php
    // Get performance data for assigned businesses
    $performance_sql = "SELECT 
                        COUNT(DISTINCT b.id) as business_count,
                        SUM(CASE WHEN t.type = 'income' THEN t.amount ELSE 0 END) as total_income,
                        SUM(CASE WHEN t.type = 'expense' THEN t.amount ELSE 0 END) as total_expenses,
                        COUNT(t.id) as total_transactions,
                        COUNT(CASE WHEN t.user_id = {$current_user['id']} THEN 1 END) as my_transactions
                        FROM user_business_assignments uba
                        JOIN businesses b ON uba.business_id = b.id
                        LEFT JOIN transactions t ON t.business_id = b.id AND t.status = 'active'
                        WHERE uba.user_id = {$current_user['id']} AND b.status = 'active'";
    $performance_result = mysqli_query($conn, $performance_sql);
    $performance = mysqli_fetch_assoc($performance_result);
    ?>

    <div class="bg-white shadow rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Performance Overview</h3>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="text-center">
                    <div class="text-2xl font-bold text-blue-600">
                        <?php echo format_currency($performance['total_income'] ?? 0); ?>
                    </div>
                    <div class="text-sm text-gray-500">Total Revenue Managed</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-red-600">
                        <?php echo format_currency($performance['total_expenses'] ?? 0); ?>
                    </div>
                    <div class="text-sm text-gray-500">Total Expenses Managed</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-gray-900">
                        <?php echo $performance['total_transactions'] ?? 0; ?>
                    </div>
                    <div class="text-sm text-gray-500">Total Transactions</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-purple-600">
                        <?php echo $performance['my_transactions'] ?? 0; ?>
                    </div>
                    <div class="text-sm text-gray-500">My Contributions</div>
                </div>
            </div>
            
            <?php 
            $net_managed = ($performance['total_income'] ?? 0) - ($performance['total_expenses'] ?? 0);
            $contribution_percentage = ($performance['total_transactions'] ?? 0) > 0 ? 
                round((($performance['my_transactions'] ?? 0) / ($performance['total_transactions'] ?? 0)) * 100, 1) : 0;
            ?>
            
            <div class="mt-6 pt-6 border-t border-gray-200">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="text-center">
                        <div class="text-xl font-bold <?php echo $net_managed >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                            <?php echo format_currency($net_managed); ?>
                        </div>
                        <div class="text-sm text-gray-500">Net Value Managed</div>
                    </div>
                    <div class="text-center">
                        <div class="text-xl font-bold text-indigo-600">
                            <?php echo $contribution_percentage; ?>%
                        </div>
                        <div class="text-sm text-gray-500">My Transaction Contribution</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-refresh data every 5 minutes
setTimeout(function() {
    location.reload();
}, 300000);

// Add tooltips for action buttons
document.addEventListener('DOMContentLoaded', function() {
    const tooltips = document.querySelectorAll('[title]');
    tooltips.forEach(function(element) {
        element.addEventListener('mouseenter', function() {
            // Simple tooltip implementation
            const title = this.getAttribute('title');
            this.setAttribute('data-original-title', title);
            this.removeAttribute('title');
        });
        
        element.addEventListener('mouseleave', function() {
            const title = this.getAttribute('data-original-title');
            if (title) {
                this.setAttribute('title', title);
                this.removeAttribute('data-original-title');
            }
        });
    });
});
</script>

<?php
mysqli_close($conn);
include '../../components/footer.php';
?>
