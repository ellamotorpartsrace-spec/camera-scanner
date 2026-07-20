<?php
$data = '{"scans": [{"code": "TEST_OB_' . time() . '", "type": "QR"}]}';
$ch = curl_init('https://localhost/camera/api/scan/save_batch.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
$response = curl_exec($ch);
echo "RESPONSE: " . $response . "\n";
echo "ERROR: " . curl_error($ch) . "\n";
curl_close($ch);
