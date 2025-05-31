<?php
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';
require_once '../../config/database.php';

// Require partner role
require_role('partner');

$page_title = 'My Businesses';
$current_user = get_logged_user();

// Get database connection
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Handle business operations
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['create_business'])) {
        $name = sanitize_input($_POST['name']);
        $description = sanitize_input($_POST['description']);
        $business_type = sanitize_input($_POST['business_type']);
        
        if (empty($name) || empty($business_type)) {
            $message = 'Business name and type are required.';
            $message_type = 'error';
        } else {
            $sql = "INSERT INTO businesses (name, description, business_type, owner_id, status, created_at) 
                    VALUES (?, ?, ?, ?, 'active', NOW())";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "sssi", $name, $description, $business_type, $current_user['id']);
            
            if (mysqli_stmt_execute($stmt)) {
                $message = 'Business created successfully!';
                $message_type = 'success';
            } else {
                $message = 'Error creating business. Please try again.';
                $message_type = 'error';
            }
            mysqli_stmt_close($stmt);
        }
    }
    
    if (isset($_POST['update_business'])) {
        $business_id = (int)$_POST['business_id'];
        $name = sanitize_input($_POST['name']);
        $description = sanitize_input($_POST['description']);
        $business_type = sanitize_input($_POST['business_type']);
        $status = sanitize_input($_POST['status']);
        
        if (empty($name) || empty($business_type)) {
            $message = 'Business name and type are required.';
            $message_type = 'error';
        } else {
            $sql = "UPDATE businesses SET name = ?, description = ?, business_type = ?, status = ?, updated_at = NOW() 
                    WHERE id = ? AND owner_id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ssssii", $name, $description, $business_type, $status, $business_id, $current_user['id']);
            
            if (mysqli_stmt_execute($stmt)) {
                $message = 'Business updated successfully!';
                $message_type = 'success';
            } else {
                $message = 'Error updating business. Please try again.';
                $message_type = 'error';
            }
            mysqli_stmt_close($stmt);
        }
    }
    
    if (isset($_POST['delete_business'])) {
        $business_id = (int)$_POST['business_id'];
        
        // Check if business has transactions
        $check_sql = "SELECT COUNT(*) as count FROM transactions WHERE business_id = ? AND status = 'active'";
        $check_stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "i", $business_id);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        $transaction_count = mysqli_fetch_assoc($check_result)['count'];
        mysqli_stmt_close($check_stmt);
        
        if ($transaction_count > 0) {
            $message = 'Cannot delete business with existing transactions. Please delete transactions first or set business status to inactive.';
            $message_type = 'error';
        } else {
            $sql = "UPDATE businesses SET status = 'inactive', updated_at = NOW() WHERE id = ? AND owner_id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ii", $business_id, $current_user['id']);
            
            if (mysqli_stmt_execute($stmt)) {
                $message = 'Business deleted successfully!';
                $message_type = 'success';
            } else {
                $message = 'Error deleting business. Please try again.';
                $message_type = 'error';
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';
$type_filter = isset($_GET['type']) ? sanitize_input($_GET['type']) : '';
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';

// Build WHERE conditions
$where_conditions = ["b.owner_id = {$current_user['id']}"];

if (!empty($status_filter)) {
    $where_conditions[] = "b.status = '$status_filter'";
} else {
    $where_conditions[] = "b.status = 'active'"; // Default to active only
}

if (!empty($type_filter)) {
    $where_conditions[] = "b.business_type = '$type_filter'";
}

if (!empty($search)) {
    $search_escaped = mysqli_real_escape_string($conn, $search);
    $where_conditions[] = "(b.name LIKE '%$search_escaped%' OR b.description LIKE '%$search_escaped%')";
}

$where_clause = implode(' AND ', $where_conditions);

// Get businesses with statistics
$businesses_sql = "SELECT b.*,
                   (SELECT COUNT(*) FROM transactions t WHERE t.business_id = b.id AND t.status = 'active') as transaction_count,
                   (SELECT SUM(amount) FROM transactions t WHERE t.business_id = b.id AND t.type = 'income' AND t.status = 'active') as total_income,
                   (SELECT SUM(amount) FROM transactions t WHERE t.business_id = b.id AND t.type = 'expense' AND t.status = 'active') as total_expenses,
                   (SELECT COUNT(*) FROM user_business_assignments uba WHERE uba.business_id = b.id) as assigned_accountants
                   FROM businesses b
                   WHERE $where_clause
                   ORDER BY b.created_at DESC";

$businesses_result = mysqli_query($conn, $businesses_sql);

// Get business types for filter
$types_result = mysqli_query($conn, "SELECT DISTINCT business_type FROM businesses WHERE owner_id = {$current_user['id']} AND business_type IS NOT NULL ORDER BY business_type");

include '../../components/header.php';
include '../../components/sidebar.php';
?>

<div class="space-y-6">
    <!-- Page Header -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">My Businesses</h1>
                <p class="text-gray-600">Manage your business portfolio and track performance</p>
            </div>
            <button onclick="showCreateBusinessModal()" 
                    class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                Add New Business
            </button>
        </div>
    </div>

    <!-- Messages -->
    <?php if (!empty($message)): ?>
    <div class="<?php echo $message_type == 'error' ? 'bg-red-100 border-red-400 text-red-700' : 'bg-green-100 border-green-400 text-green-700'; ?> border px-4 py-3 rounded-lg" role="alert">
        <div class="flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                      d="<?php echo $message_type == 'error' ? 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z' : 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'; ?>">
                </path>
            </svg>
            <?php echo htmlspecialchars($message); ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="bg-white shadow rounded-lg p-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Filters</h3>
        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Search businesses..."
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">All Statuses</option>
                    <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Business Type</label>
                <select name="type" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">All Types</option>
                    <?php while ($type = mysqli_fetch_assoc($types_result)): ?>
                    <option value="<?php echo htmlspecialchars($type['business_type']); ?>" 
                            <?php echo $type_filter == $type['business_type'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($type['business_type']); ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="flex items-end">
                <button type="submit" class="w-full bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md">
                    Apply Filters
                </button>
            </div>
        </form>
    </div>

    <!-- Business Statistics -->
    <?php
    $stats_sql = "SELECT 
                  COUNT(*) as total_businesses,
                  COUNT(CASE WHEN status = 'active' THEN 1 END) as active_businesses,
                  SUM(CASE WHEN status = 'active' THEN (SELECT COUNT(*) FROM transactions t WHERE t.business_id = b.id AND t.status = 'active') ELSE 0 END) as total_transactions,
                  SUM(CASE WHEN status = 'active' THEN (SELECT COALESCE(SUM(amount), 0) FROM transactions t WHERE t.business_id = b.id AND t.type = 'income' AND t.status = 'active') ELSE 0 END) as total_income,
                  SUM(CASE WHEN status = 'active' THEN (SELECT COALESCE(SUM(amount), 0) FROM transactions t WHERE t.business_id = b.id AND t.type = 'expense' AND t.status = 'active') ELSE 0 END) as total_expenses
                  FROM businesses b 
                  WHERE b.owner_id = {$current_user['id']}";
    $stats_result = mysqli_query($conn, $stats_sql);
    $stats = mysqli_fetch_assoc($stats_result);
    ?>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6">
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
                            <dt class="text-sm font-medium text-gray-500 truncate">Total Businesses</dt>
                            <dd class="text-lg font-medium text-gray-900"><?php echo $stats['total_businesses']; ?></dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

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

        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-yellow-500 rounded-md flex items-center justify-center">
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

        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-600 rounded-md flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Total Income</dt>
                            <dd class="text-lg font-medium text-green-600"><?php echo format_currency($stats['total_income'] ?? 0); ?></dd>
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
                            <dd class="text-lg font-medium text-red-600"><?php echo format_currency($stats['total_expenses'] ?? 0); ?></dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Business List -->
    <div class="bg-white shadow rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">
                Business List 
                <span class="text-sm text-gray-500">(<?php echo mysqli_num_rows($businesses_result); ?> businesses)</span>
            </h3>
        </div>
        
        <?php if (mysqli_num_rows($businesses_result) > 0): ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Business</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transactions</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Income</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expenses</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Net</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Accountants</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php while ($business = mysqli_fetch_assoc($businesses_result)): ?>
                    <?php 
                    $net_amount = ($business['total_income'] ?? 0) - ($business['total_expenses'] ?? 0);
                    ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div>
                                <div class="text-sm font-medium text-gray-900">
                                    <a href="business-profile.php?id=<?php echo $business['id']; ?>" 
                                       class="text-blue-600 hover:text-blue-800">
                                        <?php echo htmlspecialchars($business['name']); ?>
                                    </a>
                                </div>
                                <div class="text-sm text-gray-500">
                                    <?php echo htmlspecialchars(truncate_text($business['description'], 50)); ?>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?php echo htmlspecialchars($business['business_type']); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo get_status_badge_class($business['status']); ?>">
                                <?php echo ucfirst($business['status']); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <a href="../common/transactions.php?business_id=<?php echo $business['id']; ?>" 
                               class="text-blue-600 hover:text-blue-800">
                                <?php echo $business['transaction_count']; ?>
                            </a>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600">
                            <?php echo format_currency($business['total_income'] ?? 0); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-red-600">
                            <?php echo format_currency($business['total_expenses'] ?? 0); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm <?php echo $net_amount >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                            <?php echo format_currency($net_amount); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?php echo $business['assigned_accountants']; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <div class="flex space-x-2">
                                <button onclick="showEditBusinessModal(<?php echo htmlspecialchars(json_encode($business)); ?>)"
                                        class="text-blue-600 hover:text-blue-900">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                    </svg>
                                </button>
                                <a href="business-profile.php?id=<?php echo $business['id']; ?>"
                                   class="text-green-600 hover:text-green-900">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                    </svg>
                                </a>
                                <?php if ($business['transaction_count'] == 0): ?>
                                <button onclick="showDeleteBusinessModal(<?php echo $business['id']; ?>, '<?php echo htmlspecialchars($business['name']); ?>')"
                                        class="text-red-600 hover:text-red-900">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                </button>
                                <?php endif; ?>
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
            <h3 class="mt-2 text-sm font-medium text-gray-900">No businesses found</h3>
            <p class="mt-1 text-sm text-gray-500">Get started by creating your first business.</p>
            <div class="mt-6">
                <button onclick="showCreateBusinessModal()" 
                        class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    Add Business
                </button>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Create Business Modal -->
<div id="createBusinessModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900">Create New Business</h3>
                <button onclick="hideCreateBusinessModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Business Name *</label>
                    <input type="text" name="name" required 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Business Type *</label>
                    <select name="business_type" required 
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
                    <textarea name="description" rows="3" 
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                              placeholder="Brief description of your business..."></textarea>
                </div>

                <div class="flex justify-end space-x-3 pt-4">
                    <button type="button" onclick="hideCreateBusinessModal()"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300">
                        Cancel
                    </button>
                    <button type="submit" name="create_business"
                            class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700">
                        Create Business
                    </button>
                </div>
            </form>
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
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <form method="POST" class="space-y-4">
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

<!-- Delete Business Modal -->
<div id="deleteBusinessModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900">Delete Business</h3>
                <button onclick="hideDeleteBusinessModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <div class="mb-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-gray-900">Confirm Deletion</h3>
                        <div class="mt-2 text-sm text-gray-500">
                            <p>Are you sure you want to delete "<span id="delete_business_name" class="font-medium"></span>"? This action cannot be undone.</p>
                        </div>
                    </div>
                </div>
            </div>

            <form method="POST" class="space-y-4">
                <input type="hidden" name="business_id" id="delete_business_id">

                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="hideDeleteBusinessModal()"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300">
                        Cancel
                    </button>
                    <button type="submit" name="delete_business"
                            class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-md hover:bg-red-700">
                        Delete Business
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Create Business Modal Functions
function showCreateBusinessModal() {
    document.getElementById('createBusinessModal').classList.remove('hidden');
}

function hideCreateBusinessModal() {
    document.getElementById('createBusinessModal').classList.add('hidden');
}

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

// Delete Business Modal Functions
function showDeleteBusinessModal(businessId, businessName) {
    document.getElementById('delete_business_id').value = businessId;
    document.getElementById('delete_business_name').textContent = businessName;
    document.getElementById('deleteBusinessModal').classList.remove('hidden');
}

function hideDeleteBusinessModal() {
    document.getElementById('deleteBusinessModal').classList.add('hidden');
}

// Close modals when clicking outside
window.onclick = function(event) {
    const modals = ['createBusinessModal', 'editBusinessModal', 'deleteBusinessModal'];
    modals.forEach(modalId => {
        const modal = document.getElementById(modalId);
        if (event.target === modal) {
            modal.classList.add('hidden');
        }
    });
}

// Auto-hide messages after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('[role="alert"]');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s ease-out';
            alert.style.opacity = '0';
            setTimeout(() => {
                alert.remove();
            }, 500);
        }, 5000);
    });
});
</script>

<?php
mysqli_close($conn);
include '../../components/footer.php';
?>
