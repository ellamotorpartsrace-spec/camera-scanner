<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
require_once __DIR__ . '/api/core/session.php';
$config = require __DIR__ . '/api/core/config.php';
require_once __DIR__ . '/api/core/db.php';

// Auto-authenticate — no password required
$_SESSION['authenticated'] = true;
$_SESSION['last_login'] = $_SESSION['last_login'] ?? time();
$is_authenticated = true;

// Fetch Today's Stats
$stats = ['total' => 0, 'returned' => 0, 'platforms' => []];
try {
  // Use MySQL CURDATE() so it perfectly matches the +08:00 timezone set in db.php
  $stmt = $pdo->query("SELECT COUNT(*) as total, SUM(IF(returned_at IS NOT NULL AND returned_at > '2000-01-01', 1, 0)) as returned FROM scans WHERE DATE(scanned_at) = CURDATE()");
  $row = $stmt->fetch();
  $stats['total'] = (int) ($row['total'] ?? 0);
  $stats['returned'] = (int) ($row['returned'] ?? 0);
} catch (PDOException $e) {
  error_log("Stats Error: " . $e->getMessage());
}
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>ELLA Scanner – Select Mode</title>
  <meta name="description" content="ELLA Motor Parts Scanner – Smart, Manual & History" />
  <link rel="manifest" href="manifest.json" />
  <meta name="theme-color" content="#6366f1" />
  <link rel="apple-touch-icon" href="logo.png" />
  <link rel="stylesheet" href="css/bootstrap-5.3.8-dist/css/bootstrap.min.css" />
  <link rel="stylesheet" href="css/scanner.css?v=4">
  <script>
    const savedTheme = localStorage.getItem('ella-theme');
    if (savedTheme === 'light') {
      document.documentElement.classList.add('light-mode');
    } else {
      document.documentElement.classList.add('dark-mode');
    }
  </script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    /* ─── Design Tokens ─── */
    :root {
      --accent: #6366f1;
      --accent-2: #a855f7;
      --accent-gradient: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);
      --body-bg: #f0f2f8;
      --surface: rgba(255, 255, 255, 0.75);
      --surface-border: rgba(255, 255, 255, 0.5);
      --text: #0f172a;
      --muted: #64748b;
      --card-bg: #ffffff;
      --shadow-sm: 0 2px 8px rgba(99, 102, 241, .08);
      --shadow-lg: 0 16px 48px rgba(99, 102, 241, .14);
      --radius: 1.25rem;
      --font: 'Inter', system-ui, sans-serif;
    }

    body.dark-mode, html.dark-mode {
      --body-bg: #060818;
      --surface: rgba(20, 28, 58, 0.72);
      --surface-border: rgba(99, 102, 241, 0.18);
      --text: #e2e8f0;
      --muted: #94a3b8;
      --card-bg: #0f172a;
      --shadow-sm: 0 2px 8px rgba(0, 0, 0, .35);
      --shadow-lg: 0 16px 48px rgba(0, 0, 0, .5);
    }

    /* ─── Base ─── */
    *,
    *::before,
    *::after {
      box-sizing: border-box;
    }

    body {
      font-family: var(--font);
      background: var(--body-bg);
      color: var(--text);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      transition: background 0.35s ease, color 0.35s ease;
      padding-bottom: 56px;
    }

    /* ─── Animated mesh background ─── */
    body::before {
      content: '';
      position: fixed;
      inset: 0;
      background:
        radial-gradient(ellipse 80% 60% at 20% -10%, rgba(99, 102, 241, .15) 0%, transparent 60%),
        radial-gradient(ellipse 60% 50% at 80% 110%, rgba(168, 85, 247, .12) 0%, transparent 55%);
      pointer-events: none;
      z-index: 0;
    }

    body.dark-mode::before {
      background:
        radial-gradient(ellipse 80% 60% at 20% -10%, rgba(99, 102, 241, .25) 0%, transparent 60%),
        radial-gradient(ellipse 60% 50% at 80% 110%, rgba(168, 85, 247, .2) 0%, transparent 55%);
    }

    .page-wrap {
      position: relative;
      z-index: 1;
      flex: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 2.5rem 1rem;
    }

    /* ─── Header / Hero ─── */
    .hero {
      text-align: center;
      margin-bottom: 2.5rem;
    }

    .logo-wrap {
      width: 88px;
      height: 88px;
      margin: 0 auto 1.25rem;
      border-radius: 28px;
      background: var(--accent-gradient);
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 8px 32px rgba(99, 102, 241, .4);
      overflow: hidden;
      animation: logoFloat 3s ease-in-out infinite;
    }

    .logo-wrap img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    @keyframes logoFloat {

      0%,
      100% {
        transform: translateY(0);
      }

      50% {
        transform: translateY(-6px);
      }
    }

    .hero-title {
      font-size: clamp(1.9rem, 5vw, 2.8rem);
      font-weight: 800;
      letter-spacing: -0.03em;
      background: var(--accent-gradient);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      margin-bottom: .35rem;
    }

    .hero-sub {
      font-size: .95rem;
      color: var(--muted);
      font-weight: 500;
      margin-bottom: 1.25rem;
    }

    .stats-row {
      display: inline-flex;
      align-items: center;
      gap: .65rem;
      background: var(--surface);
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      border: 1px solid var(--surface-border);
      border-radius: 2rem;
      padding: .5rem 1.25rem;
      box-shadow: var(--shadow-sm);
    }

    .stat-dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: #22c55e;
      animation: pulse 2s infinite;
    }

    @keyframes pulse {

      0%,
      100% {
        opacity: 1;
        box-shadow: 0 0 0 0 rgba(34, 197, 94, .4);
      }

      50% {
        opacity: .85;
        box-shadow: 0 0 0 6px rgba(34, 197, 94, 0);
      }
    }

    .stats-text {
      font-size: .82rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .08em;
      color: var(--text);
    }

    .stats-text span {
      background: var(--accent-gradient);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    /* ─── Mode Cards ─── */
    .cards-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
      gap: 1.25rem;
      width: 100%;
      max-width: 800px;
    }

    .mode-card {
      background: var(--card-bg);
      border: 1px solid var(--surface-border);
      border-radius: var(--radius);
      padding: 2rem 1.5rem;
      text-align: center;
      text-decoration: none;
      color: var(--text);
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: .75rem;
      position: relative;
      overflow: hidden;
      transition: transform .28s cubic-bezier(.4, 0, .2, 1),
        box-shadow .28s cubic-bezier(.4, 0, .2, 1),
        border-color .28s ease;
      box-shadow: var(--shadow-sm);
    }

    .mode-card::before {
      content: '';
      position: absolute;
      inset: 0;
      background: var(--accent-gradient);
      opacity: 0;
      transition: opacity .28s ease;
      border-radius: var(--radius);
    }

    .mode-card:hover {
      transform: translateY(-8px);
      box-shadow: var(--shadow-lg);
      border-color: rgba(99, 102, 241, .3);
      color: var(--text);
    }

    .mode-card:hover::before {
      opacity: .04;
    }

    .card-icon-wrap {
      width: 64px;
      height: 64px;
      border-radius: 18px;
      background: linear-gradient(135deg, rgba(99, 102, 241, .12) 0%, rgba(168, 85, 247, .12) 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      transition: transform .28s ease, background .28s ease;
    }

    .mode-card:hover .card-icon-wrap {
      background: var(--accent-gradient);
      transform: scale(1.08);
    }

    .card-icon-wrap svg {
      width: 30px;
      height: 30px;
      fill: none;
      stroke: var(--accent);
      stroke-width: 1.8;
      stroke-linecap: round;
      stroke-linejoin: round;
      transition: stroke .28s ease;
    }

    .mode-card:hover .card-icon-wrap svg {
      stroke: #fff;
    }

    .card-label {
      font-size: 1rem;
      font-weight: 700;
      letter-spacing: -.01em;
    }

    .card-desc {
      font-size: .8rem;
      color: var(--muted);
      line-height: 1.5;
      margin: 0;
    }

    /* ─── Fade-in animations ─── */
    @keyframes fadeUp {
      from {
        opacity: 0;
        transform: translateY(22px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .animate-in {
      animation: fadeUp .55s ease-out both;
    }

    .delay-0 {
      animation-delay: .05s;
    }

    .delay-1 {
      animation-delay: .15s;
    }

    .delay-2 {
      animation-delay: .25s;
    }

    .delay-3 {
      animation-delay: .35s;
    }

    /* ─── Theme Toggle ─── */
    .theme-btn {
      position: fixed;
      top: 1rem;
      right: 1rem;
      z-index: 1050;
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: var(--surface);
      backdrop-filter: blur(10px);
      -webkit-backdrop-filter: blur(10px);
      border: 1px solid var(--surface-border);
      box-shadow: var(--shadow-sm);
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      font-size: 1.1rem;
      transition: transform .2s ease, box-shadow .2s ease;
    }

    .theme-btn:hover {
      transform: scale(1.1);
      box-shadow: var(--shadow-lg);
    }

    /* ─── Copyright Banner ─── */
    .copyright-banner {
      position: fixed;
      bottom: 0;
      left: 0;
      width: 100%;
      background: rgba(0, 0, 0, 0.82);
      backdrop-filter: blur(6px);
      color: rgba(255, 255, 255, .7);
      padding: 7px 0;
      overflow: hidden;
      white-space: nowrap;
      z-index: 1000;
      font-size: .68rem;
      letter-spacing: .15em;
      text-transform: uppercase;
      border-top: 1px solid rgba(255, 255, 255, .08);
    }

    .copyright-content {
      display: inline-block;
      padding-left: 100%;
      animation: scrollLeft 28s linear infinite;
    }

    @keyframes scrollLeft {
      from {
        transform: translateX(0);
      }

      to {
        transform: translateX(-100%);
      }
    }

    .auth-form-wrap {
      width: 100%;
      max-width: 400px;
      margin-top: 1rem;
      background: var(--surface);
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      border: 1px solid var(--surface-border);
      border-radius: var(--radius);
      padding: 2rem;
      box-shadow: var(--shadow-lg);
    }

    .form-control-custom {
      background: var(--card-bg);
      border: 1px solid var(--surface-border);
      color: var(--text);
      padding: 0.8rem 1rem;
      border-radius: 0.75rem;
      width: 100%;
      margin-bottom: 1rem;
    }

    .btn-accent {
      background: var(--accent-gradient);
      color: white;
      border: none;
      padding: 0.8rem;
      border-radius: 0.75rem;
      font-weight: 700;
      width: 100%;
      box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
      transition: transform 0.2s;
    }

    .btn-accent:hover {
      transform: translateY(-2px);
      color: white;
    }

    .logout-btn {
      margin-top: 2rem;
      color: var(--muted);
      text-decoration: none;
      font-size: 0.8rem;
      font-weight: 600;
      border-bottom: 1px solid transparent;
      transition: color 0.2s, border-color 0.2s;
    }

    .logout-btn:hover {
      color: var(--text);
      border-color: var(--text);
    }
  </style>
</head>

<body>

  <!-- Theme Toggle -->
  <button class="theme-btn" id="themeToggle" title="Toggle dark/light mode" aria-label="Toggle theme">
    <span id="themeIcon">🌙</span>
  </button>

  <div class="page-wrap">

    <!-- ── Hero ── -->
    <div class="hero animate-in delay-0">

      <!-- Logo -->
      <div class="logo-wrap">
        <img src="logo.png" alt="ELLA Motor Parts Logo" />
      </div>

      <h1 class="hero-title">ELLA Scanner</h1>
      <p class="hero-sub">Ella Motor Parts · Parcel Management System</p>

      <!-- Live stats pill -->
      <div class="stats-row">
        <div class="stat-dot"></div>
        <div class="stats-text">
          Today: <span id="dashTotalScans"><?php echo $stats['total']; ?> Scans</span>
          &nbsp;·&nbsp;
          Returns: <span id="dashTotalReturns"><?php echo $stats['returned']; ?></span>
        </div>
      </div>
    </div>

    <!-- ── Mode Cards ── -->
    <div class="cards-grid">

      <!-- Smart Scanner -->
      <a href="scan-smart.php" class="mode-card animate-in delay-1">
        <div class="card-icon-wrap">
          <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2" />
            <circle cx="12" cy="12" r="3" />
            <path d="M12 9v-1M12 16v-1M9 12H8M16 12h-1" />
          </svg>
        </div>
        <div class="card-label">Smart Scanner</div>
        <p class="card-desc">Auto-detects QR codes &amp; barcodes using the camera.</p>
      </a>

      <!-- Manual Entry -->
      <a href="scan-manual.php" class="mode-card animate-in delay-2">
        <div class="card-icon-wrap">
          <svg viewBox="0 0 24 24" aria-hidden="true">
            <rect x="3" y="5" width="18" height="14" rx="2" />
            <path d="M8 10h8M8 14h5" />
          </svg>
        </div>
        <div class="card-label">Manual Entry</div>
        <p class="card-desc">Type or paste tracking codes manually.</p>
      </a>

      <!-- History -->
      <a href="history.php" class="mode-card animate-in delay-3">
        <div class="card-icon-wrap">
          <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M12 8v4l2.5 2.5" />
            <path d="M3.05 11a9 9 0 1 0 .5-3" />
            <path d="M3 5v3h3" />
          </svg>
        </div>
        <div class="card-label">History</div>
        <p class="card-desc">View, filter, search &amp; export scan records.</p>
      </a>

    </div>

    <a href="?logout=1" class="logout-btn animate-in delay-3">Logout Securely</a>

  </div>

  <!-- Copyright Banner -->
  <div class="copyright-banner">
    <div class="copyright-content">
      ★ DEVELOPED BY BENEDICT RAMIREZ · EST 2026 · COPYRIGHT ELLA MOTOR PARTS · ALL RIGHTS RESERVED · CONTACT:
      0997-7855-120 ★
    </div>
  </div>

  <!-- Bootstrap JS -->
  <script src="css/bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js"></script>

  <!-- Theme Toggle -->
  <script>
    const body = document.body;
    const btn = document.getElementById('themeToggle');
    const icon = document.getElementById('themeIcon');

    function applyTheme(dark) {
      document.documentElement.classList.toggle('dark-mode', dark);
      document.documentElement.classList.toggle('light-mode', !dark);
      icon.textContent = dark ? '☀️' : '🌙';
    }

    const saved = localStorage.getItem('ella-theme');
    if (saved !== null) {
      applyTheme(saved === 'dark');
    } else {
      applyTheme(true); // default to dark
    }

    btn.addEventListener('click', () => {
      const isDark = document.documentElement.classList.contains('dark-mode');
      localStorage.setItem('ella-theme', isDark ? 'light' : 'dark');
      applyTheme(!isDark);
    });
  </script>

  <!-- PWA Service Worker -->
  <script>
    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.register('service-worker.js').catch(() => { });
    }

    // Live Auto-Update for Dashboard Stats
    async function fetchLiveStats() {
      try {
        const res = await fetch("api/scan/dashboard_stats.php");
        const json = await res.json();
        if (json.status === "success") {
          document.getElementById("dashTotalScans").innerText = json.data.total + " Scans";
          document.getElementById("dashTotalReturns").innerText = json.data.returned;
        }
      } catch (e) {
        console.error("Failed to fetch live stats", e);
      }
    }

    // Bust bfcache on mobile and fetch latest stats when returning to the tab
    window.addEventListener("pageshow", function (event) {
      if (event.persisted) {
        fetchLiveStats();
      }
    });
    
    // Also update stats every time the tab becomes visible
    document.addEventListener("visibilitychange", function() {
      if (document.visibilityState === 'visible') {
        fetchLiveStats();
      }
    });

    // Auto poll every 10 seconds just in case they leave the phone on the dashboard
    setInterval(fetchLiveStats, 10000);
  </script>

</body>

</html>