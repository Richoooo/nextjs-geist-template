<?php
/**
 * Database Configuration
 * Student Attendance System
 */

class Database {
    private $host = 'localhost';
    private $db_name = 'student_attendance';
    private $username = 'root';
    private $password = '';
    private $charset = 'utf8mb4';
    private $conn;

    public function __construct() {
        $this->connect();
    }

    private function connect() {
        $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset={$this->charset}";
        
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$this->charset}"
        ];

        try {
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed. Please check your configuration.");
        }
    }

    public function getConnection() {
        return $this->conn;
    }

    public function query($sql, $params = []) {
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Database query failed: " . $e->getMessage());
            throw new Exception("Database query failed.");
        }
    }

    public function fetch($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }

    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    public function execute($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    public function lastInsertId() {
        return $this->conn->lastInsertId();
    }

    public function beginTransaction() {
        return $this->conn->beginTransaction();
    }

    public function commit() {
        return $this->conn->commit();
    }

    public function rollback() {
        return $this->conn->rollback();
    }

    public function testConnection() {
        try {
            $stmt = $this->conn->query("SELECT 1");
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    public function getSetting($key, $default = null) {
        $sql = "SELECT setting_value FROM settings WHERE setting_key = ?";
        $result = $this->fetch($sql, [$key]);
        return $result ? $result['setting_value'] : $default;
    }

    public function setSetting($key, $value, $description = '') {
        $sql = "INSERT INTO settings (setting_key, setting_value, description) 
                VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE 
                setting_value = VALUES(setting_value), 
                description = VALUES(description)";
        return $this->execute($sql, [$key, $value, $description]);
    }
}

// Global database instance
$db = new Database();

// Function to get database instance
function getDB() {
    global $db;
    return $db;
}
?>
