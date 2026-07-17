<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

require_once __DIR__ . '/../core/db.php';

try {
    $stmt = $pdo->query("SELECT COUNT(*) as total, SUM(IF(returned_at IS NOT NULL AND returned_at > '2000-01-01', 1, 0)) as returned FROM scans WHERE DATE(scanned_at) = CURDATE()");
    $row = $stmt->fetch();
    
    echo json_encode([
        "status" => "success",
        "data" => [
            "total" => (int) ($row['total'] ?? 0),
            "returned" => (int) ($row['returned'] ?? 0)
        ]
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
