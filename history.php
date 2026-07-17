<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
require_once __DIR__ . '/api/core/session.php';
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header("Location: index.php");
    exit();
}
$config = require __DIR__ . '/api/core/config.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Scan History</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="stylesheet" href="css/scanner.css">

    <link rel="stylesheet" href="css/history.css">
</head>

<body>

    <div class="history-header">
        <h2 style="margin:0;">
            📋 Scan History
            <span id="totalCount"></span>
        </h2>
        <a href="index.php" class="pg-btn">← Back</a>
    </div>

    <div class="filter-bar">
        <input type="text" id="codeSearch" placeholder="🔍 Search Code..." style="min-width:180px;">
        <input type="date" id="fromDate">
        <input type="date" id="toDate">
        <select id="courierFilter">
            <option value="">All Couriers</option>
            <?php foreach ($config['lists']['couriers'] as $courier): ?>
                <option value="<?php echo htmlspecialchars($courier); ?>"><?php echo htmlspecialchars($courier); ?></option>
            <?php endforeach; ?>
        </select>
        <select id="sizeFilter">
            <option value="">All Sizes</option>
            <option value="POUCH">Pouch</option>
            <option value="BULKY">Bulky</option>
        </select>
        <select id="platformFilter">
            <option value="">All Platforms</option>
            <?php foreach ($config['lists']['platforms'] as $platform): ?>
                <option value="<?php echo htmlspecialchars($platform); ?>"><?php echo htmlspecialchars($platform); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <select id="batchFilter">
            <option value="">All Batches</option>
        </select>
        <select id="typeFilter">
            <option value="">All Scans</option>
            <option value="scanned">First Scan</option>
            <option value="returned">Return parcels</option>
        </select>
        <button onclick="applyFilter()">Apply</button>
        <button onclick="preset('today')">Today</button>
        <button onclick="preset('yesterday')">Yesterday</button>
        <button onclick="preset('week')">This Week</button>
        <div style="flex:1"></div>
        <button id="batchDeleteBtn" class="pg-btn" style="display:none; background: #ef4444; color: white; border-color: #ef4444;" onclick="deleteSelected()">🗑️ Delete Selected</button>
        <button class="pg-btn" onclick="openSummaryReport()" style="background:#a855f7; border-color:#a855f7; color:white">📊 Summary Report</button>
        <a href="#" class="pg-btn" onclick="exportCSV()">Export CSV</a>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th style="width:40px;"><input type="checkbox" id="selectAll" onclick="toggleSelectAll(this)"></th>
                    <th>Code</th>
                    <th>Type</th>
                    <th>Courier</th>
                    <th>Size</th>
                    <th>Platform</th>
                    <th>GTIN</th>
                    <th>Batch</th>
                    <th>First Scanned</th>
                    <th>Last Scanned</th>
                    <th>Returned At</th>
                    <th>Freq</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody id="tableBody">
                <tr>
                    <td colspan="11" style="text-align:center;padding:40px;">
                        Loading…
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="pagination" id="pagination"></div>

    <!-- Summary Report Modal -->
    <div id="summaryModal" class="modal" tabindex="-1" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:1000; overflow-y:auto;">
        <div style="background:var(--bg); margin: 5% auto; padding: 24px; border-radius: 12px; width: 90%; max-width: 900px; box-shadow: 0 10px 30px rgba(0,0,0,0.5);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 24px;">
                <h2 style="margin:0; font-weight:800;">📊 Summary Report <span id="summaryModalDate" style="font-size:1rem; color:var(--muted); font-weight:600; margin-left:12px;"></span></h2>
            </div>
            
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 24px;">
                <div style="background:var(--card); padding:16px; border-radius:8px; border-top: 4px solid #22c55e; border-left:1px solid var(--border); border-right:1px solid var(--border); border-bottom:1px solid var(--border); text-align:center;">
                    <div id="smTotalScans" style="font-size:2rem; font-weight:800; color:#22c55e;">0</div>
                    <div style="font-size:0.8rem; font-weight:700; color:var(--muted); text-transform:uppercase;">Total Scans</div>
                </div>
                <div style="background:var(--card); padding:16px; border-radius:8px; border-top: 4px solid #3b82f6; border-left:1px solid var(--border); border-right:1px solid var(--border); border-bottom:1px solid var(--border); text-align:center;">
                    <div id="smTotalPouch" style="font-size:2rem; font-weight:800; color:#3b82f6;">0</div>
                    <div style="font-size:0.8rem; font-weight:700; color:var(--muted); text-transform:uppercase;">Total Pouches</div>
                </div>
                <div style="background:var(--card); padding:16px; border-radius:8px; border-top: 4px solid #a78bfa; border-left:1px solid var(--border); border-right:1px solid var(--border); border-bottom:1px solid var(--border); text-align:center;">
                    <div id="smTotalBulky" style="font-size:2rem; font-weight:800; color:#a78bfa;">0</div>
                    <div style="font-size:0.8rem; font-weight:700; color:var(--muted); text-transform:uppercase;">Total Bulky</div>
                </div>
                <div style="background:var(--card); padding:16px; border-radius:8px; border-top: 4px solid #c084fc; border-left:1px solid var(--border); border-right:1px solid var(--border); border-bottom:1px solid var(--border); text-align:center;">
                    <div id="smTotalBatches" style="font-size:2rem; font-weight:800; color:#c084fc;">0</div>
                    <div style="font-size:0.8rem; font-weight:700; color:var(--muted); text-transform:uppercase;">Total Batches</div>
                </div>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Batch</th>
                            <th>Total Scans</th>
                            <th>Pouches</th>
                            <th>Bulky</th>
                        </tr>
                    </thead>
                    <tbody id="summaryTableBody">
                    </tbody>
                </table>
            </div>

            <div style="display:flex; justify-content:flex-end; margin-top:24px;">
                <button class="pg-btn" onclick="closeSummaryReport()">Close</button>
            </div>
        </div>
    </div>

    <script>
        /* =========================
   CONFIG
========================= */
        const API_URL = "api/scan/list.php";
        const LIMIT = 100;
        const REFRESH_INTERVAL = 10000;

        /* =========================
           STATE
        ========================= */
        let state = {
            page: 1,
            totalPages: 1,
            from: new Date().toISOString().split("T")[0],
            to: new Date().toISOString().split("T")[0],
            courier: "",
            size: "",
            platform: "",
            batch: "",
            type: "",
            search: "",
            autoRefresh: true
        };

        /* =========================
           INIT
        ========================= */
        const tbody = document.getElementById("tableBody");
        const countEl = document.getElementById("totalCount");
        const fromEl = document.getElementById("fromDate");
        const toEl = document.getElementById("toDate");
        const courierEl = document.getElementById("courierFilter");
        const sizeEl = document.getElementById("sizeFilter");
        const platformEl = document.getElementById("platformFilter");
        const batchEl = document.getElementById("batchFilter");
        const typeEl = document.getElementById("typeFilter");
        const searchEl = document.getElementById("codeSearch");

        fromEl.value = state.from;
        toEl.value = state.to;
        courierEl.value = state.courier;
        sizeEl.value = state.size;
        platformEl.value = state.platform;
        batchEl.value = state.batch;
        typeEl.value = state.type;
        searchEl.value = state.search;

        // Debounced search
        let searchTimeout;
        searchEl.addEventListener("input", () => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                state.search = searchEl.value.trim();
                state.page = 1;
                loadData();
            }, 300);
        });

        document.addEventListener("visibilitychange", () => {
            state.autoRefresh = !document.hidden;
        });

        /* =========================
           LOAD DATA
        ========================= */
        async function fetchBatchesDropdown() {
            try {
                const batchEl = document.getElementById("batchFilter");
                const currentBatch = state.batch;
                
                const res = await fetch(`api/scan/batch_summary.php?from=${state.from}&to=${state.to}`);
                const data = await res.json();
                
                let html = '<option value="">All Batches</option>';
                if (data.status === "success" && data.data) {
                    data.data.forEach(b => {
                        html += `<option value="${b.id}">Batch ${b.id} (${b.count})</option>`;
                    });
                }
                batchEl.innerHTML = html;
                batchEl.value = currentBatch; // Restore selection if valid
            } catch (err) {
                console.error("Failed to fetch batches dropdown", err);
            }
        }

        async function loadData() {
            try {
                let url = `${API_URL}?page=${state.page}&limit=${LIMIT}&from=${state.from}&to=${state.to}`;
                if (state.courier) {
                    url += `&courier=${encodeURIComponent(state.courier)}`;
                }
                if (state.size) {
                    url += `&size=${encodeURIComponent(state.size)}`;
                }
                if (state.platform) {
                    url += `&platform=${encodeURIComponent(state.platform)}`;
                }
                if (state.type) {
                    url += `&type=${encodeURIComponent(state.type)}`;
                }
                if (state.batch) {
                    url += `&batch=${encodeURIComponent(state.batch)}`;
                }
                if (state.search) {
                    url += `&search=${encodeURIComponent(state.search)}`;
                }
                const res = await fetch(url);

                if (!res.ok) {
                    throw new Error(`HTTP ${res.status}`);
                }

                const data = await res.json();

                if (!Array.isArray(data.rows)) {
                    throw new Error("Invalid API response");
                }

                renderTable(data.rows);
                renderPagination(data.totalPages);

                const unique = data.totalRows ?? 0;
                const scans = data.totalScans ?? 0;

                countEl.innerHTML = `
            <span class="count-pill">
                ${scans} Scans · ${unique} Codes
            </span>
        `;

            } catch (err) {
                console.error(err);
                tbody.innerHTML = `
            <tr>
                <td colspan="11" style="text-align:center;padding:40px;">
                    Failed to load data
                </td>
            </tr>
        `;
                countEl.innerHTML = `
            <span class="count-pill" style="background:#ef4444">
                Error
            </span>
        `;
            }
        }

        /* =========================
           RENDER TABLE
        ========================= */
        function formatTime(ts) {
            if (!ts || ts === "0000-00-00 00:00:00") return "-";
            const d = new Date(ts);
            return isNaN(d) ? "-" : d.toLocaleString();
        }

        function highlight(text, term) {
            if (!term) return text;
            const regex = new RegExp(`(${term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
            return text.replace(regex, '<mark class="p-0 bg-warning">$1</mark>');
        }
        
        function getTrackingLink(courier, platform, code) {
            const c = courier || "";
            const p = platform || "";
            
            if (c.includes("Shopee") || p === "Shopee") {
                // By omitting 'code=' the SPX site will just insert the tracking number directly into the search bar
                return `https://spx.ph/track?${code}`;
            }
            if (c.includes("JNT") || c.includes("J&T")) {
                return `https://www.jtexpress.ph/index/query/gzquery.html?bills=${code}`;
            }
            if (c.includes("Lazada") || p === "Lazada") {
                return `https://tracker.lel.asia/tracker?trackingNumber=${code}`;
            }
            if (c.includes("Flash")) {
                return `https://www.flashexpress.ph/tools/tracking/?se=${code}`;
            }
            if (c.includes("LBC")) {
                return `https://www.lbcexpress.com/track/`; // LBC usually requires manual input
            }
            // Fallback for others
            return `https://www.google.com/search?q=track+package+${code}`;
        }

        function renderTable(rows) {
            if (!rows.length) {
                tbody.innerHTML = `
            <tr>
                <td colspan="13" style="text-align:center;padding:40px;">
                    No records found
                </td>
            </tr>
        `;
                document.getElementById('selectAll').checked = false;
                updateDeleteBtn();
                return;
            }

            tbody.innerHTML = rows.map(r => {
                const dup = r.update_count > 0;
                
                // Status Badge Logic
                let statusBadge = "";
                if (r.returned_at) {
                    statusBadge = `<span class="badge" style="background:rgba(239, 68, 68, 0.15);color:#ef4444">RETURNED</span>`;
                } else if (dup) {
                    statusBadge = `<span class="badge" style="background:rgba(245, 158, 11, 0.15);color:#f59e0b">RESCAN</span>`;
                } else {
                    statusBadge = `<span class="badge" style="background:rgba(34, 197, 94, 0.15);color:#22c55e">UNIQUE</span>`;
                }

                // Type Badge Logic
                let typeBadge = "";
                if (r.code_type === 'QR') {
                    typeBadge = `<span class="badge" style="background:rgba(34, 197, 94, 0.15);color:#22c55e">QR</span>`;
                } else {
                    typeBadge = `<span class="badge" style="background:rgba(59, 130, 246, 0.15);color:#3b82f6">BARCODE</span>`;
                }

                const trackUrl = getTrackingLink(r.courier, r.platform, r.code_value);

                return `
            <tr>
                <td style="text-align:center;">
                    <input type="checkbox" class="row-checkbox" value="${r.code_value}" onclick="updateDeleteBtn()">
                </td>
                <td style="font-family:monospace;color:#38bdf8">
                    <a href="${trackUrl}" target="_blank" style="color:#38bdf8; text-decoration:none;" title="Track Package">
                        ${highlight(r.code_value, state.search)}
                    </a>
                </td>
                <td>${typeBadge}</td>
                <td><span class="courier-badge">${r.courier || "-"}</span></td>
                <td>
                    <span class="badge" style="background:${r.parcel_size === 'BULKY' ? 'rgba(139, 92, 246, 0.15)' : 'rgba(59, 130, 246, 0.15)'};color:${r.parcel_size === 'BULKY' ? '#a78bfa' : '#3b82f6'}">
                        ${r.parcel_size || "POUCH"}
                    </span>
                </td>
                <td>
                    ${r.platform ? `<span class="badge" style="background:${r.platform === 'Lazada' ? 'rgba(0,147,255,0.15)' : r.platform === 'TikTok' ? 'rgba(0,0,0,0.15)' : 'rgba(238,77,45,0.15)'};color:${r.platform === 'Lazada' ? '#0093ff' : r.platform === 'TikTok' ? '#9ca3af' : '#ee4d2d'}">${r.platform}</span>` : '<span style="color:#64748b">-</span>'}
                </td>
                <td>${r.gs1_gtin || "-"}</td>
                <td>
                    ${r.gs1_batch ? `<span class="badge" style="background:rgba(192, 132, 252, 0.15); color:#d8b4fe; font-family:monospace; font-size:0.85rem; border: 1px solid rgba(192, 132, 252, 0.3); box-shadow: 0 0 5px rgba(192, 132, 252, 0.2);">${r.gs1_batch}</span>` : "-"}
                </td>
                <td>${formatTime(r.created_at)}</td>
                <td>${formatTime(r.scanned_at)}</td>
                <td>${formatTime(r.returned_at)}</td>
                <td style="text-align:center">
                    ${r.update_count + 1}
                </td>
                <td>${statusBadge}</td>
            </tr>
        `;
            }).join("");
            
            // Reset Select All state after re-render
            document.getElementById('selectAll').checked = false;
            updateDeleteBtn();
        }

        /* =========================
           PAGINATION
        ========================= */
        function renderPagination(total) {
            state.totalPages = total || 1;

            document.getElementById("pagination").innerHTML = `
        <button class="pg-btn" onclick="goPage(1)" ${state.page === 1 ? "disabled" : ""}>«</button>
        <button class="pg-btn" onclick="goPage(${state.page - 1})" ${state.page === 1 ? "disabled" : ""}>‹</button>
        <span style="padding:6px 10px">
            Page ${state.page} / ${state.totalPages}
        </span>
        <button class="pg-btn" onclick="goPage(${state.page + 1})" ${state.page >= state.totalPages ? "disabled" : ""}>›</button>
        <button class="pg-btn" onclick="goPage(${state.totalPages})" ${state.page >= state.totalPages ? "disabled" : ""}>»</button>
    `;
        }

        function goPage(p) {
            if (p < 1 || p > state.totalPages) return;
            state.page = p;
            loadData();
            window.scrollTo({
                top: 0,
                behavior: "smooth"
            });
        }

        /* =========================
           FILTERS
        ========================= */
        function applyFilter() {
            const from = fromEl.value;
            const to = toEl.value;
            const courier = courierEl.value;
            const size = sizeEl.value;
            const platform = platformEl.value;
            const batch = document.getElementById("batchFilter").value;
            const type = typeEl.value;
            const search = searchEl.value.trim();

            if (from && to && from > to) {
                alert("From date cannot be after To date");
                return;
            }

            // Only fetch batches if date changed
            if (state.from !== from || state.to !== to) {
                state.from = from;
                state.to = to;
                fetchBatchesDropdown();
            }

            state.from = from;
            state.to = to;
            state.courier = courier;
            state.size = size;
            state.platform = platform;
            state.batch = batch;
            state.type = type;
            state.search = search;
            state.page = 1;
            loadData();
        }

        function preset(type) {
            const today = new Date();
            const toISO = d => d.toISOString().split("T")[0];

            if (type === "today") {
                const d = toISO(today);
                state.from = d;
                state.to = d;
            } else if (type === "yesterday") {
                const y = new Date(today);
                y.setDate(today.getDate() - 1);
                const d = toISO(y);
                state.from = d;
                state.to = d;
            } else if (type === "week") {
                const start = new Date(today);
                const day = today.getDay() || 7;
                start.setDate(today.getDate() - day + 1);
                state.from = toISO(start);
                state.to = toISO(today);
            }

            fromEl.value = state.from;
            toEl.value = state.to;
            courierEl.value = state.courier;
            sizeEl.value = state.size;
            platformEl.value = state.platform;
            state.page = 1;
            
            fetchBatchesDropdown();
            loadData();
        }

        /* =========================
           START
        ========================= */
        fetchBatchesDropdown();
        loadData();

        setInterval(() => {
            if (state.autoRefresh) loadData();
        }, REFRESH_INTERVAL);

        function exportCSV() {
            const from = document.getElementById("fromDate").value;
            const to = document.getElementById("toDate").value;
            const courier = document.getElementById("courierFilter").value;
            const size = document.getElementById("sizeFilter").value;
            const search = document.getElementById("codeSearch").value.trim();

            if (from && to && from > to) {
                alert("From date cannot be after To date");
                return;
            }

            let url = "api/report/csv.php";
            const params = [];

            if (from) params.push(`from=${encodeURIComponent(from)}`);
            if (to) params.push(`to=${encodeURIComponent(to)}`);
            if (courier) params.push(`courier=${encodeURIComponent(courier)}`);
            if (size) params.push(`size=${encodeURIComponent(size)}`);
            const platform = document.getElementById("platformFilter").value;
            if (platform) params.push(`platform=${encodeURIComponent(platform)}`);
            const type = document.getElementById("typeFilter").value;
            if (type) params.push(`type=${encodeURIComponent(type)}`);
            if (search) params.push(`search=${encodeURIComponent(search)}`);

            if (params.length) {
                url += "?" + params.join("&");
            }

            window.location.href = url;
        }

        /* =========================
           BATCH DELETE LOGIC
        ========================= */
        function toggleSelectAll(masterCheckbox) {
            const checkboxes = document.querySelectorAll('.row-checkbox');
            checkboxes.forEach(cb => {
                cb.checked = masterCheckbox.checked;
            });
            updateDeleteBtn();
        }

        function updateDeleteBtn() {
            const checkedBoxes = document.querySelectorAll('.row-checkbox:checked');
            const btn = document.getElementById('batchDeleteBtn');
            const masterCheckbox = document.getElementById('selectAll');
            const allCheckboxes = document.querySelectorAll('.row-checkbox');

            if (checkedBoxes.length > 0) {
                btn.style.display = 'inline-block';
                btn.innerHTML = `🗑️ Delete Selected (${checkedBoxes.length})`;
            } else {
                btn.style.display = 'none';
            }

            // Sync master checkbox state
            if (allCheckboxes.length > 0) {
                masterCheckbox.checked = checkedBoxes.length === allCheckboxes.length;
            }
        }

        async function deleteSelected() {
            const checkedBoxes = document.querySelectorAll('.row-checkbox:checked');
            if (checkedBoxes.length === 0) return;

            const codes = Array.from(checkedBoxes).map(cb => cb.value);

            if (!confirm(`Are you sure you want to permanently delete ${codes.length} scan(s)? This action cannot be undone.`)) {
                return;
            }

            try {
                const res = await fetch("api/scan/delete.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify({ codes: codes })
                });

                if (!res.ok) {
                    throw new Error(`HTTP Error: ${res.status}`);
                }

                const data = await res.json();
                if (data.status === "success") {
                    // Uncheck master checkbox just in case
                    document.getElementById('selectAll').checked = false;
                    loadData(); // Refresh table
                } else {
                    alert("Error: " + data.message);
                }
            } catch (err) {
                console.error(err);
                alert("Failed to delete records.");
            }
        }

        /* =========================
           SUMMARY REPORT MODAL
        ========================= */
        function closeSummaryReport() {
            document.getElementById('summaryModal').style.display = 'none';
        }

        async function openSummaryReport() {
            document.getElementById('summaryModal').style.display = 'block';
            const tbody = document.getElementById('summaryTableBody');
            
            // Set date subtitle
            const from = document.getElementById("fromDate").value;
            const to = document.getElementById("toDate").value;
            if (from === to) {
                document.getElementById('summaryModalDate').innerText = `(${from})`;
            } else {
                document.getElementById('summaryModalDate').innerText = `(${from} - ${to})`;
            }

            tbody.innerHTML = `<tr><td colspan="4" style="text-align:center; padding: 40px; color: var(--muted)">Loading data...</td></tr>`;
            
            try {
                const res = await fetch(`api/scan/batch_summary.php?from=${from}&to=${to}`);
                const data = await res.json();

                if (data.status === "success") {
                    const batches = data.data;
                    
                    let totalScans = 0;
                    let totalPouch = 0;
                    let totalBulky = 0;

                    if (batches.length === 0) {
                        tbody.innerHTML = `<tr><td colspan="4" style="text-align:center; padding: 40px; color: var(--muted)">No batches found for this date range.</td></tr>`;
                    } else {
                        tbody.innerHTML = batches.map(b => {
                            totalScans += b.count;
                            totalPouch += (b.pouch_count || 0);
                            totalBulky += (b.bulky_count || 0);

                            let badgeHtml = '';
                            if (b.id === "NORMAL") {
                                badgeHtml = `<span class="badge" style="background:rgba(239, 68, 68, 0.15); color:#ef4444; font-family:monospace; font-size:0.85rem; border: 1px solid rgba(239, 68, 68, 0.3);">UNBATCHED</span>`;
                            } else {
                                badgeHtml = `<span class="badge" style="background:rgba(192, 132, 252, 0.15); color:#d8b4fe; font-family:monospace; font-size:0.85rem; border: 1px solid rgba(192, 132, 252, 0.3);">BATCH-${b.id}</span>`;
                            }

                            return `
                            <tr>
                                <td>${badgeHtml}</td>
                                <td><span class="badge" style="background:rgba(34, 197, 94, 0.15); color:#22c55e; font-weight:800;">${b.count} Items</span></td>
                                <td><span class="badge" style="background:rgba(59, 130, 246, 0.15); color:#3b82f6;">${b.pouch_count || 0} Pouches</span></td>
                                <td><span class="badge" style="background:rgba(139, 92, 246, 0.15); color:#a78bfa;">${b.bulky_count || 0} Bulky</span></td>
                            </tr>
                            `;
                        }).join("");
                    }

                    document.getElementById("smTotalScans").innerText = totalScans;
                    document.getElementById("smTotalPouch").innerText = totalPouch;
                    document.getElementById("smTotalBulky").innerText = totalBulky;
                    document.getElementById("smTotalBatches").innerText = batches.length;
                } else {
                    tbody.innerHTML = `<tr><td colspan="4" style="text-align:center; padding: 40px; color: #ef4444">Error: ${data.message}</td></tr>`;
                }
            } catch (err) {
                console.error(err);
                tbody.innerHTML = `<tr><td colspan="4" style="text-align:center; padding: 40px; color: #ef4444">Failed to load report data</td></tr>`;
            }
        }
    </script>

</body>

</html>