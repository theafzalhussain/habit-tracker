<?php
/** Login. No signup link. No forgot password. Credentials only. */
declare(strict_types=1);

$theme = 'ember';
?>
<!doctype html>
<html lang="en" class="theme-<?= hh_e($theme) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Sign in · Hector Habit</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='8' fill='%230b0c0f'/><path d='M9 22V10h3v4.4h5V10h3v12h-3v-4.6h-5V22z' fill='%23e8a54b'/></svg>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Geist:wght@300;400;500;600;700&family=Geist+Mono:wght@400;500&family=Fraunces:opsz,wght@9..144,300;400;600&display=swap">
<link rel="stylesheet" href="<?= hh_e(hh_url('/assets/app.css')) ?>">
<style>
  /* Login-specific micro-polish */
  .auth-logo-mark {
    display: flex; align-items: center; gap: .6rem;
    margin-bottom: .9rem;
  }
  .auth-logo-icon {
    width: 40px; height: 40px; flex: 0 0 40px;
    color: var(--accent);
    filter: drop-shadow(0 0 10px var(--accent-glow));
  }
  .auth-logo-icon svg { display: block; width: 100%; height: 100%; }
  .auth-card .wordmark { font-size: 1.6rem; }
  .auth-divider {
    display: flex; align-items: center; gap: .75rem;
    margin: 1.1rem 0; color: var(--faint); font-size: .75rem; letter-spacing: .06em;
  }
  .auth-divider::before, .auth-divider::after {
    content: ''; flex: 1; height: 1px; background: var(--line);
  }
  .auth-input-group { position: relative; }
  .auth-input-icon {
    position: absolute; left: .85rem; top: 50%; transform: translateY(-50%);
    color: var(--faint); pointer-events: none;
    display: flex; align-items: center;
  }
  .auth-input-icon svg { width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 1.6; stroke-linecap: round; stroke-linejoin: round; }
  .auth-input-group input { padding-left: 2.5rem; }
</style>
</head>
<body>
<div class="auth-shell">
  <div class="auth-card">

    <!-- Brand -->
    <div class="auth-logo-mark">
      <span class="auth-logo-icon" aria-hidden="true">
        <svg viewBox="0 0 28 28">
          <rect x="1.5" y="1.5" width="25" height="25" rx="8" fill="none" stroke="currentColor" stroke-width="1.5"/>
          <rect x="6" y="6" width="6" height="6" rx="2" fill="currentColor"/>
          <rect x="16" y="6" width="6" height="6" rx="2" fill="currentColor" opacity=".5"/>
          <rect x="6" y="16" width="6" height="6" rx="2" fill="currentColor" opacity=".5"/>
          <rect x="16" y="16" width="6" height="6" rx="2" fill="currentColor"/>
        </svg>
      </span>
      <div class="wordmark">Hector <span>Habit</span></div>
    </div>

    <p class="lede">Private access only. Sign in with your credentials.</p>

    <?php if (!empty($error)): ?>
      <div class="alert alert-error"><?= hh_e($error) ?></div>
    <?php endif; ?>

    <div class="auth-divider">CREDENTIALS</div>

    <form method="post" action="<?= hh_e(hh_url('/login')) ?>" autocomplete="on">
      <input type="hidden" name="csrf" value="<?= hh_e(hh_csrf_token()) ?>">
      <div class="field">
        <label for="username">Username</label>
        <div class="auth-input-group">
          <span class="auth-input-icon" aria-hidden="true">
            <svg viewBox="0 0 20 20"><circle cx="10" cy="7" r="3.5"/><path d="M3 18c0-3.9 3.1-7 7-7s7 3.1 7 7"/></svg>
          </span>
          <input type="text" id="username" name="username" required autofocus
                 autocomplete="username" value="<?= hh_e($username ?? '') ?>" placeholder="your username">
        </div>
      </div>
      <div class="field">
        <label for="password">Password</label>
        <div class="auth-input-group">
          <span class="auth-input-icon" aria-hidden="true">
            <svg viewBox="0 0 20 20"><rect x="4" y="9" width="12" height="9" rx="2"/><path d="M7 9V6a3 3 0 0 1 6 0v3"/></svg>
          </span>
          <input type="password" id="password" name="password" required autocomplete="current-password" placeholder="••••••••">
        </div>
      </div>
      <button type="submit" class="btn-primary btn-block" style="margin-top:.25rem;height:48px;font-size:.95rem;border-radius:12px;">
        Sign in
        <svg width="16" height="16" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 10h10M11 6l4 4-4 4"/></svg>
      </button>
    </form>

    <div class="auth-foot">
      <em>"Obsession is a word the lazy use to describe the dedicated."</em>
    </div>

  </div>
</div>
</body>
</html>
