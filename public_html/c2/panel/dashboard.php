<?php
/**
 * Admin Panel - Dashboard
 * Overview of devices and statistics
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

// Get device statistics
$deviceStatsStmt = $db->executeQuery("
    SELECT 
        COUNT(*) as total_devices,
        SUM(CASE WHEN last_seen >= DATE_SUB(NOW(), INTERVAL 10 MINUTE) THEN 1 ELSE 0 END) as online_devices,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_devices
    FROM devices
");

if ($deviceStatsStmt === false) {
    $deviceStats = [
        'total_devices' => 0,
        'online_devices' => 0,
        'active_devices' => 0
    ];
} else {
    $deviceStats = $deviceStatsStmt->get_result()->fetch_assoc();
    $deviceStatsStmt->close();
}

// Get command statistics
$commandStatsStmt = $db->executeQuery("
    SELECT 
        COUNT(*) as total_commands,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_commands,
        SUM(CASE WHEN status = 'complete' THEN 1 ELSE 0 END) as completed_commands
    FROM commands
");

if ($commandStatsStmt === false) {
    $commandStats = [
        'total_commands' => 0,
        'pending_commands' => 0,
        'completed_commands' => 0
    ];
} else {
    $commandStats = $commandStatsStmt->get_result()->fetch_assoc();
    $commandStatsStmt->close();
}

// Get log statistics
$logStatsStmt = $db->executeQuery("
    SELECT 
        COUNT(*) as total_logs,
        SUM(CASE WHEN type = 'gps' THEN 1 ELSE 0 END) as gps_logs,
        SUM(CASE WHEN type = 'sms' THEN 1 ELSE 0 END) as sms_logs,
        SUM(CASE WHEN type = 'notification' THEN 1 ELSE 0 END) as notification_logs
    FROM logs
");

if ($logStatsStmt === false) {
    $logStats = [
        'total_logs' => 0,
        'gps_logs' => 0,
        'sms_logs' => 0,
        'notification_logs' => 0
    ];
} else {
    $logStats = $logStatsStmt->get_result()->fetch_assoc();
    $logStatsStmt->close();
}

// Get recent devices
$recentDevicesStmt = $db->executeQuery("
    SELECT device_id, name, os, ip_address, last_seen, status
    FROM devices
    ORDER BY last_seen DESC
    LIMIT 5
");

if ($recentDevicesStmt === false) {
    $recentDevices = [];
} else {
    $result = $recentDevicesStmt->get_result();
    $recentDevices = [];
    while ($row = $result->fetch_assoc()) {
        $recentDevices[] = $row;
    }
    $recentDevicesStmt->close();
}

// Get recent commands
$recentCommandsStmt = $db->executeQuery("
    SELECT c.id, c.device_id, d.name as device_name, c.command, c.issued_at, c.status
    FROM commands c
    JOIN devices d ON c.device_id = d.device_id
    ORDER BY c.issued_at DESC
    LIMIT 5
");

if ($recentCommandsStmt === false) {
    $recentCommands = [];
} else {
    $result = $recentCommandsStmt->get_result();
    $recentCommands = [];
    while ($row = $result->fetch_assoc()) {
        $recentCommands[] = $row;
    }
    $recentCommandsStmt->close();
}

// Get recent logs
$recentLogsStmt = $db->executeQuery("
    SELECT l.id, l.device_id, d.name as device_name, l.type, LEFT(l.content, 100) as content_preview, l.timestamp
    FROM logs l
    JOIN devices d ON l.device_id = d.device_id
    ORDER BY l.timestamp DESC
    LIMIT 5
");

if ($recentLogsStmt === false) {
    $recentLogs = [];
} else {
    $result = $recentLogsStmt->get_result();
    $recentLogs = [];
    while ($row = $result->fetch_assoc()) {
        $recentLogs[] = $row;
    }
    $recentLogsStmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Astra C2 - Dashboard</title>
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
                        <a class="nav-link active" href="dashboard.php">
                            <i class="material-icons">dashboard</i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="devices.php">
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
                    <h1 class="mt-4 mb-4">Dashboard</h1>
                    
                    <!-- Stats Cards -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">Devices</h5>
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h3><?php echo $deviceStats['total_devices']; ?></h3>
                                            <p>Total Devices</p>
                                        </div>
                                        <div>
                                            <h3><?php echo $deviceStats['online_devices']; ?></h3>
                                            <p>Online</p>
                                        </div>
                                        <div>
                                            <h3><?php echo $deviceStats['active_devices']; ?></h3>
                                            <p>Active</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">Commands</h5>
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h3><?php echo $commandStats['total_commands']; ?></h3>
                                            <p>Total Commands</p>
                                        </div>
                                        <div>
                                            <h3><?php echo $commandStats['pending_commands']; ?></h3>
                                            <p>Pending</p>
                                        </div>
                                        <div>
                                            <h3><?php echo $commandStats['completed_commands']; ?></h3>
                                            <p>Completed</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">Logs</h5>
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h3><?php echo $logStats['total_logs']; ?></h3>
                                            <p>Total Logs</p>
                                        </div>
                                        <div>
                                            <h3><?php echo $logStats['gps_logs']; ?></h3>
                                            <p>GPS</p>
                                        </div>
                                        <div>
                                            <h3><?php echo $logStats['sms_logs']; ?></h3>
                                            <p>SMS</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Devices -->
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Recent Devices</h5>
                                    <a href="devices.php" class="btn btn-sm btn-primary">View All</a>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Name</th>
                                                    <th>OS</th>
                                                    <th>IP Address</th>
                                                    <th>Last Seen</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($recentDevices)): ?>
                                                <tr>
                                                    <td colspan="5" class="text-center">No devices registered</td>
                                                </tr>
                                                <?php else: ?>
                                                <?php foreach ($recentDevices as $device): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($device['name']); ?></td>
                                                    <td><?php echo htmlspecialchars($device['os']); ?></td>
                                                    <td><?php echo htmlspecialchars($device['ip_address']); ?></td>
                                                    <td><?php echo timeAgo($device['last_seen']); ?></td>
                                                    <td>
                                                        <?php if (strtotime($device['last_seen']) >= strtotime('-10 minutes')): ?>
                                                        <span class="badge bg-success">Online</span>
                                                        <?php else: ?>
                                                        <span class="badge bg-secondary">Offline</span>
                                                        <?php endif; ?>
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
                    
                    <!-- Recent Commands and Logs -->
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Recent Commands</h5>
                                    <a href="command_center.php" class="btn btn-sm btn-primary">Command Center</a>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Device</th>
                                                    <th>Command</th>
                                                    <th>Issued</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($recentCommands)): ?>
                                                <tr>
                                                    <td colspan="4" class="text-center">No commands issued</td>
                                                </tr>
                                                <?php else: ?>
                                                <?php foreach ($recentCommands as $command): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($command['device_name']); ?></td>
                                                    <td><?php echo htmlspecialchars(substr($command['command'], 0, 30)); ?></td>
                                                    <td><?php echo timeAgo($command['issued_at']); ?></td>
                                                    <td>
                                                        <?php if ($command['status'] === 'pending'): ?>
                                                        <span class="badge bg-warning">Pending</span>
                                                        <?php elseif ($command['status'] === 'in_progress'): ?>
                                                        <span class="badge bg-info">In Progress</span>
                                                        <?php else: ?>
                                                        <span class="badge bg-success">Complete</span>
                                                        <?php endif; ?>
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
                        
                        <div class="col-md-6 mb-4">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Recent Logs</h5>
                                    <a href="logs.php" class="btn btn-sm btn-primary">View All Logs</a>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Device</th>
                                                    <th>Type</th>
                                                    <th>Content</th>
                                                    <th>Time</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($recentLogs)): ?>
                                                <tr>
                                                    <td colspan="4" class="text-center">No logs received</td>
                                                </tr>
                                                <?php else: ?>
                                                <?php foreach ($recentLogs as $log): ?>
                                                <tr>
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
                                                    <td><?php echo htmlspecialchars($log['content_preview']) . (strlen($log['content_preview']) >= 100 ? '...' : ''); ?></td>
                                                    <td><?php echo timeAgo($log['timestamp']); ?></td>
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
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="../assets/js/dashboard.js"></script>
</body>
</html>
