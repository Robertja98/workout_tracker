<?php
/**
 * Logout Handler
 */

require_once __DIR__ . '/Auth.php';

// Load config
$configFile = __DIR__ . '/config.php';
if (!file_exists($configFile)) {
    die('Configuration file not found.');
}
$config = require $configFile;

$auth = new Auth($config);
$auth->logout();

// Redirect to login page
header('Location: login.php');
exit;
