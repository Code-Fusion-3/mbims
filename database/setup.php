<?php
// error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/database.php';

class DatabaseSetup
{
    private $connection;

    public function __construct()
    {
        $this->connection = connect_db();
        select_database($this->connection);
    }

    // Create all tables
    public function createTables()
    {
        echo "<h2>Creating Database Tables...</h2>";

        $this->createUsersTable();
        $this->createBusinessesTable();
        $this->createCategoriesTable();
        $this->createTransactionsTable();
        $this->createUserBusinessAssignmentsTable();
        $this->insertDefaultData();

        echo "<h3>Database setup completed successfully!</h3>";
    }

    // Create users table
    private function createUsersTable()
    {
        $sql = "CREATE TABLE IF NOT EXISTS users (
            id INT PRIMARY KEY AUTO_INCREMENT,
            email VARCHAR(255) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            phone VARCHAR(20) NULL DEFAULT NULL,
            role ENUM('admin', 'partner', 'accountant') NOT NULL,
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";

        if (mysqli_query($this->connection, $sql)) {
            echo "✓ Users table created or already exists<br>";

            // Check if 'phone' column exists and add it if not
            $check_column_sql = "SHOW COLUMNS FROM `users` LIKE 'phone'";
            $result = mysqli_query($this->connection, $check_column_sql);
            if (mysqli_num_rows($result) == 0) {
                $alter_sql = "ALTER TABLE `users` ADD `phone` VARCHAR(20) NULL DEFAULT NULL AFTER `last_name`";
                if (mysqli_query($this->connection, $alter_sql)) {
                    echo "✓ 'phone' column added to users table.<br>";
                } else {
                    echo "✗ Error adding 'phone' column: " . mysqli_error($this->connection) . "<br>";
                }
            }
        } else {
            echo "✗ Error creating users table: " . mysqli_error($this->connection) . "<br>";
        }
    }

    // Create businesses table
    private function createBusinessesTable()
    {
        $sql = "CREATE TABLE IF NOT EXISTS businesses (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            business_type VARCHAR(100),
            owner_id INT,
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL
        )";

