<?php
echo "<h1>PHP Test Page</h1>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>Server Time: " . date('Y-m-d H:i:s') . "</p>";
echo "<p>Current Directory: " . __DIR__ . "</p>";

// Test database connection
try {
    $pdo = new PDO("mysql:host=localhost;dbname=pembayaran_listrik", "root", "");
    echo "<p style='color: green;'>✅ Database Connection: SUCCESS</p>";
    
    // Test query
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "<p>Tables found: " . implode(', ', $tables) . "</p>";
    
} catch(Exception $e) {
    echo "<p style='color: red;'>❌ Database Connection: FAILED - " . $e->getMessage() . "</p>";
}

// List files in current directory
echo "<h3>Files in current directory:</h3>";
$files = scandir('.');
foreach($files as $file) {
    if($file != '.' && $file != '..') {
        echo "- " . $file . "<br>";
    }
}
?>