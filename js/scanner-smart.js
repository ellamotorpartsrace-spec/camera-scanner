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

/* ══════════════════════════════════════════
   COURIER PATTERN DETECTION & VALIDATION
══════════════════════════════════════════ */
const CourierDetector = {
  patterns: [
    {
      id: "shopee_spx",
      courier: "Shopee Express",
      platform: "Shopee",
      regex: /^(SPX[A-Z0-9]*|PH[0-9A-Z]{8,})/i,
      name: "Shopee Express",
      badgeClass: "badge-shopee",
      icon: "🟠"
    },
    {
      id: "flash",
      courier: "Flash Express",
      platform: null,
      regex: /^P(?![Hh])[0-9A-Z]{8,20}$/i,
      name: "Flash Express",
      badgeClass: "badge-flash",
      icon: "⚡"
    },
    {
      id: "lazada_jnt",
      courier: "JNT Express",
      platform: "Lazada",
      regex: /^8[2-5]\d{10}$/,
      name: "J&T Express (Lazada)",
      badgeClass: "badge-jnt",
      icon: "🔴"
    },
    {
      id: "tiktok_jnt",
      courier: "JNT Express",
      platform: "TikTok",
      regex: /^JT[0-9A-Z]{8,20}/i,
      name: "J&T Express (TikTok)",
      badgeClass: "badge-jnt",
      icon: "🔴"
    },
    {
      id: "lazada_lex",
      courier: "Lazada Express",
      platform: "Lazada",
      regex: /^(LX|MP|NLPH)[0-9A-Z]+/i,
      name: "Lazada Express (LEX)",
      badgeClass: "badge-lex",
      icon: "🔵"
    },
    {
      id: "general_jnt",
      courier: "JNT Express",
      platform: null,
      regex: /^[789]\d{11}$/,
      name: "J&T Express",
      badgeClass: "badge-jnt",
      icon: "🔴"
    }
  ],

  detect(code) {
    if (!code) return null;
    const clean = code.trim().toUpperCase();
    for (const p of this.patterns) {
      if (p.regex.test(clean)) {
        return p;
      }
    }
    return null;
  },

  validate(code, selectedCourier, selectedPlatform) {
    if (!selectedCourier || selectedCourier === "Others") {
      return { match: true }; // Allow unselected or Others
    }

    const detected = this.detect(code);
    if (!detected) {
      return { match: true, unknown: true }; // Unknown formats don't block
    }

    const courierMatches = (detected.courier.toLowerCase() === selectedCourier.toLowerCase());

    let platformMatches = true;
    if (selectedPlatform && detected.platform && detected.platform.toLowerCase() !== selectedPlatform.toLowerCase()) {
      platformMatches = false;
    }

    return {
      match: courierMatches && platformMatches,
      detected,
      courierMismatch: !courierMatches,
      platformMismatch: !platformMatches
    };
  },

  getBadge(courierName) {
    const c = (courierName || "").toLowerCase();
    if (c.includes("flash")) return { class: "badge-flash", icon: "⚡", label: "Flash" };
    if (c.includes("jnt") || c.includes("j&t")) return { class: "badge-jnt", icon: "🔴", label: "J&T" };
    if (c.includes("shopee")) return { class: "badge-shopee", icon: "🟠", label: "SPX" };
    if (c.includes("lazada")) return { class: "badge-lex", icon: "🔵", label: "LEX" };
    return { class: "badge-other", icon: "📦", label: courierName || "Courier" };
  }
};

function speakVoice(text) {
  if ('speechSynthesis' in window) {
    try {
      window.speechSynthesis.cancel();
      const msg = new SpeechSynthesisUtterance(text);
      msg.rate = 1.1;
      msg.pitch = 1.0;
      msg.lang = 'en-US';
      window.speechSynthesis.speak(msg);
    } catch(e) {
      console.warn("Speech synthesis error", e);
    }
  }
}

// Backward compatibility alias
function speakMismatchAlert(text) {
  speakVoice(text);
}

function highlightField(el) {
  if (!el) return;
  el.style.transition = "all 0.3s";
  el.style.borderColor = "#f59e0b";
  el.style.boxShadow = "0 0 10px rgba(245, 158, 11, 0.5)";
  setTimeout(() => {
    el.style.borderColor = "";
    el.style.boxShadow = "";
  }, 1200);
}

/* ── STATE ── */
let scanner = null;
let isScanning = false;
let lastValue = null;
let snapTimer = null;

