<?php
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

  <script src="https://unpkg.com/html5-qrcode/html5-qrcode.min.js"></script>
  <link rel="stylesheet" href="css/scanner.css" />

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
      <div class="scanner-title">
        <h2>🔍 Smart Scanner</h2>
        <span id="type-badge">AUTO</span>
      </div>
      <p class="subtitle" style="text-align:center; margin-bottom: 1.25rem; color:var(--muted); font-size:0.9rem;">
        Automatically detects QR codes &amp; barcodes
      </p>

      <!-- Return Mode Toggle -->
      <div class="mode-selection">
        <label class="switch-label" for="returnModeToggle">
          <div class="switch">
            <input type="checkbox" id="returnModeToggle">
            <span class="slider"></span>
          </div>
          <span id="modeText" class="mode-text">Normal Mode</span>
        </label>
      </div>

      <!-- Shared Options -->
      <div class="options-grid">
        <div class="form-group full-width">
          <label for="courierSelect">📦 Courier</label>
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
          <label for="platformSelect">🛍️ Platform</label>
          <select id="platformSelect">
            <option value="">-- None --</option>
            <option value="Lazada">Lazada</option>
            <option value="TikTok">TikTok</option>
            <option value="Shopee">Shopee</option>
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

      <!-- Camera Area -->
      <div id="reader-wrapper">
        <div id="reader"></div>
        <canvas id="tracker-canvas"></canvas>
        <!-- Fallback guide for browsers without BarcodeDetector -->
        <div id="fallbackGuide" class="fallback-guide" style="display:none;">
          <div class="guide-box"></div>
          <div class="guide-label">Point at code</div>
        </div>
      </div>

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

      <!-- Footer -->
      <div class="btn-group" style="margin-top:1.5rem;">
        <button onclick="location.href='index.php'" class="secondary">⬅ Back</button>
        <button id="clearSessionBtn" class="danger">🗑️ Clear</button>
      </div>

    </div>
  </div>

  <div id="flash"></div>

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

  <script src="js/sound.js"></script>
  <script src="js/scanner-smart.js"></script>
</body>

</html>
