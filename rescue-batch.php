<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>🚨 Rescue Batch – Ella Scanner</title>
  <style>
    body { font-family: Arial, sans-serif; background: #0a0a1a; color: #e2e8f0; padding: 16px; margin: 0; }
    h1 { color: #f59e0b; margin-bottom: 4px; font-size: 1.3rem; }
    p  { color: #94a3b8; margin-top: 0; font-size: 13px; }
    .card { background: #0f172a; border: 1px solid #1e293b; border-radius: 12px; padding: 16px; margin-bottom: 14px; }
    pre  { background: #020617; border-radius: 8px; padding: 10px; overflow-x: auto; font-size: 11px; color: #94a3b8; max-height: 160px; overflow-y: auto; word-break: break-all; white-space: pre-wrap; }
    button { padding: 14px 24px; border: none; border-radius: 10px; font-size: 15px; font-weight: bold; cursor: pointer; width: 100%; margin-top: 8px; }
    .btn-submit { background: #6366f1; color: white; }
    .btn-fix    { background: #f59e0b; color: #000; }
    .btn-clear  { background: #ef4444; color: white; }
    .btn-back   { background: #1e293b; color: #e2e8f0; }
    #result { margin-top: 14px; padding: 14px; border-radius: 10px; display: none; font-size: 14px; font-weight: bold; white-space: pre-wrap; }
    .ok  { background: #14532d; color: #86efac; display: block !important; }
    .err { background: #450a0a; color: #fca5a5; display: block !important; }
    .warn { background: #451a03; color: #fdba74; display: block !important; }
    .count { font-size: 42px; font-weight: 900; color: #6366f1; text-align: center; padding: 6px; }
    label { font-size: 12px; color: #94a3b8; }
    textarea { width: 100%; box-sizing: border-box; background: #020617; color: #e2e8f0; border: 1px solid #1e293b; border-radius: 8px; padding: 10px; font-size: 11px; height: 120px; margin-top: 4px; resize: vertical; }
  </style>
</head>
<body>

<h1>🚨 Rescue Batch Submitter</h1>
<p>Force-submit your stuck batch from localStorage.</p>

<div class="card">
  <strong>Scans recovered:</strong>
  <div class="count" id="count">…</div>
  <label>Raw localStorage value:</label>
  <pre id="raw">…</pre>
</div>

<div class="card" id="manualCard" style="display:none">
  <label><strong>⚠️ Your data is corrupted. Paste the raw value below to try to recover it, OR use "Clear Queue" to start fresh:</strong></label>
  <textarea id="manualInput" placeholder="Paste raw localStorage here…"></textarea>
  <button class="btn-fix" onclick="tryFixManual()">🔧 Try to Fix & Recover</button>
</div>

<button class="btn-submit" id="submitBtn" onclick="submitNow()">🚀 SUBMIT BATCH NOW</button>
<button class="btn-clear"  id="clearBtn"  onclick="clearNow()">🗑️ CLEAR QUEUE (start fresh)</button>
<button class="btn-back"   onclick="window.location='scan-smart.php'">← Back to Scanner</button>

<div id="result"></div>

<script>
  let batchQueue = [];
  let parseOK = false;

  // Try to extract scan codes from a broken JSON string using regex
  function extractCodesFromBrokenJSON(raw) {
    const recovered = [];
    // Match anything that looks like "code":"SOMEVALUE"
    const codeRegex = /"code"\s*:\s*"([^"]+)"/g;
    const courierRegex = /"courier"\s*:\s*"([^"]*)"/g;
    const sizeRegex = /"parcel_size"\s*:\s*"([^"]*)"/g;

    let match;
    const codes = [], couriers = [], sizes = [];
    while ((match = codeRegex.exec(raw))   !== null) codes.push(match[1]);
    while ((match = courierRegex.exec(raw)) !== null) couriers.push(match[1]);
    while ((match = sizeRegex.exec(raw))    !== null) sizes.push(match[1]);

    for (let i = 0; i < codes.length; i++) {
      recovered.push({
        code: codes[i],
        type: 'QR',
        courier: couriers[i] || '',
        parcel_size: sizes[i] || 'POUCH',
        is_return: false,
        platform: ''
      });
    }
    return recovered;
  }

  function loadAndShow() {
    const raw = localStorage.getItem('smartBatchQueue');
    document.getElementById('raw').textContent = raw || '(empty – nothing in queue)';

    if (!raw) {
      document.getElementById('count').textContent = '0';
      showResult('Queue is empty. Nothing to submit.', 'warn');
      return;
    }

    // Try normal JSON parse first
    try {
      const parsed = JSON.parse(raw);
      if (Array.isArray(parsed)) {
        batchQueue = parsed;
        parseOK = true;
      } else if (typeof parsed === 'object') {
        // object – convert values to array
        batchQueue = Object.values(parsed);
        parseOK = true;
      }
    } catch(e) {
      // JSON is broken – try regex extraction
      console.warn('JSON parse failed:', e.message);
      batchQueue = extractCodesFromBrokenJSON(raw);
      parseOK = batchQueue.length > 0;
      document.getElementById('manualCard').style.display = 'block';
      if (batchQueue.length > 0) {
        showResult('⚠️ Your queue data had a JSON error but we recovered ' + batchQueue.length + ' scan(s) via regex. Review and submit.', 'warn');
      } else {
        showResult('❌ Could not recover any scans from corrupted data.\nUse "Clear Queue" and scan again.', 'err');
      }
    }

    document.getElementById('count').textContent = batchQueue.length;
  }

  function tryFixManual() {
    const raw = document.getElementById('manualInput').value.trim();
    if (!raw) { showResult('Nothing pasted.', 'err'); return; }
    try {
      const parsed = JSON.parse(raw);
      batchQueue = Array.isArray(parsed) ? parsed : Object.values(parsed);
      parseOK = true;
      document.getElementById('count').textContent = batchQueue.length;
      showResult('Recovered ' + batchQueue.length + ' scans. Now click Submit!', 'warn');
    } catch(e) {
      batchQueue = extractCodesFromBrokenJSON(raw);
      document.getElementById('count').textContent = batchQueue.length;
      if (batchQueue.length > 0) {
        showResult('Regex recovered ' + batchQueue.length + ' scans. Click Submit!', 'warn');
      } else {
        showResult('Still can\'t parse. Try Clear Queue and rescan.', 'err');
      }
    }
  }

  async function submitNow() {
    const safeScans = batchQueue.filter(s => s && typeof s === 'object' && s.code);
    if (safeScans.length === 0) {
      showResult('No valid scans to submit.\n\nIf the queue count shows 0, click Clear Queue then go back and rescan.\n\nIf count > 0 but scans are invalid, the data is corrupted — clear and rescan.', 'err');
      return;
    }

    document.getElementById('submitBtn').textContent = '⏳ Uploading ' + safeScans.length + ' scans…';
    document.getElementById('submitBtn').disabled = true;

    try {
      const payload = JSON.stringify({ scans: safeScans });
      console.log('Sending payload (first 300 chars):', payload.substring(0, 300));

      const res = await fetch('api/scan/save_batch.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: payload
      });

      const text = await res.text();
      console.log('Raw server response:', text);

      let data;
      try {
        data = JSON.parse(text);
      } catch(e) {
        showResult('Server returned non-JSON:\n' + text.substring(0, 400), 'err');
        return;
      }

      if (data.status === 'success') {
        const r = data.results;
        localStorage.removeItem('smartBatchQueue');
        batchQueue = [];
        document.getElementById('count').textContent = '0';
        document.getElementById('raw').textContent = '(cleared after successful submit)';
        showResult('✅ SUBMITTED!\n\nSaved: ' + r.saved + '\nDuplicates: ' + r.duplicates + '\nErrors: ' + r.errors, 'ok');
      } else {
        showResult('❌ Server error:\n' + JSON.stringify(data, null, 2), 'err');
      }
    } catch(err) {
      showResult('❌ Network error: ' + err.message, 'err');
    } finally {
      document.getElementById('submitBtn').textContent = '🚀 SUBMIT BATCH NOW';
      document.getElementById('submitBtn').disabled = false;
    }
  }

  function clearNow() {
    if (!confirm('Clear all scans from queue? This CANNOT be undone.')) return;
    localStorage.removeItem('smartBatchQueue');
    batchQueue = [];
    document.getElementById('count').textContent = '0';
    document.getElementById('raw').textContent = '(cleared)';
    showResult('Queue cleared. Go back to scanner and rescan your items.', 'warn');
  }

  function showResult(msg, type) {
    const el = document.getElementById('result');
    el.textContent = msg;
    el.className = type;
  }

  loadAndShow();
</script>
</body>
</html>
