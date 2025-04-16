-- Astra C2 Database Setup for SQLite

-- Create tables if they don't exist
CREATE TABLE IF NOT EXISTS devices (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    device_id TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    os TEXT NOT NULL,
    ip_address TEXT NOT NULL,
    auth_token TEXT NOT NULL,
    last_seen DATETIME NOT NULL,
    status TEXT NOT NULL DEFAULT 'active'
);

CREATE TABLE IF NOT EXISTS commands (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    device_id TEXT NOT NULL,
    command TEXT NOT NULL,
    issued_at DATETIME NOT NULL,
    status TEXT NOT NULL DEFAULT 'pending',
    output TEXT,
    FOREIGN KEY (device_id) REFERENCES devices(device_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    device_id TEXT NOT NULL,
    type TEXT NOT NULL,
    content TEXT NOT NULL,
    timestamp DATETIME NOT NULL,
    FOREIGN KEY (device_id) REFERENCES devices(device_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS admins (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Create indexes for performance
CREATE INDEX IF NOT EXISTS idx_devices_last_seen ON devices(last_seen);
CREATE INDEX IF NOT EXISTS idx_devices_status ON devices(status);
CREATE INDEX IF NOT EXISTS idx_commands_device_id ON commands(device_id);
CREATE INDEX IF NOT EXISTS idx_commands_status ON commands(status);
CREATE INDEX IF NOT EXISTS idx_logs_device_id ON logs(device_id);
CREATE INDEX IF NOT EXISTS idx_logs_type ON logs(type);
CREATE INDEX IF NOT EXISTS idx_logs_timestamp ON logs(timestamp);

-- Insert default admin if the table is empty
INSERT INTO admins (username, password_hash, created_at)
SELECT 'admin', '$2y$10$RbkPDmVbZ7XNiKhQSu3t1.X4aAQqzEM8SePXb3UTcH1yp3H7BbpjO', CURRENT_TIMESTAMP
WHERE NOT EXISTS (SELECT 1 FROM admins LIMIT 1);