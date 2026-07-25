<?php
$boundary = '----FormBoundaryTest' . uniqid();
$payload = json_encode(['codes' => ['DIAG_FORM_1784618150']]);
$body = "--$boundary\r\nContent-Disposition: form-data; name=\"payload\"\r\n\r\n$payload\r\n--$boundary--\r\n";
$ctx = stream_context_create(['http'=>['method'=>'POST','header'=>"Content-Type: multipart/form-data; boundary=$boundary\r\n",'content'=>$body,'ignore_errors'=>true,'timeout'=>10]]);
$r = file_get_contents('https://scanner.ellamotorparts.net/api/scan/delete.php', false, $ctx);
echo "[delete.php FormData] " . $r . "\n";
