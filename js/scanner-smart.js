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
  Html5QrcodeSupportedFormats.AZTEC,
  Html5QrcodeSupportedFormats.DATA_MATRIX,
  Html5QrcodeSupportedFormats.PDF_417,
  Html5QrcodeSupportedFormats.CODE_128,
  Html5QrcodeSupportedFormats.CODE_39,
  Html5QrcodeSupportedFormats.CODE_93,
  Html5QrcodeSupportedFormats.EAN_13,
  Html5QrcodeSupportedFormats.EAN_8,
  Html5QrcodeSupportedFormats.UPC_A,
  Html5QrcodeSupportedFormats.UPC_E,
  Html5QrcodeSupportedFormats.ITF,
  Html5QrcodeSupportedFormats.CODABAR,
];

const QR_FORMAT_NAMES = new Set([
  "QR_CODE", "AZTEC", "DATA_MATRIX", "PDF_417", "MAXI_CODE",
  "qr_code", "aztec", "data_matrix", "pdf417",   // BarcodeDetector uses lowercase
]);

/* ── BarcodeDetector format mapping ── */
const BD_FORMATS = [
  "qr_code", "aztec", "data_matrix", "pdf417",
  "code_128", "code_39", "code_93",
  "ean_13", "ean_8", "upc_a", "upc_e",
  "itf", "codabar",
];

/* ── STATE ── */
let scanner = null;
let isScanning = false;
let lastValue = null;
let snapTimer = null;

let barcodeDetector = null;
let trackerRunning = false;
let lastTrackTime = 0;
let hasTrackerSupport = false;

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

  // Clear session
  const clearBtn = document.getElementById("clearSessionBtn");
  if (clearBtn) clearBtn.addEventListener("click", clearSession);
});

/* ══════════════════════════════════════════
   HTML5-QRCODE SCANNER (decode + save)
══════════════════════════════════════════ */
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
    fps: 12,
    qrbox: (viewW, viewH) => {
      const w = viewW || 300, h = viewH || 200;
      return { width: Math.round(w * 0.85), height: Math.round(h * 0.50) };
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
      targetCamera,
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
  isScanning = true;
  updateStatus("Ready – point at a QR or barcode");
  applyAdvancedCamera();
  initTracker(); // Restart the visual tracker
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

    // Slight zoom (1.3-1.8x) makes barcode lines thicker in the frame
    if (caps.zoom) {
      const targetZoom = Math.min(caps.zoom.max, Math.max(caps.zoom.min, 1.5));
      constraints.zoom = targetZoom;
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

function onDecoded(decodedText, decodedResult) {
  if (!isScanning) return;
  if (decodedText === lastValue) return;

  const fmt = decodedResult?.result?.format?.formatName || "";
  const isQR = QR_FORMAT_NAMES.has(fmt);

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

    const res = await fetch(API_ENDPOINT, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ code: value, type, courier, platform, parcel_size: parcelSize, is_return: isReturn })
    });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const data = await res.json();

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
    flash("error");
    Sound.error();
    updateStatus("❌ Save failed");
  }

  setTimeout(resumeScanner, RESUME_DELAY);
}

function resumeScanner() {
  isScanning = true;
  lastValue = null;
  updateStatus("Ready – point at a QR or barcode");
}

/* ══════════════════════════════════════════
   BARCODEDETECTOR TRACKER (visual overlay)
══════════════════════════════════════════ */
function initTracker() {
  if (!("BarcodeDetector" in window)) {
    console.log("BarcodeDetector not available – using fallback guide");
    hasTrackerSupport = false;
    showFallbackGuide();
    return;
  }

  // Check which formats are actually supported
  BarcodeDetector.getSupportedFormats().then(supported => {
    const usable = BD_FORMATS.filter(f => supported.includes(f));
    if (usable.length === 0) {
      hasTrackerSupport = false;
      showFallbackGuide();
      return;
    }

    barcodeDetector = new BarcodeDetector({ formats: usable });
    hasTrackerSupport = true;
    trackerRunning = true;
    requestAnimationFrame(trackLoop);
  }).catch(() => {
    hasTrackerSupport = false;
    showFallbackGuide();
  });
}

function trackLoop(timestamp) {
  if (!trackerRunning) return;

  // Throttle to TRACK_FPS
  if (timestamp - lastTrackTime < TRACK_MS) {
    requestAnimationFrame(trackLoop);
    return;
  }
  lastTrackTime = timestamp;

  // Find the video element html5-qrcode creates
  const video = document.querySelector("#reader video");
  if (!video || video.readyState < 2) {
    requestAnimationFrame(trackLoop);
    return;
  }

  barcodeDetector.detect(video).then(codes => {
    drawTrackerOverlay(codes, video);
  }).catch(() => {
    // Ignore occasional detection errors
  });

  requestAnimationFrame(trackLoop);
}

