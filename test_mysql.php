<?php
/**
 * MySQL Connection Test Script
 * 
 * Upload this file to your server and run it to test MySQL connectivity
 * Delete after successful testing for security reasons
 */

// Load environment variables if available
$envFile = __DIR__ . '/public_html/c2/config/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            if (preg_match('/^([\'"])(.*)\1$/', $value, $matches)) {
                $value = $matches[2];
            }
            putenv("$name=$value");
        }
    }
    echo "<p>Loaded configuration from .env file</p>";
} else {
    echo "<p>No .env file found, using provided credentials</p>";
}

// Database credentials can be overridden here
$dbHost = isset($_POST['db_host']) ? $_POST['db_host'] : (getenv('DB_HOST') ?: '193.203.184.212');
$dbPort = isset($_POST['db_port']) ? $_POST['db_port'] : (getenv('DB_PORT') ?: '3306');
$dbName = isset($_POST['db_name']) ? $_POST['db_name'] : (getenv('DB_NAME') ?: '');
$dbUser = isset($_POST['db_user']) ? $_POST['db_user'] : (getenv('DB_USER') ?: '');
$dbPass = isset($_POST['db_pass']) ? $_POST['db_pass'] : (getenv('DB_PASSWORD') ?: '');

// Store form submission status
$submitted = isset($_POST['submit']);
$success = false;
$error = '';
$tables = [];

// Try connecting if form submitted
if ($submitted && !empty($dbHost) && !empty($dbName) && !empty($dbUser)) {
    try {
        // Try to connect
        $conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName, $dbPort);
        
        // Check connection
        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }
        
        // Connection successful
        $success = true;
        
        // Get tables if connected
        $result = $conn->query("SHOW TABLES");
        if ($result) {
            while ($row = $result->fetch_array()) {
                $tables[] = $row[0];
            }
        }
        
        // Close connection
        $conn->close();
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MySQL Connection Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .container {
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 20px;
            margin-top: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background-color: #45a049;
        }
        .error {
            color: red;
            margin: 10px 0;
        }
        .success {
            color: green;
            margin: 10px 0;
        }
        .tables {
            margin-top: 20px;
        }
        .tables ul {
            background-color: #f9f9f9;
            padding: 10px 20px;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <h1>Astra C2 - MySQL Connection Test</h1>
    
    <div class="container">
        <h2>Test Your MySQL Connection</h2>
        <p>Use this tool to verify your MySQL connection settings before deploying Astra C2.</p>
        
        <?php if ($submitted): ?>
            <?php if ($success): ?>
                <div class="success">
                    <h3>✅ Connection Successful!</h3>
                    <p>Successfully connected to MySQL database "<?php echo htmlspecialchars($dbName); ?>" on "<?php echo htmlspecialchars($dbHost); ?>".</p>
                    
                    <?php if (count($tables) > 0): ?>
                        <div class="tables">
                            <h4>Tables in Database:</h4>
                            <ul>
                                <?php foreach ($tables as $table): ?>
                                <li><?php echo htmlspecialchars($table); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php else: ?>
                        <p>No tables found in the database. You will need to run the setup script.</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="error">
                    <h3>❌ Connection Failed</h3>
                    <p><?php echo htmlspecialchars($error); ?></p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <form method="post" action="">
            <div class="form-group">
                <label for="db_host">Database Host:</label>
                <input type="text" id="db_host" name="db_host" value="<?php echo htmlspecialchars($dbHost); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="db_port">Database Port:</label>
                <input type="text" id="db_port" name="db_port" value="<?php echo htmlspecialchars($dbPort); ?>">
            </div>
            
            <div class="form-group">
                <label for="db_name">Database Name:</label>
                <input type="text" id="db_name" name="db_name" value="<?php echo htmlspecialchars($dbName); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="db_user">Database Username:</label>
                <input type="text" id="db_user" name="db_user" value="<?php echo htmlspecialchars($dbUser); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="db_pass">Database Password:</label>
                <input type="password" id="db_pass" name="db_pass" value="<?php echo htmlspecialchars($dbPass); ?>" required>
            </div>
            
            <button type="submit" name="submit" value="1">Test Connection</button>
        </form>
    </div>
    
    <div class="container">
        <h2>Next Steps</h2>
        <p>Once you have verified your connection:</p>
        <ol>
            <li>Update your .env file with these credentials</li>
            <li>Run the setup_mysql.php script or import setup_mysql.sql</li>
            <li>Delete this test file for security</li>
        </ol>
    </div>
</body>
</html>