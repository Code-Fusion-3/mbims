<?php
require_once '../config/database.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>MBIMS Database Initialization</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background-color: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .info { color: #007bff; }
        .step { margin: 15px 0; padding: 10px; background: #f8f9fa; border-left: 4px solid #007bff; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>🚀 MBIMS Database Initialization</h1>";

echo "<div class='step'>";
echo "<h3>Step 1: Testing MySQL Connection</h3>";

try {
    $conn = connect_db();
    echo "<p class='success'>✓ MySQL server connection successful!</p>";
    echo "<p class='info'>Host: " . DB_HOST . "</p>";
    echo "<p class='info'>User: " . DB_USER . "</p>";
    
    echo "<h3>Step 2: Creating Database</h3>";
    select_database($conn);
    echo "<p class='success'>✓ Database '" . DB_NAME . "' is ready!</p>";
    
    echo "<h3>Step 3: Testing Database Connection</h3>";
    close_db($conn);
    
    // Test direct connection to our database
    $mbims_conn = connect_mbims_db();
    echo "<p class='success'>✓ Direct connection to MBIMS database successful!</p>";
    close_db($mbims_conn);
    
    echo "<h3>🎉 Database initialization completed successfully!</h3>";
    echo "<p><strong>Next steps:</strong></p>";
    echo "<ul>";
    echo "<li><a href='setup.php'>Run Database Setup (Create Tables)</a></li>";
    echo "<li><a href='../test_db.php'>Test Database Connection</a></li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<p class='error'>✗ Error: " . $e->getMessage() . "</p>";
    echo "<h3>🔧 Troubleshooting:</h3>";
    echo "<ul>";
    echo "<li>Make sure MySQL service is running: <code>sudo service mysql start</code></li>";
    echo "<li>Check your MySQL credentials in config/database.php</li>";
    echo "<li>Make sure you have permission to create databases</li>";
    echo "</ul>";
}

echo "</div>";
echo "</div></body></html>";
?>