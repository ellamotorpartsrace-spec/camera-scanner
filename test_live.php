<?php
// Test every possible way to send data to save.php and save_batch.php
$baseUrl = 'https://scanner.ellamotorparts.net';

function testEndpoint($url, $method, $body, $contentType, $label) {
    $opts = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: $contentType\r\nAccept: application/json\r\n",
            'content' => $body,
            'ignore_errors' => true,
            'timeout' => 10
        ]
    ];
    $ctx = stream_context_create($opts);
    $r = file_get_contents($url, false, $ctx);
    echo "[$label]\n  -> " . ($r ?: '(empty response)') . "\n\n";
}

// Test 1: save_batch.php with JSON body
$jsonPayload = json_encode(['scans' => [['code' => 'DIAG_JSON_' . time(), 'type' => 'QR']]]);
testEndpoint("$baseUrl/api/scan/save_batch.php", 'POST', $jsonPayload, 'application/json', 'save_batch JSON body');

// Test 2: save_batch.php with FormData
$boundary = '----Boundary' . uniqid();
$formBody = "--$boundary\r\nContent-Disposition: form-data; name=\"payload\"\r\n\r\n$jsonPayload\r\n--$boundary--\r\n";
testEndpoint("$baseUrl/api/scan/save_batch.php", 'POST', $formBody, "multipart/form-data; boundary=$boundary", 'save_batch FormData');

// Test 3: save_batch.php with URLEncoded
$urlBody = 'payload=' . urlencode($jsonPayload);
testEndpoint("$baseUrl/api/scan/save_batch.php", 'POST', $urlBody, 'application/x-www-form-urlencoded', 'save_batch URLEncoded');

// Test 4: save.php with JSON body
$scanJson = json_encode(['code' => 'DIAG_SCAN_' . time(), 'type' => 'QR', 'parcel_size' => 'POUCH']);
testEndpoint("$baseUrl/api/scan/save.php", 'POST', $scanJson, 'application/json', 'save.php JSON body');

// Test 5: save.php with FormData
$boundary2 = '----Boundary' . uniqid();
$formBody2 = "--$boundary2\r\nContent-Disposition: form-data; name=\"payload\"\r\n\r\n$scanJson\r\n--$boundary2--\r\n";
testEndpoint("$baseUrl/api/scan/save.php", 'POST', $formBody2, "multipart/form-data; boundary=$boundary2", 'save.php FormData');

// Test 6: save.php with URLEncoded
$urlBody2 = 'payload=' . urlencode($scanJson);
testEndpoint("$baseUrl/api/scan/save.php", 'POST', $urlBody2, 'application/x-www-form-urlencoded', 'save.php URLEncoded');
