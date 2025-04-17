<?php
/**
 * SQLite Database Setup Script
 * Creates the necessary tables for the Astra C2 system
 */

// Define the database path
$dbPath = __DIR__ . '/astra_c2.db';
$absolutePath = realpath($dbPath) ?: $dbPath;
echo "Setting up SQLite database at: $dbPath\n";
echo "Absolute path: $absolutePath\n";
echo "Current directory: " . getcwd() . "\n";

try {
    // Connect to SQLite database
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Enable foreign keys
    $db->exec('PRAGMA foreign_keys = ON;');
    
    // Create devices table
    $db->exec("
        CREATE TABLE IF NOT EXISTS devices (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            device_id TEXT NOT NULL UNIQUE,
            name TEXT NOT NULL,
            os TEXT NOT NULL,
            ip_address TEXT NOT NULL,
            auth_token TEXT NOT NULL,
            last_seen DATETIME NOT NULL,
            status TEXT NOT NULL DEFAULT 'active'
        )
    ");
    echo "- Created devices table\n";
    
    // Create commands table
    $db->exec("
        CREATE TABLE IF NOT EXISTS commands (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            device_id TEXT NOT NULL,
            command TEXT NOT NULL,
            issued_at DATETIME NOT NULL,
            status TEXT NOT NULL DEFAULT 'pending',
            output TEXT,
            FOREIGN KEY (device_id) REFERENCES devices(device_id) ON DELETE CASCADE
        )
    ");
    echo "- Created commands table\n";
    
    // Create logs table
    $db->exec("
        CREATE TABLE IF NOT EXISTS logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            device_id TEXT NOT NULL,
            type TEXT NOT NULL,
            content TEXT NOT NULL,
            timestamp DATETIME NOT NULL,
            FOREIGN KEY (device_id) REFERENCES devices(device_id) ON DELETE CASCADE
        )
    ");
    echo "- Created logs table\n";
    
    // Create admins table
    $db->exec("
        CREATE TABLE IF NOT EXISTS admins (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "- Created admins table\n";
    
    // Create indexes
    $db->exec("CREATE INDEX IF NOT EXISTS idx_devices_last_seen ON devices(last_seen)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_devices_status ON devices(status)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_commands_device_id ON commands(device_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_commands_status ON commands(status)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_logs_device_id ON logs(device_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_logs_type ON logs(type)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_logs_timestamp ON logs(timestamp)");
    echo "- Created indexes\n";
    
    // Insert default admin if the table is empty
    $checkAdmin = $db->query("SELECT COUNT(*) FROM admins");
    $adminCount = $checkAdmin->fetchColumn();
    
    if ($adminCount == 0) {
        $db->exec("
            INSERT INTO admins (username, password_hash, created_at)
            VALUES ('admin', '\$2y\$10\$RbkPDmVbZ7XNiKhQSu3t1.X4aAQqzEM8SePXb3UTcH1yp3H7BbpjO', datetime('now'))
        ");
        echo "- Added default admin user (username: admin, password: admin123)\n";
    }
    
    echo "\nDatabase setup complete!\n";
    
} catch (PDOException $e) {
    die("Database setup failed: " . $e->getMessage() . "\n");
}
?>