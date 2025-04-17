<?php
/**
 * Get Command API
 * Fetches the latest command for the device
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

// Update device last seen
updateDeviceLastSeen($deviceId);

// Connect to database
$db = Database::getInstance();

// Get the oldest pending command for the device
$stmt = $db->executeQuery(
    "SELECT id, command, issued_at FROM commands 
     WHERE device_id = ? AND status = 'pending' 
     ORDER BY issued_at ASC LIMIT 1",
    "s",
    [$deviceId]
);

if ($stmt === false) {
    sendJsonResponse(['error' => 'Failed to fetch command'], 500);
}

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // No pending commands
    $stmt->close();
    sendJsonResponse([
        'status' => 'success',
        'command' => null,
        'message' => 'No pending commands'
    ]);
}

// Get command data
$command = $result->fetch_assoc();
$commandId = $command['id'];
$commandText = $command['command'];
$issuedAt = $command['issued_at'];
$stmt->close();

// Update command status to 'in_progress'
$updateStmt = $db->executeQuery(
    "UPDATE commands SET status = 'in_progress' WHERE id = ?",
    "i",
    [$commandId]
);

if ($updateStmt !== false) {
    $updateStmt->close();
}

// Log the command retrieval
logMessage("Command retrieved by device $deviceId: Command ID $commandId");

// Return command data
sendJsonResponse([
    'status' => 'success',
    'command_id' => $commandId,
    'command' => $commandText,
    'issued_at' => $issuedAt
]);
?>
