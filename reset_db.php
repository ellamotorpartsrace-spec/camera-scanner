<?php
/**
 * UTILITY SCRIPT: Wipe Database for Testing
 * This script empties the 'scans' and 'scan_history' tables.
 * Do not leave this script on the server when you go live into production!
 */

require_once __DIR__ . '/api/core/session.php';
require_once __DIR__ . '/api/core/db.php';

// Only allow logged-in users to run this
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header("Location: index.php");
    exit();
}

try {
    // Disable foreign keys temporarily so we can TRUNCATE (which resets the ID auto-increment to 1)
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    $pdo->exec('TRUNCATE TABLE scan_history');
    $pdo->exec('TRUNCATE TABLE scans');
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    
    $success = true;
} catch (PDOException $e) {
    $success = false;
    $error = $e->getMessage();
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Reset Database</title>
  <style>
    body { font-family: system-ui, sans-serif; background: #0f172a; color: white; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; text-align: center; }
    .box { background: #1e293b; padding: 40px; border-radius: 16px; border: 1px solid rgba(255,255,255,0.1); max-width: 400px; }
    h1 { margin-top: 0; color: #22c55e; }
    .error { color: #ef4444; }
    a { display: inline-block; margin-top: 20px; padding: 12px 24px; background: #3b82f6; color: white; text-decoration: none; border-radius: 8px; font-weight: bold; transition: 0.2s; }
    a:hover { background: #2563eb; }
  </style>
</head>
<body>
  <div class="box">
    <?php if ($success): ?>
        <h1>✅ Database Wiped!</h1>
        <p>Both the `scans` and `scan_history` tables are completely empty.</p>
        <p>You can now test scanning your waybills again as if they were brand new.</p>
    <?php else: ?>
        <h1 class="error">❌ Error</h1>
        <p>Could not wipe database: <?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>
    <a href="scan-smart.php">Back to Scanner</a>
  </div>
</body>
</html>
