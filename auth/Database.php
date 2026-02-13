<?php
/**
 * Database Connection Handler
 * 
 * Provides a singleton PDO connection with security features
 */

class Database {
    private static $instance = null;
    private $pdo;
    private $config;
    
    private function __construct($config) {
        $this->config = $config;
        $this->connect();
    }
    
    /**
     * Get singleton instance
     */
    public static function getInstance($config = null) {
        if (self::$instance === null) {
            if ($config === null) {
                throw new Exception('Database configuration required for first initialization');
            }
            self::$instance = new self($config);
        }
        return self::$instance;
    }
    
    /**
     * Establish database connection
     */
    private function connect() {
        $db = $this->config['db'];
        
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $db['host'],
            $db['port'],
            $db['database'],
            $db['charset']
        );
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => false,
        ];
        
        try {
            $this->pdo = new PDO($dsn, $db['username'], $db['password'], $options);
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            throw new Exception('Database connection failed');
        }
    }
    
    /**
     * Get PDO instance
     */
    public function getPdo() {
        return $this->pdo;
    }
    
    /**
     * Execute a query and return results
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log('Query failed: ' . $e->getMessage());
            throw new Exception('Database query failed');
        }
    }
    
    /**
     * Fetch single row
     */
    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }
    
    /**
     * Fetch all rows
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Insert and return last insert ID
     */
    public function insert($table, $data) {
        $fields = array_keys($data);
        $values = array_values($data);
        $placeholders = array_fill(0, count($fields), '?');
        
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $fields),
            implode(', ', $placeholders)
        );
        
        $this->query($sql, $values);
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Update rows
     */
    public function update($table, $data, $where, $whereParams = []) {
        $fields = [];
        $values = [];
        
        foreach ($data as $field => $value) {
            $fields[] = "$field = ?";
            $values[] = $value;
        }
        
        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s',
            $table,
            implode(', ', $fields),
            $where
        );
        
        $params = array_merge($values, $whereParams);
        return $this->query($sql, $params);
    }
    
    /**
     * Delete rows
     */
    public function delete($table, $where, $params = []) {
        $sql = sprintf('DELETE FROM %s WHERE %s', $table, $where);
        return $this->query($sql, $params);
    }
    
    /**
     * Begin transaction
     */
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }
    
    /**
     * Commit transaction
     */
    public function commit() {
        return $this->pdo->commit();
    }
    
    /**
     * Rollback transaction
     */
    public function rollback() {
        return $this->pdo->rollBack();
    }
    
    /**
     * Check if table exists
     */
    public function tableExists($tableName) {
        $sql = "SHOW TABLES LIKE ?";
        $result = $this->fetchOne($sql, [$tableName]);
        return $result !== false;
    }
    
    /**
     * Prevent cloning
     */
    private function __clone() {}
    
    /**
     * Prevent unserialization
     */
    public function __wakeup() {
        throw new Exception('Cannot unserialize singleton');
    }
}
