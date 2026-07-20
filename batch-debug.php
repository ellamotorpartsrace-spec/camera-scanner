<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Batch Debug</title>
  <style>
    body { font-family: monospace; background: #000; color: #0f0; padding: 16px; }
    pre { white-space: pre-wrap; word-break: break-all; background: #111; padding: 12px; border-radius: 8px; font-size: 12px; }
    button { background: #6366f1; color: white; border: none; padding: 12px 20px; border-radius: 8px; font-size: 14px; width: 100%; margin: 6px 0; cursor: pointer; }
    .red { background: #7f1d1d; }
    h3 { color: #f59e0b; }
  </style>
</head>
<body>
<h3>🔍 Batch Debug Tool</h3>

<button onclick="testPing()">1. Test save_batch.php is reachable</button>
<button onclick="showRaw()">2. Show raw localStorage value</button>
<button onclick="testSubmit()">3. Try submit (logs everything)</button>
<button class="red" onclick="clearAndGo()">4. CLEAR QUEUE &amp; Go Back to Scanner</button>

<pre id="out">Click a button above…</pre>

<script>
function log(msg) {
  document.getElementById('out').textContent += '\n' + msg;
}
function clear2() { document.getElementById('out').textContent = ''; }

async function testPing() {
  clear2();
  log('Sending minimal valid payload to save_batch.php...');
  try {
    const res = await fetch('api/scan/save_batch.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ scans: [{ code: 'PING_TEST_' + Date.now(), type: 'QR' }] })
    });
    log('HTTP status: ' + res.status);
    const txt = await res.text();
    log('Response: ' + txt);
  } catch(e) {
    log('FETCH ERROR: ' + e.message);
  }
}

function showRaw() {
  clear2();
  const raw = localStorage.getItem('smartBatchQueue');
  log('localStorage key: smartBatchQueue');
  log('Length: ' + (raw ? raw.length : 'null'));
  log('First 500 chars:');
  log(raw ? raw.substring(0, 500) : '(empty)');
  
  // Try parse
  if (raw) {
    try {
      const p = JSON.parse(raw);
      log('\nJSON.parse: OK');
      log('Type: ' + typeof p);
      log('Is Array: ' + Array.isArray(p));
      log('Length/Keys: ' + (Array.isArray(p) ? p.length : Object.keys(p).length));
      if (Array.isArray(p) && p.length > 0) {
        log('First item: ' + JSON.stringify(p[0]));
      }
    } catch(e) {
      log('\nJSON.parse FAILED: ' + e.message);
      log('⚠ Data is CORRUPTED - must clear queue');
    }
  }
}

async function testSubmit() {
  clear2();
  const raw = localStorage.getItem('smartBatchQueue');
  log('Raw data first 200 chars: ' + (raw ? raw.substring(0,200) : '(empty)'));
  
  let scans = [];
  try {
    const p = JSON.parse(raw);
    scans = Array.isArray(p) ? p : Object.values(p);
    log('Parsed ' + scans.length + ' scans');
  } catch(e) {
    log('Parse failed: ' + e.message);
    log('Cannot submit - data corrupted. Use button 4 to clear.');
    return;
  }
  
  const safe = scans.filter(s => s && typeof s === 'object' && s.code);
  log('Valid scans with code: ' + safe.length);
  
  if (safe.length === 0) {
    log('Nothing valid to send. Use button 4 to clear.');
    return;
  }
  
  const payload = JSON.stringify({ scans: safe });
  log('Payload size: ' + payload.length + ' bytes');
  log('Sending...');
  
  try {
    const res = await fetch('api/scan/save_batch.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: payload
    });
    log('HTTP status: ' + res.status);
    const txt = await res.text();
    log('Raw response: ' + txt.substring(0, 1000));
    
    try {
      const d = JSON.parse(txt);
      if (d.status === 'success') {
        log('\n✅ SUCCESS! Saved:' + d.results.saved);
        localStorage.removeItem('smartBatchQueue');
        log('Queue cleared.');
      } else {
        log('\n❌ Server error: ' + JSON.stringify(d));
      }
    } catch(e) {
      log('\n❌ Response is not JSON - PHP may have a syntax error or fatal error!');
    }
  } catch(e) {
    log('FETCH ERROR: ' + e.message);
  }
}

function clearAndGo() {
  if (!confirm('Clear queue and go back to scanner?')) return;
  localStorage.removeItem('smartBatchQueue');
  alert('Queue cleared! You will need to rescan your items.');
  window.location = 'scan-smart.php';
}
</script>
</body>
</html>
