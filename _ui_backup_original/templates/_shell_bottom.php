<?php
declare(strict_types=1);
/** Closes the shell opened by _shell_top.php and boots the JS. */
?>
</main>

<footer class="app-footer">
  <div class="quote">"Obsession is a word the lazy use to describe the dedicated."</div>
  <div class="small" style="margin-top:.5rem;display:flex;align-items:center;justify-content:center;gap:.6rem;flex-wrap:wrap">
    <span style="color:var(--faint)">Hector Habit</span>
    <span style="color:var(--line)">·</span>
    <?php if (!empty($access['active']) && $access['reason'] === 'trial'): ?>
      <span style="color:var(--accent);font-weight:600">Trial</span>
      <span style="color:var(--faint)">ends <?= hh_e($access['until']->format('d M Y')) ?> (<?= (int) $access['days_left'] ?> days left)</span>
      <span style="color:var(--line)">·</span>
    <?php elseif (!empty($access['active'])): ?>
      <span style="color:var(--good);font-weight:600">✓ Active</span>
      <span style="color:var(--faint)">until <?= hh_e($access['until']->format('d M Y')) ?></span>
      <span style="color:var(--line)">·</span>
    <?php endif; ?>
    <a href="<?= hh_e(hh_url('/billing')) ?>" style="color:var(--muted)">Plan</a>
    <span style="color:var(--line)">·</span>
    <a href="<?= hh_e(hh_url('/app?v=look')) ?>" style="color:var(--muted)">Look</a>
  </div>
</footer>

<div id="toast" role="status" aria-live="polite"></div>

<script>
window.HECTOR = <?= json_encode([
    'base'  => rtrim(hh_base_path(), '/'),
    'csrf'  => hh_csrf_token(),
    'theme' => in_array((string) $user['theme'], HH_THEMES, true) ? (string) $user['theme'] : 'ember',
    'profile' => isset($profileData) ? [
        'level' => (int) $profileData['level'],
        'rank_emoji' => (string) $profileData['rank']['emoji'],
        'rank_label' => (string) $profileData['rank']['label'],
    ] : null,
    'state' => $state ?? null,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="<?= hh_e(hh_url('/assets/app.js')) ?>" defer></script>
<?php if (!empty($razorpayScript)): ?>
<script src="https://checkout.razorpay.com/v1/checkout.js" defer></script>
<?php endif; ?>
</body>
</html>
