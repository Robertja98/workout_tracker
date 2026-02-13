<?php
/**
 * CSV Data Handler
 * 
 * Provides CSV-based data storage for authentication system
 * Alternative to database storage
 */

class CsvDataStore {
    private $dataDir;
    private $config;
    
    public function __construct($config) {
        $this->config = $config;
        $this->dataDir = $config['storage']['data_dir'];
        
        // Create data directory if it doesn't exist
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0755, true);
        }
        
        // Initialize CSV files if they don't exist
        $this->initializeFiles();
    }
    
    /**
     * Initialize CSV files with headers
     */
    private function initializeFiles() {
        $files = [
            'users.csv' => ['id', 'username', 'email', 'password_hash', 'created_at', 'updated_at', 'last_login', 'is_active', 'is_verified', 'verification_token', 'reset_token', 'reset_token_expires', 'failed_login_attempts', 'locked_until'],
            'sessions.csv' => ['id', 'user_id', 'session_token', 'ip_address', 'user_agent', 'created_at', 'expires_at', 'last_activity'],
            'login_attempts.csv' => ['id', 'username_or_email', 'ip_address', 'success', 'attempted_at'],
            'activity_log.csv' => ['id', 'user_id', 'action_type', 'action_details', 'ip_address', 'user_agent', 'created_at'],
        ];
        
        foreach ($files as $filename => $headers) {
            $filepath = $this->dataDir . '/' . $filename;
            if (!file_exists($filepath)) {
                $fp = fopen($filepath, 'w');
                fputcsv($fp, $headers);
                fclose($fp);
            }
        }
    }
    
    /**
     * Read all records from a CSV file
     */
    private function readCsv($filename) {
        $filepath = $this->dataDir . '/' . $filename;
        $records = [];
        
        if (!file_exists($filepath)) {
            return $records;
        }
        
        $fp = fopen($filepath, 'r');
        $headers = fgetcsv($fp);
        
        if (!$headers) {
            fclose($fp);
            return $records;
        }
        
        while (($row = fgetcsv($fp)) !== false) {
            if (count($row) === count($headers)) {
                $record = array_combine($headers, $row);
                $records[] = $record;
            }
        }
        
        fclose($fp);
        return $records;
    }
    
    /**
     * Write all records to a CSV file
     */
    private function writeCsv($filename, $records, $headers) {
        $filepath = $this->dataDir . '/' . $filename;
        $tempFile = $filepath . '.tmp';
        
        $fp = fopen($tempFile, 'w');
        fputcsv($fp, $headers);
        
        foreach ($records as $record) {
            $row = [];
            foreach ($headers as $header) {
                $row[] = $record[$header] ?? '';
            }
            fputcsv($fp, $row);
        }
        
        fclose($fp);
        
        // Atomic replace
        rename($tempFile, $filepath);
    }
    
    /**
     * Insert a record
     */
    public function insert($table, $data) {
        $filename = $table . '.csv';
        $records = $this->readCsv($filename);
        
        // Generate ID
        $maxId = 0;
        foreach ($records as $record) {
            if (isset($record['id']) && (int)$record['id'] > $maxId) {
                $maxId = (int)$record['id'];
            }
        }
        $data['id'] = $maxId + 1;
        
        // Add timestamp if not set
        if (!isset($data['created_at'])) {
            $data['created_at'] = date('Y-m-d H:i:s');
        }
        
        $records[] = $data;
        
        // Get headers from first record or data keys
        $headers = !empty($records[0]) ? array_keys($records[0]) : array_keys($data);
        
        $this->writeCsv($filename, $records, $headers);
        
        return $data['id'];
    }
    
    /**
     * Find one record
     */
    public function fetchOne($table, $where) {
        $records = $this->readCsv($table . '.csv');
        
        foreach ($records as $record) {
            $match = true;
            foreach ($where as $field => $value) {
                if (!isset($record[$field]) || $record[$field] !== $value) {
                    $match = false;
                    break;
                }
            }
            if ($match) {
                return $record;
            }
        }
        
        return null;
    }
    
    /**
     * Find all matching records
     */
    public function fetchAll($table, $where = []) {
        $records = $this->readCsv($table . '.csv');
        
        if (empty($where)) {
            return $records;
        }
        
        $results = [];
        foreach ($records as $record) {
            $match = true;
            foreach ($where as $field => $value) {
                if (!isset($record[$field]) || $record[$field] !== $value) {
                    $match = false;
                    break;
                }
            }
            if ($match) {
                $results[] = $record;
            }
        }
        
        return $results;
    }
    
    /**
     * Update records
     */
    public function update($table, $data, $where) {
        $filename = $table . '.csv';
        $records = $this->readCsv($filename);
        $updated = false;
        
        // Add updated_at timestamp
        if (!isset($data['updated_at'])) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }
        
        foreach ($records as $index => $record) {
            $match = true;
            foreach ($where as $field => $value) {
                if (!isset($record[$field]) || $record[$field] !== $value) {
                    $match = false;
                    break;
                }
            }
            
            if ($match) {
                $records[$index] = array_merge($record, $data);
                $updated = true;
            }
        }
        
        if ($updated && !empty($records)) {
            $headers = array_keys($records[0]);
            $this->writeCsv($filename, $records, $headers);
        }
        
        return $updated;
    }
    
    /**
     * Delete records
     */
    public function delete($table, $where) {
        $filename = $table . '.csv';
        $records = $this->readCsv($filename);
        $newRecords = [];
        
        foreach ($records as $record) {
            $match = true;
            foreach ($where as $field => $value) {
                if (!isset($record[$field]) || $record[$field] !== $value) {
                    $match = false;
                    break;
                }
            }
            
            if (!$match) {
                $newRecords[] = $record;
            }
        }
        
        if (!empty($newRecords)) {
            $headers = array_keys($newRecords[0]);
            $this->writeCsv($filename, $newRecords, $headers);
        } else {
            // Write empty file with headers
            $this->initializeFiles();
        }
        
        return true;
    }
    
    /**
     * Count records matching criteria
     */
    public function count($table, $where = []) {
        return count($this->fetchAll($table, $where));
    }
    
    /**
     * Execute a custom filter function
     */
    public function filter($table, callable $filterFunc) {
        $records = $this->readCsv($table . '.csv');
        return array_filter($records, $filterFunc);
    }
    
    /**
     * Clean up old records
     */
    public function cleanup($table, $field, $olderThan) {
        $filename = $table . '.csv';
        $records = $this->readCsv($filename);
        $newRecords = [];
        $cutoffTime = strtotime($olderThan);
        
        foreach ($records as $record) {
            if (isset($record[$field])) {
                $recordTime = strtotime($record[$field]);
                if ($recordTime >= $cutoffTime) {
                    $newRecords[] = $record;
                }
            } else {
                $newRecords[] = $record;
            }
        }
        
        if (!empty($newRecords)) {
            $headers = array_keys($newRecords[0]);
            $this->writeCsv($filename, $newRecords, $headers);
        }
        
        return count($records) - count($newRecords);
    }
    
    /**
     * Lock file for atomic operations
     */
    private function lockFile($filepath) {
        $lockFile = $filepath . '.lock';
        $fp = fopen($lockFile, 'w');
        if (flock($fp, LOCK_EX)) {
            return ['fp' => $fp, 'lock' => $lockFile];
        }
        fclose($fp);
        return null;
    }
    
    /**
     * Unlock file
     */
    private function unlockFile($lock) {
        flock($lock['fp'], LOCK_UN);
        fclose($lock['fp']);
        @unlink($lock['lock']);
    }
}
