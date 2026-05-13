<?php
header("Content-Type: text/csv; charset=utf-8");
header("Content-Disposition: attachment; filename=scans_export_" . date("Ymd_His") . ".csv");
header("Pragma: no-cache");
header("Expires: 0");

require_once __DIR__ . '/../core/db.php';

$from     = $_GET['from']     ?? null;
$to       = $_GET['to']       ?? null;
$courier  = $_GET['courier']  ?? null;
$size     = $_GET['size']     ?? null;
$platform = $_GET['platform'] ?? null;
$type     = $_GET['type']     ?? null;

$where  = [];
$params = [];

function isValidDate($d)
{
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt && $dt->format('Y-m-d') === $d;
}

if ($from && isValidDate($from)) {
    $where[] = "scanned_at >= :fromStart";
    $params[':fromStart'] = $from . " 00:00:00";
}

if ($to && isValidDate($to)) {
    $where[] = "scanned_at <= :toEnd";
    $params[':toEnd'] = $to . " 23:59:59";
}

if ($courier) {
    $where[] = "courier = :courier";
    $params[':courier'] = $courier;
}

if ($size) {
    $where[] = "parcel_size = :size";
    $params[':size'] = $size;
}

if ($platform) {
    $where[] = "platform = :platform";
    $params[':platform'] = $platform;
}

if ($type === 'returned') {
    $where[] = "returned_at IS NOT NULL";
} elseif ($type === 'scanned') {
    $where[] = "returned_at IS NULL";
}

$whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";

$sql = "
    SELECT
        code_value,
        code_type,
        courier,
        parcel_size,
        platform,
        gs1_gtin,
        gs1_batch,
        created_at,
        scanned_at,
        returned_at,
        update_count,
        (update_count + 1) AS scan_count
    FROM scans
    $whereSql
    ORDER BY scanned_at DESC, id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$output = fopen("php://output", "w");

fwrite($output, "\xEF\xBB\xBF");

fputcsv($output, [
    "Code",
    "Type",
    "Courier",
    "Parcel Size",
    "Platform",
    "GTIN",
    "Batch",
    "First Scan Time",
    "Last Scan Time",
    "Returned At",
    "Rescan Count",
    "Total Scans"
]);

while ($row = $stmt->fetch()) {
    fputcsv($output, [
        $row['code_value'],
        $row['code_type'],
        $row['courier']      ?? '',
        $row['parcel_size']  ?? 'POUCH',
        $row['platform']     ?? '',
        $row['gs1_gtin']     ?? '',
        $row['gs1_batch']    ?? '',
        $row['created_at'],
        $row['scanned_at'],
        $row['returned_at']  ?? '',
        $row['update_count'],
        $row['scan_count']
    ]);
}

fclose($output);
exit;