function drawTrackerOverlay(codes, video) {
  const canvas = document.getElementById("tracker-canvas");
  if (!canvas) return;

  const wrapper = document.getElementById("reader-wrapper");
  const wrapW = wrapper.offsetWidth;
  const wrapH = wrapper.offsetHeight;

  // Match canvas pixel size to wrapper size
  if (canvas.width !== wrapW || canvas.height !== wrapH) {
    canvas.width = wrapW;
    canvas.height = wrapH;
  }

  const ctx = canvas.getContext("2d");
  ctx.clearRect(0, 0, wrapW, wrapH);

  if (!codes || codes.length === 0) return;

  // The video's intrinsic size vs displayed size
  const vidW = video.videoWidth;
  const vidH = video.videoHeight;
  if (!vidW || !vidH) return;

  // html5-qrcode uses object-fit: cover on the video — compute the mapping
  const vidAspect = vidW / vidH;
  const wrapAspect = wrapW / wrapH;

  let sx, sy, sw, sh;
  if (vidAspect > wrapAspect) {
    // Video is wider – cropped left/right
    sh = vidH;
    sw = vidH * wrapAspect;
    sx = (vidW - sw) / 2;
    sy = 0;
  } else {
    // Video is taller – cropped top/bottom
    sw = vidW;
    sh = vidW / wrapAspect;
    sx = 0;
    sy = (vidH - sh) / 2;
  }

  // Map video coords → wrapper coords
  const mapX = (vx) => ((vx - sx) / sw) * wrapW;
  const mapY = (vy) => ((vy - sy) / sh) * wrapH;

  for (const code of codes) {
    const isQR = QR_FORMAT_NAMES.has(code.format);
    const clr = isQR ? CLR_QR : CLR_BARCODE;
    const label = isQR ? "QR" : "BARCODE";

    // Update the badge in header
    updateBadge(isQR ? "qr" : "barcode");

    if (code.cornerPoints && code.cornerPoints.length >= 4) {
      // Draw using cornerPoints (allows for perspective/rotation)
      drawPolygon(ctx, code.cornerPoints, sx, sy, sw, sh, wrapW, wrapH, clr, label);
    } else if (code.boundingBox) {
      // Draw using boundingBox (axis-aligned rectangle)
      const bb = code.boundingBox;
      const x = mapX(bb.x);
      const y = mapY(bb.y);
      const w = (bb.width / sw) * wrapW;
      const h = (bb.height / sh) * wrapH;
      drawRect(ctx, x, y, w, h, clr, label);
    }
  }
}

function drawPolygon(ctx, points, sx, sy, sw, sh, wrapW, wrapH, clr, label) {
  const mapX = (vx) => ((vx - sx) / sw) * wrapW;
  const mapY = (vy) => ((vy - sy) / sh) * wrapH;

  const mapped = points.map(p => ({ x: mapX(p.x), y: mapY(p.y) }));

  // Glow effect
  ctx.shadowColor = clr.glow;
  ctx.shadowBlur = 15;

  // Fill
  ctx.beginPath();
  ctx.moveTo(mapped[0].x, mapped[0].y);
  for (let i = 1; i < mapped.length; i++) ctx.lineTo(mapped[i].x, mapped[i].y);
  ctx.closePath();
  ctx.fillStyle = clr.fill;
  ctx.fill();

  // Stroke
  ctx.lineWidth = 3;
  ctx.strokeStyle = clr.stroke;
  ctx.stroke();

  // Corner brackets
  ctx.shadowBlur = 0;
  const bracketLen = 14;
  const bracketW = 4;
  ctx.strokeStyle = clr.stroke;
  ctx.lineWidth = bracketW;
  ctx.lineCap = "round";

  for (let i = 0; i < mapped.length; i++) {
    const curr = mapped[i];
    const prev = mapped[(i - 1 + mapped.length) % mapped.length];
    const next = mapped[(i + 1) % mapped.length];

    // Direction toward prev
    const dp = normalise(prev.x - curr.x, prev.y - curr.y);
    // Direction toward next
    const dn = normalise(next.x - curr.x, next.y - curr.y);

    ctx.beginPath();
    ctx.moveTo(curr.x + dp.x * bracketLen, curr.y + dp.y * bracketLen);
    ctx.lineTo(curr.x, curr.y);
    ctx.lineTo(curr.x + dn.x * bracketLen, curr.y + dn.y * bracketLen);
    ctx.stroke();
  }

  // Label
  const cx = mapped.reduce((s, p) => s + p.x, 0) / mapped.length;
  const minY = Math.min(...mapped.map(p => p.y));
  drawLabel(ctx, label, cx, minY - 10, clr);
}

