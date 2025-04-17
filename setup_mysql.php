<?php
/**
 * MySQL Database Setup Script
 * Creates the necessary tables for the Astra C2 system
 * 
 * Usage: Update the database credentials below and run this script
 * php setup_mysql.php
 */

// Load environment from .env file if it exists
$envFile = __DIR__ . '/public_html/c2/config/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Parse line
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            
            // Remove quotes if present
            if (preg_match('/^([\'"])(.*)\1$/', $value, $matches)) {
                $value = $matches[2];
            }
            
            putenv("$name=$value");
        }
    }
    echo "Loaded configuration from .env file\n";
} else {
    echo "No .env file found, using default or provided credentials\n";
}

// Database configuration
$dbHost = getenv('DB_HOST') ?: '193.203.184.212';
$dbPort = getenv('DB_PORT') ?: '3306';
$dbName = getenv('DB_NAME') ?: 'your_database_name';
$dbUser = getenv('DB_USER') ?: 'your_database_user';
$dbPass = getenv('DB_PASSWORD') ?: 'your_database_password';

echo "Connecting to MySQL database at $dbHost:$dbPort\n";
echo "Database: $dbName\n";
echo "User: $dbUser\n";

try {
    // Connect to MySQL
    $conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName, $dbPort);
    
    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    echo "Connected successfully to MySQL database\n";
    
    // Set charset
    $conn->set_charset("utf8mb4");
    
    // Drop tables if they exist
    $tables = ['logs', 'commands', 'devices', 'admins'];
    foreach ($tables as $table) {
        $conn->query("DROP TABLE IF EXISTS $table");
        echo "- Dropped table if exists: $table\n";
    }
    
    // Create devices table
    $conn->query("
        CREATE TABLE devices (
            id INT AUTO_INCREMENT PRIMARY KEY,
            device_id VARCHAR(64) NOT NULL UNIQUE,
            name VARCHAR(255) NOT NULL,
            os VARCHAR(100) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            auth_token VARCHAR(255) NOT NULL,
            last_seen DATETIME NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "- Created devices table\n";
    
    // Create commands table
    $conn->query("
        CREATE TABLE commands (
            id INT AUTO_INCREMENT PRIMARY KEY,
            device_id VARCHAR(64) NOT NULL,
            command TEXT NOT NULL,
            issued_at DATETIME NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            output TEXT,
            FOREIGN KEY (device_id) REFERENCES devices(device_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "- Created commands table\n";
    
    // Create logs table
    $conn->query("
        CREATE TABLE logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            device_id VARCHAR(64) NOT NULL,
            type VARCHAR(50) NOT NULL,
            content TEXT NOT NULL,
            timestamp DATETIME NOT NULL,
            FOREIGN KEY (device_id) REFERENCES devices(device_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "- Created logs table\n";
    
    // Create admins table
    $conn->query("
        CREATE TABLE admins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "- Created admins table\n";
    
    // Create indexes
    $indexes = [
        "CREATE INDEX idx_devices_last_seen ON devices(last_seen)",
        "CREATE INDEX idx_devices_status ON devices(status)",
        "CREATE INDEX idx_commands_device_id ON commands(device_id)",
        "CREATE INDEX idx_commands_status ON commands(status)",
        "CREATE INDEX idx_logs_device_id ON logs(device_id)",
        "CREATE INDEX idx_logs_type ON logs(type)",
        "CREATE INDEX idx_logs_timestamp ON logs(timestamp)"
    ];
    
    foreach ($indexes as $index) {
        $conn->query($index);
    }
    echo "- Created indexes\n";
    
    // Insert default admin user
    $defaultAdmin = getenv('DEFAULT_ADMIN_USER') ?: 'admin';
    $defaultPassHash = getenv('DEFAULT_ADMIN_PASS_HASH') ?: '$2y$10$RbkPDmVbZ7XNiKhQSu3t1.X4aAQqzEM8SePXb3UTcH1yp3H7BbpjO'; // admin123
    
    $stmt = $conn->prepare("
        INSERT INTO admins (username, password_hash, created_at) 
        VALUES (?, ?, NOW())
    ");
    $stmt->bind_param("ss", $defaultAdmin, $defaultPassHash);
    $stmt->execute();
    echo "- Added default admin user (username: $defaultAdmin, password: admin123)\n";
    
    echo "\nMySQL database setup complete!\n";
    
} catch (Exception $e) {
    die("Database setup failed: " . $e->getMessage() . "\n");
}
?>