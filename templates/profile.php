<?php
/** Sheet parity: Profile totals, per-habit lifetime completions, and rank. */
declare(strict_types=1);
$p = $profileData ?? hh_profile_stats(hh_db(), (int) $user['id']);
?>
<header class="page-intro">
  <div class="eyebrow"><span class="eyebrow-dot"></span> LIFETIME PROGRESS <span class="eyebrow-divider">/</span> PROFILE</div>
  <h1>Your system rank</h1>
  <p>Every completed habit earns XP. Your rank and lifetime records stay backed by the database.</p>
</header>

<section class="stats" aria-label="Lifetime profile statistics">
  <div class="stat"><div class="stat-head"><span class="stat-icon" aria-hidden="true"><svg viewBox="0 0 20 20"><path d="m11.5 2.5-7 8h4.3l-.4 7 7.1-9h-4.3z"/></svg></span><div class="k">Total XP</div></div><div class="v"><?= number_format($p['total_xp']) ?> <small>XP earned</small></div></div>
  <div class="stat"><div class="stat-head"><span class="stat-icon" aria-hidden="true"><svg viewBox="0 0 20 20"><path d="M10 2.5 12.3 7l5 .7-3.6 3.5.9 5-4.6-2.4-4.6 2.4.9-5L2.7 7.7l5-.7z"/></svg></span><div class="k">Current level</div></div><div class="v"><?= (int) $p['level'] ?> <small><?= hh_e($p['rank']['emoji'] . ' ' . $p['rank']['label']) ?></small></div></div>
  <div class="stat"><div class="stat-head"><span class="stat-icon" aria-hidden="true"><svg viewBox="0 0 20 20"><path d="m4 10 4 4 8-8"/></svg></span><div class="k">Lifetime completions</div></div><div class="v"><?= number_format($p['total_completions']) ?> <small>logged days</small></div></div>
  <div class="stat"><div class="stat-head"><span class="stat-icon" aria-hidden="true"><svg viewBox="0 0 20 20"><rect x="3" y="3" width="6" height="6" rx="1.5"/><rect x="11" y="3" width="6" height="6" rx="1.5"/><rect x="3" y="11" width="6" height="6" rx="1.5"/><rect x="11" y="11" width="6" height="6" rx="1.5"/></svg></span><div class="k">Active habits</div></div><div class="v"><?= (int) $p['active_habits'] ?> <small>of 20 slots</small></div></div>
</section>

<section class="rank-card" aria-label="Level progress">
  <div class="rank-card-top">
    <div>
      <span class="eyebrow-small">CURRENT RANK</span>
      <h2><?= hh_e($p['rank']['emoji'] . ' ' . $p['rank']['label']) ?> <span class="rank-level">Level <?= (int) $p['level'] ?></span></h2>
    </div>
    <div class="rank-next"><strong><?= (int) $p['xp_to_next'] ?> XP</strong><span>to next level</span></div>
  </div>
  <div class="progress-track" role="progressbar" aria-label="Progress to next level" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= (int) $p['level_progress_pct'] ?>"><span style="width:<?= (int) $p['level_progress_pct'] ?>%"></span></div>
  <p class="small muted" style="margin:.55rem 0 0">Levels 1–4 Novice · 5–9 Adept · 10–19 Elite · 20–49 Master · 50+ Legend. Each level takes 100 XP.</p>
</section>

<div class="section-head">
  <h2>Lifetime by habit</h2>
  <span class="hint">Past logs remain here even if a habit is archived.</span>
</div>
<div class="table-wrap card">
  <table class="mini profile-table">
    <thead><tr><th>Habit</th><th>XP reward</th><th>Lifetime completions</th><th>Total XP</th><th>Status</th></tr></thead>
    <tbody>
      <?php foreach ($p['habits'] as $habit): ?>
        <tr>
          <td><strong><?= hh_e((string) $habit['name']) ?></strong></td>
          <td class="mono"><?= (int) $habit['xp'] ?> XP</td>
          <td class="mono"><?= number_format((int) $habit['lifetime_completions']) ?></td>
          <td class="mono"><?= number_format((int) $habit['total_xp']) ?> XP</td>
          <td><span class="badge <?= $habit['archived_at'] ? '' : 'ok' ?>"><?= $habit['archived_at'] ? 'Archived' : 'Active' ?></span></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$p['habits']): ?><tr><td colspan="5" class="muted">No habits yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
