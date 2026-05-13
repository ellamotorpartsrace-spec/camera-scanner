/* =========================
   CONFIG
========================= */
const API_ENDPOINT = "api/scan/save.php";
const RESUME_DELAY = 1800;   // slightly longer for barcode stability
const BARCODE_DELAY = 300;  // small snap delay helps focus

/* =========================
   STATE
========================= */
let barcodeScanner = null;
let scanning = false;
let lastValue = null;
let snapTimer = null;

/* =========================
   INIT
========================= */
window.addEventListener("load", startScanner);

/* =========================
   START SCANNER
========================= */
async function startScanner() {
    const status = document.getElementById("status-pill");
    status.innerText = "Starting camera…";

    barcodeScanner = new Html5Qrcode("reader");

    try {
        await barcodeScanner.start(
            { facingMode: "environment" },
            {
                fps: 20,
                qrbox: { width: 300, height: 120 },
                disableFlip: true,
                formatsToSupport: [
                    Html5QrcodeSupportedFormats.CODE_128,
                    Html5QrcodeSupportedFormats.EAN_13,
                    Html5QrcodeSupportedFormats.EAN_8,
                    Html5QrcodeSupportedFormats.UPC_A,
                    Html5QrcodeSupportedFormats.UPC_E
                ]
            },
            onScanDetected
        );

        scanning = true;
        status.innerText = "Ready to scan barcode";

    } catch (err) {
        console.error(err);
        status.innerText = "Camera access denied";
    }
}

/* =========================
   SCAN DETECTED (WITH SNAP)
========================= */
function onScanDetected(decodedText) {
    if (!scanning) return;
    if (decodedText === lastValue) return;

    // Debounce / snap delay for barcodes
    scanning = false;
    snapTimer = setTimeout(() => {
        handleBarcode(decodedText);
    }, BARCODE_DELAY);
}

/* =========================
   HANDLE BARCODE
========================= */
async function handleBarcode(value) {
    clearTimeout(snapTimer);
    lastValue = value;

    updateStatus("Processing…");

    try {
        const courierSelect = document.getElementById("courierSelect");
        const courier = courierSelect ? courierSelect.value : "";

        const platformSelect = document.getElementById("platformSelect");
        const platform = platformSelect ? platformSelect.value : "";

        const sizeSelect = document.getElementById("parcelSizeSelect");
        const parcelSize = sizeSelect ? sizeSelect.value : "POUCH";

        const res = await fetch(API_ENDPOINT, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                code: value,
                type: "BARCODE",
                courier: courier,
                platform: platform,
                parcel_size: parcelSize
            })
        });

        const data = await res.json();

        if (data.duplicate) {
            flash("duplicate");
            Sound.duplicate();
            updateStatus("Duplicate ignored");
        } else {
            flash("success");
            Sound.success();
            updateStatus("Saved");
        }

        pushHistory(value, data.duplicate, data.data);

    } catch (err) {
        console.error(err);
        flash("error");
        Sound.error();
        updateStatus("Save failed");
    }

    setTimeout(resumeScanner, RESUME_DELAY);
}

/* =========================
   RESUME
========================= */
function resumeScanner() {
    scanning = true;
    updateStatus("Ready to scan barcode");
}

/* =========================
   UI HELPERS
========================= */
/* =========================
   UI HELPERS
========================= */
function updateStatus(text) {
    const el = document.getElementById("status-pill");
    if (el) el.innerText = text;
}

function formatDateTime(ts) {
    if (!ts) return "-";
    const d = new Date(ts);
    if (isNaN(d)) return "-";
    return d.toLocaleString([], {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

/* =========================
   HISTORY
========================= */
function pushHistory(value, duplicate = false, data = {}) {
    const list = document.getElementById("historyList");
    if (!list) return;

    // Remove placeholder if present
    const placeholder = list.querySelector(".placeholder");
    if (placeholder) placeholder.remove();

    const li = document.createElement("li");
    li.className = `history-item ${duplicate ? 'duplicate' : 'new'}`;

    const scanCount = data?.update_count || 0;
    const createdAt = data?.created_at || null;
    const lastScanned = data?.last_scanned || data?.timestamp || null;

    li.innerHTML = `
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:0.5rem;">
            <div class="history-code">${value}</div>
            <span class="history-badge ${duplicate ? 'dup' : 'new'}">
                ${duplicate ? `${scanCount + 1}x` : 'NEW'}
            </span>
        </div>
        <div class="history-meta">
            <span>🟢 First: ${formatDateTime(createdAt)}</span>
            <span>🔄 Last: ${formatDateTime(lastScanned)}</span>
        </div>
    `;

    list.prepend(li);

    // Limit to last 50 entries
    while (list.children.length > 50) {
        list.removeChild(list.lastChild);
    }
}

/* =========================
   FLASH FEEDBACK
========================= */
function flash(type) {
    const el = document.getElementById("flash");
    if (!el) return;

    el.style.background =
        type === "success"
            ? "rgba(34,197,94,.35)"
            : type === "duplicate"
            ? "rgba(245,158,11,.4)"
            : "rgba(239,68,68,.4)";

    el.classList.add("active");
    setTimeout(() => el.classList.remove("active"), 150);
}
