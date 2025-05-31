<?php
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';
require_once '../../config/database.php';

// Require login
require_login();

$page_title = 'Categories';
$current_user = get_logged_user();

// Get database connection
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_category'])) {
        $name = sanitize_input($_POST['name']);
        $type = sanitize_input($_POST['type']);
        $description = sanitize_input($_POST['description']);
        
        $errors = [];
        
        // Validate inputs
        if (empty($name)) {
            $errors[] = "Category name is required";
        }
        
        if (!in_array($type, ['income', 'expense'])) {
            $errors[] = "Invalid category type";
        }
        
        // Check if category name already exists for this type
        $check_sql = "SELECT id FROM categories WHERE name = '$name' AND type = '$type' AND status = 'active'";
        $check_result = mysqli_query($conn, $check_sql);
        if (mysqli_num_rows($check_result) > 0) {
            $errors[] = "A category with this name already exists for this type";
        }
        
        if (empty($errors)) {
            $sql = "INSERT INTO categories (name, type, description) VALUES ('$name', '$type', '$description')";
            
            if (mysqli_query($conn, $sql)) {
                $success_message = "Category added successfully!";
            } else {
                $error_message = "Error adding category: " . mysqli_error($conn);
            }
        } else {
            $error_message = implode(', ', $errors);
        }
    }
    
    if (isset($_POST['update_category'])) {
        $category_id = (int)$_POST['category_id'];
        $name = sanitize_input($_POST['name']);
        $type = sanitize_input($_POST['type']);
        $description = sanitize_input($_POST['description']);
        $status = sanitize_input($_POST['status']);
        
        $errors = [];
        
        // Validate inputs
        if (empty($name)) {
            $errors[] = "Category name is required";
        }
        
        if (!in_array($type, ['income', 'expense'])) {
            $errors[] = "Invalid category type";
        }
        
        if (!in_array($status, ['active', 'inactive'])) {
            $errors[] = "Invalid status";
        }
        
        // Check if category name already exists for this type (excluding current category)
        $check_sql = "SELECT id FROM categories WHERE name = '$name' AND type = '$type' AND status = 'active' AND id != $category_id";
        $check_result = mysqli_query($conn, $check_sql);
        if (mysqli_num_rows($check_result) > 0) {
            $errors[] = "A category with this name already exists for this type";
        }
        
        // Check if category is being used in transactions before changing type
        if (empty($errors)) {
            $usage_check = mysqli_query($conn, "SELECT COUNT(*) as count FROM transactions WHERE category_id = $category_id AND status = 'active'");
            $usage = mysqli_fetch_assoc($usage_check);
            
            if ($usage['count'] > 0) {
                // Get current category type
                $current_category = mysqli_query($conn, "SELECT type FROM categories WHERE id = $category_id");
                $current = mysqli_fetch_assoc($current_category);
                
                if ($current['type'] != $type) {
                    $errors[] = "Cannot change category type because it's being used in {$usage['count']} transactions";
                }
            }
        }
        
        if (empty($errors)) {
            $sql = "UPDATE categories SET 
                    name = '$name', 
                    type = '$type', 
                    description = '$description',
                    status = '$status'
                    WHERE id = $category_id";
            
            if (mysqli_query($conn, $sql)) {
                $success_message = "Category updated successfully!";
            } else {
                $error_message = "Error updating category: " . mysqli_error($conn);
            }
        } else {
            $error_message = implode(', ', $errors);
        }
    }
    
    if (isset($_POST['delete_category'])) {
        $category_id = (int)$_POST['category_id'];
        
        // Check if category is being used in transactions
        $usage_check = mysqli_query($conn, "SELECT COUNT(*) as count FROM transactions WHERE category_id = $category_id AND status = 'active'");
        $usage = mysqli_fetch_assoc($usage_check);
        
        if ($usage['count'] > 0) {
            $error_message = "Cannot delete category because it's being used in {$usage['count']} transactions. Set it to inactive instead.";
        } else {
            $sql = "UPDATE categories SET status = 'inactive' WHERE id = $category_id";
            
            if (mysqli_query($conn, $sql)) {
                $success_message = "Category deleted successfully!";
            } else {
                $error_message = "Error deleting category: " . mysqli_error($conn);
            }
        }
    }
}

// Get filter parameters
$type_filter = isset($_GET['type']) ? sanitize_input($_GET['type']) : '';
$status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : 'active';
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';

// Build WHERE clause
$where_conditions = [];

if (!empty($type_filter) && in_array($type_filter, ['income', 'expense'])) {
    $where_conditions[] = "type = '$type_filter'";
}

