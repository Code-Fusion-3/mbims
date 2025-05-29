<?php
require_once 'config/database.php';
require_once 'config/config.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>MBIMS Database Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background-color: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #28a745; padding-bottom: 10px; }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .info { color: #007bff; }
        .test { margin: 15px 0; padding: 15px; background: #f8f9fa; border-radius: 5px; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>🧪 MBIMS Database Connection Test</h1>";

echo "<div class='test'>";
echo "<h3>Test 1: Basic MySQL Connection</h3>";

try {
    $conn = connect_db();
    echo "<p class='success'>✓ MySQL connection successful!</p>";
    echo "<p class='info'>Connected to: " . DB_HOST . " as " . DB_USER . "</p>";
    
    echo "<h3>Test 2: Database Creation/Selection</h3>";
    select_database($conn);
    echo "<p class='success'>✓ Database '" . DB_NAME . "' ready!</p>";
    
    echo "<h3>Test 3: Database Operations</h3>";
    // Test a simple query
    $result = mysqli_query($conn, "SELECT DATABASE() as current_db");
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        echo "<p class='success'>✓ Currently using database: " . $row['current_db'] . "</p>";
    }
    
    // Check if we can create a test table
    $test_sql = "CREATE TABLE IF NOT EXISTS connection_test (id INT PRIMARY KEY AUTO_INCREMENT, test_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP)";
    if (mysqli_query($conn, $test_sql)) {
        echo "<p class='success'>✓ Table creation test passed</p>";
        
        // Clean up test table
        mysqli_query($conn, "DROP TABLE connection_test");
        echo "<p class='info'>Test table cleaned up</p>";
    }
    
    close_db($conn);
    
    echo "<h3>✅ All tests passed!</h3>";
    echo "<p><strong>Your database is ready. Next steps:</strong></p>";
    echo "<ul>";
    echo "<li><a href='database/setup.php'>Create Database Tables</a></li>";
    echo "<li><a href='database/init.php'>View Database Info</a></li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<p class='error'>✗ Database test failed: " . $e->getMessage() . "</p>";
    echo "<h3>🔧 Troubleshooting Steps:</h3>";
    echo "<ol>";
    echo "<li>Check if MySQL is running: <code>sudo service mysql status</code></li>";
    echo "<li>Start MySQL if needed: <code>sudo service mysql start</code></li>";
    echo "<li>Verify MySQL credentials in config/database.php</li>";
    echo "<li>Try running: <a href='database/init.php'>Database Initialization</a></li>";
    echo "</ol>";
}

echo "</div>";
echo "</div></body></html>";
?>