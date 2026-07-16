<?php
/**
 * Configuration for Ella Scanner API
 */

return [
    'db' => [
        'host' => 'localhost',
        // Check if we are running on localhost (XAMPP) or Hostinger
        'name' => (in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1'])) ? 'ella_scanner' : 'u296077208_camera_scanner',
        'user' => (in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1'])) ? 'root' : 'u296077208_scanner',
        'pass' => (in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1'])) ? 'elladbPogisiBen' : 'ella2002Scanner',
        'charset' => 'utf8mb4'
    ],
    'security' => [
        // Default password: 'ellamotors'
        'master_hash' => '$2y$12$LK2riq6SnHWapADAFKZOverf1j9xH5JnRJGYvcEcRrCuNbfmxOD3S',
        'cookie_secret' => 'ella_motor_parts_2026_secure'
    ],
    'lists' => [
        'couriers' => [
            'JNT Express',
            'LBC',
            'Shopee Express',
            'Lazada Express',
            'Flash Express',
            'Others'
        ],
        'platforms' => [
            'Lazada',
            'TikTok',
            'Shopee'
        ]
    ]
];