if (!empty($status_filter) && in_array($status_filter, ['active', 'inactive'])) {
    $where_conditions[] = "status = '$status_filter'";
}

if (!empty($search)) {
    $where_conditions[] = "(name LIKE '%$search%' OR description LIKE '%$search%')";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get categories with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

$categories_sql = "SELECT c.*, 
                   (SELECT COUNT(*) FROM transactions t WHERE t.category_id = c.id AND t.status = 'active') as transaction_count
                   FROM categories c
                   $where_clause
                   ORDER BY c.type, c.name
                   LIMIT $per_page OFFSET $offset";

$categories_result = mysqli_query($conn, $categories_sql);

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM categories c $where_clause";
$count_result = mysqli_query($conn, $count_sql);
$total_categories = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_categories / $per_page);

// Get summary statistics
$summary_sql = "SELECT 
                COUNT(CASE WHEN type = 'income' AND status = 'active' THEN 1 END) as active_income_categories,
                COUNT(CASE WHEN type = 'expense' AND status = 'active' THEN 1 END) as active_expense_categories,
                COUNT(CASE WHEN status = 'active' THEN 1 END) as total_active_categories,
                COUNT(CASE WHEN status = 'inactive' THEN 1 END) as total_inactive_categories
                FROM categories";
$summary_result = mysqli_query($conn, $summary_sql);
$summary = mysqli_fetch_assoc($summary_result);

include '../../components/header.php';
include '../../components/sidebar.php';
?>

