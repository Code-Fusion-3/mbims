<?php
$current_user = get_logged_user();
$current_page = basename($_SERVER['PHP_SELF']);

// Determine the correct path prefix based on current location
$path_parts = explode('/', $_SERVER['REQUEST_URI']);
$is_in_common = in_array('common', $path_parts);
$is_in_admin = in_array('admin', $path_parts);
$is_in_partner = in_array('partner', $path_parts);
$is_in_accountant = in_array('accountant', $path_parts);

// Set the correct path prefix
if ($is_in_common) {
    $admin_prefix = '../admin/';
    $partner_prefix = '../partner/';
    $accountant_prefix = '../accountant/';
    $common_prefix = '';
} else {
    $admin_prefix = '';
    $partner_prefix = '';
    $accountant_prefix = '';
    $common_prefix = '../common/';
}

// Define menu items based on role
$menu_items = [];

switch ($current_user['role']) {
    case 'admin':
        $menu_items = [
            ['name' => 'Dashboard', 'url' => $admin_prefix . 'dashboard.php', 'icon' => 'dashboard'],
            ['name' => 'Users', 'url' => $admin_prefix . 'users.php', 'icon' => 'users'],
            ['name' => 'Businesses', 'url' => $admin_prefix . 'businesses.php', 'icon' => 'business'],
            ['name' => 'Transactions', 'url' => $common_prefix . 'transactions.php', 'icon' => 'transactions'],
            ['name' => 'Categories', 'url' => $common_prefix . 'categories.php', 'icon' => 'category'],
            ['name' => 'Reports', 'url' => $admin_prefix . 'reports.php', 'icon' => 'reports'],
            // ['name' => 'Settings', 'url' => $admin_prefix . 'settings.php', 'icon' => 'settings']
        ];
        break;
    case 'partner':
        $menu_items = [
            ['name' => 'Dashboard', 'url' => $partner_prefix . 'dashboard.php', 'icon' => 'dashboard'],
            ['name' => 'My Businesses', 'url' => $partner_prefix . 'businesses.php', 'icon' => 'business'],
            ['name' => 'Transactions', 'url' => $common_prefix . 'transactions.php', 'icon' => 'transactions'],
            ['name' => 'Categories', 'url' => $common_prefix . 'categories.php', 'icon' => 'category'],
            ['name' => 'Reports', 'url' => $partner_prefix . 'reports.php', 'icon' => 'reports'],
            ['name' => 'Profile', 'url' => $partner_prefix . 'profile.php', 'icon' => 'profile']
        ];
        break;
    case 'accountant':
        $menu_items = [
            ['name' => 'Dashboard', 'url' => $accountant_prefix . 'dashboard.php', 'icon' => 'dashboard'],
            ['name' => 'Assigned Businesses', 'url' => $accountant_prefix . 'businesses.php', 'icon' => 'business'],
            ['name' => 'Transactions', 'url' => $common_prefix . 'transactions.php', 'icon' => 'transactions'],
            ['name' => 'Categories', 'url' => $common_prefix . 'categories.php', 'icon' => 'category'],
            ['name' => 'Reports', 'url' => $accountant_prefix . 'reports.php', 'icon' => 'reports'],
            ['name' => 'Profile', 'url' => $accountant_prefix . 'profile.php', 'icon' => 'profile']
        ];
        break;
}


function get_icon_svg($icon) {
    $icons = [
        'dashboard' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5a2 2 0 012-2h2a2 2 0 012 2v6H8V5z"></path>',
        'users' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>',
        'business' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>',
        'transactions' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>',
        'category' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>',
        'reports' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>',
        'settings' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>',
        'profile' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>'
    ];
    
    return isset($icons[$icon]) ? $icons[$icon] : $icons['dashboard'];
}
?>

<div class="flex">
    <!-- Sidebar -->
    <div class="bg-gray-800 text-white w-64 min-h-screen p-4">
        <div class="mb-8">
            <h2 class="text-lg font-semibold"><?php echo ucfirst($current_user['role']); ?> Panel</h2>
        </div>

        <nav class="space-y-2">
            <?php foreach ($menu_items as $item): ?>
            <a href="<?php echo $item['url']; ?>"
                class="flex items-center space-x-2 p-3 rounded-lg hover:bg-gray-700 transition-colors <?php echo ($current_page == $item['url']) ? 'bg-gray-700' : ''; ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <?php echo get_icon_svg($item['icon']); ?>
                </svg>
                <span><?php echo $item['name']; ?></span>
            </a>
            <?php endforeach; ?>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="flex-1 p-6">