/* ============================================================
   ELLA Smart Scanner – with real-time code tracking
   • Camera: auto-detects QR / barcode format
   • Tracker: BarcodeDetector API draws live bounding boxes
   • Fallback: static guide box for unsupported browsers
   • Stats, session, return mode, flash, sounds
   ============================================================ */

/* ── CONFIG ── */
const API_ENDPOINT = "api/scan/save.php";
const RESUME_DELAY = 1800;
const SNAP_DELAY = 200;
const SESSION_KEY = "smartScannerSession";
const TRACK_FPS = 12;       // tracker frames per second
const TRACK_MS = 1000 / TRACK_FPS;

/* ── COLOURS ── */
const CLR_QR = { stroke: "#22c55e", fill: "rgba(34,197,94,0.08)", text: "#22c55e", glow: "rgba(34,197,94,0.5)" };
const CLR_BARCODE = { stroke: "#3b82f6", fill: "rgba(59,130,246,0.08)", text: "#3b82f6", glow: "rgba(59,130,246,0.5)" };
const CLR_DEFAULT = { stroke: "rgba(255,255,255,0.4)", fill: "transparent", text: "rgba(255,255,255,0.6)", glow: "transparent" };

/* ── SCAN FORMATS ── */
const ALL_FORMATS = [
  Html5QrcodeSupportedFormats.QR_CODE,
  Html5QrcodeSupportedFormats.CODE_128,
];

const QR_FORMAT_NAMES = new Set([
  "QR_CODE", "AZTEC", "DATA_MATRIX", "PDF_417", "MAXI_CODE",
  "qr_code", "aztec", "data_matrix", "pdf417",   // BarcodeDetector uses lowercase
]);

/* ── BarcodeDetector format mapping ── */
const BD_FORMATS = [
  "qr_code", "code_128"
];

/* ── STATE ── */
let scanner = null;
let isScanning = false;
let lastValue = null;
let snapTimer = null;

/* ── OFFLINE & BATCH ── */
let isBatchMode = false;
let batchQueue = [];
let offlineQueue = [];

/* ── COUNTERS ── */
let scanCount = 0;
let successScanCount = 0;
let pouchCount = 0;
let bulkyCount = 0;

/* ══════════════════════════════════════════
   INIT
══════════════════════════════════════════ */
window.addEventListener("load", () => {
  loadSession();
  loadQueues();
  restoreCounterUI();
  // We no longer call initScanner here — it's called by unlockAudio() 
  // on a user gesture to satisfy mobile browser security.
  updateStatus("Waiting for user gesture…");

  // Return mode toggle
  const toggle = document.getElementById("returnModeToggle");
  const mText = document.getElementById("modeText");
  if (toggle && mText) {
    toggle.addEventListener("change", () => {
      mText.innerText = toggle.checked ? "Return Mode" : "Normal Mode";
    });
  }

  // Batch mode toggle
  const batchToggle = document.getElementById("batchModeToggle");
  const bText = document.getElementById("batchModeText");
  const batchAction = document.getElementById("batch-action-container");
  if (batchToggle && bText) {
    batchToggle.addEventListener("change", () => {
      isBatchMode = batchToggle.checked;
      bText.innerText = isBatchMode ? "Batch: ON" : "Batch: OFF";
      if (batchAction) batchAction.style.display = isBatchMode ? "block" : "none";
    });
  }

  // Submit Batch Button
  const submitBtn = document.getElementById("submitBatchBtn");
  if (submitBtn) submitBtn.addEventListener("click", submitBatch);

  // Clear session
  const clearBtn = document.getElementById("clearSessionBtn");
  if (clearBtn) clearBtn.addEventListener("click", clearSession);

  // Network Status
  window.addEventListener("online", handleOnline);
  window.addEventListener("offline", handleOffline);
  handleOffline(); // Check initial state
});

/* ══════════════════════════════════════════
   CAMERA / SCANNER INIT
══════════════════════════════════════════ */
let isTorchOn = false;

