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
<!doctype html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <title>ELLA Smart Scanner</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="description" content="ELLA Smart Scanner – automatically detects QR codes and barcodes." />

  <script>
    // Apply theme BEFORE CSS renders to prevent flash
    var t = localStorage.getItem('ella-theme');
    document.documentElement.classList.add(t === 'light' ? 'light-mode' : 'dark-mode');
  </script>
  <script src="https://unpkg.com/html5-qrcode/html5-qrcode.min.js"></script>
  <link rel="stylesheet" href="css/scanner.css?v=37" />

  <!-- Force SW & Cache Reset v13: clears any stuck old service workers -->
  <script>
    (function() {
      var SW_EXPECTED = 'ella-scanner-v13';
      var RESET_KEY   = 'sw_reset_done_v13';
      if (localStorage.getItem(RESET_KEY)) return; // already reset this session

      if ('serviceWorker' in navigator) {
        // Unregister ALL service workers first
        navigator.serviceWorker.getRegistrations().then(function(regs) {
          var kills = regs.map(function(r) { return r.unregister(); });
          return Promise.all(kills);
        }).then(function() {
          // Clear ALL caches
          return caches.keys().then(function(keys) {
            return Promise.all(keys.map(function(k) { return caches.delete(k); }));
          });
        }).then(function() {
          localStorage.setItem(RESET_KEY, '1');
          // Reload once with cache-busted URL to get fresh code
          var url = window.location.href.split('?')[0] + '?_r=' + Date.now();
          window.location.replace(url);
        }).catch(function() {
          localStorage.setItem(RESET_KEY, '1');
        });
      } else {
        localStorage.setItem(RESET_KEY, '1');
      }
    })();
  </script>


  <style>
    /* ── Smart Scanner Assets ── */
    .scanner-title {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      margin-bottom: 4px;
    }

    .scanner-title h2 {
      margin: 0;
    }

    /* Detected-type badge */
    #type-badge {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      font-size: 0.7rem;
      font-weight: 800;
      letter-spacing: 0.8px;
      text-transform: uppercase;
      padding: 3px 10px;
      border-radius: 20px;
      background: rgba(100, 116, 139, 0.12);
      color: var(--muted);
      border: 1px solid var(--border);
      transition: all 0.3s ease;
    }

    #type-badge.qr-mode {
      background: rgba(34, 197, 94, .15);
      color: #16a34a;
      border-color: rgba(34, 197, 94, .3);
    }

    #type-badge.bar-mode {
      background: rgba(37, 99, 235, .15);
      color: #1d4ed8;
      border-color: rgba(37, 99, 235, .3);
    }

    /* Return Mode Toggle */
    .mode-selection {
      display: flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 1.25rem;
      padding: 8px;
      background: rgba(0, 0, 0, 0.02);
      border-radius: 14px;
      border: 1px solid var(--border);
    }

    @media (prefers-color-scheme: dark) {
      .mode-selection {
        background: rgba(255, 255, 255, 0.02);
      }
    }

    .switch-label {
      display: flex;
      align-items: center;
      gap: 12px;
      cursor: pointer;
      user-select: none;
    }

    .switch {
      position: relative;
      display: inline-block;
      width: 44px;
      height: 24px;
    }

    .switch input {
      opacity: 0;
      width: 0;
      height: 0;
    }

    .slider {
      position: absolute;
      inset: 0;
      background: #cbd5e1;
      border-radius: 24px;
      transition: background 0.3s;
    }

    .slider::before {
      content: '';
      position: absolute;
      width: 18px;
      height: 18px;
      left: 3px;
      bottom: 3px;
      background: #fff;
      border-radius: 50%;
      transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
    }

    .switch input:checked+.slider {
      background: var(--warning);
    }

    .switch input:checked+.slider::before {
      transform: translateX(20px);
    }

    .mode-text {
      font-size: 0.85rem;
      font-weight: 700;
      color: var(--text);
    }

    /* Tracker Canvas Overlay */
    #tracker-canvas {
      position: absolute;
      inset: 0;
      width: 100%;
      height: 100%;
      pointer-events: none;
      z-index: 5;
    }

    /* Laser Line */
    .laser-line {
      position: absolute;
      left: 10%;
      width: 80%;
      height: 2px;
      background: #ef4444;
      box-shadow: 0 0 10px #ef4444, 0 0 20px #ef4444;
      top: 50%;
      z-index: 6;
      animation: scan-laser 2.5s infinite alternate ease-in-out;
      pointer-events: none;
      opacity: 0.75;
    }

    @keyframes scan-laser {
      0% { top: 20%; }
      100% { top: 80%; }
    }

    /* Fallback static guide (when BarcodeDetector not available) */
    .fallback-guide {
      position: absolute;
      inset: 0;
      pointer-events: none;
      display: flex;
      justify-content: center;
      align-items: center;
      z-index: 4;
    }

    .fallback-guide .guide-box {
      width: 65%;
      height: 65%;
      border: 2px dashed rgba(255, 255, 255, 0.35);
      border-radius: 14px;
    }

    .fallback-guide .guide-label {
      position: absolute;
      bottom: calc(15% + 10px);
      left: 50%;
      transform: translateX(-50%);
      font-size: 0.72rem;
      font-weight: 800;
      letter-spacing: 1px;
      text-transform: uppercase;
      color: rgba(255, 255, 255, 0.7);
      background: rgba(0, 0, 0, 0.45);
      padding: 3px 12px;
      border-radius: 8px;
      backdrop-filter: blur(6px);
      white-space: nowrap;
    }

    /* Stats Row */
    .stats-row {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 10px;
      margin-top: 1rem;
    }

    .stat-box {
      background: rgba(0, 0, 0, 0.03);
      border: 1px solid var(--border);
      border-radius: 14px;
      padding: 10px 6px;
      text-align: center;
      transition: transform 0.2s;
    }

    .stat-box:active {
      transform: scale(0.95);
    }

    @media (prefers-color-scheme: dark) {
      .stat-box {
        background: rgba(255, 255, 255, 0.03);
      }
    }

    .stat-box.success {
      border-color: rgba(34, 197, 94, .3);
      background: rgba(34, 197, 94, 0.03);
    }

    .stat-label {
      display: block;
      font-size: 0.6rem;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.6px;
      color: var(--muted);
      margin-bottom: 4px;
    }

    .stat-value {
      display: block;
      font-size: 1.2rem;
      font-weight: 800;
      color: var(--text);
    }

    .stat-box.success .stat-value {
      color: #16a34a;
    }

    /* History Chips */
    .type-chip {
      font-size: 0.62rem;
      font-weight: 800;
      padding: 2px 8px;
      border-radius: 6px;
      letter-spacing: 0.5px;
      text-transform: uppercase;
    }

    .type-chip.qr {
      background: rgba(34, 197, 94, .15);
      color: #16a34a;
    }

    .type-chip.bar {
      background: rgba(59, 130, 246, .15);
      color: #1d4ed8;
    }

    /* Action Buttons */
    button.danger {
      background: rgba(239, 68, 68, 0.1);
      color: #ef4444;
      border: 1px solid rgba(239, 68, 68, 0.25);
    }

    button.danger:hover {
      background: rgba(239, 68, 68, 0.15);
    }

    /* ── Embedded Mismatch Blocker Modal Styles (Immune to CSS Caching) ── */
    .mismatch-modal-backdrop {
      position: fixed !important;
      inset: 0 !important;
      width: 100vw !important;
      height: 100vh !important;
      background: rgba(2, 6, 23, 0.92) !important;
      backdrop-filter: blur(8px) !important;
      -webkit-backdrop-filter: blur(8px) !important;
      z-index: 10000 !important;
      display: none;
      align-items: center;
      justify-content: center;
      padding: 16px;
      box-sizing: border-box;
    }

    .mismatch-modal-backdrop.active {
      display: flex !important;
    }

    .mismatch-card {
      width: 100%;
      max-width: 380px;
      background: var(--card, #10141e);
      border: 2px solid #ef4444 !important;
      border-radius: 20px;
      padding: 1.5rem;
      box-shadow: 0 15px 40px rgba(239, 68, 68, 0.4) !important;
      text-align: center;
      box-sizing: border-box;
      animation: modal-pop 0.25s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
    }

    @keyframes modal-pop {
      from { transform: scale(0.92); opacity: 0; }
      to { transform: scale(1); opacity: 1; }
    }

    .mismatch-icon {
      font-size: 3rem;
      line-height: 1;
      margin-bottom: 8px;
    }

    .mismatch-title {
      font-size: 1.25rem;
      font-weight: 900;
      color: #ef4444;
      margin-bottom: 4px;
      letter-spacing: 0.5px;
      text-transform: uppercase;
    }

    .mismatch-subtitle {
      font-size: 0.82rem;
      color: var(--muted, #94a3b8);
      margin-bottom: 1rem;
      line-height: 1.4;
    }

    .mismatch-details {
      background: rgba(0, 0, 0, 0.2);
      border-radius: 12px;
      padding: 12px;
      margin-bottom: 1.25rem;
      text-align: left;
      display: flex;
      flex-direction: column;
      gap: 8px;
      border: 1px solid var(--border, rgba(239, 68, 68, 0.2));
    }

    html.light-mode .mismatch-details {
      background: rgba(0, 0, 0, 0.04);
    }

    .mismatch-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 0.85rem;
    }

    .mismatch-row .label {
      color: var(--muted, #94a3b8);
      font-weight: 600;
    }

    .mismatch-row .val {
      font-weight: 800;
      color: var(--text, #e2e8f0);
    }

    .mismatch-row .val.expected {
      color: #3b82f6;
    }

    .mismatch-row .val.detected {
      color: #ef4444;
    }

    .mismatch-actions {
      display: flex;
      flex-direction: column;
      gap: 10px;
    }

    .btn-mismatch-discard {
      width: 100%;
      padding: 14px;
      border-radius: 12px;
      font-size: 1rem;
      font-weight: 800;
      background: #ef4444;
      color: white;
      border: none;
      cursor: pointer;
      box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
      transition: transform 0.15s ease;
    }

    .btn-mismatch-discard:active {
      transform: scale(0.97);
    }

    .btn-mismatch-switch {
      width: 100%;
      padding: 12px;
      border-radius: 12px;
      font-size: 0.95rem;
      font-weight: 800;
      background: #3b82f6;
      color: white;
      border: none;
      cursor: pointer;
      box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
      transition: transform 0.15s ease;
    }

    .btn-mismatch-switch:active {
      transform: scale(0.97);
    }

    .btn-mismatch-force {
      width: 100%;
      padding: 10px;
      border-radius: 10px;
      font-size: 0.8rem;
      font-weight: 700;
      background: transparent;
      color: var(--muted, #94a3b8);
      border: 1px solid var(--border, rgba(255,255,255,0.1));
      cursor: pointer;
    }
  </style>
</head>

<body>
  <!-- Audio unlock -->
  <div id="audioUnlock" style="
    position:fixed; inset:0; background:rgba(2,6,23,0.95);
    color:white; display:flex; align-items:center; justify-content:center;
    z-index:9999; font-size:1.1rem; text-align:center; cursor:pointer;
    flex-direction:column; gap:16px;" onclick="unlockAudio()">
    <span style="font-size:3rem; filter: drop-shadow(0 0 10px rgba(255,255,255,0.3));">🔊</span>
    <div style="max-width:280px;">
      <h3 style="margin-bottom:8px;">Camera Ready</h3>
      <p style="opacity:0.7; font-size:0.95rem;">Tap anywhere to enable sound &amp; begin scanning</p>
    </div>
  </div>

  <div class="container">
    <div class="card">

      <!-- Header -->
      <div style="text-align: center; margin-bottom: 12px;">
        <img src="logo.png?v=6" alt="ELLA SCANNER" style="height: 48px; object-fit: contain; filter: drop-shadow(0 2px 8px rgba(239, 68, 68, 0.4));" />
      </div>
      <div class="scanner-title">
        <h2>Smart Scanner</h2>
        <span id="type-badge">AUTO</span>
      </div>
      <p class="subtitle" style="text-align:center; margin-bottom: 1.25rem; color:var(--muted); font-size:0.9rem;">
        Automatically detects QR codes &amp; barcodes
      </p>

      <!-- Mode Toggles -->
      <div style="display: flex; gap: 10px; margin-bottom: 1.25rem;">
        <div class="mode-selection" style="flex:1; margin-bottom: 0;">
          <label class="switch-label" for="returnModeToggle" style="justify-content: center;">
            <div class="switch">
              <input type="checkbox" id="returnModeToggle">
              <span class="slider"></span>
            </div>
            <span id="modeText" class="mode-text">Normal</span>
          </label>
        </div>
        
        <div class="mode-selection" style="flex:1; margin-bottom: 0;">
          <label class="switch-label" for="batchModeToggle" style="justify-content: center;">
            <div class="switch">
              <input type="checkbox" id="batchModeToggle">
              <span class="slider"></span>
            </div>
            <span id="batchModeText" class="mode-text">Batch: OFF</span>
          </label>
        </div>
      </div>

      <!-- Shared Options -->
      <div class="options-grid">
        <div class="form-group full-width">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
            <label for="courierSelect" style="margin-bottom: 0;">📦 Courier</label>
            <label class="switch-label" for="autoDetectToggle" style="font-size: 0.75rem; gap: 6px; cursor: pointer;">
              <span id="autoDetectLabel" style="color: var(--accent-2); font-weight: 800; display: flex; align-items: center; gap: 4px;">⚡ Auto-Detect</span>
              <div class="switch switch-mini">
                <input type="checkbox" id="autoDetectToggle">
                <span class="slider"></span>
              </div>
            </label>
          </div>
          <select id="courierSelect">
            <option value="">-- Select Courier --</option>
            <option value="JNT Express">JNT Express</option>
            <option value="Shopee Express" selected>Shopee Express</option>
            <option value="Lazada Express">Lazada Express</option>
            <option value="Flash Express">Flash Express</option>
            <option value="Others">Others</option>
          </select>
        </div>
        <div class="form-group">
          <label for="platformSelect">🛍️ Platform</label>
          <select id="platformSelect">
            <option value="">-- None --</option>
            <option value="Lazada">Lazada</option>
            <option value="TikTok">TikTok</option>
            <option value="Shopee" selected>Shopee</option>
          </select>
        </div>
        <div class="form-group">
          <label for="parcelSizeSelect">📏 Size</label>
          <select id="parcelSizeSelect">
            <option value="POUCH">Pouch</option>
            <option value="BULKY">Bulky</option>
          </select>
        </div>
      </div>

      <!-- Offline Banner -->
      <div id="offline-banner" style="display: none; background: #ef4444; color: white; padding: 10px; border-radius: 8px; text-align: center; margin-bottom: 15px; font-weight: bold; font-size: 0.9rem; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4); animation: pulse 2s infinite;">
        ⚠️ OFFLINE: <span id="offline-count">0</span> scans queued
      </div>

      <!-- Camera Area -->
      <div id="reader-wrapper" style="position: relative; overflow: hidden; border-radius: 14px; margin-bottom: 15px;">
        <div id="reader"></div>
        
        <!-- Flashlight Button -->
        <button id="torchBtn" onclick="toggleTorch(event)" style="position: absolute; bottom: 15px; right: 15px; z-index: 50; background: rgba(0,0,0,0.6); color: white; border: 1px solid rgba(255,255,255,0.3); border-radius: 50%; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 1.2rem; backdrop-filter: blur(4px); box-shadow: 0 4px 12px rgba(0,0,0,0.3); transition: 0.3s;" title="Toggle Flashlight">
          🔦
        </button>

        <!-- Fallback Static Guide (Now permanently used for maximum FPS) -->
        <div id="fallbackGuide" class="fallback-guide" style="display:flex;">
          <div class="bracket tl"></div>
          <div class="bracket tr"></div>
          <div class="bracket bl"></div>
          <div class="bracket br"></div>
          <div class="laser-line" style="display:none;"></div>
        </div>
      </div>

      <style>
        @keyframes pulse-soft {
          0% { transform: scale(1); box-shadow: 0 4px 15px rgba(34, 197, 94, 0.4); }
          50% { transform: scale(1.02); box-shadow: 0 4px 25px rgba(34, 197, 94, 0.6); }
          100% { transform: scale(1); box-shadow: 0 4px 15px rgba(34, 197, 94, 0.4); }
        }
        .btn-pulse-idle {
          animation: pulse-soft 2s infinite ease-in-out;
        }
      </style>

      <!-- Manual Scan Button -->
      <button id="manualScanBtn" class="btn-pulse-idle" onclick="triggerManualScan()" style="width: 100%; background: #22c55e; color: white; border: none; padding: 18px; border-radius: 12px; font-size: 1.2rem; font-weight: 900; letter-spacing: 1px; cursor: pointer; margin-bottom: 15px; text-transform: uppercase; transition: 0.2s;">
        📸 TAP TO SCAN
      </button>

      <!-- Status -->
      <div id="status-pill">Starting camera…</div>

      <!-- Stats Row -->
      <div class="stats-row">
        <div class="stat-box">
          <span class="stat-label">Total</span>
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
      </div>

      <!-- History -->
      <section class="history">
        <h3 style="display:flex; justify-content:space-between;">
          <span>📋 Recent Scans</span>
        </h3>
        <ul id="historyList" class="history-list">
          <li class="placeholder">No scans yet</li>
        </ul>
      </section>

      <!-- Batch Action -->
      <div id="batch-action-container" style="display: none; margin-top: 15px;">
        <button id="submitBatchBtn" style="width: 100%; background: #3b82f6; color: white; border: none; padding: 15px; border-radius: 12px; font-size: 1.1rem; font-weight: bold; cursor: pointer; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);">
          🚀 Submit Batch (<span id="batch-submit-count">0</span>)
        </button>
      </div>

      <!-- Submitted Batches Tally -->
      <div id="submitted-batches-container" style="display: none; margin-top: 15px;">
        <h4 style="font-size: 0.9rem; color: var(--muted); margin-bottom: 8px;">📦 Today's Submitted Batches</h4>
        <ul id="submitted-batches-list" style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 8px;">
        </ul>
      </div>

      <!-- Footer -->
      <div class="btn-group" style="margin-top:1.5rem;">
        <button onclick="location.href='index.php'" class="secondary">⬅ Back</button>
        <button id="clearSessionBtn" class="danger">🗑️ Clear</button>
      </div>

    </div>
  </div>

  <div id="flash"></div>

  <!-- Mismatch Blocker Modal -->
  <div id="mismatchModal" class="mismatch-modal-backdrop" style="display: none;">
    <div class="mismatch-card">
      <div class="mismatch-icon">⚠️</div>
      <div class="mismatch-title">Courier Mismatch!</div>
      <p class="mismatch-subtitle" id="mismatchReason">The scanned package does not match your selected courier.</p>
      
      <div class="mismatch-details">
        <div class="mismatch-row">
          <span class="label">Scanned Code:</span>
          <span class="val" id="mismatchCodeVal" style="font-family: monospace; font-size: 0.95rem;">-</span>
        </div>
        <div class="mismatch-row">
          <span class="label">Detected As:</span>
          <span class="val detected" id="mismatchDetectedVal">-</span>
        </div>
        <div class="mismatch-row">
          <span class="label">Selected In App:</span>
          <span class="val expected" id="mismatchSelectedVal">-</span>
        </div>
      </div>

      <div class="mismatch-actions">
        <button id="mismatchDiscardBtn" class="btn-mismatch-discard" type="button">
          🛑 Discard &amp; Put Aside
        </button>
        <button id="mismatchSwitchBtn" class="btn-mismatch-switch" type="button">
          🔀 Switch to <span id="mismatchSwitchTarget">-</span> &amp; Save
        </button>
        <button id="mismatchForceBtn" class="btn-mismatch-force" type="button">
          ⚠️ Force Save Anyway
        </button>
      </div>
    </div>
  </div>

  <script>
    function unlockAudio() {
      // 1. Unlock Web Audio
      if (window.Sound) document.body.dispatchEvent(new Event("click"));

      // 2. Start Camera (must be triggered by user gesture on most mobile browsers)
      if (window.initScanner) {
        window.initScanner();
      }

      const el = document.getElementById("audioUnlock");
      if (el) el.remove();
    }
  </script>

  <script src="js/sound.js?v=38"></script>
  <script src="js/scanner-smart.js?v=38"></script>

</body>

</html>
