<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");

require_once __DIR__ . '/../core/db.php';

$data = json_decode(file_get_contents("php://input"), true);

if (!is_array($data) || !isset($data['scans']) || !is_array($data['scans'])) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid payload"]);
    exit;
}

$scans = $data['scans'];
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

$results = [
    "total" => count($scans),
    "saved" => 0,
    "duplicates" => 0,
    "errors" => 0
];

try {
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
            created_at
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
            NOW()
        )
        ON DUPLICATE KEY UPDATE
            scanned_at   = NOW(),
            returned_at  = IF(:is_return_upd, NOW(), returned_at),
            update_count = update_count + 1,
            ip_address   = :ip_upd,
            courier      = IF(:courier_upd != '', :courier_upd2, courier),
            parcel_size  = IF(:parcel_size_upd != '', :parcel_size_upd2, parcel_size),
            platform     = IF(:platform_upd != '', :platform_upd2, platform)
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
            ":platform_upd2" => $platform
        ]);

        $isDuplicate = ($stmt->rowCount() === 2);
        if ($isDuplicate) {
            $results["duplicates"]++;
        } else {
            $results["saved"]++;
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
    echo json_encode([
        "status" => "error", 
        "message" => "Batch save failed: " . $e->getMessage()
    ]);
}
