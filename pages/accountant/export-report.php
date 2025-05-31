<?php
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';
require_once '../../config/database.php';

// Require accountant role
require_role('accountant');

$current_user = get_logged_user();

// Get export format
$export_format = isset($_GET['export']) ? sanitize_input($_GET['export']) : 'pdf';

// Get filter parameters
$date_from = isset($_GET['date_from']) ? sanitize_input($_GET['date_from']) : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? sanitize_input($_GET['date_to']) : date('Y-m-t');
$business_filter = isset($_GET['business']) ? (int)$_GET['business'] : 0;

// Get database connection
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Get accountant's assigned business IDs
$business_ids_sql = "SELECT b.id 
                    FROM user_business_assignments uba
                    JOIN businesses b ON uba.business_id = b.id
                    WHERE uba.user_id = {$current_user['id']} AND b.status = 'active'";
$business_ids_result = mysqli_query($conn, $business_ids_sql);
$business_ids = [];
while ($row = mysqli_fetch_assoc($business_ids_result)) {
    $business_ids[] = $row['id'];
}

if (empty($business_ids)) {
    die("No businesses assigned to this accountant.");
}

// Build WHERE conditions
$where_conditions = [
    "t.status = 'active'",
    "t.business_id IN (" . implode(',', $business_ids) . ")"
];

if (!empty($date_from)) {
    $where_conditions[] = "t.transaction_date >= '$date_from'";
}

if (!empty($date_to)) {
    $where_conditions[] = "t.transaction_date <= '$date_to'";
}

if ($business_filter > 0) {
    $where_conditions[] = "t.business_id = $business_filter";
}

$where_clause = implode(' AND ', $where_conditions);

// Get report data
$stats_sql = "SELECT 
              COUNT(*) as total_transactions,
              SUM(CASE WHEN t.type = 'income' THEN t.amount ELSE 0 END) as total_income,
              SUM(CASE WHEN t.type = 'expense' THEN t.amount ELSE 0 END) as total_expenses,
              COUNT(CASE WHEN t.type = 'income' THEN 1 END) as income_transactions,
              COUNT(CASE WHEN t.type = 'expense' THEN 1 END) as expense_transactions
              FROM transactions t
              WHERE $where_clause";

$stats_result = mysqli_query($conn, $stats_sql);
$stats = mysqli_fetch_assoc($stats_result);

// Get detailed transactions
$transactions_sql = "SELECT 
                     t.*,
                     b.name as business_name,
                     c.name as category_name,
                     u.first_name as owner_first_name,
                     u.last_name as owner_last_name
                     FROM transactions t
                     JOIN businesses b ON t.business_id = b.id
                     JOIN categories c ON t.category_id = c.id
                     JOIN users u ON b.owner_id = u.id
                     WHERE $where_clause
                     ORDER BY t.transaction_date DESC, t.created_at DESC";

$transactions_result = mysqli_query($conn, $transactions_sql);

// Get business performance
$business_performance_sql = "SELECT 
                            b.name as business_name,
                            b.business_type,
                            u.first_name as owner_first_name,
                            u.last_name as owner_last_name,
                            uba.permission_level,
                            SUM(CASE WHEN t.type = 'income' THEN t.amount ELSE 0 END) as total_income,
                            SUM(CASE WHEN t.type = 'expense' THEN t.amount ELSE 0 END) as total_expenses,
                            COUNT(*) as transaction_count
                            FROM transactions t
                            JOIN businesses b ON t.business_id = b.id
                            JOIN users u ON b.owner_id = u.id
                            JOIN user_business_assignments uba ON (uba.business_id = b.id AND uba.user_id = {$current_user['id']})
                            WHERE $where_clause
                            GROUP BY b.id, b.name, b.business_type, u.first_name, u.last_name, uba.permission_level
                            ORDER BY total_income DESC";

$business_performance_result = mysqli_query($conn, $business_performance_sql);

