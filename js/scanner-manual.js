/* =========================
   CONFIG
========================= */
const API_ENDPOINT = "api/scan/save.php";
const AUTO_SUBMIT_DELAY = 500;
const SESSION_KEY = "batchData";

/* =========================
   STATE
========================= */
let manualTimer = null;
let isSubmitting = false;

// Scan counters
let scanCount = 0; // total scans
let successScanCount = 0; // non-duplicate scans
let pouchCount = 0;
let bulkyCount = 0;

// Batch counters
let currentBatchNumber = 1;
let currentBatchCount = 0;
let batchHistory = [];

// Session storage for batch codes
let batchCodes = {}; // { batchNumber: [{ code, courier, timestamp, ... }] }

/* =========================
   INIT
========================= */
window.addEventListener("load", initManual);

/* =========================
   INITIALIZE
========================= */
function initManual() {
  const input = document.getElementById("manualScanInput");
  const submitBtn = document.getElementById("manualSubmitBtn");
  const newBatchBtn = document.getElementById("newBatchBtn");
  const closeBatchDetailsBtn = document.getElementById("closeBatchDetails");

  if (!input || !submitBtn) return;

  // Mode toggle listener
  const modeToggle = document.getElementById("returnModeToggle");
  const modeText = document.getElementById("modeText");
  if (modeToggle && modeText) {
    modeToggle.addEventListener("change", () => {
      modeText.innerText = modeToggle.checked ? "Return Mode" : "Normal Mode";
      input.focus();
    });
  }

  // Load session data
  loadSessionData();

  input.focus();

  // Auto-submit after typing / scan
  input.addEventListener("input", onManualInput);

  // Enter key submit
  input.addEventListener("keydown", (e) => {
    if (e.key === "Enter") {
      e.preventDefault();
      submitManual();
    }
  });

  // Button submit
  submitBtn.addEventListener("click", submitManual);

  // New batch button
  if (newBatchBtn) {
    newBatchBtn.addEventListener("click", startNewBatch);
  }

  // Close batch details button
  if (closeBatchDetailsBtn) {
    closeBatchDetailsBtn.addEventListener("click", closeBatchDetails);
  }

  // Clear session button
  const clearSessionBtn = document.getElementById("clearSessionBtn");
  if (clearSessionBtn) {
    clearSessionBtn.addEventListener("click", clearSession);
  }

  // Initialize counters
  updateScanCount();
  updateSuccessScanCount();
  updatePouchCount();
  updateBulkyCount();
  updateBatchUI();

  // Restore batch history UI from session
  restoreBatchHistoryUI();
}

/* =========================
   SESSION STORAGE
========================= */
function loadSessionData() {
  try {
    const data = sessionStorage.getItem(SESSION_KEY);
    if (data) {
      const parsed = JSON.parse(data);
      batchCodes = parsed.batchCodes || {};
      batchHistory = parsed.batchHistory || [];
      currentBatchNumber = parsed.currentBatchNumber || 1;
      currentBatchCount = parsed.currentBatchCount || 0;
      scanCount = parsed.scanCount || 0;
      successScanCount = parsed.successScanCount || 0;
      pouchCount = parsed.pouchCount || 0;
      bulkyCount = parsed.bulkyCount || 0;
    }
  } catch (e) {
    console.error("Failed to load session data:", e);
  }
}

function saveSessionData() {
  try {
    const data = {
      batchCodes,
      batchHistory,
      currentBatchNumber,
      currentBatchCount,
      scanCount,
      successScanCount,
      pouchCount,
      bulkyCount,
    };
    sessionStorage.setItem(SESSION_KEY, JSON.stringify(data));
  } catch (e) {
    console.error("Failed to save session data:", e);
  }
}