function toggleTorch() {
  if (!scannerInstance) return;
  
  isTorchOn = !isTorchOn;
  const btn = document.getElementById("torchBtn");
  
  try {
    scannerInstance.applyVideoConstraints({
      advanced: [{ torch: isTorchOn }]
    }).then(() => {
      if (btn) btn.style.background = isTorchOn ? "rgba(255, 255, 255, 0.9)" : "rgba(0,0,0,0.6)";
    }).catch(err => {
      console.warn("Torch failed", err);
      alert("Flashlight is not supported by your browser or camera.");
      isTorchOn = false;
      if (btn) btn.style.background = "rgba(0,0,0,0.6)";
    });
  } catch(e) {
    console.warn("Torch API error", e);
  }
}

async function initScanner() {
  if (window.scannerInstance) {
    try {
      await window.scannerInstance.stop();
      window.scannerInstance.clear();
    } catch (e) { }
    window.scannerInstance = null;
  }

  updateStatus("Initialising...");

  // 1. Check for File Protocol (common XAMPP mistake)
  if (window.location.protocol === "file:") {
    updateStatus("❌ Error: Use localhost");
    alert("CRITICAL: You are opening this as a FILE.\n\nBrowsers block the camera if you open the file directly. You MUST use your XAMPP server address (e.g., http://localhost/camera/scan-smart.html) to scan.");
    return;
  }

  // 2. Check for Secure Context
  if (!window.isSecureContext) {
    updateStatus("❌ Error: Insecure Connection");
    alert("Camera access BLOCKED.\n\nBrowser security requires HTTPS or 'localhost'. Please ensure you are accessing via http://localhost or https://");
    return;
  }

  // 3. Delay slightly
  await new Promise(r => setTimeout(r, 500));

  updateStatus("Requesting permission...");
  const scannerInstance = new Html5Qrcode("reader");
  window.scannerInstance = scannerInstance;

  const scanConfig = {
    fps: 30, // Increased for faster frame capture
    qrbox: (viewW, viewH) => {
      const w = viewW || 300, h = viewH || 200;
      // Much wider box to easily capture long 1D shipping barcodes
      return { width: Math.round(w * 0.95), height: Math.round(h * 0.60) };
    },
    disableFlip: true,
    formatsToSupport: ALL_FORMATS,
    experimentalFeatures: { useBarCodeDetectorIfSupported: true }
  };

  try {
    // 4. Trigger permission prompt by listing cameras first
    const devices = await Html5Qrcode.getCameras();
    if (!devices || devices.length === 0) {
      throw { name: "NotFoundError" };
    }

    // Try to find the back camera (environment)
    let targetCamera = devices[0].id;
    const backCam = devices.find(d => d.label.toLowerCase().includes("back") || d.label.toLowerCase().includes("environment"));
    if (backCam) targetCamera = backCam.id;

    updateStatus("Starting camera stream...");
    await scannerInstance.start(
      targetCamera, // Using direct ID so browser manages resolution and focus best
      scanConfig,
      onDecoded
    );
    finalizeStart();
  } catch (err) {
    console.error("Scanner init error:", err);
    handleCameraError(err);
  }
}

function finalizeStart() {
  isScanning = false; // Start in idle mode, waiting for button press
  updateStatus("Camera ready. Tap SCAN to begin.");
  applyAdvancedCamera();
  showFallbackGuide();
}

let scanTimeoutTimer = null;
let resumeTimeoutTimer = null;

window.triggerManualScan = function() {
  // Clear any pending UI resets from previous scans
  clearTimeout(resumeTimeoutTimer);

  if (isScanning) {
    // Cancel the scan if they tap it again while scanning
    stopManualScan();
    updateStatus("Scan cancelled.");
    return;
  }
  
  // Haptic feedback for hardware-like feel
  if (navigator.vibrate) navigator.vibrate(50);
  
  isScanning = true;
  candidateCode = null;
  candidateCount = 0;
  lastValue = ""; // IMPORTANT: Clear last value so they can deliberately rescan the same package!
  
  const btn = document.getElementById("manualScanBtn");
  if (btn) {
    btn.innerHTML = "⏳ SCANNING...";
    btn.style.background = "#eab308"; // Yellow
    btn.style.boxShadow = "0 4px 15px rgba(234, 179, 8, 0.4)";
    btn.classList.remove("btn-pulse-idle"); // Stop animation while scanning
  }
  
  // Make laser line visible
  const laser = document.querySelector(".laser-line");
  if (laser) laser.style.display = "block";

  updateStatus("Scanning... Point at barcode");
  
  // Timeout after 8 seconds if nothing is found (gives them more time to focus)
  clearTimeout(scanTimeoutTimer);
  scanTimeoutTimer = setTimeout(() => {
    if (isScanning) {
      stopManualScan();
      updateStatus("Scan timeout. Tap again.");
    }
  }, 8000);
}