function drawRect(ctx, x, y, w, h, clr, label) {
  const pad = 8;
  const rx = x - pad;
  const ry = y - pad;
  const rw = w + pad * 2;
  const rh = h + pad * 2;
  const r = 8;

  // Glow
  ctx.shadowColor = clr.glow;
  ctx.shadowBlur = 15;

  // Fill
  ctx.fillStyle = clr.fill;
  roundRect(ctx, rx, ry, rw, rh, r);
  ctx.fill();

  // Stroke
  ctx.lineWidth = 3;
  ctx.strokeStyle = clr.stroke;
  roundRect(ctx, rx, ry, rw, rh, r);
  ctx.stroke();

  // Corner brackets
  ctx.shadowBlur = 0;
  const bl = 16;
  ctx.lineWidth = 4;
  ctx.lineCap = "round";
  ctx.strokeStyle = clr.stroke;

  // TL
  ctx.beginPath(); ctx.moveTo(rx, ry + bl); ctx.lineTo(rx, ry + r); ctx.arcTo(rx, ry, rx + r, ry, r); ctx.lineTo(rx + bl, ry); ctx.stroke();
  // TR
  ctx.beginPath(); ctx.moveTo(rx + rw - bl, ry); ctx.lineTo(rx + rw - r, ry); ctx.arcTo(rx + rw, ry, rx + rw, ry + r, r); ctx.lineTo(rx + rw, ry + bl); ctx.stroke();
  // BL
  ctx.beginPath(); ctx.moveTo(rx, ry + rh - bl); ctx.lineTo(rx, ry + rh - r); ctx.arcTo(rx, ry + rh, rx + r, ry + rh, r); ctx.lineTo(rx + bl, ry + rh); ctx.stroke();
  // BR
  ctx.beginPath(); ctx.moveTo(rx + rw, ry + rh - bl); ctx.lineTo(rx + rw, ry + rh - r); ctx.arcTo(rx + rw, ry + rh, rx + rw - r, ry + rh, r); ctx.lineTo(rx + rw - bl, ry + rh); ctx.stroke();

  // Label
  drawLabel(ctx, label, rx + rw / 2, ry - 10, clr);
}

function drawLabel(ctx, text, cx, y, clr) {
  ctx.shadowBlur = 0;
  ctx.font = "bold 11px system-ui, sans-serif";
  ctx.textAlign = "center";
  ctx.textBaseline = "bottom";

  const tm = ctx.measureText(text);
  const pw = 10, ph = 4;
  const bw = tm.width + pw * 2;
  const bh = 18;

  // Background pill
  ctx.fillStyle = "rgba(0,0,0,0.55)";
  roundRect(ctx, cx - bw / 2, y - bh, bw, bh, 6);
  ctx.fill();

  // Text
  ctx.fillStyle = clr.text;
  ctx.fillText(text, cx, y - ph);
}

/* ── Canvas Helpers ── */
function roundRect(ctx, x, y, w, h, r) {
  ctx.beginPath();
  ctx.moveTo(x + r, y);
  ctx.lineTo(x + w - r, y);
  ctx.arcTo(x + w, y, x + w, y + r, r);
  ctx.lineTo(x + w, y + h - r);
  ctx.arcTo(x + w, y + h, x + w - r, y + h, r);
  ctx.lineTo(x + r, y + h);
  ctx.arcTo(x, y + h, x, y + h - r, r);
  ctx.lineTo(x, y + r);
  ctx.arcTo(x, y, x + r, y, r);
  ctx.closePath();
}

function normalise(dx, dy) {
  const len = Math.sqrt(dx * dx + dy * dy) || 1;
  return { x: dx / len, y: dy / len };
}

function showFallbackGuide() {
  const guide = document.getElementById("fallbackGuide");
  if (guide) guide.style.display = "flex";
}

/* ══════════════════════════════════════════
   UI HELPERS
══════════════════════════════════════════ */
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
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:0.5rem;">
      <div class="history-code" style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${value}</div>
      <div style="display:flex;gap:5px;align-items:center;flex-shrink:0;">
        <span class="type-chip ${chipClass}">${chipLabel}</span>
        <span class="history-badge ${duplicate ? 'dup' : 'new'}">
          ${returnedAt ? "RETURNED" : duplicate ? `${scanCt + 1}×` : "NEW"}
        </span>
      </div>
    </div>
    <div class="history-meta">
      <span>🟢 First: ${formatDateTime(createdAt)}</span>
      ${returnedAt
      ? `<span>🟠 Returned: ${formatDateTime(returnedAt)}</span>`
      : `<span>🔄 Last: ${formatDateTime(lastScan)}</span>`}
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