function restoreBatchHistoryUI() {
  const list = document.getElementById("batchList");
  if (!list || batchHistory.length === 0) return;

  // Clear placeholder
  const placeholder = list.querySelector(".batch-placeholder");
  if (placeholder) placeholder.remove();

  // Add all batch history items
  batchHistory.forEach((item) => {
    const li = document.createElement("li");
    li.className = "batch-item clickable";
    li.dataset.batchNumber = item.batch;
    li.innerHTML = `
            <span class="batch-name">Batch #${item.batch}</span>
            <span class="batch-count">${item.count} codes</span>
        `;
    li.addEventListener("click", () => showBatchDetails(item.batch));
    list.appendChild(li);
  });
}

/* =========================
   INPUT HANDLER
========================= */
function onManualInput(e) {
  clearManualTimer();

  const value = e.target.value.trim();
  if (!value) return;

  // Only auto-submit if length is at least 7
  if (value.length < 10) return;

  manualTimer = setTimeout(submitManual, AUTO_SUBMIT_DELAY);
}

/* =========================
   SUBMIT
========================= */
async function submitManual() {
  clearManualTimer();
  if (isSubmitting) return;

  const input = document.getElementById("manualScanInput");
  if (!input) return;

  const value = input.value.trim();
  if (!value) return;

  isSubmitting = true;
  updateStatus("Processing…");

  try {
    const courierSelect = document.getElementById("courierSelect");
    const courier = courierSelect ? courierSelect.value : "";

    const platformSelect = document.getElementById("platformSelect");
    const platform = platformSelect ? platformSelect.value : "";

    const copyrightHash = await getCopyrightHash();

    const res = await fetch(API_ENDPOINT, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-Copyright-Hash": copyrightHash || "",
      },
      body: JSON.stringify({
        code: value,
        type: "MANUAL",
        courier: courier,
        parcel_size: document.getElementById('parcelSizeSelect').value,
        platform: platform,
        is_return: document.getElementById('returnModeToggle')?.checked || false
      }),
    });

    if (!res.ok) {
      throw new Error(`HTTP ${res.status}`);
    }

    const data = await res.json();

    // Count every scan attempt
    scanCount++;
    updateScanCount();

    if (data.duplicate) {
      const scanCount = data.data?.update_count || 0;
      flash("duplicate");
      Sound.duplicate();
      updateStatus(`Duplicate - scanned ${scanCount + 1} times`);
    } else {
      // Count only successful scans
      successScanCount++;
      updateSuccessScanCount();

      const parcelSize = document.getElementById('parcelSizeSelect').value;
      if (parcelSize === 'BULKY') {
        bulkyCount++;
        updateBulkyCount();
      } else {
        pouchCount++;
        updatePouchCount();
      }

      // Add code to current batch
      addCodeToBatch(value, courier, platform, data.data);

      // Increment batch counter
      incrementBatchCount();

      if (data.is_return) {
        flash("warning"); // Orange for return
        Sound.success(); // Or a different sound? For now keep success
        updateStatus("Returned Saved");
      } else {
        flash("success");
        Sound.success();
        updateStatus("Saved");
      }
    }

    pushHistory(value, data.duplicate, data.data);

    // Clear input ONLY after processing
    input.value = "";
    input.focus();

    // Save session
    saveSessionData();
  } catch (err) {
    console.error(err);
    flash("error");
    Sound.error();
    updateStatus("Save failed");
  } finally {
    isSubmitting = false;

    setTimeout(() => {
      updateStatus("Waiting for input");
    }, 300);
  }
}

/* =========================
   BATCH CODE TRACKING
========================= */
function addCodeToBatch(code, courier, platform, apiData) {
  if (!batchCodes[currentBatchNumber]) {
    batchCodes[currentBatchNumber] = [];
  }

  batchCodes[currentBatchNumber].push({
    code: code,
    courier: courier || "Unknown",
    platform: platform || "",
    parcelSize: apiData?.parcel_size || "POUCH",
    timestamp: new Date().toISOString(),
    createdAt: apiData?.created_at || null,
    lastScanned: apiData?.last_scanned || null,
  });
}