function stopManualScan() {
  isScanning = false;
  const btn = document.getElementById("manualScanBtn");
  if (btn) {
    btn.innerHTML = "📸 TAP TO SCAN";
    btn.style.background = "#22c55e"; // Green
    btn.style.boxShadow = "0 4px 15px rgba(34, 197, 94, 0.4)";
  }
  
  // Hide laser
  const laser = document.querySelector(".laser-line");
  if (laser) laser.style.display = "none";
}

function handleCameraError(err) {
  console.error("Camera error details:", err);
  let msg = "❌ Connection Failed";
  let showRetry = true;
  const errStr = String(err).toLowerCase();

  if (err.name === "NotAllowedError" || err.name === "PermissionDeniedError" || errStr.includes("permission")) {
    msg = "❌ Permission Denied";
    alert("Camera access was blocked by your browser.\n\nTo fix:\n1. Open your browser settings.\n2. Find 'Site Settings' -> 'Camera'.\n3. Allow camera for this website.");
  } else if (err.name === "NotFoundError" || err.name === "DevicesNotFoundError") {
    msg = "❌ No Camera Found";
    showRetry = false;
  } else if (err.name === "NotReadableError" || err.name === "TrackStartError" || errStr.includes("readable") || errStr.includes("could not start")) {
    msg = "❌ Hardware Error";
    alert("The camera could not be started.\n\nThis usually means it's ALREADY IN USE by another app (like Zoom, Facebook, or another browser tab). Please close all other apps and refresh.");
  } else if (err.name === "OverconstrainedError") {
    msg = "❌ Hardware Mismatch";
  }

  if (showRetry) {
    updateStatus(`${msg} — Tap to Retry`);
    const pill = document.getElementById("status-pill");
    if (pill) {
      pill.style.cursor = "pointer";
      pill.onclick = () => {
        initScanner(); // Re-trigger
      };
    }
  } else {
    updateStatus(msg);
  }
}

// Expose to window for the HTML unlockAudio() gesture
window.initScanner = initScanner;

/* Apply advanced camera settings AFTER the stream is started.
   This helps with devices that support torch, zoom, or focus
   constraints but only on an active track. */
function applyAdvancedCamera() {
  try {
    const video = document.querySelector("#reader video");
    if (!video || !video.srcObject) return;

    const track = video.srcObject.getVideoTracks()[0];
    if (!track) return;

    const caps = track.getCapabilities?.() || {};

    const constraints = {};

    // Continuous autofocus (critical for barcodes at close range)
    if (caps.focusMode && caps.focusMode.includes("continuous")) {
      constraints.focusMode = "continuous";
    }

    if (Object.keys(constraints).length > 0) {
      track.applyConstraints({ advanced: [constraints] }).catch(() => {
        // Silently ignore — these are optional enhancements
      });
    }
  } catch (e) {
    // Not critical — ignore
  }
}

let candidateCode = null;
let candidateCount = 0;
let candidateTimer = null;

function onDecoded(decodedText, decodedResult) {
  if (!isScanning) return;
  if (decodedText === lastValue) return;

  // Anti-hallucination: Ignore random short noise detections (waybills are at least 6+ chars)
  if (!decodedText || decodedText.trim().length < 6) return;

  // IMPORTANT: Ignore URLs to prevent "double waybill" bugs!
  // Many waybills (J&T, Shopee) have a QR code containing a URL, and a Barcode containing the tracking number.
  // If the scanner sees both, it will scan twice. Since we only want tracking numbers, we ignore URLs.
  if (decodedText.toLowerCase().startsWith("http://") || decodedText.toLowerCase().startsWith("https://")) {
    return;
  }

  const fmt = decodedResult?.result?.format?.formatName || "";
  const isQR = QR_FORMAT_NAMES.has(fmt);

  // Redundancy Check for 1D Barcodes:
  // 1D barcodes are prone to partial reads if the camera is moving.
  // We require the scanner to see the EXACT same code 2 frames in a row before accepting it.
  if (!isQR) {
    if (candidateCode !== decodedText) {
      candidateCode = decodedText;
      candidateCount = 1;
      clearTimeout(candidateTimer);
      // Reset if we don't see it again within 1500ms (gives slow phones enough time)
      candidateTimer = setTimeout(() => { candidateCode = null; candidateCount = 0; }, 1500);
      return;
    } else {
      candidateCount++;
      if (candidateCount < 2) return; // Wait until we see it 2 times!
    }
  }

  // Clear candidate buffer on success
  candidateCode = null;
  candidateCount = 0;
  clearTimeout(candidateTimer);
  
  clearTimeout(scanTimeoutTimer);
  stopManualScan();

  // Strong haptic feedback on success
  if (navigator.vibrate) navigator.vibrate([100, 50, 100]);

  // Update badge
  updateBadge(isQR ? "qr" : "barcode");

  isScanning = false;
  clearTimeout(snapTimer);
  snapTimer = setTimeout(() => handleScan(decodedText, isQR ? "QR" : "BARCODE"), isQR ? 0 : SNAP_DELAY);
}

