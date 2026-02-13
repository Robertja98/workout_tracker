<?php
/**
 * Maintenance Script
 * 
 * Run this periodically to clean up old data
 */

require_once __DIR__ . '/CsvDataStore.php';

$config = require __DIR__ . '/config.php';
$store = new CsvDataStore($config);

echo "=== Auth System Maintenance ===\n\n";

// Clean expired sessions
echo "Cleaning expired sessions...\n";
$sessions = $store->fetchAll('sessions');
$expiredCount = 0;
foreach ($sessions as $session) {
    if (isset($session['expires_at']) && strtotime($session['expires_at']) < time()) {
        $expiredCount++;
    }
}
$store->cleanup('sessions', 'expires_at', date('Y-m-d H:i:s'));
echo "Removed $expiredCount expired sessions\n\n";

// Clean old login attempts (keep last 30 days)
echo "Cleaning old login attempts...\n";
$cutoff = date('Y-m-d H:i:s', strtotime('-30 days'));
$removed = $store->cleanup('login_attempts', 'attempted_at', $cutoff);
echo "Removed $removed old login attempts\n\n";

// Clean old activity logs (keep last 90 days)
echo "Cleaning old activity logs...\n";
$cutoff = date('Y-m-d H:i:s', strtotime('-90 days'));
$removed = $store->cleanup('activity_log', 'created_at', $cutoff);
echo "Removed $removed old activity log entries\n\n";

// Show statistics
echo "=== Current Statistics ===\n";
echo "Total users: " . $store->count('users') . "\n";
echo "Active sessions: " . $store->count('sessions') . "\n";
echo "Login attempts (last 30 days): " . $store->count('login_attempts') . "\n";
echo "Activity logs (last 90 days): " . $store->count('activity_log') . "\n";

echo "\nMaintenance complete!\n";
