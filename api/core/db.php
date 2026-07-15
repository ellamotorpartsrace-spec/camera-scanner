<?php
/**
 * Database Connection Handler
 */

$config = require __DIR__ . '/config.php';

try {
    // Split host and port if host contains a colon (e.g. 127.0.0.1:3306)
    $host_parts = explode(':', $config['db']['host']);
    $host = $host_parts[0];
    $port = isset($host_parts[1]) ? ";port=" . $host_parts[1] : "";
    
    $dsn = "mysql:host=$host$port;dbname={$config['db']['name']};charset={$config['db']['charset']}";
    $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_general_ci"
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    $error_msg = "Database connection failed: " . $e->getMessage();
    
    // Log error to file for diagnosis
    $log_file = dirname(__DIR__, 2) . '/db_connection_test.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] [APP ERROR] $error_msg" . PHP_EOL, FILE_APPEND);
    
    if (!headers_sent()) {
        header("Content-Type: application/json");
        echo json_encode(["status" => "error", "message" => $error_msg]);
    } else {
        echo $error_msg;
    }
    exit;
}

// Export security hash for convenience
if (isset($config['security']['expected_hash'])) {
    $EXPECTED_HASH = $config['security']['expected_hash'];
}