async function handleScan(value, type) {
  lastValue = value;
  updateStatus("Processing…");

  try {
    const courier = document.getElementById("courierSelect")?.value || "";
    const platform = document.getElementById("platformSelect")?.value || "";
    const parcelSize = document.getElementById("parcelSizeSelect")?.value || "POUCH";
    const isReturn = document.getElementById("returnModeToggle")?.checked || false;

    const scanData = { code: value, type, courier, platform, parcel_size: parcelSize, is_return: isReturn };

    // Strict duplicate check against session history to prevent double-scanning
    // the same waybill if it has both a QR and a Barcode (or just rescanning)
    let isLocalDuplicate = false;
    const historyList = document.querySelectorAll('.history-code');
    historyList.forEach(item => {
      if (item.innerText === value) isLocalDuplicate = true;
    });

    if (isLocalDuplicate) {
      flash("duplicate");
      Sound.duplicate();
      updateStatus("⚠️ Duplicate – already scanned");
      setTimeout(resumeScanner, 1500);
      return;
    }

    if (isBatchMode) {
      batchQueue.push(scanData);
      saveQueues();
      scanCount++;
      updateCounterUI();
      flash("success");
      Sound.success();
      updateStatus(`📦 Added to Batch (${batchQueue.length})`);
      pushHistory(value, type, false, { timestamp: new Date().toISOString() });
      setTimeout(resumeScanner, 1200); // Increased from 500ms to give time to move parcel away
      return;
    }

    let res;
    try {
      res = await fetch(API_ENDPOINT, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(scanData)
      });
    } catch(fetchErr) {
      // Network Error (Offline)
      offlineQueue.push(scanData);
      saveQueues();
      scanCount++;
      updateCounterUI();
      flash("warning");
      Sound.success();
      updateStatus(`⚠️ Saved Offline (${offlineQueue.length} pending)`);
      pushHistory(value, type, false, { timestamp: new Date().toISOString() });
      handleOffline(); // update banner
      setTimeout(resumeScanner, 500);
      return;
    }
    
    let data;
    try {
      data = await res.json();
    } catch(e) {
      throw new Error(`Server returned HTTP ${res.status} without JSON.`);
    }

    if (!res.ok || data.status === "error") {
      throw new Error(data.message || `HTTP ${res.status} Error`);
    }

    scanCount++;
    updateCounterUI();

    if (data.duplicate) {
      flash("duplicate");
      Sound.duplicate();
      updateStatus("⚠️ Duplicate – already scanned");
    } else {
      successScanCount++;
      if (parcelSize === "BULKY") bulkyCount++; else pouchCount++;
      updateCounterUI();

      if (data.is_return) {
        flash("warning");
        Sound.success();
        updateStatus(`🔄 Return Saved (${type})`);
      } else {
        flash("success");
        Sound.success();
        updateStatus(`✅ Saved (${type})`);
      }
    }

    pushHistory(value, type, data.duplicate, data.data);
    saveSession();
  } catch (err) {
    console.error(err);
    alert("Database Error: " + err.message);
    flash("error");
    Sound.error();
    updateStatus("❌ Save failed");
  }

  resumeTimeoutTimer = setTimeout(resumeScanner, RESUME_DELAY);
}

function resumeScanner() {
  if (!isScanning) {
    updateStatus("Camera ready. Tap SCAN to begin.");
  }
}

