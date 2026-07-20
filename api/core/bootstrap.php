<?php
/**
 * API Bootstrap
 * 
 * MUST be the very first thing included in every API endpoint.
 * 
 * Why this exists:
 *   PHP warnings, notices, deprecated messages, and even invisible BOM characters
 *   from included files can silently prepend text to the HTTP response body.
 *   When the client expects clean JSON, any stray output causes a JSON parse
 *   error ("invalid payload", "syntax error", etc.) that is very hard to debug.
 * 
 * How it works:
 *   1. ob_start()         — captures ALL output into a buffer from this point on
 *   2. error_reporting(0) — prevents PHP notices/warnings from entering the buffer
 *   3. json_response()    — a helper that calls ob_clean() before echoing JSON,
 *                           discarding any garbage that slipped through anyway
 *   4. json_error()       — same, for error responses
 */

ob_start();
error_reporting(0);
ini_set('display_errors', '0');

/**
 * Send a clean JSON success response and exit.
 * Discards any buffered PHP output before sending.
 */
function json_response(array $data, int $status = 200): void {
    ob_clean();
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Send a clean JSON error response and exit.
 * Also logs to api_errors.log for debugging.
 */
function json_error(string $message, int $status = 400, array $extra = []): void {
    $payload = array_merge(['status' => 'error', 'message' => $message], $extra);
    // Log to file for diagnosis
    $logFile = dirname(__DIR__, 2) . '/logs/api_errors.log';
    @file_put_contents(
        $logFile,
        date('Y-m-d H:i:s') . ' [' . $status . '] ' . $message . ' ' . json_encode($extra) . PHP_EOL,
        FILE_APPEND
    );
    ob_clean();
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
