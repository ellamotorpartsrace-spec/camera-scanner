<?php
// Version probe - tells us what code is on Hostinger
echo json_encode([
    'deployed_at' => date('Y-m-d H:i:s'),
    'version' => 'v_' . filemtime(__FILE__),
    'save_batch_mtime' => date('Y-m-d H:i:s', filemtime(__DIR__ . '/api/scan/save_batch.php')),
    'save_mtime' => date('Y-m-d H:i:s', filemtime(__DIR__ . '/api/scan/save.php')),
    'sw_mtime' => date('Y-m-d H:i:s', filemtime(__DIR__ . '/service-worker.js')),
    'scanner_js_mtime' => date('Y-m-d H:i:s', filemtime(__DIR__ . '/js/scanner-smart.js')),
]);
