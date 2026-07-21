<?php
require_once __DIR__ . '/../core/bootstrap.php';
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../core/db.php';

// ── Robust input reader ──
// Hostinger/nginx proxies can sometimes deliver the body in different ways.
// Try every known strategy before giving up.
$rawInput = '';
$data = null;

// Strategy 1: Standard php://input (works on most servers)
$rawInput = (string) file_get_contents("php://input");
$rawInput = preg_replace('/^\xef\xbb\xbf/', '', $rawInput); // strip UTF-8 BOM
$rawInput = trim($rawInput);

// Strategy 2: Fall back to $_POST['payload'] or $_POST['data'] (form-encoded fallback)
if (empty($rawInput) && !empty($_POST['payload'])) {
    $rawInput = $_POST['payload'];
}
if (empty($rawInput) && !empty($_POST['data'])) {
    $rawInput = $_POST['data'];
}

if (!empty($rawInput)) {
    $data = json_decode($rawInput, true);
}

// Strategy 3: If $_POST itself IS the structured data (when sent as form-encoded)
if ($data === null && !empty($_POST['scans'])) {
    $data = $_POST;
}


if (!is_array($data)) {
    http_response_code(400);
    $err = [
        "status"       => "error",
        "message"      => "Invalid payload - not JSON",
        "raw"          => substr($rawInput, 0, 200),
        "raw_len"      => strlen($rawInput),
        "json_err"     => json_last_error_msg(),
        "content_type" => $_SERVER['CONTENT_TYPE'] ?? 'none',
        "method"       => $_SERVER['REQUEST_METHOD'] ?? 'none',
        "post_keys"    => array_keys($_POST),
    ];
    file_put_contents(__DIR__ . '/batch_error.log', date('Y-m-d H:i:s') . " - " . json_encode($err) . "\n", FILE_APPEND);
    ob_clean();
    echo json_encode($err);
    exit;
}


// If 'scans' is missing or not an array (e.g. an object), reject cleanly
if (!isset($data['scans'])) {
    http_response_code(400);
    $err = ["status" => "error", "message" => "Invalid payload - missing 'scans' key", "keys" => array_keys($data)];
    file_put_contents(__DIR__ . '/batch_error.log', date('Y-m-d H:i:s') . " - " . json_encode($err) . "\n", FILE_APPEND);
    ob_clean();
    echo json_encode($err);
    exit;
}

// Accept an object/assoc-array by converting it to a simple list
if (!is_array($data['scans'])) {
    http_response_code(400);
    $err = ["status" => "error", "message" => "Invalid payload - 'scans' is not an array", "type" => gettype($data['scans'])];
    file_put_contents(__DIR__ . '/batch_error.log', date('Y-m-d H:i:s') . " - " . json_encode($err) . "\n", FILE_APPEND);
    ob_clean();
    echo json_encode($err);
    exit;
}

$scans = $data['scans'];
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

$results = [
    "total" => count($scans),
    "saved" => 0,
    "duplicates" => 0,
    "errors" => 0,
    "pouch_saved" => 0,
    "bulky_saved" => 0
];

