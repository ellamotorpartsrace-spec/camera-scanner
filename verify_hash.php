<?php
$text = "DEVELOPED BY BENEDICT RAMIREZ EST 2026 THIS SERVES AS A COPYRIGHT! AND SHALL BE USE ONLY FOR ELLA MOTOR PARTS. ALL RIGHTS RESERVED. TO HAVE A COPY OF THE PROGRAM CONTACT BENEDICT RAMIREZ AT 0997-7855-120";
$normalized = preg_replace('/\s+/', ' ', trim($text));
$hash = hash('sha256', $normalized);
$expected = "8ff9a57149798f3daf2c7e94cf911719fc7a433f8b98ec9f4941fb5dc62e8528";

echo "Normalized: \"$normalized\"\n";
echo "Calculated: $hash\n";
echo "Expected:   $expected\n";
echo "Match:      " . ($hash === $expected ? "YES" : "NO") . "\n";
