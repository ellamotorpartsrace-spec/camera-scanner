/* =========================
   SHARED SOUND UTILITY
========================= */

let audioCtx = null;

/* =========================
   INIT AUDIO (MOBILE SAFE)
========================= */
function initSound() {
  if (!audioCtx) {
    audioCtx = new (window.AudioContext || window.webkitAudioContext)();
  }

  if (audioCtx.state === "suspended") {
    audioCtx.resume();
  }
}

/* =========================
   PLAY BEEP
========================= */
function playBeep({
  frequency,
  duration = 0.22,
  volume = 0.15,
  type = "sine",
}) {
  if (!audioCtx) return;

  const osc = audioCtx.createOscillator();
  const gain = audioCtx.createGain();

  osc.type = type;
  osc.frequency.value = frequency;

  osc.connect(gain);
  gain.connect(audioCtx.destination);

  gain.gain.setValueAtTime(volume, audioCtx.currentTime);
  gain.gain.exponentialRampToValueAtTime(
    0.001,
    audioCtx.currentTime + duration,
  );

  osc.start();
  osc.stop(audioCtx.currentTime + duration);
}

/* =========================
   PUBLIC SOUND API
========================= */
window.Sound = {
  success() {
    if (navigator.vibrate) navigator.vibrate(100); // Short tap
    if (!audioCtx || audioCtx.state !== "running") return;

    const osc = audioCtx.createOscillator();
    const gain = audioCtx.createGain();

    osc.type = "sine";
    osc.frequency.value = 800;

    osc.connect(gain);
    gain.connect(audioCtx.destination);

    const now = audioCtx.currentTime;
    gain.gain.setValueAtTime(1.0, now);
    gain.gain.exponentialRampToValueAtTime(0.01, now + 0.2);

    osc.start(now);
    osc.stop(now + 0.2);
  },

  duplicate() {
    if (navigator.vibrate) navigator.vibrate([200, 100, 200]); // Dual pulse
    if (!audioCtx || audioCtx.state !== "running") return;

    const osc = audioCtx.createOscillator();
    const gain = audioCtx.createGain();

    osc.type = "square";
    osc.connect(gain);
    gain.connect(audioCtx.destination);

    const now = audioCtx.currentTime;
    const HIGH = 1600;
    const LOW = 700;
    const SWEEP_TIME = 0.4;
    const CYCLES = 2; // Reduced cycles for better UX
    const VOLUME = 0.25;

    gain.gain.setValueAtTime(VOLUME, now);

    let t = now;
    for (let i = 0; i < CYCLES; i++) {
      osc.frequency.setValueAtTime(HIGH, t);
      osc.frequency.linearRampToValueAtTime(LOW, t + SWEEP_TIME);
      t += SWEEP_TIME;
    }

    gain.gain.exponentialRampToValueAtTime(0.01, t + 0.25);

    osc.start(now);
    osc.stop(t + 0.3);
  },

  error() {
    if (navigator.vibrate) navigator.vibrate(300); // Long pulse
    if (!audioCtx || audioCtx.state !== "running") return;

    const osc = audioCtx.createOscillator();
    const gain = audioCtx.createGain();

    osc.type = "sawtooth";
    osc.frequency.value = 280;

    osc.connect(gain);
    gain.connect(audioCtx.destination);

    const now = audioCtx.currentTime;
    gain.gain.setValueAtTime(0.15, now);
    gain.gain.exponentialRampToValueAtTime(0.01, now + 0.4);

    osc.start(now);
    osc.stop(now + 0.4);
  },
};

/* =========================
   AUTO-UNLOCK ON FIRST USER ACTION
========================= */
["click", "touchstart", "keydown"].forEach((evt) => {
  document.addEventListener(evt, initSound, { once: true });
});
