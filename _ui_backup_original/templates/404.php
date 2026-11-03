<?php
declare(strict_types=1);
?>
<!doctype html>
<html lang="en" class="theme-ember">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Not found · Hector Habit</title>
<link rel="stylesheet" href="<?= hh_e(hh_url('/assets/app.css')) ?>">
</head>
<body>
<div class="auth-shell">
  <div class="auth-card">
    <div class="wordmark">Hector <span>Habit</span></div>
    <h1 style="margin-top:1rem; font-size:1.4rem">That page does not exist.</h1>
    <p class="muted small">The app has five places: Today, Week, Month, Plan, Look. Nothing else.</p>
    <a class="btn btn-primary btn-block" href="<?= hh_e(hh_url('/app')) ?>">Back to the dashboard</a>
  </div>
</div>
</body>
</html>
