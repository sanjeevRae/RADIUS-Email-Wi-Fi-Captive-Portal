<?php

$manifestPath = __DIR__ . '/uploads/ads/manifest.json';
$adInfo       = file_exists($manifestPath)
                ? json_decode(file_get_contents($manifestPath), true)
                : null;

$adFile = null;
$adMime = null;
$adType = null; // 'image' | 'video'

if ($adInfo) {
    $filePath = __DIR__ . '/uploads/ads/' . $adInfo['filename'];
    if (file_exists($filePath)) {
        $adFile = 'uploads/ads/' . $adInfo['filename'];
        $adMime = $adInfo['mime'];
        $adType = strpos($adMime, 'video/') === 0 ? 'video' : 'image';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Advertisement</title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --bg:        #000;
    --overlay:   rgba(0,0,0,.55);
    --accent:    #ffcc00;
    --skip-bg:   rgba(20,20,20,.88);
    --skip-border: rgba(255,255,255,.2);
    --skip-ready:#ffcc00;
    --text:      #ffffff;
    --radius:    6px;
    --font: 'Outfit', sans-serif;
  }

  html, body {
    width: 100%; height: 100%;
    background: var(--bg);
    overflow: hidden;
    font-family: var(--font);
    color: var(--text);
  }

  #ad-container {
    position: fixed; inset: 0;
    display: flex; align-items: center; justify-content: center;
    background: #000;
  }

  #ad-image {
    max-width: 100%; max-height: 100%;
    width: 100%; height: 100%;
    object-fit: contain;
    display: block;
  }

  #ad-video {
    width: 100%; height: 100%;
    object-fit: contain;
    display: block;
    outline: none;
  }

  #ad-placeholder {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 1.25rem;
    width: 100%; height: 100%;
    background: radial-gradient(ellipse at 50% 50%, #1a1a2e 0%, #000 70%);
    text-align: center;
    padding: 2rem;
  }
  .placeholder-icon {
    width: 80px; height: 80px;
    background: rgba(255,204,0,.1);
    border: 2px solid rgba(255,204,0,.3);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 2rem;
  }
  .placeholder-title {
    font-size: 1.5rem; font-weight: 700; color: rgba(255,255,255,.8);
  }
  .placeholder-sub {
    font-size: .9rem; color: rgba(255,255,255,.35); max-width: 320px;
  }
  .placeholder-link {
    font-size: .8rem;
    color: rgba(255,204,0,.6);
    text-decoration: none;
    border: 1px solid rgba(255,204,0,.3);
    padding: .4rem 1rem;
    border-radius: 4px;
    transition: all .2s;
  }
  .placeholder-link:hover { background: rgba(255,204,0,.1); color: var(--accent); }

  #top-bar {
    position: fixed; top: 0; left: 0; right: 0; z-index: 20;
    padding: .75rem 1.25rem;
    display: flex; align-items: center; justify-content: space-between;
    background: linear-gradient(to bottom, rgba(0,0,0,.7), transparent);
    pointer-events: none;
  }
  #ad-label {
    font-size: .7rem; letter-spacing: .12em; text-transform: uppercase;
    color: rgba(255,255,255,.5);
    background: rgba(0,0,0,.4);
    padding: .2rem .6rem;
    border-radius: 3px;
    border: 1px solid rgba(255,255,255,.1);
  }

  #skip-btn {
    position: fixed;
    bottom: 28px;
    right: 28px;
    z-index: 50;

    display: inline-flex;
    align-items: center;
    gap: .5rem;

    padding: .55rem 1.1rem;
    background: var(--skip-bg);
    border: 1px solid var(--skip-border);
    border-radius: var(--radius);
    backdrop-filter: blur(8px);
    cursor: default;
    user-select: none;

    font-family: var(--font);
    font-size: .82rem;
    font-weight: 600;
    color: rgba(255,255,255,.75);

    transition: background .2s, border-color .2s, color .2s, transform .1s;
  }

  #skip-ring {
    position: relative;
    width: 22px; height: 22px;
    flex-shrink: 0;
  }
  #skip-ring svg {
    transform: rotate(-90deg);
    width: 22px; height: 22px;
  }
  #skip-ring circle {
    fill: none;
    stroke-width: 2.5;
    stroke-linecap: round;
  }
  #ring-bg   { stroke: rgba(255,255,255,.15); }
  #ring-fill {
    stroke: var(--accent);
    stroke-dasharray: 56.5; /* 2π × 9 */
    stroke-dashoffset: 0;
    transition: stroke-dashoffset .9s linear;
  }
  #ring-num {
    position: absolute; inset: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: .62rem; font-weight: 700; color: var(--accent);
  }

  #skip-btn.ready {
    cursor: pointer;
    background: var(--accent);
    border-color: var(--accent);
    color: #000;
    animation: skipPulse .6s ease;
  }
  #skip-btn.ready #ring-fill { stroke: #000; }
  #skip-btn.ready #ring-bg   { stroke: rgba(0,0,0,.2); }
  #skip-btn.ready #ring-num  { color: #000; }
  #skip-btn.ready:hover { transform: scale(1.04); filter: brightness(1.1); }

  @keyframes skipPulse {
    0%   { transform: scale(1); }
    40%  { transform: scale(1.08); }
    100% { transform: scale(1); }
  }

  body { animation: fadeIn .5s ease; }
  @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
