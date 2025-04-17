-- Astra C2 - MySQL Database Setup Script
-- This script creates the necessary tables for the Astra C2 system
-- Execute this script on your MySQL database using phpMyAdmin or the MySQL command line

-- Drop tables if they exist to avoid conflicts (BE CAREFUL WITH THIS IN PRODUCTION)
DROP TABLE IF EXISTS logs;
DROP TABLE IF EXISTS commands;
DROP TABLE IF EXISTS devices;
DROP TABLE IF EXISTS admins;

-- Create devices table
CREATE TABLE devices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id VARCHAR(64) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    os VARCHAR(100) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    auth_token VARCHAR(255) NOT NULL,
    last_seen DATETIME NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create commands table
CREATE TABLE commands (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id VARCHAR(64) NOT NULL,
    command TEXT NOT NULL,
    issued_at DATETIME NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    output TEXT,
    FOREIGN KEY (device_id) REFERENCES devices(device_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create logs table
CREATE TABLE logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id VARCHAR(64) NOT NULL,
    type VARCHAR(50) NOT NULL,
    content TEXT NOT NULL,
    timestamp DATETIME NOT NULL,
    FOREIGN KEY (device_id) REFERENCES devices(device_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create admins table
CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create indexes for better performance
CREATE INDEX idx_devices_last_seen ON devices(last_seen);
CREATE INDEX idx_devices_status ON devices(status);
CREATE INDEX idx_commands_device_id ON commands(device_id);
CREATE INDEX idx_commands_status ON commands(status);
CREATE INDEX idx_logs_device_id ON logs(device_id);
CREATE INDEX idx_logs_type ON logs(type);
CREATE INDEX idx_logs_timestamp ON logs(timestamp);

-- Insert default admin user (username: admin, password: admin123)
INSERT INTO admins (username, password_hash, created_at)
VALUES ('admin', '$2y$10$RbkPDmVbZ7XNiKhQSu3t1.X4aAQqzEM8SePXb3UTcH1yp3H7BbpjO', NOW());