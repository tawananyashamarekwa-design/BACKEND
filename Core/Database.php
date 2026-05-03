<?php

/**
 * ============================================================================
 * DATABASE CONNECTION HANDLER
 * ============================================================================
 * 
 * This class is responsible for establishing and managing the database
 * connection using PDO (PHP Data Objects). It provides a singleton pattern
 * to ensure only one database connection is created throughout the application
 * lifecycle, reducing resource usage and improving performance.
 * 
 * @package EcommerceElectronics\Core
 * @author School Project
 * @version 1.0.0
 * ============================================================================
 */

require_once __DIR__ . '/../config/app.php';

class Database {
    private static $instance = null;

    private $connection = null;
    
    private $lastError = '';
   
    private $maxRetries = 3;
    
    private $retryDelay = 1;
    
   
    private function __construct() {
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
            self::$instance->connect();
        }
        return self::$instance;
    }
    
    private function connect() {
        $dsn = DB_DRIVER === 'pgsql'
            ? sprintf(
                'pgsql:host=%s;port=%d;dbname=%s',
                DB_HOST,
                DB_PORT,
                DB_NAME
            )
            : sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                DB_HOST,
                DB_PORT,
                DB_NAME,
                DB_CHARSET
            );
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,

            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

            PDO::ATTR_PERSISTENT => false,

            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        $attempt = 0;
        $lastException = null;
        
        while ($attempt < $this->maxRetries) {
            try {
                $this->connection = new PDO(
                    $dsn,
                    DB_USER,
                    DB_PASS,
                    $options
                );
                
                $this->log('Database connection established successfully');
                return;
                
            } catch (PDOException $e) {
                $lastException = $e;
                $attempt++;
                
                $this->log("Database connection attempt {$attempt} failed: " . $e->getMessage());
                
                if ($attempt < $this->maxRetries) {
                    sleep($this->retryDelay);
                }
            }
        }
        
        $this->lastError = $lastException->getMessage();
        throw $lastException;
    }
    
    
    public function getConnection() {
        if ($this->connection === null) {
            throw new Exception('Database connection not available');
        }
        return $this->connection;
    }
    
    
    public function testConnection() {
        try {
            $statement = $this->connection->prepare('SELECT 1');
            $statement->execute();
            return true;
        } catch (Exception $e) {
            $this->log('Connection test failed: ' . $e->getMessage());
            return false;
        }
    }
    
    
    public function disconnect() {
        $this->connection = null;
        self::$instance = null;
    }
    
    
    public function getLastError() {
        return $this->lastError;
    }
    
   
    private function log($message, $level = 'INFO') {
        if (APP_DEBUG) {
            error_log("[Database] [$level] $message");
        }
       
        if (is_writable(LOG_DIR)) {
            $timestamp = date('Y-m-d H:i:s');
            $logMessage = "[$timestamp] [Database] [$level] $message\n";
            file_put_contents(LOG_FILE, $logMessage, FILE_APPEND);
        }
    }
    
    
    public function __clone() {
        throw new Exception('Cannot clone singleton Database instance');
    }
    
    
    public function __wakeup() {
        throw new Exception('Cannot unserialize singleton Database instance');
    }
}

?>
