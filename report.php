<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
require_once __DIR__ . '/api/core/session.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0" />
    <title>Summary Report</title>
    <link rel="stylesheet" href="css/bootstrap-5.3.8-dist/css/bootstrap.min.css" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f8fafc;
            --text: #0f172a;
            --muted: #64748b;
            --border: #e2e8f0;
            --card: #ffffff;
            --accent: #3b82f6;
        }

        body.dark-mode {
            --bg: #0b0f19;
            --text: #f8fafc;
            --muted: #94a3b8;
            --border: #1e293b;
            --card: #0f172a;
            --accent: #3b82f6;
        }

        body {
            background-color: var(--bg);
            color: var(--text);
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 20px;
        }

        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
        }

        .title {
            font-size: 1.5rem;
            font-weight: 800;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .filters {
            display: flex;
            gap: 12px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }

        .form-control,
        .form-select {
            background-color: var(--card);
            border-color: var(--border);
            color: var(--text);
            border-radius: 8px;
            font-weight: 600;
        }

        .form-control:focus,
        .form-select:focus {
            background-color: var(--card);
            color: var(--text);
            border-color: var(--accent);
            box-shadow: 0 0 0 0.25rem rgba(59, 130, 246, 0.25);
        }

        .btn-custom {
            background: var(--accent);
            color: white;
            border: none;
            font-weight: 600;
            border-radius: 8px;
            padding: 8px 16px;
        }

        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .summary-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .summary-card .value {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 4px;
        }

        .summary-card .label {
            font-size: 0.85rem;
            color: var(--muted);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .table-wrap {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: rgba(0, 0, 0, 0.02);
            padding: 12px 16px;
            font-size: 0.75rem;
            text-transform: uppercase;
            font-weight: 700;
            color: var(--muted);
            border-bottom: 1px solid var(--border);
            text-align: left;
        }

        td {
            padding: 12px 16px;
            font-size: 0.9rem;
            font-weight: 600;
            border-bottom: 1px solid var(--border);
        }

        tbody tr:hover {
            background: rgba(255, 255, 255, 0.05);
        }

        .badge-batch {
            background: rgba(192, 132, 252, 0.15);
            color: #d8b4fe;
            border: 1px solid rgba(192, 132, 252, 0.3);
            border-radius: 6px;
            padding: 4px 8px;
            font-family: monospace;
            font-size: 0.85rem;
            box-shadow: 0 0 5px rgba(192, 132, 252, 0.2);
        }

        .badge-pouch {
            background: rgba(59, 130, 246, 0.15);
            color: #3b82f6;
            border-radius: 6px;
            padding: 4px 8px;
            font-size: 0.8rem;
        }

        .badge-bulky {
            background: rgba(139, 92, 246, 0.15);
            color: #a78bfa;
            border-radius: 6px;
            padding: 4px 8px;
            font-size: 0.8rem;
        }

        .badge-total {
            background: rgba(34, 197, 94, 0.15);
            color: #22c55e;
            border-radius: 6px;
            padding: 4px 8px;
            font-size: 0.8rem;
            font-weight: 800;
        }
    </style>
</head>

<body class="dark-mode">

    <div class="header">
        <h1 class="title">📊 Summary Report</h1>
        <a href="index.php" class="btn btn-outline-light btn-sm" style="font-weight:600">← Back</a>
    </div>

    <div class="filters">
        <input type="date" id="fromDate" class="form-control" style="width: auto;">
        <input type="date" id="toDate" class="form-control" style="width: auto;">
        
        <button class="btn btn-custom" onclick="preset('today')">Today</button>
        <button class="btn btn-custom" onclick="preset('yesterday')">Yesterday</button>
        <button class="btn btn-custom" onclick="preset('week')">This Week</button>
        <button class="btn btn-primary" style="font-weight:700" onclick="loadData()">Apply</button>
    </div>

    <div class="summary-cards">
        <div class="summary-card" style="border-top: 4px solid #22c55e">
            <div class="value" id="totalScansCard" style="color: #22c55e">0</div>
            <div class="label">Total Scans</div>
        </div>
        <div class="summary-card" style="border-top: 4px solid #3b82f6">
            <div class="value" id="totalPouchCard" style="color: #3b82f6">0</div>
            <div class="label">Total Pouches</div>
        </div>
        <div class="summary-card" style="border-top: 4px solid #a78bfa">
            <div class="value" id="totalBulkyCard" style="color: #a78bfa">0</div>
            <div class="label">Total Bulky</div>
        </div>
        <div class="summary-card" style="border-top: 4px solid #c084fc">
            <div class="value" id="totalBatchesCard" style="color: #c084fc">0</div>
            <div class="label">Total Batches</div>
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
            <tbody id="tableBody">
                <tr>
                    <td colspan="4" style="text-align:center; padding: 40px; color: var(--muted)">Loading data...</td>
                </tr>
            </tbody>
        </table>
    </div>

    <script>
        const REFRESH_INTERVAL = 15000;

        function toISO(date) {
            return date.toISOString().split("T")[0];
        }

        const today = new Date();
        document.getElementById("fromDate").value = toISO(today);
        document.getElementById("toDate").value = toISO(today);

        function preset(type) {
            const today = new Date();
            let from, to;

            if (type === "today") {
                from = toISO(today);
                to = toISO(today);
            } else if (type === "yesterday") {
                const y = new Date(today);
                y.setDate(today.getDate() - 1);
                from = toISO(y);
                to = toISO(y);
            } else if (type === "week") {
                const start = new Date(today);
                const day = today.getDay() || 7;
                start.setDate(today.getDate() - day + 1);
                from = toISO(start);
                to = toISO(today);
            }

            document.getElementById("fromDate").value = from;
            document.getElementById("toDate").value = to;
            loadData();
        }

        async function loadData() {
            const from = document.getElementById("fromDate").value;
            const to = document.getElementById("toDate").value;
            const tbody = document.getElementById("tableBody");

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

                            return `
                            <tr>
                                <td><span class="badge-batch">BATCH-${b.id}</span></td>
                                <td><span class="badge-total">${b.count} Items</span></td>
                                <td><span class="badge-pouch">${b.pouch_count || 0} Pouches</span></td>
                                <td><span class="badge-bulky">${b.bulky_count || 0} Bulky</span></td>
                            </tr>
                            `;
                        }).join("");
                    }

                    // Update Summary Cards
                    document.getElementById("totalScansCard").innerText = totalScans;
                    document.getElementById("totalPouchCard").innerText = totalPouch;
                    document.getElementById("totalBulkyCard").innerText = totalBulky;
                    document.getElementById("totalBatchesCard").innerText = batches.length;
                } else {
                    tbody.innerHTML = `<tr><td colspan="4" style="text-align:center; padding: 40px; color: #ef4444">Error: ${data.message}</td></tr>`;
                }
            } catch (err) {
                console.error(err);
                tbody.innerHTML = `<tr><td colspan="4" style="text-align:center; padding: 40px; color: #ef4444">Failed to load report data</td></tr>`;
            }
        }

        // Auto Load
        loadData();

        // Auto Refresh if tab is visible
        setInterval(() => {
            if (!document.hidden) {
                loadData();
            }
        }, REFRESH_INTERVAL);
    </script>
</body>
</html>