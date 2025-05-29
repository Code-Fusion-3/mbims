<?php
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';
require_once '../../config/database.php';

// Require admin role
require_role('admin');

$page_title = 'Business Management';
$current_user = get_logged_user();

// Get database connection
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_business'])) {
        // Add new business
        $name = sanitize_input($_POST['name']);
        $description = sanitize_input($_POST['description']);
        $business_type = sanitize_input($_POST['business_type']);
        $owner_id = (int)$_POST['owner_id'];
        
        $errors = validate_business_data($_POST);
        
        // Check if owner exists and is a partner
        $owner_check = mysqli_query($conn, "SELECT role FROM users WHERE id = $owner_id AND role = 'partner' AND status = 'active'");
        if (mysqli_num_rows($owner_check) == 0) {
            $errors[] = "Selected owner must be an active partner";
        }
        
        if (empty($errors)) {
            $sql = "INSERT INTO businesses (name, description, business_type, owner_id) 
                    VALUES ('$name', '$description', '$business_type', $owner_id)";
            
            if (mysqli_query($conn, $sql)) {
                $success_message = "Business added successfully!";
            } else {
                $error_message = "Error adding business: " . mysqli_error($conn);
            }
        }
    }
    
    if (isset($_POST['update_business'])) {
        // Update business
        $business_id = (int)$_POST['business_id'];
        $name = sanitize_input($_POST['name']);
        $description = sanitize_input($_POST['description']);
        $business_type = sanitize_input($_POST['business_type']);
        $owner_id = (int)$_POST['owner_id'];
        $status = sanitize_input($_POST['status']);
        
        $errors = validate_business_data($_POST);
        
        if (!validate_status($status)) {
            $errors[] = "Invalid status selected";
        }
        
        // Check if owner exists and is a partner
        $owner_check = mysqli_query($conn, "SELECT role FROM users WHERE id = $owner_id AND role = 'partner' AND status = 'active'");
        if (mysqli_num_rows($owner_check) == 0) {
            $errors[] = "Selected owner must be an active partner";
        }
        
        if (empty($errors)) {
            $sql = "UPDATE businesses SET 
                    name = '$name', 
                    description = '$description', 
                    business_type = '$business_type', 
                    owner_id = $owner_id,
                    status = '$status',
                    updated_at = CURRENT_TIMESTAMP
                    WHERE id = $business_id";
            
            if (mysqli_query($conn, $sql)) {
                $success_message = "Business updated successfully!";
            } else {
                $error_message = "Error updating business: " . mysqli_error($conn);
            }
        }
    }
    
    if (isset($_POST['delete_business'])) {
        // Soft delete business
        $business_id = (int)$_POST['business_id'];
        
        $sql = "UPDATE businesses SET status = 'inactive', updated_at = CURRENT_TIMESTAMP WHERE id = $business_id";
        
        if (mysqli_query($conn, $sql)) {
            $success_message = "Business deactivated successfully!";
        } else {
            $error_message = "Error deactivating business: " . mysqli_error($conn);
        }
    }
}

// Get search and filter parameters
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';
$type_filter = isset($_GET['type']) ? sanitize_input($_GET['type']) : '';
$owner_filter = isset($_GET['owner']) ? (int)$_GET['owner'] : 0;