        if (mysqli_query($this->connection, $sql)) {
            echo "✓ Businesses table created successfully<br>";
        } else {
            echo "✗ Error creating businesses table: " . mysqli_error($this->connection) . "<br>";
        }
    }

    // Create categories table
    private function createCategoriesTable()
    {
        $sql = "CREATE TABLE IF NOT EXISTS categories (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            type ENUM('income', 'expense') NOT NULL,
            description TEXT,
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";

        if (mysqli_query($this->connection, $sql)) {
            echo "✓ Categories table created successfully<br>";
        } else {
            echo "✗ Error creating categories table: " . mysqli_error($this->connection) . "<br>";
        }
    }

    // Create transactions table
    private function createTransactionsTable()
    {
        $sql = "CREATE TABLE IF NOT EXISTS transactions (
            id INT PRIMARY KEY AUTO_INCREMENT,
            business_id INT NOT NULL,
            category_id INT NOT NULL,
            user_id INT NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            type ENUM('income', 'expense') NOT NULL,
            description TEXT,
            transaction_date DATE NOT NULL,
            status ENUM('active', 'deleted') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
            FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
        )";

        if (mysqli_query($this->connection, $sql)) {
            echo "✓ Transactions table created successfully<br>";
        } else {
            echo "✗ Error creating transactions table: " . mysqli_error($this->connection) . "<br>";
        }
    }

    // Create user business assignments table
    private function createUserBusinessAssignmentsTable()
    {
        $sql = "CREATE TABLE IF NOT EXISTS user_business_assignments (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            business_id INT NOT NULL,
            permission_level ENUM('read', 'write', 'admin') DEFAULT 'read',
            assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
            UNIQUE KEY unique_assignment (user_id, business_id)
        )";

        if (mysqli_query($this->connection, $sql)) {
            echo "✓ User business assignments table created successfully<br>";
        } else {
            echo "✗ Error creating user business assignments table: " . mysqli_error($this->connection) . "<br>";
        }
    }

    // Insert default data
    private function insertDefaultData()
    {
        echo "<br><h3>Inserting Default Data...</h3>";

        // Insert default admin user
        $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
        $sql = "INSERT IGNORE INTO users (email, password, first_name, last_name, role, phone) 
                VALUES ('admin@mbims.com', '$admin_password', 'System', 'Administrator', 'admin', '0700000000')";

        if (mysqli_query($this->connection, $sql)) {
            echo "✓ Default admin user created (admin@mbims.com / admin123)<br>";
        } else {
            echo "✗ Error creating admin user: " . mysqli_error($this->connection) . "<br>";
        }

        // Insert default categories
        $categories = [
            ['Sales Revenue', 'income', 'Revenue from sales'],
            ['Service Revenue', 'income', 'Revenue from services'],
            ['Investment Income', 'income', 'Income from investments'],
            ['Other Income', 'income', 'Miscellaneous income'],
            ['Office Supplies', 'expense', 'Office supplies and materials'],
            ['Marketing', 'expense', 'Marketing and advertising expenses'],
            ['Utilities', 'expense', 'Electricity, water, internet'],
            ['Rent', 'expense', 'Office or business rent'],
            ['Salaries', 'expense', 'Employee salaries and wages'],
            ['Travel', 'expense', 'Business travel expenses'],
            ['Other Expenses', 'expense', 'Miscellaneous expenses']
        ];

        foreach ($categories as $category) {
            $sql = "INSERT IGNORE INTO categories (name, type, description) 
                    VALUES ('{$category[0]}', '{$category[1]}', '{$category[2]}')";
            mysqli_query($this->connection, $sql);
        }
        echo "✓ Default categories inserted<br>";
    }

    // Drop all tables (for reset)
    public function dropTables()
    {
        echo "<h2>Dropping All Tables...</h2>";

        $tables = ['user_business_assignments', 'transactions', 'categories', 'businesses', 'users'];

        foreach ($tables as $table) {
            $sql = "DROP TABLE IF EXISTS $table";
            if (mysqli_query($this->connection, $sql)) {
                echo "✓ Table $table dropped<br>";
            } else {
                echo "✗ Error dropping table $table: " . mysqli_error($this->connection) . "<br>";
            }
        }
    }

    // Check if tables exist
    public function checkTables()
    {
        echo "<h2>Checking Database Tables...</h2>";

        $tables = ['users', 'businesses', 'categories', 'transactions', 'user_business_assignments'];

        foreach ($tables as $table) {
            $sql = "SHOW TABLES LIKE '$table'";
            $result = mysqli_query($this->connection, $sql);

            if (mysqli_num_rows($result) > 0) {
                echo "✓ Table $table exists<br>";
            } else {
                echo "✗ Table $table does not exist<br>";
            }
        }
    }

    public function __destruct()
    {
        close_db($this->connection);
    }
}

// If accessed directly, run setup
if (basename($_SERVER['PHP_SELF']) == 'setup.php') {
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>MBIMS Database Setup</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; }
            h2, h3 { color: #333; }
            .success { color: green; }
            .error { color: red; }
        </style>
    </head>
    <body>";

    echo "<h1>MBIMS Database Setup</h1>";

    $setup = new DatabaseSetup();

    // Check what action to perform
    $action = isset($_GET['action']) ? $_GET['action'] : 'create';

    switch ($action) {
        case 'drop':
            $setup->dropTables();
            break;
        case 'check':
            $setup->checkTables();
            break;
        case 'create':
        default:
            $setup->createTables();
            break;
    }

    echo "<br><hr>";
    echo "<p><a href='setup.php?action=create'>Create Tables</a> | ";
    echo "<a href='setup.php?action=check'>Check Tables</a> | ";
    echo "<a href='setup.php?action=drop'>Drop Tables</a></p>";
    echo "</body></html>";
}
?>