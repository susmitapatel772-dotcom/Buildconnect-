<?php
/**
 * BuildConnect Central Database Connection Handler
 * Connects strictly to MySQL via PDO with utf8mb4 and prepared statement enforcement.
 */

require_once __DIR__ . '/constants.php';

class Database {
    private static $instance = null;
    private $pdo = null;

    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];

            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Log technical error securely in production; avoid exposing raw credentials
            error_log("BuildConnect DB Connection Error: " . $e->getMessage());
            die("Unable to connect to the database. Please verify MySQL service and credentials in config/constants.php.");
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->pdo;
    }
}

// Global PDO database helper function
function getDB() {
    return Database::getInstance()->getConnection();
}