<div class="space-y-6">
    <!-- Page Header -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Categories</h1>
                <p class="text-gray-600">Manage income and expense categories</p>
            </div>
            <?php if ($current_user['role'] == 'admin'): ?>
            <button onclick="showAddCategoryModal()" 
                    class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                Add Category
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Display Messages -->
    <?php if (isset($success_message)): ?>
        <div class="bg-green-50 border border-green-200 rounded-md p-4">
            <p class="text-green-800"><?php echo $success_message; ?></p>
        </div>
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
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Income Categories</dt>
                            <dd class="text-lg font-medium text-gray-900"><?php echo $summary['active_income_categories']; ?></dd>
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
                            <dt class="text-sm font-medium text-gray-500 truncate">Expense Categories</dt>
                            <dd class="text-lg font-medium text-gray-900"><?php echo $summary['active_expense_categories']; ?></dd>
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
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Active Categories</dt>
                            <dd class="text-lg font-medium text-gray-900"><?php echo $summary['total_active_categories']; ?></dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-gray-500 rounded-md flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728L5.636 5.636m12.728 12.728L5.636 5.636"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Inactive Categories</dt>
                            <dd class="text-lg font-medium text-gray-900"><?php echo $summary['total_inactive_categories']; ?></dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white shadow rounded-lg p-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Filters</h3>
        <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                       <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                <select name="type" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">All Types</option>
                    <option value="income" <?php echo $type_filter == 'income' ? 'selected' : ''; ?>>Income</option>
                    <option value="expense" <?php echo $type_filter == 'expense' ? 'selected' : ''; ?>>Expense</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    <option value="" <?php echo $status_filter == '' ? 'selected' : ''; ?>>All Status</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Category name or description..."
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            <div class="flex items-end space-x-3">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                    Apply Filters
                </button>
                <a href="categories.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg">
                    Clear Filters
                </a>
            </div>
        </form>
    </div>

    <!-- Categories Table -->
    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">
                Categories 
                <?php if ($total_categories > 0): ?>
                    (<?php echo number_format($total_categories); ?> total)
                <?php endif; ?>
            </h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transactions</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                        <?php if ($current_user['role'] == 'admin'): ?>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (mysqli_num_rows($categories_result) > 0): ?>
                        <?php while ($category = mysqli_fetch_assoc($categories_result)): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $category['type'] == 'income' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                    <?php echo ucfirst($category['type']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900">
                                <?php echo htmlspecialchars(truncate_text($category['description'], 50)); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo get_status_badge_class($category['status']); ?>">
                                    <?php echo ucfirst($category['status']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo $category['transaction_count']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo format_date($category['created_at']); ?>
                            </td>
                            <?php if ($current_user['role'] == 'admin'): ?>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex space-x-2">
                                    <button onclick="showEditCategoryModal(<?php echo htmlspecialchars(json_encode($category)); ?>)"
                                            class="text-blue-600 hover:text-blue-900">Edit</button>
                                    <?php if ($category['transaction_count'] == 0): ?>
                                        <button onclick="showDeleteCategoryModal(<?php echo $category['id']; ?>, '<?php echo htmlspecialchars($category['name']); ?>')"
                                                class="text-red-600 hover:text-red-900">Delete</button>
                                    <?php else: ?>
                                        <span class="text-gray-400" title="Cannot delete category with transactions">In Use</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?php echo $current_user['role'] == 'admin' ? '7' : '6'; ?>" class="px-6 py-4 text-center text-gray-500">No categories found</td>
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
                        to <span class="font-medium"><?php echo min($page * $per_page, $total_categories); ?></span>
                        of <span class="font-medium"><?php echo $total_categories; ?></span> results
                    </p>
                </div>
                <div>
                    <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                        <?php if ($page > 1): ?>
                        <a href="?page=<?php echo ($page - 1); ?>&<?php echo http_build_query(array_filter($_GET, function($key) { return $key != 'page'; }, ARRAY_FILTER_USE_KEY)); ?>"
                           class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"/>
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
                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
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

<!-- Add Category Modal -->
<?php if ($current_user['role'] == 'admin'): ?>
<div id="addCategoryModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900">Add Category</h3>
                <button onclick="hideAddCategoryModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Category Name</label>
                    <input type="text" name="name" required 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                               <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                    <select name="type" required 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Select Type</option>
                        <option value="income">Income</option>
                        <option value="expense">Expense</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" rows="3" 
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                              placeholder="Optional description for this category"></textarea>
                </div>

                <div class="flex justify-end space-x-3 pt-4">
                    <button type="button" onclick="hideAddCategoryModal()" 
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300">
                        Cancel
                    </button>
                    <button type="submit" name="add_category" 
                            class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700">
                        Add Category
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Category Modal -->
<div id="editCategoryModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900">Edit Category</h3>
                <button onclick="hideEditCategoryModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <form method="POST" class="space-y-4">
                <input type="hidden" name="category_id" id="edit_category_id">

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Category Name</label>
                    <input type="text" name="name" id="edit_name" required 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                    <select name="type" id="edit_type" required 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="income">Income</option>
                        <option value="expense">Expense</option>
                    </select>
                    <p class="text-xs text-gray-500 mt-1" id="type_warning" style="display: none;">
                        Warning: Changing type may affect existing transactions
                    </p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" id="edit_description" rows="3" 
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
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
                    <button type="button" onclick="hideEditCategoryModal()" 
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300">
                        Cancel
                    </button>
                    <button type="submit" name="update_category" 
                            class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700">
                        Update Category
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Category Modal -->
<div id="deleteCategoryModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900">Delete Category</h3>
                <button onclick="hideDeleteCategoryModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <p class="text-sm text-gray-600 mb-4">Are you sure you want to delete this category?</p>
            <p class="text-sm font-medium text-gray-900 mb-4" id="delete_category_name"></p>
            <p class="text-xs text-gray-500 mb-4">This action cannot be undone.</p>

            <form method="POST" class="space-y-4">
                <input type="hidden" name="category_id" id="delete_category_id">

                <div class="flex justify-end space-x-3 pt-4">
                    <button type="button" onclick="hideDeleteCategoryModal()" 
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300">
                        Cancel
                    </button>
                    <button type="submit" name="delete_category" 
                            class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-md hover:bg-red-700">
                        Delete Category
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// Add Category Modal Functions
function showAddCategoryModal() {
    document.getElementById('addCategoryModal').classList.remove('hidden');
}

function hideAddCategoryModal() {
    document.getElementById('addCategoryModal').classList.add('hidden');
}

// Edit Category Modal Functions
function showEditCategoryModal(category) {
    document.getElementById('edit_category_id').value = category.id;
    document.getElementById('edit_name').value = category.name;
    document.getElementById('edit_type').value = category.type;
    document.getElementById('edit_description').value = category.description || '';
    document.getElementById('edit_status').value = category.status;
    
    // Show warning if category has transactions
    if (category.transaction_count > 0) {
        document.getElementById('type_warning').style.display = 'block';
    } else {
        document.getElementById('type_warning').style.display = 'none';
    }
    
    document.getElementById('editCategoryModal').classList.remove('hidden');
}

function hideEditCategoryModal() {
    document.getElementById('editCategoryModal').classList.add('hidden');
}

// Delete Category Modal Functions
function showDeleteCategoryModal(categoryId, categoryName) {
    document.getElementById('delete_category_id').value = categoryId;
    document.getElementById('delete_category_name').textContent = categoryName;
    document.getElementById('deleteCategoryModal').classList.remove('hidden');
}

function hideDeleteCategoryModal() {
    document.getElementById('deleteCategoryModal').classList.add('hidden');
}

// Close modals when clicking outside
window.onclick = function(event) {
    const modals = ['addCategoryModal', 'editCategoryModal', 'deleteCategoryModal'];
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
