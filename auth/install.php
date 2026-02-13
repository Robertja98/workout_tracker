<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auth System Installer</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 { color: #333; margin-bottom: 10px; }
        .subtitle { color: #666; margin-bottom: 30px; }
        .step {
            background: #f9f9f9;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 4px;
            border-left: 4px solid #4CAF50;
        }
        .step h2 { color: #333; margin-bottom: 10px; font-size: 18px; }
        .step p { color: #666; margin-bottom: 10px; line-height: 1.6; }
        .success { background: #e8f5e9; border-color: #4CAF50; }
        .error { background: #ffebee; border-color: #f44336; }
        .warning { background: #fff3e0; border-color: #ff9800; }
        .code {
            background: #263238;
            color: #aed581;
            padding: 15px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            overflow-x: auto;
            margin: 10px 0;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 20px;
            font-weight: 500;
        }
        .btn:hover { background: #45a049; }
        .checklist {
            list-style: none;
            margin: 15px 0;
        }
        .checklist li {
            padding: 8px 0;
            color: #666;
        }
        .checklist li:before {
            content: "□ ";
            color: #4CAF50;
            font-weight: bold;
            margin-right: 8px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        table th, table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        table th {
            background: #f5f5f5;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔐 Authentication System Installer</h1>
        <p class="subtitle">Set up secure CSV-based authentication for your PHP application (No database required!)</p>

        <?php
        $errors = [];
        $warnings = [];
        $success = [];
        
        // Check PHP version
        if (version_compare(PHP_VERSION, '7.4.0') >= 0) {
            $success[] = "PHP version " . PHP_VERSION . " is supported";
        } else {
            $errors[] = "PHP 7.4 or higher required. Current version: " . PHP_VERSION;
        }
        
        // Check for required functions
        if (function_exists('password_hash')) {
            $success[] = "Password hashing functions available";
        } else {
            $errors[] = "Password hashing functions not available";
        }
        
        // Check if Argon2ID is available
        if (defined('PASSWORD_ARGON2ID')) {
            $success[] = "Argon2ID password hashing available (recommended)";
        } else {
            $warnings[] = "Argon2ID not available. Will fall back to bcrypt.";
        }
        
        // Check if config exists
        $configExists = file_exists(__DIR__ . '/config.php');
        if ($configExists) {
            $success[] = "Configuration file exists";
        } else {
            $warnings[] = "Configuration file not found. Copy config.example.php to config.php";
        }
        
        // Check data directory
        $dataDir = __DIR__ . '/data';
        if (is_dir($dataDir)) {
            $success[] = "Data directory exists";
            
            // Check if writable
            if (is_writable($dataDir)) {
                $success[] = "Data directory is writable";
            } else {
                $errors[] = "Data directory is not writable. Run: chmod 755 " . $dataDir;
            }
            
            // Check for CSV files
            $csvFiles = ['users.csv', 'sessions.csv', 'login_attempts.csv', 'activity_log.csv'];
            $existingFiles = [];
            foreach ($csvFiles as $file) {
                if (file_exists($dataDir . '/' . $file)) {
                    $existingFiles[] = $file;
                }
            }
            
            if (count($existingFiles) === count($csvFiles)) {
                $success[] = "All CSV data files exist";
            } elseif (count($existingFiles) > 0) {
                $success[] = "Some CSV files exist: " . implode(', ', $existingFiles);
                $warnings[] = "Missing CSV files will be created automatically on first use";
            } else {
                $warnings[] = "No CSV files yet. They will be created automatically when you register the first user.";
            }
        } else {
            $warnings[] = "Data directory will be created automatically on first use";
        }
        ?>
        
        <?php if (!empty($success)): ?>
            <div class="step success">
                <h2>✅ System Checks Passed</h2>
                <?php foreach ($success as $msg): ?>
                    <p>✓ <?= htmlspecialchars($msg) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($warnings)): ?>
            <div class="step warning">
                <h2>⚠️ Warnings</h2>
                <?php foreach ($warnings as $msg): ?>
                    <p>⚠ <?= htmlspecialchars($msg) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <div class="step error">
                <h2>❌ Errors</h2>
                <?php foreach ($errors as $msg): ?>
                    <p>✗ <?= htmlspecialchars($msg) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <div class="step">
            <h2>📋 Installation Steps</h2>
            <ul class="checklist">
                <li>Copy config.example.php to config.php (optional - defaults work fine)</li>
                <li>Ensure auth/data/ directory is writable (chmod 755)</li>
                <li>Test registration at register.php</li>
                <li>Test login at login.php</li>
                <li>Add middleware to protected pages</li>
                <li>Add auth/data/ to .gitignore</li>
                <li>Enable HTTPS in production</li>
            </ul>
        </div>
        
        <div class="step">
            <h2>📁 Data Storage</h2>
            <p>This auth system uses <strong>CSV files</strong> stored in the <code>auth/data/</code> directory.</p>
            <p><strong>No database required!</strong> CSV files are created automatically when you register your first user.</p>
            <p style="margin-top: 10px;"><strong>Important:</strong> Add <code>auth/data/</code> to your <code>.gitignore</code> file to protect user data.</p>
        </div>
        
        <div class="step">
            <h2>🔧 Configuration (Optional)</h2>
            <p>The default configuration works out of the box. To customize,copy <strong>config.example.php</strong> to <strong>config.php</strong> and edit:</p>
            <div class="code">'storage' => [<br>
    'data_dir' => __DIR__ . '/data',  // CSV files location<br>
],</div>
        </div>
        
        <div class="step">
            <h2>🛡️ Protect Your Pages</h2>
            <p>Add this at the top of any page requiring authentication:</p>
            <div class="code">&lt;?php<br>
require_once __DIR__ . '/auth/middleware.php';<br>
// Your protected page code<br>
?&gt;</div>
        </div>
        
        <div class="step">
            <h2>🔗 Quick Links</h2>
            <table>
                <tr>
                    <th>Page</th>
                    <th>Description</th>
                </tr>
                <tr>
                    <td><a href="register.php">register.php</a></td>
                    <td>User registration page</td>
                </tr>
                <tr>
                    <td><a href="login.php">login.php</a></td>
                    <td>User login page</td>
                </tr>
                <tr>
                    <td><a href="logout.php">logout.php</a></td>
                    <td>Logout handler</td>
                </tr>
                <tr>
                    <td><a href="../index.php">../index.php</a></td>
                    <td>Main application (protect this)</td>
                </tr>
            </table>
        </div>
        
        <div class="step">
            <h2>📚 Documentation</h2>
            <p>For detailed information, see:</p>
            <ul style="margin: 10px 0; padding-left: 20px;">
                <li><strong>README.md</strong> - Full documentation and API reference</li>
                <li><strong>SETUP.md</strong> - Step-by-step setup guide</li>
                <li><strong>config.example.php</strong> - Configuration options</li>
            </ul>
        </div>
        
        <?php if (empty($errors) && empty($missingTables ?? [])): ?>
            <div class="step success">
                <h2>🎉 Ready to Go!</h2>
                <p>Your authenti): ?>
            <div class="step success">
                <h2>🎉 Ready to Go!</h2>
                <p>Your CSV-based authentication system is ready to use. No database setup needed!
        <?php endif; ?>
    </div>
</body>
</html>
