<?php
/**
 * Shared Session Handler
 */

$session_lifetime = 30 * 24 * 60 * 60; // 30 Days
date_default_timezone_set('Asia/Manila'); // Force Philippine Time
ini_set('session.gc_maxlifetime', $session_lifetime);
ini_set('session.cookie_lifetime', $session_lifetime);

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => $session_lifetime,
        'path' => '/',
        'domain' => '',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Aggressive No-Cache Headers to prevent iOS Safari/Chrome from caching stale PHP pages
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Wed, 11 Jan 1984 05:00:00 GMT");

// ── Persistent Cookie Recovery ──
// If not authenticated in session, check for a valid persistent cookie
if (!isset($_SESSION['authenticated'])) {
    $config = require __DIR__ . '/config.php';
    $cookie_name = 'ella_remember_token';
    if (isset($_COOKIE[$cookie_name])) {
        $expected_token = hash_hmac('sha256', 'authenticated_user', $config['security']['cookie_secret']);
        if (hash_equals($expected_token, $_COOKIE[$cookie_name])) {
            $_SESSION['authenticated'] = true;
            $_SESSION['last_login'] = time();
        }
    }
}

// Global Headers to prevent caching...
if (!headers_sent()) {
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
}
