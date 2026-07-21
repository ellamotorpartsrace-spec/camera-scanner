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

$rawInput = file_get_contents("php://input");
$rawInput = preg_replace('/^[\xef\xbb\xbf]+/', '', $rawInput);
$data = json_decode($rawInput, true);

if (!is_array($data) || empty($data['code'])) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid payload"]);
    exit;
}

$codeValue = trim($data['code']);
$codeType = strtoupper(trim($data['type'] ?? 'UNKNOWN'));
$courier = trim($data['courier'] ?? '');
$parcelSize = strtoupper(trim($data['parcel_size'] ?? 'POUCH'));

$allowedTypes = ['QR', 'BARCODE', 'MANUAL'];
if (!in_array($codeType, $allowedTypes, true)) {
    $codeType = 'UNKNOWN';
}

$allowedCouriers = ['JNT Express', 'LBC', 'Shopee Express', 'Lazada Express', 'Flash Express', 'Ninja Van', 'Others', ''];
if (!in_array($courier, $allowedCouriers, true)) {
    $courier = '';
}

$allowedSizes = ['POUCH', 'BULKY'];
if (!in_array($parcelSize, $allowedSizes, true)) {
    $parcelSize = 'POUCH';
}

$platform = trim($data['platform'] ?? '');
$allowedPlatforms = ['Lazada', 'TikTok', 'Shopee', ''];
if (!in_array($platform, $allowedPlatforms, true)) {
    $platform = '';
}

$ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

$isReturn = (bool) ($data['is_return'] ?? false);

try {
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

    $fetchSql = "SELECT id, update_count, created_at, scanned_at, returned_at FROM scans WHERE code_value = :code";
    $fetchStmt = $pdo->prepare($fetchSql);
    $fetchStmt->execute([":code" => $codeValue]);
    $scanRecord = $fetchStmt->fetch();

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
    $historyStmt->execute([
        ":scan_id" => $scanRecord['id'],
        ":code" => $codeValue,
        ":ip" => $ipAddress,
        ":ua" => $userAgent
    ]);

    echo json_encode([
        "status" => "success",
        "duplicate" => $isDuplicate,
        "is_return" => $isReturn,
        "data" => [
            "code" => $codeValue,
            "type" => $codeType,
            "courier" => $courier,
            "parcel_size" => $parcelSize,
            "platform" => $platform,
            "timestamp" => date('c'),
            "update_count" => (int) $scanRecord['update_count'],
            "created_at" => $scanRecord['created_at'],
            "last_scanned" => $scanRecord['scanned_at'],
            "returned_at" => $scanRecord['returned_at']
        ]
    ]);
} catch (PDOException $e) {
    error_log("SAVE ERROR: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "status" => "error", 
        "message" => "Save failed: " . $e->getMessage()
    ]);
}