/* ══════════════════════════════════════════
   UI HELPERS
══════════════════════════════════════════ */
function showFallbackGuide() {
  const guide = document.getElementById("fallbackGuide");
  if (guide) guide.style.display = "flex";
}
function updateBadge(mode) {
  const badge = document.getElementById("type-badge");
  if (!badge) return;
  badge.classList.remove("qr-mode", "bar-mode");
  if (mode === "qr") {
    badge.classList.add("qr-mode");
    badge.textContent = "QR";
  } else if (mode === "barcode") {
    badge.classList.add("bar-mode");
    badge.textContent = "BARCODE";
  } else {
    badge.textContent = "AUTO";
  }
}

function updateStatus(text) {
  const el = document.getElementById("status-pill");
  if (el) el.innerText = text;
}

function flash(type) {
  const el = document.getElementById("flash");
  if (!el) return;
  el.style.background =
    type === "success" ? "rgba(34,197,94,.35)" :
      type === "duplicate" ? "rgba(245,158,11,.40)" :
        type === "warning" ? "rgba(245,158,11,.40)" :
          "rgba(239,68,68,.40)";
  el.classList.add("active");
  setTimeout(() => el.classList.remove("active"), 150);
}

/* ══════════════════════════════════════════
   COUNTER / SESSION
══════════════════════════════════════════ */
function updateCounterUI() {
  const set = (id, val) => { const el = document.getElementById(id); if (el) el.innerText = val; };
  set("scan-count", scanCount);
  set("success-scan-count", successScanCount);
  set("pouch-count", pouchCount);
  set("bulky-count", bulkyCount);
}

function restoreCounterUI() { updateCounterUI(); }

function loadSession() {
  try {
    const raw = sessionStorage.getItem(SESSION_KEY);
    if (!raw) return;
    const d = JSON.parse(raw);
    scanCount = d.scanCount || 0;
    successScanCount = d.successScanCount || 0;
    pouchCount = d.pouchCount || 0;
    bulkyCount = d.bulkyCount || 0;
  } catch (e) { console.error("Session load:", e); }
}

function saveSession() {
  try {
    sessionStorage.setItem(SESSION_KEY, JSON.stringify({
      scanCount, successScanCount, pouchCount, bulkyCount
    }));
  } catch (e) { console.error("Session save:", e); }
}

function clearSession() {
  if (!confirm("Clear counters and recent scans?")) return;
  sessionStorage.removeItem(SESSION_KEY);
  scanCount = successScanCount = pouchCount = bulkyCount = 0;
  updateCounterUI();
  const hl = document.getElementById("historyList");
  if (hl) hl.innerHTML = '<li class="placeholder">No scans yet</li>';
  flash("success");
  Sound.success();
  updateStatus("Session cleared");
  setTimeout(() => updateStatus("Ready – point at a QR or barcode"), 1500);
}

/* ══════════════════════════════════════════
   HISTORY
══════════════════════════════════════════ */
function pushHistory(value, type, duplicate = false, data = {}) {
  const list = document.getElementById("historyList");
  if (!list) return;
  const ph = list.querySelector(".placeholder");
  if (ph) ph.remove();

  const isQR = type === "QR";
  const scanCt = data?.update_count || 0;
  const createdAt = data?.created_at || null;
  const lastScan = data?.last_scanned || data?.timestamp || null;
  const returnedAt = data?.returned_at || null;

  const chipClass = isQR ? "qr" : "bar";
  const chipLabel = isQR ? "⬛ QR" : "▬ BAR";
  const borderCol = isQR ? "#22c55e" : "#3b82f6";

  const li = document.createElement("li");
  li.className = `history-item ${duplicate ? "duplicate" : "new"}`;
  li.style.borderLeftColor = borderCol;
  li.innerHTML = `
    <div style="display:flex;flex-direction:column;gap:8px;">
      <div class="history-code" style="font-size:1.1rem;font-weight:800;letter-spacing:0.5px;color:var(--text);word-break:break-all;line-height:1.2;">
        ${value}
      </div>
      <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:6px;">
        <div style="display:flex;gap:6px;align-items:center;">
          <span class="type-chip ${chipClass}">${chipLabel}</span>
          <span class="history-badge ${duplicate ? 'dup' : 'new'}" style="font-size:0.65rem;font-weight:800;padding:2px 8px;border-radius:6px;background:rgba(0,0,0,0.05);">
            ${returnedAt ? "RETURNED" : duplicate ? `${scanCt + 1}× DUP` : "NEW"}
          </span>
        </div>
        <div style="font-size:0.75rem;color:var(--muted);font-weight:600;">
          🕒 ${formatDateTime(lastScan || createdAt)}
        </div>
      </div>
    </div>
  `;
  list.prepend(li);
  while (list.children.length > 50) list.removeChild(list.lastChild);
}

