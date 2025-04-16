<?php
/**
 * Send Log API
 * Accepts logs from devices
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
$type = isset($_POST['type']) ? sanitizeInput($_POST['type']) : '';
$content = isset($_POST['content']) ? $_POST['content'] : ''; // Don't sanitize content as it might contain JSON or other data
$commandId = isset($_POST['command_id']) ? intval($_POST['command_id']) : 0;

// Validate inputs
if (empty($deviceId) || empty($authToken) || empty($type)) {
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

// If this is command output, update the command status
if ($commandId > 0) {
    $updateStmt = $db->executeQuery(
        "UPDATE commands SET status = 'complete', output = ? WHERE id = ? AND device_id = ?",
        "sis",
        [$content, $commandId, $deviceId]
    );
    
    if ($updateStmt !== false) {
        $updateStmt->close();
    }
}

// Insert log into database
$stmt = $db->executeQuery(
    "INSERT INTO logs (device_id, type, content, timestamp) VALUES (?, ?, ?, NOW())",
    "sss",
    [$deviceId, $type, $content]
);

if ($stmt === false) {
    sendJsonResponse(['error' => 'Failed to store log'], 500);
}

$logId = $stmt->insert_id;
$stmt->close();

// Log the action
logMessage("Log received from device $deviceId: Type $type, ID $logId");

// Return success response
sendJsonResponse([
    'status' => 'success',
    'message' => 'Log received',
    'log_id' => $logId
]);
?>
