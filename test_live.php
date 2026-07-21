<?php
// Test exactly what the browser sends (FormData)
$boundary = '----FormBoundaryTest' . uniqid();
$codeVal = 'DIAG_FORM_' . time();

// Test 1: FormData to save.php
$payload1 = json_encode(['code' => $codeVal, 'type' => 'QR', 'parcel_size' => 'POUCH', 'is_return' => false, 'courier' => '', 'platform' => '']);
$body1 = "--$boundary\r\nContent-Disposition: form-data; name=\"payload\"\r\n\r\n$payload1\r\n--$boundary--\r\n";
$ctx1 = stream_context_create(['http'=>['method'=>'POST','header'=>"Content-Type: multipart/form-data; boundary=$boundary\r\n",'content'=>$body1,'ignore_errors'=>true,'timeout'=>10]]);
$r1 = file_get_contents('https://scanner.ellamotorparts.net/api/scan/save.php', false, $ctx1);
echo "[save.php FormData] " . $r1 . "\n\n";

// Test 2: FormData to save_batch.php
$boundary2 = '----FormBoundaryTest' . uniqid();
$payload2 = json_encode(['scans' => [['code' => 'DIAG_BATCH_' . time(), 'type' => 'QR', 'parcel_size' => 'POUCH', 'is_return' => false, 'courier' => '', 'platform' => '']]]);
$body2 = "--$boundary2\r\nContent-Disposition: form-data; name=\"payload\"\r\n\r\n$payload2\r\n--$boundary2--\r\n";
$ctx2 = stream_context_create(['http'=>['method'=>'POST','header'=>"Content-Type: multipart/form-data; boundary=$boundary2\r\n",'content'=>$body2,'ignore_errors'=>true,'timeout'=>10]]);
$r2 = file_get_contents('https://scanner.ellamotorparts.net/api/scan/save_batch.php', false, $ctx2);
echo "[save_batch.php FormData] " . $r2 . "\n\n";

// Check what save_batch.php has (first 5 lines)
$src = file_get_contents('https://scanner.ellamotorparts.net/api/scan/save_batch.php', false, stream_context_create(['http'=>['ignore_errors'=>true]]));
echo "[save_batch.php source preview]\n" . substr($src, 0, 400) . "\n";