if ($export_format == 'excel') {
    // Excel Export
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="assigned_business_report_' . date('Y-m-d') . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    echo "<table border='1'>";
    echo "<tr><th colspan='9' style='font-size: 16px; font-weight: bold;'>Assigned Business Financial Report</th></tr>";
    echo "<tr><th colspan='9'>Accountant: " . htmlspecialchars($current_user['full_name']) . "</th></tr>";
    echo "<tr><th colspan='9'>Period: " . format_date($date_from) . " to " . format_date($date_to) . "</th></tr>";
    echo "<tr><th colspan='9'>&nbsp;</th></tr>";
    
    // Summary
    echo "<tr><th colspan='9' style='background-color: #f0f0f0;'>SUMMARY</th></tr>";
    echo "<tr><td>Total Revenue</td><td colspan='8'>" . format_currency($stats['total_income']) . "</td></tr>";
    echo "<tr><td>Total Expenses</td><td colspan='8'>" . format_currency($stats['total_expenses']) . "</td></tr>";
    echo "<tr><td>Net Profit/Loss</td><td colspan='8'>" . format_currency($stats['total_income'] - $stats['total_expenses']) . "</td></tr>";
    echo "<tr><td>Total Transactions</td><td colspan='8'>" . $stats['total_transactions'] . "</td></tr>";
    echo "<tr><th colspan='9'>&nbsp;</th></tr>";
    
    // Business Performance
    echo "<tr><th colspan='9' style='background-color: #f0f0f0;'>BUSINESS PERFORMANCE</th></tr>";
    echo "<tr>";
    echo "<th>Business</th>";
    echo "<th>Owner</th>";
    echo "<th>Type</th>";
    echo "<th>Permission</th>";
    echo "<th>Revenue</th>";
    echo "<th>Expenses</th>";
    echo "<th>Profit</th>";
    echo "<th>Margin %</th>";
    echo "<th>Transactions</th>";
    echo "</tr>";
    
    while ($business = mysqli_fetch_assoc($business_performance_result)) {
        $profit = $business['total_income'] - $business['total_expenses'];
        $margin = $business['total_income'] > 0 ? ($profit / $business['total_income']) * 100 : 0;
        
        echo "<tr>";
        echo "<td>" . htmlspecialchars($business['business_name']) . "</td>";
        echo "<td>" . htmlspecialchars($business['owner_first_name'] . ' ' . $business['owner_last_name']) . "</td>";
        echo "<td>" . htmlspecialchars($business['business_type']) . "</td>";
        echo "<td>" . ucfirst($business['permission_level']) . "</td>";
        echo "<td>" . format_currency($business['total_income']) . "</td>";
        echo "<td>" . format_currency($business['total_expenses']) . "</td>";
        echo "<td>" . format_currency($profit) . "</td>";
        echo "<td>" . number_format($margin, 1) . "%</td>";
        echo "<td>" . number_format($business['transaction_count']) . "</td>";
        echo "</tr>";
    }
    echo "<tr><th colspan='9'>&nbsp;</th></tr>";
    
    // Transactions
    echo "<tr><th colspan='9' style='background-color: #f0f0f0;'>TRANSACTIONS</th></tr>";
    echo "<tr>";
    echo "<th>Date</th>";
    echo "<th>Business</th>";
    echo "<th>Owner</th>";
    echo "<th>Category</th>";
    echo "<th>Type</th>";
    echo "<th>Amount</th>";
    echo "<th>Description</th>";
    echo "<th colspan='2'>&nbsp;</th>";
    echo "</tr>";
    
    while ($transaction = mysqli_fetch_assoc($transactions_result)) {
        echo "<tr>";
        echo "<td>" . format_date($transaction['transaction_date']) . "</td>";
        echo "<td>" . htmlspecialchars($transaction['business_name']) . "</td>";
        echo "<td>" . htmlspecialchars($transaction['owner_first_name'] . ' ' . $transaction['owner_last_name']) . "</td>";
        echo "<td>" . htmlspecialchars($transaction['category_name']) . "</td>";
        echo "<td>" . ucfirst($transaction['type']) . "</td>";
        echo "<td>" . format_currency($transaction['amount']) . "</td>";
        echo "<td>" . htmlspecialchars($transaction['description']) . "</td>";
        echo "<td colspan='2'>&nbsp;</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} else {
    // PDF Export (HTML to PDF)
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Assigned Business Report</title>
        <style>
            body { 
                font-family: Arial, sans-serif; 
                font-size: 12px; 
                margin: 20px;
            }
            .header {
                text-align: center;
                margin-bottom: 30px;
                border-bottom: 2px solid #333;
                padding-bottom: 10px;
            }
            .summary {
                margin-bottom: 30px;
            }
            .summary table, .business-table, .transactions table {
                width: 100%;
                border-collapse: collapse;
            }
            .summary th, .summary td, .business-table th, .business-table td, .transactions th, .transactions td {
                border: 1px solid #ddd;
                padding: 8px;
                text-align: left;
            }
            .summary th, .business-table th, .transactions th {
                background-color: #f5f5f5;
                font-weight: bold;
            }
            .business-table {
                margin: 30px 0;
            }
            .transactions {
                margin-top: 30px;
            }
            .transactions table {
                font-size: 10px;
            }
            .transactions th, .transactions td {
                padding: 6px;
            }
            .income { color: #059669; }
            .expense { color: #dc2626; }
            .profit { color: #059669; font-weight: bold; }
            .loss { color: #dc2626; font-weight: bold; }
            .page-break { page-break-before: always; }
            .permission-badge {
                padding: 2px 6px;
                border-radius: 4px;
                font-size: 10px;
                font-weight: bold;
            }
            .permission-read { background-color: #e5e7eb; color: #374151; }
            .permission-write { background-color: #dbeafe; color: #1e40af; }
            .permission-admin { background-color: #fef3c7; color: #92400e; }
            @media print {
                body { margin: 0; }
                .no-print { display: none; }
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>Assigned Business Financial Report</h1>
            <p>Accountant: <?php echo htmlspecialchars($current_user['full_name']); ?></p>
            <p>Period: <?php echo format_date($date_from) . ' to ' . format_date($date_to); ?></p>
            <p>Generated on: <?php echo format_datetime(date('Y-m-d H:i:s')); ?></p>
        </div>

        <div class="summary">
            <h2>Executive Summary</h2>
            <table>
                <tr>
                    <th>Metric</th>
                    <th>Value</th>
                    <th>Details</th>
                </tr>
                <tr>
                    <td>Total Revenue</td>
                    <td class="income"><?php echo format_currency($stats['total_income']); ?></td>
                    <td><?php echo $stats['income_transactions']; ?> income transactions</td>
                </tr>
                <tr>
                    <td>Total Expenses</td>
                    <td class="expense"><?php echo format_currency($stats['total_expenses']); ?></td>
                    <td><?php echo $stats['expense_transactions']; ?> expense transactions</td>
                </tr>
                <tr>
                    <td>Net Profit/Loss</td>
                    <td class="<?php echo ($stats['total_income'] - $stats['total_expenses']) >= 0 ? 'profit' : 'loss'; ?>">
                        <?php echo format_currency($stats['total_income'] - $stats['total_expenses']); ?>
                    </td>
                    <td>
                        <?php 
                        $margin = $stats['total_income'] > 0 ? (($stats['total_income'] - $stats['total_expenses']) / $stats['total_income']) * 100 : 0;
                        echo number_format($margin, 1) . '% margin';
                        ?>
                    </td>
                </tr>
                <tr>
                    <td>Total Transactions</td>
                    <td><?php echo number_format($stats['total_transactions']); ?></td>
                    <td>
                        Average: <?php echo format_currency(($stats['total_income'] + $stats['total_expenses']) / max($stats['total_transactions'], 1)); ?>
                    </td>
                </tr>
                <tr>
                    <td>Assigned Businesses</td>
                    <td><?php echo mysqli_num_rows($business_performance_result); ?></td>
                    <td>Under your management</td>
                </tr>
            </table>
        </div>

              <?php if (mysqli_num_rows($business_performance_result) > 0): ?>
        <div class="business-performance">
            <h2>Business Performance</h2>
            <table class="business-table">
                <thead>
                    <tr>
                        <th>Business</th>
                        <th>Owner</th>
                        <th>Type</th>
                        <th>Permission</th>
                        <th>Revenue</th>
                        <th>Expenses</th>
                        <th>Profit</th>
                        <th>Margin</th>
                        <th>Transactions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    mysqli_data_seek($business_performance_result, 0);
                    while ($business = mysqli_fetch_assoc($business_performance_result)): 
                        $profit = $business['total_income'] - $business['total_expenses'];
                        $margin = $business['total_income'] > 0 ? ($profit / $business['total_income']) * 100 : 0;
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($business['business_name']); ?></td>
                        <td><?php echo htmlspecialchars($business['owner_first_name'] . ' ' . $business['owner_last_name']); ?></td>
                        <td><?php echo htmlspecialchars($business['business_type']); ?></td>
                        <td>
                            <span class="permission-badge permission-<?php echo $business['permission_level']; ?>">
                                <?php echo ucfirst($business['permission_level']); ?>
                            </span>
                        </td>
                        <td class="income"><?php echo format_currency($business['total_income']); ?></td>
                        <td class="expense"><?php echo format_currency($business['total_expenses']); ?></td>
                        <td class="<?php echo $profit >= 0 ? 'profit' : 'loss'; ?>">
                            <?php echo format_currency($profit); ?>
                        </td>
                        <td class="<?php echo $margin >= 0 ? 'profit' : 'loss'; ?>">
                            <?php echo number_format($margin, 1); ?>%
                        </td>
                        <td><?php echo number_format($business['transaction_count']); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if ($stats['total_transactions'] > 0): ?>
        <div class="transactions page-break">
            <h2>Transaction Details</h2>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Business</th>
                        <th>Owner</th>
                        <th>Category</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    mysqli_data_seek($transactions_result, 0);
                    while ($transaction = mysqli_fetch_assoc($transactions_result)): 
                    ?>
                    <tr>
                        <td><?php echo format_date($transaction['transaction_date']); ?></td>
                        <td><?php echo htmlspecialchars($transaction['business_name']); ?></td>
                        <td><?php echo htmlspecialchars($transaction['owner_first_name'] . ' ' . $transaction['owner_last_name']); ?></td>
                        <td><?php echo htmlspecialchars($transaction['category_name']); ?></td>
                        <td>
                            <span class="<?php echo $transaction['type']; ?>">
                                <?php echo ucfirst($transaction['type']); ?>
                            </span>
                        </td>
                        <td class="<?php echo $transaction['type']; ?>">
                            <?php echo format_currency($transaction['amount']); ?>
                        </td>
                        <td><?php echo htmlspecialchars(truncate_text($transaction['description'], 50)); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <div class="page-break">
            <h2>Report Information</h2>
            <table class="summary">
                <tr>
                    <td><strong>Date Range:</strong></td>
                    <td><?php echo format_date($date_from) . ' to ' . format_date($date_to); ?></td>
                </tr>
                <?php if ($business_filter > 0): ?>
                <tr>
                    <td><strong>Business Filter:</strong></td>
                    <td>
                        <?php 
                        $business_result = mysqli_query($conn, "SELECT name FROM businesses WHERE id = $business_filter");
                        $business = mysqli_fetch_assoc($business_result);
                        echo htmlspecialchars($business['name']);
                        ?>
                    </td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td><strong>Accountant:</strong></td>
                    <td><?php echo htmlspecialchars($current_user['full_name']); ?></td>
                </tr>
                <tr>
                    <td><strong>Email:</strong></td>
                    <td><?php echo htmlspecialchars($current_user['email']); ?></td>
                </tr>
                <tr>
                    <td><strong>Generated On:</strong></td>
                    <td><?php echo format_datetime(date('Y-m-d H:i:s')); ?></td>
                </tr>
                <tr>
                    <td><strong>Total Assigned Businesses:</strong></td>
                    <td><?php echo mysqli_num_rows($business_performance_result); ?></td>
                </tr>
            </table>
        </div>

        <div class="page-break">
            <h2>Accountant Notes</h2>
            <table class="summary">
                <tr>
                    <td colspan="2">
                        <strong>Key Observations:</strong><br><br>
                        • Total portfolio performance: <?php echo ($stats['total_income'] - $stats['total_expenses']) >= 0 ? 'Profitable' : 'Loss-making'; ?><br>
                        • Most profitable business: 
                        <?php 
                        mysqli_data_seek($business_performance_result, 0);
                        $top_business = mysqli_fetch_assoc($business_performance_result);
                        if ($top_business) {
                            echo htmlspecialchars($top_business['business_name']);
                        } else {
                            echo 'N/A';
                        }
                        ?><br>
                        • Average transaction value: <?php echo format_currency(($stats['total_income'] + $stats['total_expenses']) / max($stats['total_transactions'], 1)); ?><br>
                        • Income vs Expense ratio: <?php echo $stats['total_expenses'] > 0 ? number_format($stats['total_income'] / $stats['total_expenses'], 2) . ':1' : 'N/A'; ?><br><br>
                        
                        <strong>Recommendations:</strong><br><br>
                        <?php if (($stats['total_income'] - $stats['total_expenses']) < 0): ?>
                        • Focus on cost reduction strategies across assigned businesses<br>
                        • Review expense categories for optimization opportunities<br>
                        • Implement revenue enhancement initiatives<br>
                        <?php else: ?>
                        • Continue current profitable strategies<br>
                        • Identify growth opportunities in top-performing businesses<br>
                        • Monitor expense trends to maintain profitability<br>
                        <?php endif; ?>
                        • Regular financial review meetings with business owners<br>
                        • Implement better expense tracking and categorization<br><br>
                        
                        <em>This report is confidential and intended for authorized personnel only.</em>
                    </td>
                </tr>
            </table>
        </div>

        <script>
            // Auto-print for PDF
            window.onload = function() {
                window.print();
            }
        </script>
    </body>
    </html>
    <?php
}

mysqli_close($conn);
?>
