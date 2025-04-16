<?php
/**
 * Device Registration API
 * Registers Android clients in the system
 */

// Define included constant to prevent direct access to includes
define('INCLUDED', true);

// Include required files
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(['error' => 'Method not allowed'], 405);
}

// Get and validate input
$deviceName = isset($_POST['device_name']) ? sanitizeInput($_POST['device_name']) : '';
$os = isset($_POST['os']) ? sanitizeInput($_POST['os']) : '';
$ipAddress = isset($_POST['ip_address']) ? sanitizeInput($_POST['ip_address']) : getClientIp();

// Validate inputs
if (empty($deviceName) || empty($os)) {
    sendJsonResponse(['error' => 'Missing required parameters'], 400);
}

// Validate IP address
if (!validateIp($ipAddress)) {
    $ipAddress = getClientIp(); // Fallback to client IP if provided IP is invalid
}

// Generate device ID and auth token
$deviceId = generateDeviceId();
$authToken = generateToken();

// Connect to database
$db = Database::getInstance();

// Check if device already exists with the same name (optional)
$stmt = $db->executeQuery(
    "SELECT device_id FROM devices WHERE name = ?",
    "s",
    [$deviceName]
);

if ($stmt !== false) {
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Device with this name already exists, you might want to update instead
        $row = $result->fetch_assoc();
        $existingDeviceId = $row['device_id'];
        
        // Update existing device
        $updateStmt = $db->executeQuery(
            "UPDATE devices SET os = ?, ip_address = ?, last_seen = NOW(), auth_token = ? WHERE device_id = ?",
            "ssss",
            [$os, $ipAddress, $authToken, $existingDeviceId]
        );
        
        if ($updateStmt !== false) {
            $updateStmt->close();
            $stmt->close();
            sendJsonResponse([
                'device_id' => $existingDeviceId,
                'auth_token' => $authToken,
                'status' => 'updated'
            ]);
        }
    }
    
    $stmt->close();
}

// Insert new device
$stmt = $db->executeQuery(
    "INSERT INTO devices (device_id, name, os, ip_address, auth_token, last_seen, status) VALUES (?, ?, ?, ?, ?, NOW(), 'active')",
    "sssss",
    [$deviceId, $deviceName, $os, $ipAddress, $authToken]
);

if ($stmt === false) {
    sendJsonResponse(['error' => 'Failed to register device'], 500);
}

$stmt->close();

// Log the registration
logMessage("New device registered: $deviceName ($deviceId)");

// Return device ID and auth token
sendJsonResponse([
    'device_id' => $deviceId,
    'auth_token' => $authToken,
    'status' => 'registered'
]);
?>
