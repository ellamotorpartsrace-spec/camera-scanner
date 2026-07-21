<?php
$ctx = stream_context_create(['http'=>[
  'method'=>'POST',
  'header'=> "Content-Type: application/json\r\n",
  'content'=> '{"scans":[{"code":"TEST_LIVE_' . time() . '","type":"QR"}]}',
  'ignore_errors'=>true
]]);
$r = file_get_contents('https://scanner.ellamotorparts.net/api/scan/save_batch.php', false, $ctx);
echo "=== JSON POST response ===" . PHP_EOL;
echo $r . PHP_EOL;

// Now test FormData fallback
$boundary = '----FormBoundary' . uniqid();
$payload = json_encode(['scans'=>[['code'=>'TEST_FORM_' . time(),'type'=>'QR']]]);
$body = "--$boundary\r\nContent-Disposition: form-data; name=\"payload\"\r\n\r\n$payload\r\n--$boundary--\r\n";
$ctx2 = stream_context_create(['http'=>[
  'method'=>'POST',
  'header'=> "Content-Type: multipart/form-data; boundary=$boundary\r\n",
  'content'=> $body,
  'ignore_errors'=>true
]]);
$r2 = file_get_contents('https://scanner.ellamotorparts.net/api/scan/save_batch.php', false, $ctx2);
echo "=== FORM POST response ===" . PHP_EOL;
echo $r2 . PHP_EOL;