/* =========================
   BATCH DETAILS VIEW
========================= */
function showBatchDetails(batchNumber) {
  const defaultContent = document.getElementById("defaultPanelContent");
  const detailsContent = document.getElementById("batchDetailsContent");
  const titleEl = document.getElementById("batchDetailTitle");
  const countEl = document.getElementById("batchDetailCount");
  const listEl = document.getElementById("batchDetailsList");
  const panel = document.getElementById("batchDetailsPanel");

  if (!defaultContent || !detailsContent) return;

  const codes = batchCodes[batchNumber] || [];

  // Update header
  titleEl.textContent = `Batch #${batchNumber}`;
  countEl.textContent = `${codes.length} codes`;

  // Clear and populate list
  listEl.innerHTML = "";

  if (codes.length === 0) {
    listEl.innerHTML = '<li class="placeholder">No codes in this batch</li>';
  } else {
    codes.forEach((item, index) => {
      const li = document.createElement("li");
      li.className = "batch-code-item";
      li.innerHTML = `
                <div class="code-number">${index + 1}</div>
                <div class="code-details">
                    <div class="code-value">${item.code}</div>
                    <div class="code-meta">
                        <span class="courier-tag">${item.courier}</span>
                        <span class="size-tag ${item.parcelSize === 'BULKY' ? 'bulky' : 'pouch'}">${item.parcelSize || 'POUCH'}</span>
                        ${item.platform ? `<span class="platform-tag">${item.platform}</span>` : ''}
                        <span class="time-tag">${formatTime(item.timestamp)}</span>
                    </div>
                </div>
            `;
      listEl.appendChild(li);
    });
  }

  // Switch views
  defaultContent.style.display = "none";
  detailsContent.style.display = "flex";
  panel.classList.add("active");
}

function closeBatchDetails() {
  const defaultContent = document.getElementById("defaultPanelContent");
  const detailsContent = document.getElementById("batchDetailsContent");
  const panel = document.getElementById("batchDetailsPanel");

  if (!defaultContent || !detailsContent) return;

  // Switch back
  defaultContent.style.display = "flex";
  detailsContent.style.display = "none";
  panel.classList.remove("active");
}

/* =========================
   UTILITIES
========================= */
function clearManualTimer() {
  if (manualTimer) {
    clearTimeout(manualTimer);
    manualTimer = null;
  }
}

/* =========================
   UI HELPERS
========================= */
function updateStatus(text) {
  const el = document.getElementById("status-pill");
  if (el) el.innerText = text;
}

function updateScanCount() {
  const el = document.getElementById("scan-count");
  if (el) el.innerText = scanCount;
}

function updateSuccessScanCount() {
  const el = document.getElementById("success-scan-count");
  if (el) el.innerText = successScanCount;
}

function updatePouchCount() {
  const el = document.getElementById("pouch-count");
  if (el) el.innerText = pouchCount;
}

function updateBulkyCount() {
  const el = document.getElementById("bulky-count");
  if (el) el.innerText = bulkyCount;
}

/* =========================
   HISTORY
========================= */
function formatTime(ts) {
  if (!ts) return "-";
  const d = new Date(ts);
  return isNaN(d)
    ? "-"
    : d.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" });
}

