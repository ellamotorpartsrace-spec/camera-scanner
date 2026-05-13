<?php
/**
 * Test Database Connection with Sample Config
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = "127.0.0.1";
$port = "3306";
$dbname = "u296077208_ella_parts_db";
$user = "u296077208_BenzEllaMotor";
$pass = "elladbPogisiBen13";

echo "Attempting to connect with credentials from sampleconfig.php...\n";
echo "Host: $host:$port\n";
echo "Database: $dbname\n";
echo "User: $user\n";

try {
    // Try both formats
    $dsn1 = "mysql:host=$host:$port;dbname=$dbname";
    $dsn2 = "mysql:host=$host;port=$port;dbname=$dbname";
    
    echo "Trying DSN 1: $dsn1\n";
    try {
        $pdo = new PDO($dsn1, $user, $pass);
        echo "SUCCESS with DSN 1!\n";
    } catch (PDOException $e1) {
        echo "FAILED DSN 1: " . $e1->getMessage() . "\n";
        
        echo "Trying DSN 2: $dsn2\n";
        try {
            $pdo = new PDO($dsn2, $user, $pass);
            echo "SUCCESS with DSN 2!\n";
        } catch (PDOException $e2) {
            echo "FAILED DSN 2: " . $e2->getMessage() . "\n";
        }
    }
} catch (Exception $e) {
    echo "General Error: " . $e->getMessage() . "\n";
}
