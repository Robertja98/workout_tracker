<?php
/**
 * CSV Authentication System - Quick Test
 * 
 * Tests basic functionality of the CSV-based auth system
 */

require_once __DIR__ . '/Auth.php';

// Load config
$config = require __DIR__ . '/config.php';

echo "<h1>Auth System Test</h1>";
echo "<pre>";

try {
    $auth = new Auth($config);
    echo "✅ Auth system initialized successfully\n\n";
    
    // Test user registration
    echo "Testing user registration...\n";
    $testUsername = 'testuser_' . time();
    $testEmail = $testUsername . '@example.com';
    $testPassword = 'TestPass123!';
    
    $result = $auth->register($testUsername, $testEmail, $testPassword);
    
    if ($result['success']) {
        echo "✅ Registration successful\n";
        echo "   User ID: " . $result['user_id'] . "\n\n";
        
        // Test login
        echo "Testing login...\n";
        $loginResult = $auth->login($testUsername, $testPassword);
        
        if ($loginResult['success']) {
            echo "✅ Login successful\n";
            echo "   User: " . $loginResult['user']['username'] . "\n";
            echo "   Email: " . $loginResult['user']['email'] . "\n\n";
            
            // Test authentication check
            echo "Testing authentication check...\n";
            if ($auth->isAuthenticated()) {
                echo "✅ User is authenticated\n\n";
                
                // Get current user
                $currentUser = $auth->getCurrentUser();
                echo "Current user details:\n";
                echo "   ID: " . $currentUser['id'] . "\n";
                echo "   Username: " . $currentUser['username'] . "\n";
                echo "   Email: " . $currentUser['email'] . "\n\n";
                
                // Test logout
                echo "Testing logout...\n";
                $auth->logout();
                
                if (!$auth->isAuthenticated()) {
                    echo "✅ Logout successful\n\n";
                } else {
                    echo "❌ Logout failed\n\n";
                }
            } else {
                echo "❌ Authentication check failed\n\n";
            }
        } else {
            echo "❌ Login failed: " . $loginResult['error'] . "\n\n";
        }
    } else {
        echo "❌ Registration failed:\n";
        foreach ($result['errors'] as $error) {
            echo "   - $error\n";
        }
        echo "\n";
    }
    
    // Check data files
    echo "Checking CSV data files...\n";
    $dataDir = $config['storage']['data_dir'];
    $files = ['users.csv', 'sessions.csv', 'login_attempts.csv', 'activity_log.csv'];
    
    foreach ($files as $file) {
        $filepath = $dataDir . '/' . $file;
        if (file_exists($filepath)) {
            $size = filesize($filepath);
            $lines = count(file($filepath));
            echo "✅ $file exists ($size bytes, $lines lines)\n";
        } else {
            echo "❌ $file not found\n";
        }
    }
    
    echo "\n=== Test Complete ===\n";
    echo "Check the auth/data/ directory to see the generated CSV files.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}

echo "</pre>";
?>