function formatDateTime(ts) {
  if (!ts) return "-";
  const d = new Date(ts);
  if (isNaN(d)) return "-";
  return d.toLocaleString([], {
    month: "short",
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
}

function pushHistory(value, duplicate = false, data = {}) {
  const list = document.getElementById("historyList");
  if (!list) return;

  // Remove placeholder
  const placeholder = list.querySelector(".placeholder");
  if (placeholder) placeholder.remove();

  const li = document.createElement("li");
  li.className = `history-item ${duplicate ? "duplicate" : "new"}`;

  const scanCount = data?.update_count || 0;
  const createdAt = data?.created_at || null;
  const lastScanned = data?.last_scanned || data?.timestamp || null;
  const returnedAt = data?.returned_at || null;

  li.innerHTML = `
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:0.5rem;">
            <div class="history-code">${value}</div>
            <span class="history-badge ${returnedAt ? "dup" : (duplicate ? "dup" : "new")}">
                ${returnedAt ? "RETURNED" : (duplicate ? `${scanCount + 1}x` : "NEW")}
            </span>
        </div>
        <div class="history-meta" style="flex-direction:column; gap: 4px;">
            <span>🟢 Scanned: ${formatDateTime(createdAt)}</span>
            ${returnedAt ? `<span>🟠 Returned: ${formatDateTime(returnedAt)}</span>` : `<span>🔄 Last: ${formatDateTime(lastScanned)}</span>`}
        </div>
    `;

  list.prepend(li);

  // Keep last 50 entries
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

/* =========================
   OPTIONAL: RESET COUNTERS
========================= */
function resetScanCount() {
  scanCount = 0;
  successScanCount = 0;
  updateScanCount();
  updateSuccessScanCount();
}

/* =========================
   CLEAR SESSION
========================= */
function clearSession() {
  if (
    !confirm(
      "Are you sure you want to clear all session data? This will reset all counters, batch history, and recent scans.",
    )
  ) {
    return;
  }

  // Clear session storage
  sessionStorage.removeItem(SESSION_KEY);

  // Reset all state variables
  scanCount = 0;
  successScanCount = 0;
  pouchCount = 0;
  bulkyCount = 0;
  currentBatchNumber = 1;
  currentBatchCount = 0;
  batchHistory = [];
  batchCodes = {};

  // Update UI counters
  updateScanCount();
  updateSuccessScanCount();
  updatePouchCount();
  updateBulkyCount();
  updateBatchUI();

  // Reset history list
  const historyList = document.getElementById("historyList");
  if (historyList) {
    historyList.innerHTML = '<li class="placeholder">No scans yet</li>';
  }

  // Reset batch list
  const batchList = document.getElementById("batchList");
  if (batchList) {
    batchList.innerHTML =
      '<li class="batch-placeholder">No completed batches yet</li>';
  }

  // Close batch details if open
  closeBatchDetails();

  // Focus input
  const input = document.getElementById("manualScanInput");
  if (input) {
    input.value = "";
    input.focus();
  }

  // Show confirmation
  updateStatus("Session cleared");
  flash("success");
  Sound.success();

  setTimeout(() => {
    updateStatus("Waiting for input");
  }, 1500);
}

/* =========================
   BATCH COUNTER
========================= */
function incrementBatchCount() {
  currentBatchCount++;
  updateBatchUI();
}

function startNewBatch() {
  // Only create a batch entry if there are codes saved
  if (currentBatchCount > 0) {
    addBatchToHistory(currentBatchNumber, currentBatchCount);
  }

  // Start new batch
  currentBatchNumber++;
  currentBatchCount = 0;

  // Initialize empty array for new batch
  batchCodes[currentBatchNumber] = [];

  updateBatchUI();
  saveSessionData();

  // Focus back on input
  const input = document.getElementById("manualScanInput");
  if (input) input.focus();
}

function addBatchToHistory(batchNum, count) {
  batchHistory.unshift({ batch: batchNum, count: count });

  const list = document.getElementById("batchList");
  if (!list) return;

  // Remove placeholder
  const placeholder = list.querySelector(".batch-placeholder");
  if (placeholder) placeholder.remove();

  const li = document.createElement("li");
  li.className = "batch-item clickable";
  li.dataset.batchNumber = batchNum;
  li.innerHTML = `
        <span class="batch-name">Batch #${batchNum}</span>
        <span class="batch-count">${count} codes</span>
    `;

  // Add click handler
  li.addEventListener("click", () => showBatchDetails(batchNum));

  list.prepend(li);

  while (list.children.length > 50) {
    list.removeChild(list.lastChild);
  }

  saveSessionData();
}

function updateBatchUI() {
  const batchNumberEl = document.getElementById("currentBatchNumber");
  const batchCountEl = document.getElementById("batchCount");

  if (batchNumberEl) batchNumberEl.innerText = currentBatchNumber;
  if (batchCountEl) batchCountEl.innerText = currentBatchCount;
}