try {
    // Generate chronological Batch ID for today (e.g. BATCH-1, BATCH-2)
    $batchSql = "SELECT gs1_batch FROM scans WHERE DATE(created_at) = CURDATE() AND gs1_batch LIKE 'BATCH-%' ORDER BY CAST(SUBSTRING_INDEX(gs1_batch, '-', -1) AS UNSIGNED) DESC LIMIT 1";
    $batchStmt = $pdo->query($batchSql);
    $lastBatch = $batchStmt->fetchColumn();

    $nextBatchNum = 1;
    if ($lastBatch && preg_match('/BATCH-(\d+)$/', $lastBatch, $matches)) {
        $nextBatchNum = (int)$matches[1] + 1;
    }
    $assignedBatchId = "BATCH-" . $nextBatchNum;

    $pdo->beginTransaction();

    $sql = "
        INSERT INTO scans (
            code_value,
            code_type,
            courier,
            parcel_size,
            platform,
            ip_address,
            user_agent,
            scanned_at,
            returned_at,
            update_count,
            created_at,
            gs1_batch
        )
        VALUES (
            :code,
            :type,
            :courier,
            :parcel_size,
            :platform,
            :ip,
            :ua,
            NOW(),
            IF(:is_return_ins, NOW(), NULL),
            0,
            NOW(),
            :gs1_batch
        )
        ON DUPLICATE KEY UPDATE
            scanned_at   = NOW(),
            returned_at  = IF(:is_return_upd, NOW(), returned_at),
            update_count = update_count + 1,
            ip_address   = :ip_upd,
            courier      = IF(:courier_upd != '', :courier_upd2, courier),
            parcel_size  = IF(:parcel_size_upd != '', :parcel_size_upd2, parcel_size),
            platform     = IF(:platform_upd != '', :platform_upd2, platform),
            gs1_batch    = IF(gs1_batch IS NULL OR gs1_batch = '', :gs1_batch_upd, gs1_batch)
    ";
    
    $stmt = $pdo->prepare($sql);

    $historySQL = "
        INSERT INTO scan_history (
            scan_id,
            code_value,
            ip_address,
            user_agent,
            scanned_at
        )
        VALUES (
            :scan_id,
            :code,
            :ip,
            :ua,
            NOW()
        )
    ";
    $historyStmt = $pdo->prepare($historySQL);

    $fetchSql = "SELECT id FROM scans WHERE code_value = :code";
    $fetchStmt = $pdo->prepare($fetchSql);

    foreach ($scans as $scan) {
        $codeValue = trim($scan['code'] ?? '');
        if (empty($codeValue)) {
            $results["errors"]++;
            continue;
        }

        $codeType = strtoupper(trim($scan['type'] ?? 'UNKNOWN'));
        $courier = trim($scan['courier'] ?? '');
        $parcelSize = strtoupper(trim($scan['parcel_size'] ?? 'POUCH'));
        $platform = trim($scan['platform'] ?? '');
        $isReturn = (bool) ($scan['is_return'] ?? false);

        $allowedTypes = ['QR', 'BARCODE', 'MANUAL'];
        if (!in_array($codeType, $allowedTypes, true)) $codeType = 'UNKNOWN';

        $allowedCouriers = ['JNT Express', 'LBC', 'Shopee Express', 'Lazada Express', 'Flash Express', 'Ninja Van', 'Others', ''];
        if (!in_array($courier, $allowedCouriers, true)) $courier = '';

        $allowedSizes = ['POUCH', 'BULKY'];
        if (!in_array($parcelSize, $allowedSizes, true)) $parcelSize = 'POUCH';

        $allowedPlatforms = ['Lazada', 'TikTok', 'Shopee', ''];
        if (!in_array($platform, $allowedPlatforms, true)) $platform = '';
        $stmt->execute([
            ":code" => $codeValue,
            ":type" => $codeType,
            ":courier" => $courier ?: null,
            ":parcel_size" => $parcelSize,
            ":platform" => $platform,
            ":ip" => $ipAddress,
            ":ua" => $userAgent,
            ":is_return_ins" => $isReturn ? 1 : 0,
            ":is_return_upd" => $isReturn ? 1 : 0,
            ":ip_upd" => $ipAddress,
            ":courier_upd" => $courier,
            ":courier_upd2" => $courier,
            ":parcel_size_upd" => $parcelSize,
            ":parcel_size_upd2" => $parcelSize,
            ":platform_upd" => $platform,
            ":platform_upd2" => $platform,
            ":gs1_batch" => $assignedBatchId,
            ":gs1_batch_upd" => $assignedBatchId
        ]);

        $isDuplicate = ($stmt->rowCount() === 2);
        if ($isDuplicate) {
            $results["duplicates"]++;
        } else {
            $results["saved"]++;
            if ($parcelSize === 'BULKY') {
                $results["bulky_saved"]++;
            } else {
                $results["pouch_saved"]++;
            }
        }

        $fetchStmt->execute([":code" => $codeValue]);
        $scanRecord = $fetchStmt->fetch();

        if ($scanRecord && isset($scanRecord['id'])) {
            $historyStmt->execute([
                ":scan_id" => $scanRecord['id'],
                ":code" => $codeValue,
                ":ip" => $ipAddress,
                ":ua" => $userAgent
            ]);
        }
    }

    $pdo->commit();

    ob_clean(); // Discard any stray output (PHP warnings, BOM, etc.)
    echo json_encode([
        "status" => "success",
        "results" => $results
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("BATCH SAVE ERROR: " . $e->getMessage());
    http_response_code(500);
    ob_clean(); // Discard any stray output
    echo json_encode([
        "status" => "error", 
        "message" => "Batch save failed: " . $e->getMessage()
    ]);
}
