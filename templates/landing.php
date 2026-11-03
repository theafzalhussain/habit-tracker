<?php
/**
 * Marketing page rendered by PHP — a working stand-in for the WordPress page
 * (wordpress/landing.html is the same copy, paste-ready into a Custom HTML
 * block). The CTA points at {APP}/login. It holds no habit data.
 */
declare(strict_types=1);

$loginUrl = hh_url('/login');
$signupUrl = hh_url('/signup');

// Purely decorative preview grid (static, not real data).
$preview = [
    'Deep Work'  => [1, 1, 1, 0, 1, 1, 1],
    'Gym'        => [1, 1, 2, 1, 1, 1, -1],
    'Hydration'  => [1, 1, 1, 1, 1, 0, 0],
    'Clean Fuel' => [1, 2, 1, 1, 1, -1, -1],
    'Read'       => [1, 1, 1, 1, 0, 0, 0],
];
?>
<!doctype html>
<html lang="en" class="theme-ember">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="dark">
<meta name="theme-color" content="#08090b">
<title>Hector Habit — the grid that shows the miss</title>
<meta name="description" content="A private habit grid for one person. ₹199 a year. 3-day trial, no card.">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='7' fill='%230b0c0f'/><path d='M9 22V10h3v4.4h5V10h3v12h-3v-4.6h-5V22z' fill='%23e3a857'/></svg>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&family=Geist+Mono:wght@400;500&family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;1,9..144,400;1,9..144,500&display=swap">
<link rel="stylesheet" href="<?= hh_e(hh_url('/assets/app.css')) ?>">
</head>
<body>
<div class="landing">
  <nav class="landing-nav" aria-label="Site">
    <div class="brand">
      <span class="brand-mark" aria-hidden="true">
        <svg viewBox="0 0 28 28">
          <rect x="1.5" y="1.5" width="25" height="25" rx="8" fill="none" stroke="currentColor" stroke-width="1.5"/>
          <rect x="6" y="6" width="6" height="6" rx="2" fill="currentColor"/>
          <rect x="16" y="6" width="6" height="6" rx="2" fill="currentColor" opacity=".5"/>
          <rect x="6" y="16" width="6" height="6" rx="2" fill="currentColor" opacity=".5"/>
          <rect x="16" y="16" width="6" height="6" rx="2" fill="currentColor"/>
        </svg>
      </span>
      <span class="wordmark">Hector <span>Habit</span></span>
    </div>
    <div class="landing-nav-actions" style="display:flex;align-items:center;gap:.6rem">
      <a class="btn btn-sm btn-ghost" href="<?= hh_e($loginUrl) ?>">Login</a>
      <a class="btn btn-sm btn-primary" href="<?= hh_e($signupUrl) ?>">Sign up</a>
    </div>
  </nav>

  <main>
    <section class="landing-hero">
      <div class="landing-copy">
        <span class="landing-pill"><span class="eyebrow-dot"></span> Private habit grid · ₹199 / year</span>
        <h1>The day you skip is the day you <em>do not get back.</em></h1>
        <p class="sub">A missed box is not a small thing. It is the receipt of a decision you already made.</p>
        <div class="landing-cta">
          <a class="btn btn-primary" style="min-height:52px;padding:.85rem 1.85rem;font-size:1.02rem;border-radius:14px" href="<?= hh_e($signupUrl) ?>">
            Start trial
            <svg width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 10h10M11 6l4 4-4 4"/></svg>
          </a>
        </div>
      </div>

      <div class="landing-visual" aria-hidden="true">
        <div class="lv-head">
          <span class="lv-title">This week</span>
          <span class="lv-streak">🔥 12d</span>
        </div>
        <div class="lv-row lv-days">
          <span></span><span>M</span><span>T</span><span>W</span><span>T</span><span>F</span><span>S</span><span>S</span>
        </div>
        <?php foreach ($preview as $name => $cells): ?>
          <div class="lv-row">
            <span><?= hh_e($name) ?></span>
            <?php foreach ($cells as $c): ?>
              <i class="<?= $c === 1 ? 'on' : ($c === 2 ? 'miss' : ($c === -1 ? 'off' : '')) ?>"></i>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
        <div class="lv-foot">
          <span>⚡ Lightning · 86%</span>
          <div class="progress-track"><span style="width:86%"></span></div>
        </div>
      </div>
    </section>

    <div class="intro">
      <p>A private habit grid for one person — you.</p>
      <p>If the list already exists in your head or on paper, this keeps the score.</p>
      <p>Streak, XP, day / week / month, four themes, no noise.</p>
    </div>

    <div class="pricebox">
      <div>
        <span class="eyebrow-small">Annual membership</span>
        <div class="price">₹199 <small>/ year</small></div>
        <p class="muted">3-day trial. Then ₹199 once a year.</p>
      </div>
      <div class="pricebox-cta">
        <a class="btn btn-primary" href="<?= hh_e($signupUrl) ?>">Start trial</a>
        <p class="small faint">Affiliate coupons apply at checkout.</p>
      </div>
    </div>
  </main>

  <div class="foot">
    <p class="quote">“Obsession is a word the lazy use to describe the dedicated.”</p>
    <p>Hector Habit · Afzal Hussain · <a href="<?= hh_e($loginUrl) ?>">Login</a></p>
  </div>
</div>
</body>
</html>
