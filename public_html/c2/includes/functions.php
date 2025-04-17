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
 * Get database-agnostic date function
 * Returns the appropriate SQL function for the current database type to get current date/time
 * 
 * @param Database $db Database instance
 * @return string SQL function for current date/time
 */
function getDbDateFunction($db = null) {
    if ($db === null) {
        $db = Database::getInstance();
    }
    
    $dbType = $db->getDbType();
    
    if ($dbType === 'mysql') {
        return 'NOW()';
    } else if ($dbType === 'postgres') {
        return 'CURRENT_TIMESTAMP';
    } else if ($dbType === 'sqlite') {
        return "datetime('now')";
    }
    
    // Default fallback
    return 'CURRENT_TIMESTAMP';
}

/**
 * Get database-agnostic interval subtraction function
 * Returns SQL to subtract interval from a date for the current database type
 * 
 * @param string $field Field name
 * @param int $value Interval value
 * @param string $unit Interval unit (MINUTE, HOUR, DAY)
 * @param Database $db Database instance
 * @return string SQL for interval subtraction
 */
function getDbIntervalSubtract($field, $value, $unit, $db = null) {
    if ($db === null) {
        $db = Database::getInstance();
    }
    
    $dbType = $db->getDbType();
    
    if ($dbType === 'mysql') {
        return "DATE_SUB($field, INTERVAL $value $unit)";
    } else if ($dbType === 'postgres') {
        $unit = strtolower($unit);
        return "($field - INTERVAL '$value $unit')";
    } else if ($dbType === 'sqlite') {
        $seconds = 0;
        if ($unit === 'MINUTE') {
            $seconds = $value * 60;
        } else if ($unit === 'HOUR') {
            $seconds = $value * 3600;
        } else if ($unit === 'DAY') {
            $seconds = $value * 86400;
        }
        return "datetime($field, '-$seconds seconds')";
    }
    
    // Default fallback to MySQL syntax
    return "DATE_SUB($field, INTERVAL $value $unit)";
}

/**
 * Get fetch function appropriate for the database type and statement
 * Returns an associative array of data from the result
 * 
 * @param mixed $stmt Statement from executeQuery
 * @param Database $db Database instance
 * @return array|null Associative array of data or null on error/empty result
 */
function dbFetchAssoc($stmt, $db = null) {
    if ($stmt === false) {
        return null;
    }
    
    if ($db === null) {
        $db = Database::getInstance();
    }
    
    $dbType = $db->getDbType();
    
    if ($dbType === 'mysql') {
        $result = $stmt->get_result();
        if ($result) {
            $row = $result->fetch_assoc();
            $stmt->close();
            return $row;
        }
        $stmt->close();
        return null;
    } else if ($dbType === 'postgres' || $dbType === 'sqlite') {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
    
    return null;
}

/**
 * Get all rows from a statement
 * Returns an array of associative arrays
 * 
 * @param mixed $stmt Statement from executeQuery
 * @param Database $db Database instance
 * @return array Array of associative arrays or empty array on error/empty result
 */
function dbFetchAll($stmt, $db = null) {
    if ($stmt === false) {
        return [];
    }
    
    if ($db === null) {
        $db = Database::getInstance();
    }
    
    $dbType = $db->getDbType();
    
    if ($dbType === 'mysql') {
        $result = $stmt->get_result();
        if ($result) {
            $rows = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            return $rows;
        }
        $stmt->close();
        return [];
    } else if ($dbType === 'postgres' || $dbType === 'sqlite') {
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rows ?: [];
    }
    
    return [];
}

/**
 * Get the number of affected rows from a statement
 * 
 * @param mixed $stmt Statement from executeQuery
 * @param Database $db Database instance
 * @return int Number of affected rows or 0 on error
 */
function dbAffectedRows($stmt, $db = null) {
    if ($stmt === false) {
        return 0;
    }
    
    if ($db === null) {
        $db = Database::getInstance();
    }
    
    $dbType = $db->getDbType();
    
    if ($dbType === 'mysql') {
        $affected = $stmt->affected_rows;
        $stmt->close();
        return $affected;
    } else if ($dbType === 'postgres' || $dbType === 'sqlite') {
        return $stmt->rowCount();
    }
    
    return 0;
}

/**
 * Get a single value from the first column of the first row
 * 
 * @param mixed $stmt Statement from executeQuery
 * @param Database $db Database instance
 * @return mixed Value or null on error/empty result
 */
function dbFetchValue($stmt, $db = null) {
    if ($stmt === false) {
        return null;
    }
    
    if ($db === null) {
        $db = Database::getInstance();
    }
    
    $dbType = $db->getDbType();
    
    if ($dbType === 'mysql') {
        $result = $stmt->get_result();
        if ($result) {
            $row = $result->fetch_row();
            $stmt->close();
            return $row ? $row[0] : null;
        }
        $stmt->close();
        return null;
    } else if ($dbType === 'postgres' || $dbType === 'sqlite') {
        $value = $stmt->fetchColumn();
        return $value !== false ? $value : null;
    }
    
    return null;
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
    
    $count = dbFetchValue($stmt, $db);
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
    $now = getDbDateFunction($db);
    $stmt = $db->executeQuery(
        "UPDATE devices SET last_seen = $now WHERE device_id = ?",
        "s",
        [$deviceId]
    );
    
    $affected = dbAffectedRows($stmt, $db);
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