// Build query with filters
$where_conditions = [];
if (!empty($search)) {
    $where_conditions[] = "(b.name LIKE '%$search%' OR b.description LIKE '%$search%')";
}
if (!empty($status_filter)) {
    $where_conditions[] = "b.status = '$status_filter'";
}
if (!empty($type_filter)) {
    $where_conditions[] = "b.business_type = '$type_filter'";
}
if ($owner_filter > 0) {
    $where_conditions[] = "b.owner_id = $owner_filter";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get businesses with owner information and statistics
$businesses_sql = "SELECT b.*, 
                   u.first_name, u.last_name, u.email,
                   (SELECT COUNT(*) FROM transactions t WHERE t.business_id = b.id AND t.status = 'active') as transaction_count,
                   (SELECT SUM(amount) FROM transactions t WHERE t.business_id = b.id AND t.type = 'income' AND t.status = 'active') as total_income,
                   (SELECT SUM(amount) FROM transactions t WHERE t.business_id = b.id AND t.type = 'expense' AND t.status = 'active') as total_expenses,
                   (SELECT COUNT(*) FROM user_business_assignments uba WHERE uba.business_id = b.id) as assigned_accountants
                   FROM businesses b
                   LEFT JOIN users u ON b.owner_id = u.id
                   $where_clause
                   ORDER BY b.created_at DESC";

$businesses_result = mysqli_query($conn, $businesses_sql);

// Get partners for dropdown
$partners_result = mysqli_query($conn, "SELECT id, first_name, last_name, email FROM users WHERE role = 'partner' AND status = 'active' ORDER BY first_name, last_name");

// Get business types for filter
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
    <!-- Page Header -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Business Management</h1>
                <p class="text-gray-600">Manage all businesses in the system</p>
            </div>
            <button onclick="showAddBusinessModal()"
                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                Add Business
            </button>
        </div>
    </div>

    <!-- Display Messages -->
    <?php if (isset($success_message) ): ?>
    <?php display_success($success_message); ?>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
    <div class="bg-red-50 border border-red-200 rounded-md p-4 mb-4">
        <p class="text-red-800"><?php echo $error_message; ?></p>
    </div>
    <?php endif; ?>
    <?php if (isset($_GET['success']) && $_GET['success']=='accountant_updated'): ?>
    <?php display_success("role updated well"); ?>
    <?php endif; ?>
    <?php if (isset($_GET['success']) && $_GET['success']=='accountant_assigned'): ?>
    <?php display_success("role assigned well"); ?>
    <?php endif; ?>
    <?php if (isset($errors) && !empty($errors)): ?>
    <?php display_errors($errors); ?>
    <?php endif; ?>

    <!-- Filters and Search -->
    <div class="bg-white rounded-lg shadow p-6">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                    placeholder="Search businesses..."
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select name="status"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">All Statuses</option>
                    <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive
                    </option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Business Type</label>
                <select name="type"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">All Types</option>
                    <?php foreach ($business_types as $value => $label): ?>
                    <option value="<?php echo $value; ?>" <?php echo $type_filter == $value ? 'selected' : ''; ?>>
                        <?php echo $label; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Owner</label>
                <select name="owner"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">All Owners</option>
                    <?php 
                    mysqli_data_seek($partners_result, 0);
                    while ($partner = mysqli_fetch_assoc($partners_result)): 
                    ?>
                    <option value="<?php echo $partner['id']; ?>"
                        <?php echo $owner_filter == $partner['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($partner['first_name'] . ' ' . $partner['last_name']); ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="flex items-end space-x-2">
                <button type="submit" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md">
                    Filter
                </button>
                <a href="businesses.php" class="bg-gray-300 hover:bg-gray-400 text-gray-700 px-4 py-2 rounded-md">
                    Clear
                </a>
            </div>
        </form>
    </div>

    <!-- Businesses Table -->
    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">
                Businesses (<?php echo mysqli_num_rows($businesses_result); ?>)
            </h3>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Business</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Owner
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Transactions</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Net
                            Income</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Accountants</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Created</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (mysqli_num_rows($businesses_result) > 0): ?>
                    <?php while ($business = mysqli_fetch_assoc($businesses_result)): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div>
                                <div class="text-sm font-medium text-gray-900">
                                    <a href="business-profile.php?id=<?php echo $business['id']; ?>"
                                        class="hover:text-blue-600 hover:underline">
                                        <?php echo htmlspecialchars($business['name']); ?>
                                    </a>
                                </div>
                                <div class="text-sm text-gray-500">
                                    <?php echo htmlspecialchars(truncate_text($business['description'], 50)); ?>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900">
                                <?php if ($business['first_name']): ?>
                                <a href="user-profile.php?id=<?php echo $business['owner_id']; ?>"
                                    class="hover:text-blue-600 hover:underline">
                                    <?php echo htmlspecialchars($business['first_name'] . ' ' . $business['last_name']); ?>
                                </a>
                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($business['email']); ?>
                                </div>
                                <?php else: ?>
                                <span class="text-gray-400">No owner assigned</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?php echo htmlspecialchars($business_types[$business['business_type']] ?? ucfirst($business['business_type'])); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span
                                class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo get_status_badge_class($business['status']); ?>">
                                <?php echo ucfirst($business['status']); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?php echo $business['transaction_count']; ?>
                        </td>
                        <td
                            class="px-6 py-4 whitespace-nowrap text-sm <?php echo (($business['total_income'] ?? 0) - ($business['total_expenses'] ?? 0)) >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                            <?php echo format_currency(($business['total_income'] ?? 0) - ($business['total_expenses'] ?? 0)); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?php echo $business['assigned_accountants']; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo format_date($business['created_at']); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                            <a href="business-profile.php?id=<?php echo $business['id']; ?>"
                                class="text-blue-600 hover:text-blue-900">View</a>
                            <button
                                onclick="showEditBusinessModal(<?php echo htmlspecialchars(json_encode($business)); ?>)"
                                class="text-indigo-600 hover:text-indigo-900">Edit</button>
                            <button
                                onclick="showAssignAccountantModal(<?php echo $business['id']; ?>, '<?php echo htmlspecialchars($business['name']); ?>')"
                                class="text-green-600 hover:text-green-900">Assign</button>
                            <?php if ($business['status'] == 'active'): ?>
                            <button
                                onclick="showDeleteBusinessModal(<?php echo $business['id']; ?>, '<?php echo htmlspecialchars($business['name']); ?>')"
                                class="text-red-600 hover:text-red-900">Deactivate</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php else: ?>
                    <tr>
                        <td colspan="9" class="px-6 py-4 text-center text-gray-500">No businesses found</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Business Modal -->
<div id="addBusinessModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900">Add New Business</h3>
                <button onclick="hideAddBusinessModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                        </path>
                    </svg>
                </button>
            </div>

            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Business Name</label>
                    <input type="text" name="name" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" rows="3"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Business Type</label>
                    <select name="business_type" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Select Type</option>
                        <?php foreach ($business_types as $value => $label): ?>
                        <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Owner (Partner)</label>
                    <select name="owner_id" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Select Owner</option>
                        <?php 
                        mysqli_data_seek($partners_result, 0);
                        while ($partner = mysqli_fetch_assoc($partners_result)): 
                        ?>
                        <option value="<?php echo $partner['id']; ?>">
                            <?php echo htmlspecialchars($partner['first_name'] . ' ' . $partner['last_name'] . ' (' . $partner['email'] . ')'); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="flex justify-end space-x-3 pt-4">
                    <button type="button" onclick="hideAddBusinessModal()"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300">
                        Cancel
                    </button>
                    <button type="submit" name="add_business"
                        class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700">
                        Add Business
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Business Modal -->
<div id="editBusinessModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
    <div class="relative top-20 mx-auto p-5 border w-1/2 shadow-lg rounded-md bg-white">
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

            <form method="POST" class="space-y-4">
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
                        mysqli_data_seek($partners_result, 0);
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

<!-- Delete Business Modal -->
<div id="deleteBusinessModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900">Deactivate Business</h3>
                <button onclick="hideDeleteBusinessModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                        </path>
                    </svg>
                </button>
            </div>

            <p class="text-sm text-gray-600 mb-4">Are you sure you want to deactivate: <span id="delete_business_name"
                    class="font-medium"></span>?</p>
            <p class="text-xs text-gray-500 mb-4">This will make the business inactive but preserve all data.</p>

            <form method="POST" class="space-y-4">
                <input type="hidden" name="business_id" id="delete_business_id">

                <div class="flex justify-end space-x-3 pt-4">
                    <button type="button" onclick="hideDeleteBusinessModal()"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300">
                        Cancel
                    </button>
                    <button type="submit" name="delete_business"
                        class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-md hover:bg-red-700">
                        Deactivate Business
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Add Business Modal Functions
function showAddBusinessModal() {
    document.getElementById('addBusinessModal').classList.remove('hidden');
}

function hideAddBusinessModal() {
    document.getElementById('addBusinessModal').classList.add('hidden');
}

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
    const modals = ['addBusinessModal', 'editBusinessModal', 'assignAccountantModal', 'deleteBusinessModal'];
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