<?php
/**
 * Database Connection Handler
 * Establishes and manages database connections
 */

// Prevent direct access
if (!defined('INCLUDED')) {
    http_response_code(403);
    die('Direct access not permitted');
}

class Database {
    private $conn;
    private static $instance = null;
    private $dbType;

    private function __construct() {
        // Load environment variables
        $this->loadEnv();
        
        try {
            // Check which database system to use
            $dbType = getenv('DB_TYPE') ?: 'sqlite';
            $this->dbType = $dbType;
            
            if ($dbType === 'mysql') {
                // Create MySQL connection
                $this->conn = new mysqli(
                    getenv('DB_HOST'),
                    getenv('DB_USER'),
                    getenv('DB_PASSWORD'),
                    getenv('DB_NAME'),
                    getenv('DB_PORT') ?: 3306
                );
                
                // Check connection
                if ($this->conn->connect_error) {
                    throw new Exception("Connection failed: " . $this->conn->connect_error);
                }
                
                // Set charset
                $this->conn->set_charset("utf8mb4");
            } else if ($dbType === 'postgres') {
                // Create PostgreSQL connection
                $connString = sprintf(
                    "pgsql:host=%s;port=%s;dbname=%s;user=%s;password=%s;sslmode=require",
                    getenv('DB_HOST'),
                    getenv('DB_PORT') ?: 5432,
                    getenv('DB_NAME'),
                    getenv('DB_USER'),
                    getenv('DB_PASSWORD')
                );
                
                // Create PDO connection
                $this->conn = new PDO($connString);
                
                // Set error mode
                $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            } else if ($dbType === 'sqlite') {
                // Create SQLite connection
                $dbPath = getenv('DB_PATH') ?: __DIR__ . '/../../database/astra_c2.db';
                $this->conn = new PDO('sqlite:' . $dbPath);
                
                // Enable exceptions
                $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // Enable foreign keys
                $this->conn->exec('PRAGMA foreign_keys = ON;');
            } else {
                throw new Exception("Unsupported database type: $dbType");
            }
        } catch (Exception $e) {
            error_log("Database connection error: " . $e->getMessage());
            die("Database connection failed. Please check your configuration.");
        }
    }

    /**
     * Get singleton instance of Database
     */
    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    /**
     * Get database connection
     */
    public function getConnection() {
        return $this->conn;
    }
    
    /**
     * Get database type
     */
    public function getDbType() {
        return $this->dbType;
    }

    /**
     * Prepare and execute a parameterized SQL query
     * 
     * @param string $sql The SQL query with placeholders
     * @param string $types The types of parameters (i=integer, s=string, d=double, b=blob) - used for MySQL only
     * @param array $params The parameters to bind
     * @return mysqli_stmt|PDOStatement|false The prepared statement for fetching results or false on failure
     */
    public function executeQuery($sql, $types = "", $params = []) {
        if ($this->dbType === 'mysql') {
            // MySQL execution
            $stmt = $this->conn->prepare($sql);
            
            if ($stmt === false) {
                error_log("Query preparation failed: " . $this->conn->error . " - SQL: $sql");
                return false;
            }
            
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            
            if (!$stmt->execute()) {
                error_log("Query execution failed: " . $stmt->error . " - SQL: $sql");
                $stmt->close();
                return false;
            }
            
            return $stmt;
        } else if ($this->dbType === 'postgres' || $this->dbType === 'sqlite') {
            // PDO execution for PostgreSQL or SQLite
            try {
                $stmt = $this->conn->prepare($sql);
                
                if ($stmt === false) {
                    error_log("Query preparation failed - SQL: $sql");
                    return false;
                }
                
                if (!empty($params)) {
                    // For positional parameters using ?
                    if (strpos($sql, '?') !== false) {
                        $stmt->execute($params);
                    } else {
                        // For named parameters using :name
                        foreach ($params as $key => $value) {
                            if (is_int($key)) {
                                // If key is numeric, it's a positional parameter
                                // We'd need to convert to a named parameter
                                continue;
                            }
                            if (is_int($value)) {
                                $stmt->bindValue(":$key", $value, PDO::PARAM_INT);
                            } else if (is_bool($value)) {
                                $stmt->bindValue(":$key", $value, PDO::PARAM_BOOL);
                            } else {
                                $stmt->bindValue(":$key", $value, PDO::PARAM_STR);
                            }
                        }
                        $stmt->execute();
                    }
                } else {
                    $stmt->execute();
                }
                
                return $stmt;
            } catch (PDOException $e) {
                error_log("Query execution failed: " . $e->getMessage() . " - SQL: $sql");
                return false;
            }
        }
        
        return false;
    }

    /**
     * Load environment variables from .env file
     */
    private function loadEnv() {
        $envFile = __DIR__ . '/../config/.env';
        
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                // Skip comments
                if (strpos(trim($line), '#') === 0) {
                    continue;
                }
                
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value);
                
                // Remove quotes if present
                if (preg_match('/^([\'"])(.*)\1$/', $value, $matches)) {
                    $value = $matches[2];
                }
                
                putenv("$name=$value");
                $_ENV[$name] = $value;
            }
        } else {
            error_log('.env file not found at: ' . $envFile);
        }
    }
    
    /**
     * Close the database connection when the object is destroyed
     */
    public function __destruct() {
        if ($this->dbType === 'mysql') {
            if ($this->conn) {
                $this->conn->close();
            }
        } else if ($this->dbType === 'postgres' || $this->dbType === 'sqlite') {
            // PDO connections are automatically closed when the object is destroyed
            $this->conn = null;
        }
    }
}
?>
