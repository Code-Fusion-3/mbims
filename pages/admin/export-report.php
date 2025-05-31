<?php
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';
require_once '../../config/database.php';

// Require admin role
require_role('admin');

// Get export format
$export_format = isset($_GET['export']) ? sanitize_input($_GET['export']) : 'pdf';

// Get the same filter parameters as reports.php
$date_from = isset($_GET['date_from']) ? sanitize_input($_GET['date_from']) : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? sanitize_input($_GET['date_to']) : date('Y-m-t');
$business_filter = isset($_GET['business']) ? (int)$_GET['business'] : 0;
$user_filter = isset($_GET['user']) ? (int)$_GET['user'] : 0;

// Get database connection
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Build WHERE conditions (same as reports.php)
$where_conditions = ["t.status = 'active'"];

if (!empty($date_from)) {
    $where_conditions[] = "t.transaction_date >= '$date_from'";
}

if (!empty($date_to)) {
    $where_conditions[] = "t.transaction_date <= '$date_to'";
}

if ($business_filter > 0) {
    $where_conditions[] = "t.business_id = $business_filter";
}

if ($user_filter > 0) {
    $where_conditions[] = "t.user_id = $user_filter";
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
                     u.first_name,
                     u.last_name
                     FROM transactions t
                     JOIN businesses b ON t.business_id = b.id
                     JOIN categories c ON t.category_id = c.id
                     JOIN users u ON t.user_id = u.id
                     WHERE $where_clause
                     ORDER BY t.transaction_date DESC, t.created_at DESC";

$transactions_result = mysqli_query($conn, $transactions_sql);

if ($export_format == 'excel') {
    // Excel Export
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="financial_report_' . date('Y-m-d') . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    echo "<table border='1'>";
    echo "<tr><th colspan='8' style='font-size: 16px; font-weight: bold;'>Financial Report</th></tr>";
    echo "<tr><th colspan='8'>Period: " . format_date($date_from) . " to " . format_date($date_to) . "</th></tr>";
    echo "<tr><th colspan='8'>&nbsp;</th></tr>";
    
    // Summary
    echo "<tr><th colspan='8' style='background-color: #f0f0f0;'>SUMMARY</th></tr>";
    echo "<tr><td>Total Income</td><td colspan='7'>" . format_currency($stats['total_income']) . "</td></tr>";
    echo "<tr><td>Total Expenses</td><td colspan='7'>" . format_currency($stats['total_expenses']) . "</td></tr>";
    echo "<tr><td>Net Profit/Loss</td><td colspan='7'>" . format_currency($stats['total_income'] - $stats['total_expenses']) . "</td></tr>";
    echo "<tr><td>Total Transactions</td><td colspan='7'>" . $stats['total_transactions'] . "</td></tr>";
    echo "<tr><th colspan='8'>&nbsp;</th></tr>";
    
        // Transactions
    echo "<tr><th colspan='8' style='background-color: #f0f0f0;'>TRANSACTIONS</th></tr>";
    echo "<tr>";
    echo "<th>Date</th>";
    echo "<th>Business</th>";
    echo "<th>Category</th>";
    echo "<th>Type</th>";
    echo "<th>Amount</th>";
    echo "<th>Description</th>";
    echo "<th>User</th>";
    echo "<th>Created</th>";
    echo "</tr>";
    
    while ($transaction = mysqli_fetch_assoc($transactions_result)) {
        echo "<tr>";
        echo "<td>" . format_date($transaction['transaction_date']) . "</td>";
        echo "<td>" . htmlspecialchars($transaction['business_name']) . "</td>";
        echo "<td>" . htmlspecialchars($transaction['category_name']) . "</td>";
        echo "<td>" . ucfirst($transaction['type']) . "</td>";
        echo "<td>" . format_currency($transaction['amount']) . "</td>";
        echo "<td>" . htmlspecialchars($transaction['description']) . "</td>";
        echo "<td>" . htmlspecialchars($transaction['first_name'] . ' ' . $transaction['last_name']) . "</td>";
        echo "<td>" . format_datetime($transaction['created_at']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} else {
    // PDF Export (HTML to PDF)
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Financial Report</title>
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
            .summary table {
                width: 100%;
                border-collapse: collapse;
            }
            .summary th, .summary td {
                border: 1px solid #ddd;
                padding: 8px;
                text-align: left;
            }
            .summary th {
                background-color: #f5f5f5;
                font-weight: bold;
            }
            .transactions {
                margin-top: 30px;
            }
            .transactions table {
                width: 100%;
                border-collapse: collapse;
                font-size: 10px;
            }
            .transactions th, .transactions td {
                border: 1px solid #ddd;
                padding: 6px;
                text-align: left;
            }
            .transactions th {
                background-color: #f5f5f5;
                font-weight: bold;
            }
            .income { color: #059669; }
            .expense { color: #dc2626; }
            .profit { color: #059669; font-weight: bold; }
            .loss { color: #dc2626; font-weight: bold; }
            .page-break { page-break-before: always; }
            @media print {
                body { margin: 0; }
                .no-print { display: none; }
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>Financial Report</h1>
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
                    <td>Total Income</td>
                    <td class="income"><?php echo format_currency($stats['total_income']); ?></td>
                    <td><?php echo $stats['income_transactions']; ?> transactions</td>
                </tr>
                <tr>
                    <td>Total Expenses</td>
                    <td class="expense"><?php echo format_currency($stats['total_expenses']); ?></td>
                    <td><?php echo $stats['expense_transactions']; ?> transactions</td>
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
            </table>
        </div>

        <?php if ($stats['total_transactions'] > 0): ?>
        <div class="transactions page-break">
            <h2>Transaction Details</h2>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Business</th>
                        <th>Category</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Description</th>
                        <th>User</th>
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
                        <td><?php echo htmlspecialchars($transaction['first_name'] . ' ' . $transaction['last_name']); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <div class="page-break">
            <h2>Report Filters Applied</h2>
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
                <?php if ($user_filter > 0): ?>
                <tr>
                    <td><strong>User Filter:</strong></td>
                    <td>
                        <?php 
                        $user_result = mysqli_query($conn, "SELECT first_name, last_name FROM users WHERE id = $user_filter");
                        $user = mysqli_fetch_assoc($user_result);
                        echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']);
                        ?>
                    </td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td><strong>Generated By:</strong></td>
                    <td><?php echo htmlspecialchars($current_user['full_name']); ?></td>
                </tr>
                <tr>
                    <td><strong>Generated On:</strong></td>
                    <td><?php echo format_datetime(date('Y-m-d H:i:s')); ?></td>
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
