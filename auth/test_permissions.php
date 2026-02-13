<?php
echo "<h1>Permission Test</h1>";

$dataDir = __DIR__ . '/data';
$testFile = $dataDir . '/php_write_test.txt';

echo "<p>Data directory: <code>$dataDir</code></p>";
echo "<p>Directory exists: " . (is_dir($dataDir) ? "✅ YES" : "❌ NO") . "</p>";
echo "<p>Directory readable: " . (is_readable($dataDir) ? "✅ YES" : "❌ NO") . "</p>";
echo "<p>Directory writable: " . (is_writable($dataDir) ? "✅ YES" : "❌ NO") . "</p>";

// Try to write a test file
echo "<h2>Write Test</h2>";
try {
    $content = "PHP test at " . date('Y-m-d H:i:s');
    $result = file_put_contents($testFile, $content);
    
    if ($result !== false) {
        echo "✅ PHP successfully wrote to directory!<br>";
        
        // Try to read it back
        $read = file_get_contents($testFile);
        echo "✅ PHP successfully read the file: <code>$read</code><br>";
        
        // Clean up
        unlink($testFile);
        echo "✅ Test file cleaned up<br>";
    } else {
        echo "❌ PHP could not write to directory<br>";
    }
} catch (Exception $e) {
    echo "❌ Error: " . htmlspecialchars($e->getMessage()) . "<br>";
}

// Check if CSV files can be created
echo "<h2>CSV File Creation</h2>";
$csvFiles = ['users.csv', 'sessions.csv', 'login_attempts.csv', 'activity_log.csv'];
foreach ($csvFiles as $file) {
    $path = $dataDir . '/' . $file;
    echo "File <code>$file</code>: " . (file_exists($path) ? "✅ EXISTS" : "⏳ NOT YET (will create on first use)") . "<br>";
}

echo "<h2>Result</h2>";
if (is_writable($dataDir)) {
    echo "<p style='color: green; font-weight: bold;'>✅ All permissions OK! You can now register users.</p>";
    echo "<p><a href='register.php'>Go to Registration →</a></p>";
} else {
    echo "<p style='color: red; font-weight: bold;'>❌ Directory still not writable by PHP</p>";
    echo "<p>Check the permissions or try using a different data directory location.</p>";
}
?>
