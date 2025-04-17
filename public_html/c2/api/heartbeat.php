<?php
/**
 * Heartbeat API
 * Updates device's last seen timestamp
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
$deviceId = isset($_POST['device_id']) ? sanitizeInput($_POST['device_id']) : '';
$authToken = isset($_POST['auth_token']) ? sanitizeInput($_POST['auth_token']) : '';

// Validate inputs
if (empty($deviceId) || empty($authToken)) {
    sendJsonResponse(['error' => 'Missing required parameters'], 400);
}

// Verify device authentication
if (!verifyDeviceAuth($deviceId, $authToken)) {
    sendJsonResponse(['error' => 'Authentication failed'], 401);
}

// Connect to database
$db = Database::getInstance();

// Update last seen timestamp
$stmt = $db->executeQuery(
    "UPDATE devices SET last_seen = NOW() WHERE device_id = ?",
    "s",
    [$deviceId]
);

if ($stmt === false) {
    sendJsonResponse(['error' => 'Failed to update heartbeat'], 500);
}

$affected = $stmt->affected_rows;
$stmt->close();

if ($affected === 0) {
    sendJsonResponse(['error' => 'Device not found'], 404);
}

// Return success response
sendJsonResponse([
    'status' => 'success',
    'message' => 'Heartbeat updated',
    'timestamp' => date('Y-m-d H:i:s')
]);
?>
