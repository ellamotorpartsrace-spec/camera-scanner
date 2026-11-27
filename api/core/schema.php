<?php
/**
 * Schema helpers for Ella Scanner API.
 */

/**
 * Ensures `scans.parcel_size` accepts CARGO before inserting/updating.
 * Must be called before starting any transaction because ALTER TABLE commits implicitly.
 */
function ensureCargoSizeSupported(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }

    $colStmt = $pdo->query("SHOW COLUMNS FROM scans LIKE 'parcel_size'");
    $col = $colStmt ? $colStmt->fetch(PDO::FETCH_ASSOC) : null;
    $type = $col['Type'] ?? '';

    if (stripos($type, "'CARGO'") === false) {
        $pdo->exec("ALTER TABLE scans MODIFY COLUMN parcel_size ENUM('POUCH','BULKY','CARGO') DEFAULT 'POUCH'");
    }

    $checked = true;
}
