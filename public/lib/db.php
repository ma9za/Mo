<?php
/**
 * Database Manager
 * SQLite database operations with prepared statements
 */

require_once __DIR__ . '/env.php';

class Database {
    private static $instance = null;
    private $connection = null;
    private $dbPath = null;

    private function __construct() {
        $this->dbPath = env('DB_PATH', dirname(dirname(dirname(__FILE__))) . '/private/database.sqlite');
        $this->connect();
        $this->initializeTables();
    }

    /**
     * Get singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Connect to SQLite database
     */
    private function connect() {
        try {
            $this->connection = new PDO('sqlite:' . $this->dbPath);
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }

    /**
     * Initialize database tables
     */
    private function initializeTables() {
        // Check if tables exist
        $stmt = $this->connection->query("SELECT name FROM sqlite_master WHERE type='table' AND name='bots'");
        if ($stmt->fetch()) {
            return; // Tables already exist
        }

        // Create bots table
        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS bots (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                token TEXT NOT NULL UNIQUE,
                webhook_secret TEXT,
                channel_input TEXT,
                channel_id TEXT,
                channel_title TEXT,
                is_verified INTEGER DEFAULT 0,
                is_enabled INTEGER DEFAULT 1,
                general_prompt TEXT,
                deepseek_key_override TEXT,
                model_override TEXT,
                schedule_json TEXT,
                last_post_at DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Create settings table
        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS settings (
                key TEXT PRIMARY KEY,
                value TEXT
            )
        ");

        // Create logs table
        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                bot_id INTEGER NOT NULL,
                status TEXT NOT NULL,
                message TEXT,
                telegram_message_id TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (bot_id) REFERENCES bots(id) ON DELETE CASCADE
            )
        ");

        // Create indexes
        $this->connection->exec("CREATE INDEX IF NOT EXISTS idx_logs_bot_id ON logs(bot_id)");
        $this->connection->exec("CREATE INDEX IF NOT EXISTS idx_logs_created_at ON logs(created_at)");
        $this->connection->exec("CREATE INDEX IF NOT EXISTS idx_bots_enabled ON bots(is_enabled)");
    }

    /**
     * Execute prepared statement
     */
    public function execute($query, $params = []) {
        try {
            $stmt = $this->connection->prepare($query);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            throw new Exception("Database query failed: " . $e->getMessage());
        }
    }

    /**
     * Fetch all results
     */
    public function fetchAll($query, $params = []) {
        return $this->execute($query, $params)->fetchAll();
    }

    /**
     * Fetch single result
     */
    public function fetch($query, $params = []) {
        return $this->execute($query, $params)->fetch();
    }

    /**
     * Insert record
     */
    public function insert($table, $data) {
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');
        
        $query = "INSERT INTO {$table} (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
        
        $this->execute($query, array_values($data));
        return $this->connection->lastInsertId();
    }

    /**
     * Update record
     */
    public function update($table, $data, $where, $whereParams = []) {
        $sets = [];
        $values = [];

        foreach ($data as $column => $value) {
            $sets[] = "{$column} = ?";
            $values[] = $value;
        }

        $values = array_merge($values, $whereParams);
        $query = "UPDATE {$table} SET " . implode(', ', $sets) . " WHERE {$where}";

        return $this->execute($query, $values);
    }

    /**
     * Delete record
     */
    public function delete($table, $where, $params = []) {
        $query = "DELETE FROM {$table} WHERE {$where}";
        return $this->execute($query, $params);
    }

    /**
     * Get connection
     */
    public function getConnection() {
        return $this->connection;
    }

    /**
     * Begin transaction
     */
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public function commit() {
        return $this->connection->commit();
    }

    /**
     * Rollback transaction
     */
    public function rollback() {
        return $this->connection->rollBack();
    }
}

// Initialize database
$db = Database::getInstance();
