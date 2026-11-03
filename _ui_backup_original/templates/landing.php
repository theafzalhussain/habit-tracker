<?php
/**
 * Marketing page rendered by PHP — a working stand-in for the WordPress page
 * (wordpress/landing.html is the same copy, paste-ready into a Custom HTML
 * block). The CTA points at {APP}/login. It holds no habit data.
 */
declare(strict_types=1);

$loginUrl = hh_url('/login');
?>
<!doctype html>
<html lang="en" class="theme-ember">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Hector Habit — the grid that shows the miss</title>
<meta name="description" content="A private habit grid for one person. ₹199 a year. 3-day trial, no card.">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='7' fill='%230b0c0f'/><path d='M9 22V10h3v4.4h5V10h3v12h-3v-4.6h-5V22z' fill='%23e8a54b'/></svg>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600&family=IBM+Plex+Sans:wght@400;500;600&display=swap">
<link rel="stylesheet" href="<?= hh_e(hh_url('/assets/app.css')) ?>">
</head>
<body>
<div class="landing">
  <div class="brand">Hector <span style="color:var(--accent)">Habit</span></div>

  <h1>The day you skip is the day you <em>do not get back.</em></h1>
  <p class="sub">A missed box is not a small thing. It is the receipt of a decision you already made.</p>

  <div class="intro">
    <p>A private habit grid for one person — you.</p>
    <p>If the list already exists in your head or on paper, this keeps the score.</p>
    <p>Streak, XP, day / week / month, four themes, no noise.</p>
  </div>

  <div class="pricebox">
    <div class="price">₹199 <small>/ year</small></div>
    <p class="muted">3-day trial. Then ₹199 once a year.</p>
    <a class="btn btn-primary" href="<?= hh_e($loginUrl) ?>">Start trial</a>
    <p class="small faint" style="margin:.7rem 0 0">Affiliate coupons apply at checkout.</p>
  </div>

  <div class="foot">
    <p class="quote">“Obsession is a word the lazy use to describe the dedicated.”</p>
    <p>Hector Habit · Afzal Hussain · <a href="<?= hh_e($loginUrl) ?>">Sign in</a></p>
  </div>
</div>
</body>
</html>
