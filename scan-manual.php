<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
require_once __DIR__ . '/api/core/session.php';
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
  header("Location: index.php");
  exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>ELLA • Manual Entry</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script>
        // Apply theme BEFORE CSS renders to prevent flash
        var t = localStorage.getItem('ella-theme');
        document.documentElement.classList.add(t === 'light' ? 'light-mode' : 'dark-mode');
    </script>
</head>

<style>
    @import url("css/manual.css");
</style>

<body>
    <div class="page-layout">

        <!-- Left Column: Batch Details -->
        <div id="batchDetailsPanel" class="feature-panel batch-details-panel">
            <!-- Default State: Coming Soon -->
            <div id="defaultPanelContent" class="default-content">
                <div class="feature-icon">📋</div>
                <h3 class="feature-title">Batch Details</h3>
                <div class="status-badge">Click a batch to view</div>
                <p class="feature-desc">
                    Click on any completed batch to view all scanned codes and their details.
                </p>
            </div>

            <!-- Batch Details State (hidden by default) -->
            <div id="batchDetailsContent" class="batch-details-content" style="display:none;">
                <div class="batch-details-header">
                    <button id="closeBatchDetails" class="close-btn">✕</button>
                    <h3>📦 <span id="batchDetailTitle">Batch #1</span></h3>
                    <div class="batch-detail-stats">
                        <span id="batchDetailCount">0 codes</span>
                        <span id="batchDetailCourier"></span>
                    </div>
                </div>
                <ul id="batchDetailsList" class="batch-details-list">
                    <li class="placeholder">No codes in this batch</li>
                </ul>
            </div>
        </div>

        <div class="card scanner-card">

            <!-- Header -->
            <header class="scanner-header">
                <h1>⌨️ Manual Entry</h1>
                <p class="subtitle">Type or paste a code, then press Enter</p>
            </header>

            <!-- Scan Input -->
            <section class="scan-section">
                <!-- Courier Selection -->
                <div class="courier-selection">
                    <div class="form-group">
                        <label for="courierSelect">📦 Courier:</label>
                        <select id="courierSelect">
                            <option value="">-- Select Courier --</option>
                            <option value="JNT Express">JNT Express</option>
                            <option value="Shopee Express">Shopee Express</option>
                            <option value="Lazada Express">Lazada Express</option>
                            <option value="Flash Express">Flash Express</option>
                            <option value="Others">Others</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="parcelSizeSelect">📏 Size:</label>
                        <select id="parcelSizeSelect">
                            <option value="POUCH">Pouch</option>
                            <option value="BULKY">Bulky</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="platformSelect">🛍️ Platform:</label>
                        <select id="platformSelect">
                            <option value="">-- No Platform --</option>
                            <option value="Lazada">Lazada</option>
                            <option value="TikTok">TikTok</option>
                            <option value="Shopee">Shopee</option>
                        </select>
                    </div>
                </div>

                <!-- Mode Selection -->
                <div class="mode-selection">
                    <label class="switch-label" for="returnModeToggle">
                        <div class="switch">
                            <input type="checkbox" id="returnModeToggle">
                            <span class="slider"></span>
                        </div>
                        <span id="modeText" class="mode-text">Normal Mode</span>
                    </label>
                </div>

                <div class="manual-scan">
                    <input id="manualScanInput" type="text" placeholder="Enter code here…" autocomplete="off"
                        autofocus />
                    <button id="manualSubmitBtn" class="primary">
                        Submit
                    </button>
                </div>

                <!-- Status -->
                <div id="status-pill" class="status-pill">
                    Waiting for input
                </div>
            </section>

            <!-- Stats -->
            <section class="stats">
                <div class="stat-box">
                    <span class="stat-label">Total Scans</span>
                    <strong id="scan-count" class="stat-value">0</strong>
                </div>

                <div class="stat-box success">
                    <span class="stat-label">Saved</span>
                    <strong id="success-scan-count" class="stat-value">0</strong>
                </div>

                <div class="stat-box">
                    <span class="stat-label">Pouch</span>
                    <strong id="pouch-count" class="stat-value">0</strong>
                </div>

                <div class="stat-box">
                    <span class="stat-label">Bulky</span>
                    <strong id="bulky-count" class="stat-value">0</strong>
                </div>
            </section>

            <!-- History -->
            <section class="history">
                <h3>
                    <span>📋 Recent Scans</span>
                </h3>
                <ul id="historyList" class="history-list">
                    <li class="placeholder">No scans yet</li>
                </ul>
            </section>

            <!-- Footer Actions -->
            <footer class="footer-actions">
                <button onclick="location.href='index.php'" class="secondary">
                    ⬅ Back
                </button>
                <button id="clearSessionBtn" class="danger">
                    🗑️ Clear Session
                </button>
            </footer>

        </div>

        <!-- Batch Counter Panel -->
        <div class="card batch-panel">
            <div class="batch-header">
                <h2>📦 Batch Counter</h2>
                <div class="batch-number">Batch #<span id="currentBatchNumber">1</span></div>
            </div>

            <div class="batch-counter">
                <div id="batchCount" class="count">0</div>
                <div class="label">codes saved</div>
            </div>

            <div class="new-batch-wrapper">
                <button id="newBatchBtn" class="new-batch-btn">
                    ➕ Start New Batch
                </button>
            </div>

            <div class="batch-history">
                <h4>📊 Batch History</h4>
                <ul id="batchList" class="batch-list">
                    <li class="batch-placeholder">No completed batches yet</li>
                </ul>
            </div>
        </div>
    </div>
    </div>

    </div>

    <div class="copyright-banner">
        <div class="copyright-content">
            DEVELOPED BY BENEDICT RAMIREZ EST 2026 THIS SERVES AS A COPYRIGHT! AND
            SHALL BE USE ONLY FOR ELLA MOTOR PARTS. ALL RIGHTS RESERVED. TO HAVE A
            COPY OF THE PROGRAM CONTACT BENEDICT RAMIREZ AT 0997-7855-120
        </div>
    </div>

    <!-- Flash Overlay -->
    <div id="flash"></div>

    <script src="js/sound.js"></script>
    <script src="js/security.js"></script>
    <script src="js/scanner-manual.js"></script>
</body>

</html>