</style>
</head>
<body>

<div id="top-bar">
  <span id="ad-label">Advertisement</span>
</div>

<div id="ad-container">
  <?php if ($adType === 'image'): ?>
    <img id="ad-image"
         src="<?= htmlspecialchars($adFile) ?>"
         alt="Advertisement">

  <?php elseif ($adType === 'video'): ?>
    <video id="ad-video" autoplay muted playsinline loop>
      <source src="<?= htmlspecialchars($adFile) ?>"
              type="<?= htmlspecialchars($adMime) ?>">
      Your browser does not support this video format.
    </video>

  <?php else: ?>
    <div id="ad-placeholder">
      <div class="placeholder-icon">📺</div>
      <div class="placeholder-title">Ad Space</div>
      <div class="placeholder-sub">No advertisement media has been uploaded yet. Visit the admin panel to upload an image or video.</div>
      <a href="admin.php" class="placeholder-link">Open Admin Panel</a>
    </div>
  <?php endif; ?>
</div>

<div id="skip-btn" onclick="handleSkip()" title="Skip advertisement">
  <div id="skip-ring">
    <svg viewBox="0 0 22 22">
      <circle id="ring-bg"   cx="11" cy="11" r="9"/>
      <circle id="ring-fill" cx="11" cy="11" r="9"/>
    </svg>
    <div id="ring-num" id="ring-num">5</div>
  </div>
  <span id="skip-text">Skip in 5</span>
</div>

<script>
  (function () {
    const COUNTDOWN    = 5;             // seconds
    const REDIRECT_URL = 'emailotp.php';

    const btn      = document.getElementById('skip-btn');
    const ringFill = document.getElementById('ring-fill');
    const ringNum  = document.getElementById('ring-num');
    const skipText = document.getElementById('skip-text');

    const circumference = 2 * Math.PI * 9; // ≈ 56.5
    let remaining = COUNTDOWN;
    let ready = false;

    ringFill.style.strokeDasharray  = circumference;
    ringFill.style.strokeDashoffset = '0';

    function tick() {
      remaining--;

      const progress = remaining / COUNTDOWN;
      ringFill.style.strokeDashoffset = ((1 - progress) * circumference).toFixed(2);

      if (remaining > 0) {
        ringNum.textContent  = remaining;
        skipText.textContent = 'Skip in ' + remaining;
        setTimeout(tick, 1000);
      } else {
        ringNum.textContent  = '›';
        skipText.textContent = 'Skip Ad';
        btn.classList.add('ready');
        btn.title = 'Click to continue';
        ready = true;
      }
    }

    setTimeout(tick, 1000);

    window.handleSkip = function () {
      if (!ready) return;
      window.location.href = REDIRECT_URL;
    };
  })();
</script>
</body>
</html>