/* ── OFFLINE & BATCH ── */
let isBatchMode = false;
let batchQueue = [];
let offlineQueue = [];
let submittedBatches = [];
let recentScans = [];

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
  updateStatus("Waiting for user gesture…");

  // Return mode toggle
  const toggle = document.getElementById("returnModeToggle");
  const mText = document.getElementById("modeText");
  if (toggle && mText) {
    if (localStorage.getItem("smartReturnMode") === "true") toggle.checked = true;
    mText.innerText = toggle.checked ? "Return Mode" : "Normal Mode";
    toggle.addEventListener("change", () => {
      localStorage.setItem("smartReturnMode", toggle.checked);
      mText.innerText = toggle.checked ? "Return Mode" : "Normal Mode";
    });
  }

  // Batch mode toggle
  const batchToggle = document.getElementById("batchModeToggle");
  const bText = document.getElementById("batchModeText");
  const batchAction = document.getElementById("batch-action-container");
  if (batchToggle && bText) {
    if (localStorage.getItem("smartBatchMode") === "true") batchToggle.checked = true;
    isBatchMode = batchToggle.checked;
    bText.innerText = isBatchMode ? "Batch: ON" : "Batch: OFF";
    if (batchAction) batchAction.style.display = isBatchMode ? "block" : "none";

    batchToggle.addEventListener("change", () => {
      localStorage.setItem("smartBatchMode", batchToggle.checked);
      isBatchMode = batchToggle.checked;
      bText.innerText = isBatchMode ? "Batch: ON" : "Batch: OFF";
      if (batchAction) batchAction.style.display = isBatchMode ? "block" : "none";
    });
  }

  // Auto-Detect toggle
  const autoToggle = document.getElementById("autoDetectToggle");
  const autoLabel = document.getElementById("autoDetectLabel");
  if (autoToggle) {
    if (localStorage.getItem("smartAutoDetect") === "true") {
      autoToggle.checked = true;
    }
    const updateAutoDetectUI = (active) => {
      if (autoLabel) {
        autoLabel.innerHTML = active 
          ? '⚡ Auto-Detect <span style="font-size:0.6rem; background:#f59e0b; color:#000; padding:1px 5px; border-radius:10px; font-weight:900;">ON</span>' 
          : '⚡ Auto-Detect';
      }
    };
    updateAutoDetectUI(autoToggle.checked);
    autoToggle.addEventListener("change", () => {
      localStorage.setItem("smartAutoDetect", autoToggle.checked);
      updateAutoDetectUI(autoToggle.checked);
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

  // Poll for new batches submitted by other phones
  setInterval(() => {
    if (isScanning || document.visibilityState === 'visible') {
      fetchSubmittedBatches();
    }
  }, 10000);
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
    let courier = document.getElementById("courierSelect")?.value || "";
    let platform = document.getElementById("platformSelect")?.value || "";
    const parcelSize = document.getElementById("parcelSizeSelect")?.value || "POUCH";
    const isReturn = document.getElementById("returnModeToggle")?.checked || false;
    const isAutoDetect = document.getElementById("autoDetectToggle")?.checked || false;

    // Strict duplicate check against session history and batch queue
    let isLocalDuplicate = false;
    const historyList = document.querySelectorAll('.history-code');
    historyList.forEach(item => {
      if (item.innerText.trim() === value.trim()) isLocalDuplicate = true;
    });

    if (!isLocalDuplicate && isBatchMode && Array.isArray(batchQueue)) {
      if (batchQueue.some(item => item.code && item.code.trim() === value.trim())) {
        isLocalDuplicate = true;
      }
    }

    if (isLocalDuplicate) {
      flash("duplicate");
      if (window.Sound && window.Sound.duplicate) {
        window.Sound.duplicate();
      }
      speakVoice("Already scanned! Duplicate.");
      updateStatus("⚠️ Duplicate – already scanned");
      setTimeout(resumeScanner, 1500);
      return;
    }

    // 1. AUTO-DETECT MODE
    if (isAutoDetect) {
      const detected = CourierDetector.detect(value);
      if (detected) {
        if (detected.courier) {
          courier = detected.courier;
          const cSel = document.getElementById("courierSelect");
          if (cSel && cSel.value !== courier) {
            cSel.value = courier;
            highlightField(cSel);
          }
        }
        if (detected.platform) {
          platform = detected.platform;
          const pSel = document.getElementById("platformSelect");
          if (pSel && pSel.value !== platform) {
            pSel.value = platform;
            highlightField(pSel);
          }
        }
      }
    } else {
      // 2. STRICT VALIDATION MODE (Direct In-Flow Error - Zero Interruption!)
      const valResult = CourierDetector.validate(value, courier, platform);
      if (!valResult.match) {
        if (window.Sound && window.Sound.mismatch) {
          window.Sound.mismatch();
        } else if (window.Sound) {
          window.Sound.error();
        }
        flash("error");

        const detectedCourier = valResult.detected?.courier || "Wrong Courier";

        speakVoice(`Not match! Scanned ${detectedCourier}.`);
        updateStatus(`❌ Not Match – Scanned ${detectedCourier}`, "error");

        clearTimeout(resumeTimeoutTimer);
        resumeTimeoutTimer = setTimeout(resumeScanner, 1800);
        return; // Halt processing! Do not save or batch.
      }
    }

    const scanData = { code: value, type, courier, platform, parcel_size: parcelSize, is_return: isReturn };
    await executeScan(scanData, type);

  } catch (err) {
    console.error(err);
    alert("Database Error: " + err.message);
    flash("error");
    if (window.Sound) Sound.error();
    updateStatus("❌ Save failed");
    resumeTimeoutTimer = setTimeout(resumeScanner, RESUME_DELAY);
  }
}

async function executeScan(scanData, type) {
  const { code: value, courier, platform, parcel_size: parcelSize, is_return: isReturn } = scanData;

  if (isBatchMode) {
    batchQueue.push(scanData);
    saveQueues();
    scanCount++;
    updateCounterUI();
    flash("success");
    Sound.success();
    updateStatus(`📦 Added to Batch (${batchQueue.length})`);
    pushHistory(value, type, false, { timestamp: new Date().toISOString(), courier, platform, parcel_size: parcelSize });
    setTimeout(resumeScanner, 1200);
    return;
  }

  // Hostinger strips application/json bodies, so we MUST use FormData
  let res;
  let data;
  const scanFd = new FormData();
  scanFd.append("payload", JSON.stringify(scanData));
  try {
    res = await fetch(API_ENDPOINT, { method: "POST", body: scanFd });
  } catch(fetchErr) {
    // Network Error (Offline)
    offlineQueue.push(scanData);
    saveQueues();
    scanCount++;
    updateCounterUI();
    flash("warning");
    Sound.success();
    updateStatus(`⚠️ Saved Offline (${offlineQueue.length} pending)`);
    pushHistory(value, type, false, { timestamp: new Date().toISOString(), courier, platform, parcel_size: parcelSize });
    handleOffline();
    setTimeout(resumeScanner, 500);
    return;
  }

  try { data = await res.json(); } catch(e) { throw new Error(`Server returned HTTP ${res.status} without JSON.`); }

  if (!res.ok || data.status === "error") {
    throw new Error(data.message || `HTTP ${res.status} Error`);
  }

  scanCount++;
  updateCounterUI();

  if (data.duplicate) {
    flash("duplicate");
    if (window.Sound && window.Sound.duplicate) {
      window.Sound.duplicate();
    }
    speakVoice("Already scanned! Duplicate.");
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

  const histData = Object.assign({}, data.data || {}, { courier, platform, parcel_size: parcelSize });
  pushHistory(value, type, data.duplicate, histData);
  saveSession();

  resumeTimeoutTimer = setTimeout(resumeScanner, RESUME_DELAY);
}

function resumeScanner() {
  if (!isScanning) {
    updateStatus("Camera ready. Tap SCAN to begin.", "normal");
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

function updateStatus(text, type = "normal") {
  const el = document.getElementById("status-pill");
  if (!el) return;
  el.innerHTML = text;
  el.className = type === "error" ? "status-error" : (type === "warning" || type === "duplicate") ? "status-warning" : type === "success" ? "status-success" : "";
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
    const raw = localStorage.getItem(SESSION_KEY);
    if (raw) {
      const d = JSON.parse(raw);
      scanCount = d.scanCount || 0;
      successScanCount = d.successScanCount || 0;
      pouchCount = d.pouchCount || 0;
      bulkyCount = d.bulkyCount || 0;
    }

    const rawRecent = localStorage.getItem("smartRecentScans");
    if (rawRecent) {
      recentScans = JSON.parse(rawRecent) || [];
      // Render backwards so newest ends up on top via prepend
      for (let i = recentScans.length - 1; i >= 0; i--) {
        const item = recentScans[i];
        renderHistoryDOM(item.value, item.type, item.duplicate, item.data);
      }
    }
  } catch (e) { console.error("Session load:", e); }
}

function saveSession() {
  try {
    localStorage.setItem(SESSION_KEY, JSON.stringify({
      scanCount, successScanCount, pouchCount, bulkyCount
    }));
  } catch (e) { console.error("Session save:", e); }
}

function clearSession() {
  if (!confirm("Clear counters, recent scans, and pending batches?")) return;
  localStorage.removeItem(SESSION_KEY);
  localStorage.removeItem("smartRecentScans");
  localStorage.removeItem("smartBatchQueue");
  localStorage.removeItem("smartOfflineQueue");
  
  recentScans = [];
  batchQueue = [];
  offlineQueue = [];
  
  scanCount = successScanCount = pouchCount = bulkyCount = 0;
  updateCounterUI();
  updateBatchUI();
  
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
  // Save to memory
  recentScans.unshift({ value, type, duplicate, data });
  if (recentScans.length > 50) recentScans.pop();
  localStorage.setItem("smartRecentScans", JSON.stringify(recentScans));
  
  // Render to DOM
  renderHistoryDOM(value, type, duplicate, data);
}

function renderHistoryDOM(value, type, duplicate = false, data = {}) {
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

  const cBadge = CourierDetector.getBadge(data?.courier);
  const courierPill = data?.courier ? `
    <span class="courier-chip ${cBadge.class}">${cBadge.icon} ${data.courier}</span>
  ` : '';

  li.innerHTML = `
    <div style="display:flex;flex-direction:column;gap:8px;width:100%;">
      <div class="history-code" style="font-size:1.1rem;font-weight:800;letter-spacing:0.5px;color:var(--text);word-break:break-all;line-height:1.2;">
        ${value}
      </div>
      <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:6px;">
        <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
          <span class="type-chip ${chipClass}">${chipLabel}</span>
          ${courierPill}
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
    const b = localStorage.getItem("smartBatchQueue");
    if (b) {
      const parsed = JSON.parse(b);
      // Guard: must be an array
      batchQueue = Array.isArray(parsed) ? parsed : [];
    }
    const o = localStorage.getItem("smartOfflineQueue");
    if (o) {
      const parsedO = JSON.parse(o);
      offlineQueue = Array.isArray(parsedO) ? parsedO : [];
    }
  } catch(e) {
    console.warn("Failed to load queues from localStorage, resetting.", e);
    batchQueue = [];
    offlineQueue = [];
  }
  updateBatchUI();
  fetchSubmittedBatches();
}

function saveQueues() {
  localStorage.setItem("smartBatchQueue", JSON.stringify(batchQueue));
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

async function fetchSubmittedBatches() {
  const container = document.getElementById("submitted-batches-container");
  const list = document.getElementById("submitted-batches-list");
  if (!container || !list) return;

  try {
    const res = await fetch("api/scan/batch_summary.php");
    const data = await res.json();
    if (data.status === "success" && data.data.length > 0) {
      submittedBatches = data.data.filter(b => b.id !== 'NORMAL');
      
      if (submittedBatches.length > 0) {
        list.innerHTML = submittedBatches.map(b => `
          <li style="background: #ffffff; padding: 12px; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; border: 1px solid #e2e8f0; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
            <span style="font-weight: 600; color: #3b82f6;">Batch ${b.id}</span>
            <span class="badge" style="background: #f1f5f9; color: #475569; padding: 6px 10px; font-size: 0.85rem;">${b.count} scans</span>
          </li>
        `).join("");
        container.style.display = "block";
      } else {
        container.style.display = "none";
      }
    } else {
      container.style.display = "none";
    }
  } catch (err) {
    console.error("Failed to fetch batch summary", err);
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

/**
 * Ultra-resilient POST payload handler with multi-strategy fallback.
 * Attempt 1: URLSearchParams (x-www-form-urlencoded) - avoids boundary bugs & SW stream stripping
 * Attempt 2: FormData (multipart/form-data) - fallback if URLSearchParams fails
 * Attempt 3: Raw JSON payload
 */
async function postPayload(url, payloadStr) {
  // Attempt 1: URLSearchParams (x-www-form-urlencoded)
  try {
    const params = new URLSearchParams();
    params.append("payload", payloadStr);
    const res = await fetch(url, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8" },
      body: params
    });
    const rawText = await res.text();
    let data;
    try { data = JSON.parse(rawText); } catch(e) { throw new Error("Server response not JSON: " + rawText.substring(0, 200)); }
    
    if (res.ok && data && data.status !== "error") {
      return data;
    }
    if (!data || data.raw_len !== 0) {
      const diag = data && data.raw_len !== undefined ? `\n\n[Debug] raw_len:${data.raw_len} post_keys:${JSON.stringify(data.post_keys)} ct:${data.content_type}` : '';
      throw new Error((data && data.message ? data.message : "Request failed") + diag);
    }
    console.warn("URLSearchParams post got raw_len:0, trying FormData fallback...");
  } catch (err) {
    if (!err.message.includes("raw_len:0")) throw err;
  }

  // Attempt 2: FormData (multipart/form-data)
  try {
    const fd = new FormData();
    fd.append("payload", payloadStr);
    const res = await fetch(url, { method: "POST", body: fd });
    const rawText = await res.text();
    let data;
    try { data = JSON.parse(rawText); } catch(e) { throw new Error("Server response not JSON: " + rawText.substring(0, 200)); }
    
    if (res.ok && data && data.status !== "error") {
      return data;
    }
    if (!data || data.raw_len !== 0) {
      const diag = data && data.raw_len !== undefined ? `\n\n[Debug] raw_len:${data.raw_len} post_keys:${JSON.stringify(data.post_keys)} ct:${data.content_type}` : '';
      throw new Error((data && data.message ? data.message : "Request failed") + diag);
    }
    console.warn("FormData post got raw_len:0, trying raw JSON fallback...");
  } catch (err) {
    if (!err.message.includes("raw_len:0")) throw err;
  }

  // Attempt 3: Raw JSON payload
  const res = await fetch(url, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: payloadStr
  });
  const rawText = await res.text();
  let data;
  try { data = JSON.parse(rawText); } catch(e) { throw new Error("Server response not JSON: " + rawText.substring(0, 200)); }

  if (!res.ok || data.status === "error") {
    const diag = data && data.raw_len !== undefined ? `\n\n[Debug] raw_len:${data.raw_len} post_keys:${JSON.stringify(data.post_keys)} ct:${data.content_type}` : '';
    throw new Error((data && data.message ? data.message : "Request failed") + diag);
  }
  return data;
}

async function submitBatch() {
  // Ensure batchQueue is always a valid array (guard against localStorage corruption)
  if (!Array.isArray(batchQueue)) {
    console.warn("batchQueue was not an array, resetting.", batchQueue);
    batchQueue = [];
    saveQueues();
  }

  if (batchQueue.length === 0) {
    alert("Batch is empty!");
    return;
  }
  
  const submitBtn = document.getElementById("submitBatchBtn");
  if (submitBtn) submitBtn.innerText = "🚀 Uploading...";

  // Build safe payload, filtering out any non-object entries
  const safeScans = batchQueue.filter(s => s && typeof s === 'object' && s.code);
  if (safeScans.length === 0) {
    alert("Batch is empty or contains invalid data. Please clear and rescan.");
    batchQueue = [];
    saveQueues();
    updateBatchUI();
    if (submitBtn) submitBtn.innerHTML = `🚀 Submit Batch (<span id="batch-submit-count">0</span>)`;
    return;
  }

  const payloadStr = JSON.stringify({ scans: safeScans });
  console.log("Submitting batch payload:", payloadStr.substring(0, 200));

  try {
    const data = await postPayload("api/scan/save_batch.php", payloadStr);

    const r = data.results;
    alert(`Batch Complete!\n\nSaved: ${r.saved}\nDuplicates: ${r.duplicates}\nErrors: ${r.errors}`);
    
    successScanCount += r.saved;
    pouchCount += r.pouch_saved || 0;
    bulkyCount += r.bulky_saved || 0;
    updateCounterUI();
    saveSession();
    
    batchQueue = [];
    saveQueues();
    fetchSubmittedBatches();
  } catch (err) {
    console.error("submitBatch error:", err);
    alert("Batch Upload Error: " + err.message);
  } finally {
    if (submitBtn) submitBtn.innerHTML = `🚀 Submit Batch (<span id="batch-submit-count">${batchQueue.length}</span>)`;
    updateBatchUI();
  }
}

async function syncOfflineQueue() {
  if (!Array.isArray(offlineQueue)) {
    offlineQueue = [];
    saveQueues();
  }
  if (offlineQueue.length === 0) return;
  
  updateStatus(`Syncing ${offlineQueue.length} offline scans...`);
  const scansToSync = [...offlineQueue];
  
  try {
    const data = await postPayload("api/scan/save_batch.php", JSON.stringify({ scans: scansToSync }));

    const r = data.results;
    successScanCount += r.saved;
    pouchCount += r.pouch_saved || 0;
    bulkyCount += r.bulky_saved || 0;
    updateCounterUI();
    saveSession();
    
    offlineQueue = [];
    saveQueues();
    updateStatus("✅ Offline Scans Synced");
    setTimeout(() => updateStatus("Ready – point at a QR or barcode"), 3000);
  } catch(err) {
    console.error("Offline sync failed, will retry later.", err);
    updateStatus("⚠️ Sync Failed - will retry");
  }
}
