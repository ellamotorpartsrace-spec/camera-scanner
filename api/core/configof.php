<?php
/**
 * Configuration for Ella Scanner API
 */

return [
    'db' => [
        'host' => 'localhost',
        'name' => 'ella_scanner',
        'user' => 'root',
        'pass' => 'elladbPogisiBen',
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
            'Ninja Van',
            'Others'
        ],
        'platforms' => [
            'Lazada',
            'TikTok',
            'Shopee'
        ]
    ]
];