function formatDateTime(ts) {
  if (!ts) return "-";
  const d = new Date(ts);
  return isNaN(d) ? "-" : d.toLocaleString([], { month: "short", day: "numeric", hour: "2-digit", minute: "2-digit" });
}

/* ══════════════════════════════════════════
   BATCH & OFFLINE QUEUE LOGIC
══════════════════════════════════════════ */
function loadQueues() {
  try {
    const b = sessionStorage.getItem("smartBatchQueue");
    if (b) batchQueue = JSON.parse(b) || [];
    const o = localStorage.getItem("smartOfflineQueue");
    if (o) offlineQueue = JSON.parse(o) || [];
  } catch(e) {}
  updateBatchUI();
}

function saveQueues() {
  sessionStorage.setItem("smartBatchQueue", JSON.stringify(batchQueue));
  localStorage.setItem("smartOfflineQueue", JSON.stringify(offlineQueue));
  updateBatchUI();
}

function updateBatchUI() {
  const bCount = document.getElementById("batch-submit-count");
  if (bCount) bCount.innerText = batchQueue.length;
  
  const oBanner = document.getElementById("offline-banner");
  const oCount = document.getElementById("offline-count");
  if (oBanner && oCount) {
    if (offlineQueue.length > 0) {
      oCount.innerText = offlineQueue.length;
      oBanner.style.display = "block";
    } else {
      oBanner.style.display = (!navigator.onLine) ? "block" : "none";
      oCount.innerText = "0";
    }
  }
}

function handleOffline() {
  updateBatchUI();
}

function handleOnline() {
  updateBatchUI();
  if (offlineQueue.length > 0) {
    syncOfflineQueue();
  }
}

async function submitBatch() {
  if (batchQueue.length === 0) {
    alert("Batch is empty!");
    return;
  }
  
  const submitBtn = document.getElementById("submitBatchBtn");
  if (submitBtn) submitBtn.innerText = "🚀 Uploading...";

  try {
    const res = await fetch("api/scan/save_batch.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ scans: batchQueue })
    });
    
    let data;
    try { data = await res.json(); } catch(e) { throw new Error(`Server returned HTTP ${res.status} without JSON`); }
    
    if (!res.ok || data.status === "error") throw new Error(data.message || "Batch upload failed");

    const r = data.results;
    alert(`Batch Complete!\n\nSaved: ${r.saved}\nDuplicates: ${r.duplicates}\nErrors: ${r.errors}`);
    
    successScanCount += r.saved;
    updateCounterUI();
    
    batchQueue = [];
    saveQueues();
  } catch (err) {
    console.error(err);
    alert("Batch Upload Error: " + err.message);
  } finally {
    if (submitBtn) submitBtn.innerHTML = `🚀 Submit Batch (<span id="batch-submit-count">${batchQueue.length}</span>)`;
    updateBatchUI();
  }
}

async function syncOfflineQueue() {
  if (offlineQueue.length === 0) return;
  
  updateStatus(`Syncing ${offlineQueue.length} offline scans...`);
  const scansToSync = [...offlineQueue];
  
  try {
    const res = await fetch("api/scan/save_batch.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ scans: scansToSync })
    });
    
    let data;
    try { data = await res.json(); } catch(e) { throw new Error("Offline Sync JSON Error"); }
    if (!res.ok || data.status === "error") throw new Error(data.message || "Offline sync failed");
    
    const r = data.results;
    successScanCount += r.saved;
    updateCounterUI();
    
    offlineQueue = [];
    saveQueues();
    updateStatus("✅ Offline Scans Synced");
    setTimeout(() => updateStatus("Ready – point at a QR or barcode"), 3000);
  } catch(err) {
    console.error("Offline sync failed, will retry later.", err);
    updateStatus("⚠️ Sync Failed - will retry");
  }
}
