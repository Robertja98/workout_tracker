<?php
/**
 * Authentication Middleware
 * 
 * Include this file at the top of any protected page
 * Usage: require_once __DIR__ . '/auth/middleware.php';
 */

require_once __DIR__ . '/Auth.php';

// Load configuration
$configFile = __DIR__ . '/config.php';
if (!file_exists($configFile)) {
    die('Authentication configuration not found. Please set up auth/config.php');
}

$config = require $configFile;

// Initialize auth
$auth = new Auth($config);

// Check if user is authenticated
if (!$auth->isAuthenticated()) {
    // Store the current URL for redirect after login
    $currentUrl = $_SERVER['REQUEST_URI'];
    $loginUrl = '/Workout/auth/login.php?redirect=' . urlencode($currentUrl);
    
    header('Location: ' . $loginUrl);
    exit;
}

// Make auth and current user available globally
$GLOBALS['auth'] = $auth;
$GLOBALS['current_user'] = $auth->getCurrentUser();

/**
 * Helper function to get current authenticated user
 */
function auth_current_user() {
    return $GLOBALS['current_user'] ?? null;
}

/**
 * Helper function to check if user is authenticated
 */
function auth_check() {
    return isset($GLOBALS['current_user']) && $GLOBALS['current_user'] !== null;
}

/**
 * Helper function to get auth instance
 */
function auth() {
    return $GLOBALS['auth'] ?? null;
}
