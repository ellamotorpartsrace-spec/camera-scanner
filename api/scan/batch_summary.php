<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

require_once __DIR__ . '/../core/db.php';

try {
    // We fetch all unique batches submitted today, along with the count of items in each batch.
    // They are ordered descending, so the newest batch is on top.
    $from = $_GET['from'] ?? null;
    $to = $_GET['to'] ?? null;

    $whereStr = "DATE(created_at) = CURDATE()";
    $params = [];
    if ($from && $to) {
        $whereStr = "DATE(created_at) >= :from AND DATE(created_at) <= :to";
        $params[':from'] = $from;
        $params[':to'] = $to;
    }

    $sql = "
        SELECT 
            IFNULL(gs1_batch, 'NORMAL') as id, 
            COUNT(*) as count,
            SUM(IF(parcel_size = 'POUCH', 1, 0)) as pouch_count,
            SUM(IF(parcel_size = 'BULKY', 1, 0)) as bulky_count
        FROM scans 
        WHERE $whereStr 
        GROUP BY IFNULL(gs1_batch, 'NORMAL') 
        ORDER BY IF(IFNULL(gs1_batch, 'NORMAL') = 'NORMAL', 1, 0) ASC, CAST(SUBSTRING_INDEX(IFNULL(gs1_batch, 'NORMAL'), '-', -1) AS UNSIGNED) DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Clean up output to match JS expected format
    $formatted = [];
    foreach ($batches as $b) {
        if ($b['id'] === 'NORMAL') {
            $formatted[] = [
                "id" => "NORMAL",
                "count" => (int)$b['count'],
                "pouch_count" => (int)$b['pouch_count'],
                "bulky_count" => (int)$b['bulky_count']
            ];
        } else {
            $batchNum = (int)str_replace("BATCH-", "", $b['id']);
            $formatted[] = [
                "id" => $batchNum,
                "count" => (int)$b['count'],
                "pouch_count" => (int)$b['pouch_count'],
                "bulky_count" => (int)$b['bulky_count']
            ];
        }
    }
    
    echo json_encode(["status" => "success", "data" => $formatted]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
