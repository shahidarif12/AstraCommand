<?php
/**
 * Utility Functions
 * Common functions used throughout the application
 */

// Prevent direct access
if (!defined('INCLUDED')) {
    http_response_code(403);
    die('Direct access not permitted');
}

/**
 * Generate a secure random token
 * 
 * @param int $length Length of the token
 * @return string The generated token
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Generate a unique device ID
 * 
 * @return string Unique device ID
 */
function generateDeviceId() {
    return uniqid('DEV_', true);
}

/**
 * Sanitize and validate input
 * 
 * @param string $input Input to sanitize
 * @return string Sanitized input
 */
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate IP address
 * 
 * @param string $ip IP address to validate
 * @return bool True if valid, false otherwise
 */
function validateIp($ip) {
    return filter_var($ip, FILTER_VALIDATE_IP) !== false;
}

/**
 * Get client IP address
 * 
 * @return string Client IP address
 */
function getClientIp() {
    $ip = '';
    
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    
    return $ip;
}

/**
 * Log message to file
 * 
 * @param string $message Message to log
 * @param string $level Log level (INFO, WARNING, ERROR)
 */
function logMessage($message, $level = 'INFO') {
    $logFile = __DIR__ . '/../logs/app_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [$level] $message" . PHP_EOL;
    
    // Create logs directory if it doesn't exist
    if (!is_dir(dirname($logFile))) {
        mkdir(dirname($logFile), 0755, true);
    }
    
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

/**
 * Send JSON response
 * 
 * @param array $data Data to send
 * @param int $status HTTP status code
 */
function sendJsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Verify device authentication
 * 
 * @param string $deviceId Device ID
 * @param string $authToken Authentication token
 * @return bool True if authenticated, false otherwise
 */
function verifyDeviceAuth($deviceId, $authToken) {
    require_once __DIR__ . '/db.php';
    
    $db = Database::getInstance();
    $stmt = $db->executeQuery(
        "SELECT COUNT(*) FROM devices WHERE device_id = ? AND auth_token = ?",
        "ss",
        [$deviceId, $authToken]
    );
    
    if ($stmt === false) {
        return false;
    }
    
    $result = $stmt->get_result();
    $count = $result->fetch_row()[0];
    $stmt->close();
    
    return $count > 0;
}

/**
 * Update device last seen timestamp
 * 
 * @param string $deviceId Device ID
 * @return bool True if updated successfully, false otherwise
 */
function updateDeviceLastSeen($deviceId) {
    require_once __DIR__ . '/db.php';
    
    $db = Database::getInstance();
    $stmt = $db->executeQuery(
        "UPDATE devices SET last_seen = NOW() WHERE device_id = ?",
        "s",
        [$deviceId]
    );
    
    if ($stmt === false) {
        return false;
    }
    
    $affected = $stmt->affected_rows;
    $stmt->close();
    
    return $affected > 0;
}

/**
 * Check if admin is authenticated
 * 
 * @return bool True if authenticated, false otherwise
 */
function isAdminAuthenticated() {
    return isset($_SESSION['admin_authenticated']) && $_SESSION['admin_authenticated'] === true;
}

/**
 * Format timestamp for display
 * 
 * @param string $timestamp Timestamp to format
 * @return string Formatted timestamp
 */
function formatTimestamp($timestamp) {
    $dt = new DateTime($timestamp);
    return $dt->format('Y-m-d H:i:s');
}

/**
 * Calculate time ago from timestamp
 * 
 * @param string $timestamp Timestamp
 * @return string Time ago string
 */
function timeAgo($timestamp) {
    $time = strtotime($timestamp);
    $diff = time() - $time;
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M j, Y', $time);
    }
}
?>
