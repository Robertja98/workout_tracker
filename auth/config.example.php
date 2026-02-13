<?php
/**
 * Authentication System Configuration
 * 
 * Copy this file to config.php and update with your settings
 * DO NOT commit config.php to version control
 */

return [
    // Storage configuration (CSV files)
    'storage' => [
        'data_dir' => __DIR__ . '/data',  // Where CSV files are stored
    ],
    
    // Security settings
    'security' => [
        // Password hashing (PASSWORD_ARGON2ID is recommended for 2026)
        'password_algo' => PASSWORD_ARGON2ID,
        'password_options' => [
            'memory_cost' => 65536,  // 64 MB
            'time_cost' => 4,
            'threads' => 3,
        ],
        
        // Session settings
        'session_name' => 'WORKOUT_SESSION',
        'session_lifetime' => 86400, // 24 hours in seconds
        'session_cookie_secure' => true,  // Require HTTPS
        'session_cookie_httponly' => true,
        'session_cookie_samesite' => 'Strict',
        
        // CSRF protection
        'csrf_token_name' => 'csrf_token',
        'csrf_token_length' => 32,
        
        // Rate limiting
        'max_login_attempts' => 5,
        'lockout_duration' => 900, // 15 minutes in seconds
        'rate_limit_window' => 900, // 15 minutes
    ],
    
    // Application settings
    'app' => [
        'name' => 'Workout Tracker',
        'base_url' => 'http://localhost/Workout',
        'require_email_verification' => false,
        'enable_2fa' => false,
        'admin_email' => 'admin@example.com',
    ],
    
    // Validation rules
    'validation' => [
        'username_min_length' => 3,
        'username_max_length' => 50,
        'password_min_length' => 8,
        'password_require_uppercase' => true,
        'password_require_lowercase' => true,
        'password_require_number' => true,
        'password_require_special' => true,
    ],
    
    // Logging
    'logging' => [
        'enabled' => true,
        'log_failed_logins' => true,
        'log_successful_logins' => true,
        'log_registrations' => true,
        'log_password_changes' => true,
    ],
];
