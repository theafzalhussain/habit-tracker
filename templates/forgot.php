<?php
/**
 * Forgot password. Reset password using username or email + new password.
 * No recovery key required.
 *
 * Expects: $error, $username.
 */
declare(strict_types=1);

$theme = 'ember';
?>
<!doctype html>
<html lang="en" class="theme-<?= hh_e($theme) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="color-scheme" content="dark">
<meta name="theme-color" content="#08090b">
<title>Reset password · Hector Habit</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='8' fill='%230b0c0f'/><path d='M9 22V10h3v4.4h5V10h3v12h-3v-4.6h-5V22z' fill='%23e3a857'/></svg>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&family=Geist+Mono:wght@400;500&family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;1,9..144,400;1,9..144,500&display=swap">
<link rel="stylesheet" href="<?= hh_e(hh_url('/assets/app.css')) ?>">
</head>
<body>
<main class="auth-shell">
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

    <h1 class="auth-title">Reset your password</h1>
    <p class="lede">Enter your username or email address, then set a new password.</p>

    <?php if (!empty($error)): ?>
      <div class="alert alert-error" role="alert"><?= hh_e($error) ?></div>
    <?php endif; ?>

    <div class="auth-divider">RESET PASSWORD</div>

    <form method="post" action="<?= hh_e(hh_url('/forgot')) ?>" id="forgot-form" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= hh_e(hh_csrf_token()) ?>">

      <div class="field">
        <label for="username">Username or Email</label>
        <div class="auth-input-group">
          <span class="auth-input-icon" aria-hidden="true">
            <svg viewBox="0 0 20 20"><circle cx="10" cy="7" r="3.5"/><path d="M3 18c0-3.9 3.1-7 7-7s7 3.1 7 7"/></svg>
          </span>
          <input type="text" id="username" name="username" required autofocus
                 autocomplete="username" autocapitalize="none" spellcheck="false"
                 value="<?= hh_e($username ?? '') ?>" placeholder="your username or email">
        </div>
      </div>

      <div class="field">
        <label for="new_password">New password</label>
        <div class="auth-input-group">
          <span class="auth-input-icon" aria-hidden="true">
            <svg viewBox="0 0 20 20"><rect x="4" y="9" width="12" height="9" rx="2"/><path d="M7 9V6a3 3 0 0 1 6 0v3"/></svg>
          </span>
          <input type="password" id="new_password" name="new_password" required minlength="8"
                 autocomplete="new-password" placeholder="at least 8 characters">
        </div>
      </div>

      <div class="field">
        <label for="confirm_password">Confirm new password</label>
        <div class="auth-input-group">
          <span class="auth-input-icon" aria-hidden="true">
            <svg viewBox="0 0 20 20"><rect x="4" y="9" width="12" height="9" rx="2"/><path d="M7 9V6a3 3 0 0 1 6 0v3"/><path d="m7.8 13.6 1.6 1.6 2.9-3"/></svg>
          </span>
          <input type="password" id="confirm_password" name="confirm_password" required minlength="8"
                 autocomplete="new-password" placeholder="type it again">
        </div>
      </div>

      <button type="submit" class="btn-primary btn-block auth-submit">
        Reset password
        <svg width="16" height="16" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 10h10M11 6l4 4-4 4"/></svg>
      </button>

      <a class="auth-back" href="<?= hh_e(hh_url('/login')) ?>">
        <svg width="14" height="14" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 10H5M9 6l-4 4 4 4"/></svg>
        Back to Login
      </a>
    </form>

    <div class="auth-foot">
      <em>"Obsession is a word the lazy use to describe the dedicated."</em>
    </div>

  </div>
</main>
<script src="<?= hh_e(hh_url('/assets/app.js')) ?>" defer></script>
</body>
</html>
