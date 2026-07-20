<?php
require_once __DIR__ . '/../core/bootstrap.php';
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

require_once __DIR__ . '/../core/db.php';

$page  = max(1, (int)($_GET['page']  ?? 1));
$limit = max(1, min(200, (int)($_GET['limit'] ?? 50)));

$from     = $_GET['from']     ?? null;
$to       = $_GET['to']       ?? null;
$courier  = $_GET['courier']  ?? null;
$size     = $_GET['size']     ?? null;
$search   = $_GET['search']   ?? null;
$platform = $_GET['platform'] ?? null;
$type     = $_GET['type']     ?? null;
$batch    = $_GET['batch']    ?? null;

$offset = ($page - 1) * $limit;

$where = [];
$params = [];

if ($from) {
    $where[] = "DATE(scanned_at) >= :from";
    $params[':from'] = $from;
}

if ($to) {
    $where[] = "DATE(scanned_at) <= :to";
    $params[':to'] = $to;
}

if ($courier) {
    $where[] = "courier = :courier";
    $params[':courier'] = $courier;
}

if ($size) {
    $where[] = "parcel_size = :size";
    $params[':size'] = $size;
}

if ($search) {
    $where[] = "code_value LIKE :search";
    $params[':search'] = '%' . $search . '%';
}

if ($platform) {
    $where[] = "platform = :platform";
    $params[':platform'] = $platform;
}

if ($batch) {
    $where[] = "gs1_batch = :batch";
    $params[':batch'] = "BATCH-" . $batch;
}

if ($type === 'returned') {
    $where[] = "returned_at IS NOT NULL";
} elseif ($type === 'scanned') {
    $where[] = "returned_at IS NULL";
}

$whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";

$countSql = "SELECT 
                COUNT(*) as total_rows, 
                COALESCE(SUM(update_count + 1), 0) as total_volume 
             FROM scans 
             $whereSql";

$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$summary = $countStmt->fetch();

$totalRows   = (int)$summary['total_rows'];
$totalVolume = (int)$summary['total_volume'];

$totalPages = $limit > 0 ? (int)ceil($totalRows / $limit) : 1;

$dataSql = "
    SELECT 
        code_value, 
        code_type, 
        courier,
        parcel_size,
        platform,
        gs1_gtin, 
        gs1_batch, 
        scanned_at, 
        returned_at,
        update_count,
        created_at
    FROM scans 
    $whereSql 
    ORDER BY scanned_at DESC 
    LIMIT :limit OFFSET :offset
";

$dataStmt = $pdo->prepare($dataSql);

foreach ($params as $k => $v) {
    $dataStmt->bindValue($k, $v);
}

$dataStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);

$dataStmt->execute();

$rows = $dataStmt->fetchAll();

echo json_encode([
    "rows"       => $rows,
    "totalPages" => $totalPages,
    "totalRows"  => $totalRows,
    "totalScans" => $totalVolume,
    "page"       => $page
]);
