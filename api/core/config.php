<?php
/**
 * Configuration for Ella Scanner API
 */

return [
    'db' => [
        // ==========================================
        // LOCAL XAMPP SETTINGS (Currently disabled)
        // ==========================================
        /*
        'host' => 'localhost',
        'name' => 'ella_scanner',
        'user' => 'root',
        'pass' => '', 
        'charset' => 'utf8mb4',
        */

        // ==========================================
        // PRODUCTION SETTINGS (Hostinger) - ACTIVE
        // ==========================================
        'host' => 'localhost', // Usually 'localhost' on Hostinger
        'name' => 'u296077208_camera_scanner', // INPUT YOUR DB NAME HERE
        'user' => 'u296077208_scanner', // INPUT YOUR DB USER HERE
        'pass' => 'ella2002Scanner',         // INPUT YOUR DB PASSWORD HERE
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
