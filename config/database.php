<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', ''); // Change this to your MySQL password
define('DB_NAME', 'mbims_db');

// Database connection function (without selecting database)
function connect_db() {
    $connection = mysqli_connect(DB_HOST, DB_USER, DB_PASS);
    
    if (!$connection) {
        die("Connection failed: " . mysqli_connect_error());
    }
    
    return $connection;
}

// Function to create and select database
function select_database($connection) {
    // First, create database if it doesn't exist
    $sql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME;
    if (mysqli_query($connection, $sql)) {
        echo "Database '" . DB_NAME . "' created successfully or already exists.<br>";
    } else {
        die("Error creating database: " . mysqli_error($connection));
    }
    
    // Now select the database
    $db_selected = mysqli_select_db($connection, DB_NAME);
    
    if (!$db_selected) {
        die("Error selecting database: " . mysqli_error($connection));
    }
    
    return true;
}

// Function to close database connection
function close_db($connection) {
    mysqli_close($connection);
}

// Test database connection and creation
function test_connection() {
    $conn = connect_db();
    if ($conn) {
        echo "MySQL connection successful!<br>";
        select_database($conn);
        echo "Database operations successful!<br>";
        close_db($conn);
        return true;
    }
    return false;
}

// Function to connect directly to the MBIMS database (use after database is created)
function connect_mbims_db() {
    $connection = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if (!$connection) {
        die("Connection to MBIMS database failed: " . mysqli_connect_error());
    }
    
    return $connection;
}
?>