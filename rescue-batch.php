<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>🚨 Rescue Batch – Ella Scanner</title>
  <style>
    body { font-family: Arial, sans-serif; background: #0a0a1a; color: #e2e8f0; padding: 20px; margin: 0; }
    h1 { color: #f59e0b; margin-bottom: 4px; }
    p { color: #94a3b8; margin-top: 0; }
    .card { background: #0f172a; border: 1px solid #1e293b; border-radius: 12px; padding: 16px; margin-bottom: 16px; }
    pre { background: #020617; border-radius: 8px; padding: 12px; overflow-x: auto; font-size: 12px; color: #94a3b8; max-height: 200px; overflow-y: auto; }
    button { padding: 14px 24px; border: none; border-radius: 10px; font-size: 16px; font-weight: bold; cursor: pointer; width: 100%; margin-top: 8px; }
    .btn-submit { background: #6366f1; color: white; }
    .btn-clear  { background: #ef4444; color: white; }
    .btn-back   { background: #1e293b; color: #e2e8f0; }
    #result { margin-top: 16px; padding: 14px; border-radius: 10px; display: none; font-weight: bold; font-size: 15px; }
    .ok  { background: #14532d; color: #86efac; }
    .err { background: #450a0a; color: #fca5a5; }
    .count { font-size: 48px; font-weight: 900; color: #6366f1; text-align: center; padding: 10px; }
  </style>
</head>
<body>

<h1>🚨 Rescue Batch Submitter</h1>
<p>Use this page to force-submit your stuck batch queue.</p>

<div class="card">
  <strong>Scans found in queue:</strong>
  <div class="count" id="count">Loading…</div>
  <strong>Raw batch data:</strong>
  <pre id="raw">Loading…</pre>
</div>

<button class="btn-submit" id="submitBtn" onclick="submitNow()">🚀 SUBMIT BATCH NOW</button>
<button class="btn-clear"  id="clearBtn"  onclick="clearNow()">🗑️ CLEAR QUEUE (if stuck)</button>
<button class="btn-back"   onclick="window.location='scan-smart.php'">← Back to Scanner</button>

<div id="result"></div>

<script>
  let batchQueue = [];

  function loadAndShow() {
    try {
      const raw = localStorage.getItem('smartBatchQueue');
      document.getElementById('raw').textContent = raw || '(empty)';
      if (raw) {
        const parsed = JSON.parse(raw);
        batchQueue = Array.isArray(parsed) ? parsed : Object.values(parsed);
      }
    } catch(e) {
      document.getElementById('raw').textContent = 'ERROR reading localStorage: ' + e.message;
    }
    document.getElementById('count').textContent = batchQueue.length;
  }

  async function submitNow() {
    if (batchQueue.length === 0) {
      showResult('Queue is empty! Nothing to submit.', false);
      return;
    }

    // Filter valid entries
    const safeScans = batchQueue.filter(s => s && typeof s === 'object' && s.code);
    if (safeScans.length === 0) {
      showResult('No valid scan entries found. Try clearing the queue and scanning again.', false);
      return;
    }

    document.getElementById('submitBtn').textContent = '⏳ Uploading ' + safeScans.length + ' scans…';
    document.getElementById('submitBtn').disabled = true;

    try {
      const res = await fetch('api/scan/save_batch.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ scans: safeScans })
      });

      const text = await res.text();
      let data;
      try { data = JSON.parse(text); } catch(e) {
        showResult('Server returned bad response:\n' + text.substring(0, 300), false);
        return;
      }

      if (data.status === 'success') {
        const r = data.results;
        localStorage.removeItem('smartBatchQueue');
        showResult(`✅ SUCCESS!\n\nSaved: ${r.saved}\nDuplicates: ${r.duplicates}\nErrors: ${r.errors}`, true);
        batchQueue = [];
        loadAndShow();
      } else {
        showResult('❌ Server error:\n' + JSON.stringify(data, null, 2), false);
      }
    } catch(err) {
      showResult('❌ Network error: ' + err.message, false);
    } finally {
      document.getElementById('submitBtn').textContent = '🚀 SUBMIT BATCH NOW';
      document.getElementById('submitBtn').disabled = false;
    }
  }

  function clearNow() {
    if (!confirm('Clear all ' + batchQueue.length + ' scans from the queue? This cannot be undone.')) return;
    localStorage.removeItem('smartBatchQueue');
    batchQueue = [];
    loadAndShow();
    showResult('Queue cleared.', true);
  }

  function showResult(msg, ok) {
    const el = document.getElementById('result');
    el.textContent = msg;
    el.className = ok ? 'ok' : 'err';
    el.style.display = 'block';
    el.style.whiteSpace = 'pre-wrap';
  }

  loadAndShow();
</script>
</body>
</html>
