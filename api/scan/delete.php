<?php
require_once __DIR__ . '/../core/bootstrap.php';
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../core/db.php';

try {
    $rawInput = '';
    $data = null;

    if (!empty($_POST['payload'])) {
        $rawInput = $_POST['payload'];
    } elseif (!empty($_POST['data'])) {
        $rawInput = $_POST['data'];
    } elseif (!empty($_REQUEST['payload'])) {
        $rawInput = $_REQUEST['payload'];
    } elseif (!empty($_REQUEST['data'])) {
        $rawInput = $_REQUEST['data'];
    }

    if (!empty($rawInput)) {
        $data = json_decode($rawInput, true);
    }

    if ($data === null) {
        $phpInput = (string) file_get_contents("php://input");
        $phpInput = preg_replace('/^\xef\xbb\xbf/', '', $phpInput);
        $phpInput = trim($phpInput);

        if (!empty($phpInput)) {
            if (empty($rawInput)) {
                $rawInput = $phpInput;
            }
            $data = json_decode($phpInput, true);

            if ($data === null) {
                parse_str($phpInput, $parsed);
                if (!empty($parsed['payload'])) {
                    $rawInput = $parsed['payload'];
                    $data = json_decode($parsed['payload'], true);
                } elseif (!empty($parsed['data'])) {
                    $rawInput = $parsed['data'];
                    $data = json_decode($parsed['data'], true);
                } elseif (!empty($parsed['codes']) && is_array($parsed['codes'])) {
                    $data = $parsed;
                }
            }
        }
    }

    if (!isset($data['codes']) || !is_array($data['codes']) || empty($data['codes'])) {
        http_response_code(400);
        ob_clean();
        echo json_encode(["status" => "error", "message" => "No codes provided for deletion."]);
        exit;
    }

    $codes = $data['codes'];
    
    // Create placeholders for the IN clause
    $placeholders = implode(',', array_fill(0, count($codes), '?'));
    
    $sql = "DELETE FROM scans WHERE code_value IN ($placeholders)";
    $stmt = $pdo->prepare($sql);
    
    $stmt->execute($codes);
    $deletedCount = $stmt->rowCount();

    echo json_encode([
        "status" => "success",
        "message" => "Successfully deleted $deletedCount records.",
        "deleted_count" => $deletedCount
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Database error: " . $e->getMessage()
    ]);
}
