<?php
/**
 * Authentication API
 * Handles admin authentication
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
$username = isset($_POST['username']) ? sanitizeInput($_POST['username']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

// Validate inputs
if (empty($username) || empty($password)) {
    sendJsonResponse(['error' => 'Missing credentials'], 400);
}

// Connect to database
$db = Database::getInstance();

// Check if the admin table exists
$tableCheckStmt = $db->executeQuery("SHOW TABLES LIKE 'admins'");
if ($tableCheckStmt === false) {
    sendJsonResponse(['error' => 'Failed to verify admin table'], 500);
}

$tableExists = $tableCheckStmt->get_result()->num_rows > 0;
$tableCheckStmt->close();

// If the admin table doesn't exist, use the default admin credentials from env
if (!$tableExists) {
    $defaultAdminUser = getenv('DEFAULT_ADMIN_USER') ?: 'admin';
    $defaultAdminPassHash = getenv('DEFAULT_ADMIN_PASS_HASH') ?: password_hash('admin', PASSWORD_DEFAULT);
    
    if ($username === $defaultAdminUser && password_verify($password, $defaultAdminPassHash)) {
        // Successful authentication with default admin
        session_start();
        $_SESSION['admin_authenticated'] = true;
        $_SESSION['admin_username'] = $username;
        
        sendJsonResponse([
            'status' => 'success',
            'message' => 'Authentication successful',
            'note' => 'Using default admin account. Please create custom admin accounts for production.'
        ]);
    } else {
        // Failed authentication with default admin
        sendJsonResponse(['error' => 'Invalid credentials'], 401);
    }
    
    exit;
}

// Query for admin
$stmt = $db->executeQuery(
    "SELECT id, username, password_hash FROM admins WHERE username = ?",
    "s",
    [$username]
);

if ($stmt === false) {
    sendJsonResponse(['error' => 'Authentication failed'], 500);
}

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Admin not found
    $stmt->close();
    sendJsonResponse(['error' => 'Invalid credentials'], 401);
}

// Get admin data
$admin = $result->fetch_assoc();
$passwordHash = $admin['password_hash'];
$stmt->close();

// Verify password
if (!password_verify($password, $passwordHash)) {
    sendJsonResponse(['error' => 'Invalid credentials'], 401);
}

// Start session and set admin authentication
session_start();
$_SESSION['admin_authenticated'] = true;
$_SESSION['admin_username'] = $username;
$_SESSION['admin_id'] = $admin['id'];

// Log the authentication
logMessage("Admin login: $username");

// Return success response
sendJsonResponse([
    'status' => 'success',
    'message' => 'Authentication successful'
]);
?>
