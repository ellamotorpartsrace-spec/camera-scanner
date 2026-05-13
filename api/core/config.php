<?php
/**
 * Configuration for Ella Scanner API
 */

return [
    'db' => [
        // Local XAMPP Settings
        'host' => 'localhost',
        'name' => 'ella_scanner',
        'user' => 'root',
        'pass' => 'elladbPogisiBen',
        'charset' => 'utf8mb4'

        /* Production Settings (Hostinger)
        'host' => '127.0.0.1',
        'name' => 'u296077208_ella_scanner',
        'user' => 'u296077208_BenzEllaScan',
        'pass' => '6n%cY.-Zkr]C22.',
        */
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
