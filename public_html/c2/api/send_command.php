<?php
/**
 * Send Command API
 * Used by admin panel to send commands to devices
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

// Start session for admin authentication
session_start();

// Verify admin authentication
if (!isAdminAuthenticated()) {
    sendJsonResponse(['error' => 'Not authorized'], 401);
}

// Get and validate input
$deviceId = isset($_POST['device_id']) ? sanitizeInput($_POST['device_id']) : '';
$command = isset($_POST['command']) ? sanitizeInput($_POST['command']) : '';

// Validate inputs
if (empty($deviceId) || empty($command)) {
    sendJsonResponse(['error' => 'Missing required parameters'], 400);
}

// Connect to database
$db = Database::getInstance();

// Check if device exists
$checkStmt = $db->executeQuery(
    "SELECT COUNT(*) FROM devices WHERE device_id = ?",
    "s",
    [$deviceId]
);

if ($checkStmt === false) {
    sendJsonResponse(['error' => 'Failed to verify device'], 500);
}

$result = $checkStmt->get_result();
$deviceExists = $result->fetch_row()[0] > 0;
$checkStmt->close();

if (!$deviceExists) {
    sendJsonResponse(['error' => 'Device not found'], 404);
}

// Insert command into database
$stmt = $db->executeQuery(
    "INSERT INTO commands (device_id, command, issued_at, status, output) VALUES (?, ?, NOW(), 'pending', '')",
    "ss",
    [$deviceId, $command]
);

if ($stmt === false) {
    sendJsonResponse(['error' => 'Failed to send command'], 500);
}

$commandId = $stmt->insert_id;
$stmt->close();

// Log the command
logMessage("Command sent to device $deviceId: $command (ID: $commandId)");

// Return success response
sendJsonResponse([
    'status' => 'success',
    'message' => 'Command sent successfully',
    'command_id' => $commandId
]);
?>
