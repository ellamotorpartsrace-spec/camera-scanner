<?php
/**
 * Database Connection Diagnostic Tool
 * This script tests the connection settings in api/core/config.php
 * and provides detailed error logging to help identify why the connection fails.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$config_path = __DIR__ . '/api/core/config.php';
$log_file = __DIR__ . '/db_connection_test.log';

function log_msg($msg)
{
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $msg" . PHP_EOL, FILE_APPEND);
    echo $msg . "<br>";
}

echo "<h2>Database Connection Tester</h2>";
echo "<p>Checking configuration file: <code>$config_path</code></p>";

if (!file_exists($config_path)) {
    log_msg("ERROR: Configuration file not found at $config_path");
    exit;
}

$config = require $config_path;
$db = $config['db'] ?? null;

if (!$db) {
    log_msg("ERROR: 'db' configuration section missing in config.php");
    exit;
}

log_msg("Attempting to connect to host: <b>" . $db['host'] . "</b>");
log_msg("Database Name: " . $db['name']);
log_msg("User: " . $db['user']);

try {
    $host_parts = explode(':', $db['host']);
    $host = $host_parts[0];
    $port = isset($host_parts[1]) ? ";port=" . $host_parts[1] : "";

    $dsn = "mysql:host=$host$port;dbname={$db['name']};charset={$db['charset']}";

    $start_time = microtime(true);
    $pdo = new PDO($dsn, $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5 // 5 second timeout
    ]);
    $end_time = microtime(true);

    log_msg("<b>SUCCESS!</b> Connection established in " . round($end_time - $start_time, 4) . " seconds.");
    log_msg("Server version: " . $pdo->getAttribute(PDO::ATTR_SERVER_VERSION));

    // --- NEW: Schema Verification ---
    echo "<h3>Schema Verification:</h3>";
    $required_tables = ['scans', 'scan_history'];
    $missing_tables = [];

    foreach ($required_tables as $table) {
        try {
            $stmt = $pdo->query("DESCRIBE `$table`");
            log_msg("✅ Table `<b>$table</b>` exists.");
        } catch (PDOException $e) {
            $missing_tables[] = $table;
            log_msg("❌ Table `<b>$table</b>` is <b>MISSING</b>.");
        }
    }

    if (!empty($missing_tables)) {
        echo "<p style='color:red;'><b>CRITICAL:</b> Your database is missing required tables. Please import <code>ella_scanner.sql</code> into your Hostinger database using phpMyAdmin.</p>";
    } else {
        echo "<p style='color:green;'><b>All required tables are present.</b></p>";
    }

} catch (PDOException $e) {
    $errorCode = $e->getCode();
    $errorMessage = $e->getMessage();

    log_msg("<b>FAILED!</b> Connection error.");
    log_msg("Error Code: $errorCode");
    log_msg("Error Message: <span style='color:red;'>$errorMessage</span>");

    echo "<h3>Analysis & Troubleshooting:</h3>";
    echo "<ul>";

    if (strpos($errorMessage, 'Access denied for user') !== false) {
        echo "<li><b>Password Issue:</b> This usually means the username or password in <code>config.php</code> is incorrect. Double-check your Hostinger DB credentials.</li>";
        echo "<li><b>User Privileges:</b> Ensure the user '{$db['user']}' is assigned to the database '{$db['name']}' with all privileges in Hostinger panel.</li>";
    } elseif (strpos($errorMessage, 'Unknown database') !== false) {
        echo "<li><b>Database Name Issue:</b> The database name '{$db['name']}' was not found. Check if there are typos.</li>";
    } elseif (strpos($errorMessage, 'Connection timed out') !== false || strpos($errorMessage, 'Connection refused') !== false || strpos($errorMessage, 'getaddrinfo failed') !== false) {
        echo "<li><b>Host/Remote Access Issue:</b> 
                <ul>
                    <li>If you are connecting from XAMPP (Local) to Hostinger (Remote), you cannot use <code>127.0.0.1</code> or <code>localhost</code>. You must use the <b>MySQL Host</b> provided in your Hostinger dashboard.</li>
                    <li><b>Remote MySQL:</b> You must enable 'Remote MySQL' in your Hostinger control panel and add your local IP address to the whitelist.</li>
                    <li><b>Port 3306:</b> Ensure your firewall is not blocking outbound connections on port 3306.</li>
                </ul>
            </li>";
    } else {
        echo "<li><b>Unknown Error:</b> Please check if the host server is running and accessible.</li>";
    }
    echo "</ul>";
}

echo "<p><i>Full log available at: $log_file</i></p>";
