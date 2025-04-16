<?php
/**
 * Admin Panel - Index
 * Main entry point with auto-login to dashboard
 */

// Define included constant to prevent direct access to includes
define('INCLUDED', true);

// Include required files
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Start session
session_start();

// Auto-login (as specified in requirements)
$_SESSION['admin_authenticated'] = true;
$_SESSION['admin_username'] = 'admin';

// Redirect to dashboard
header('Location: dashboard.php');
exit;
?>
