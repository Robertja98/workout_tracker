<?php
/**
 * Auto-Configure deployment (runs after cPanel deploys)
 * Detects domain and updates config.php base_url automatically
 */

$configFile = __DIR__ . '/config.php';

// Detect the domain from server environment
$domain = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$baseUrl = $protocol . '://' . $domain;

// Read current config
if (!file_exists($configFile)) {
    die("Config file not found. Run setup first.\n");
}

$configContent = file_get_contents($configFile);

// Replace base_url with detected domain
$configContent = preg_replace(
    "/('base_url'\s*=>\s*')[^']*(')/",
    "$1{$baseUrl}$2",
    $configContent
);

// Write back
if (file_put_contents($configFile, $configContent, LOCK_EX) === false) {
    die("Failed to update config.php\n");
}

echo "✓ Deployment config updated: base_url = {$baseUrl}\n";
?>
