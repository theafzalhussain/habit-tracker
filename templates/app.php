<?php
/**
 * App views: Today | Week | Month | Look
 * Plan lives at /billing (templates/billing.php) — same shell.
 *
 * Expects: $user, $access, $state, $stats
 */
declare(strict_types=1);

$activeTab = (string) ($_GET['v'] ?? 'today');
if (!in_array($activeTab, ['today', 'week', 'month', 'habits', 'profile', 'logs', 'look'], true)) {
    $activeTab = 'today';
}

$titleMap = [
    'today'   => 'Today · Hector Habit',
    'week'    => 'Week · Hector Habit',
    'month'   => 'Month · Hector Habit',
    'habits'  => 'Habits · Hector Habit',
    'profile' => 'Profile · Hector Habit',
    'logs'    => 'Logs · Hector Habit',
    'look'    => 'Look · Hector Habit',
];
$title = $titleMap[$activeTab];

require __DIR__ . '/_shell_top.php';

$s = $state['stats'];
$dueToday = (int) $s['due_today'];
$doneToday = (int) $s['done_today'];
$todayLabel = hh_date($state['today'])->format('l, d F Y');
$weekStartLabel = hh_date($state['week'][0]['iso'])->format('d M');
$weekEndLabel = hh_date($state['week'][6]['iso'])->format('d M Y');
?>

<?php if ($activeTab === 'today'): ?>

  <header class="page-intro">
    <div class="eyebrow"><span class="eyebrow-dot"></span> DAILY PRACTICE <span class="eyebrow-divider">/</span> <?= hh_e($todayLabel) ?></div>
    <h1>Today</h1>
    <p>Focus on what is scheduled. Mark it when the work is done.</p>
  </header>

  <section class="stats" aria-label="Today at a glance">
    <div class="stat">
      <div class="stat-head"><span class="stat-icon" aria-hidden="true"><svg viewBox="0 0 20 20"><path d="m4 10 4 4 8-8"/></svg></span><div class="k">Done today</div></div>
      <div class="v" id="stat-done"><?= $doneToday ?></div>
    </div>
    <div class="stat">
      <div class="stat-head"><span class="stat-icon" aria-hidden="true"><svg viewBox="0 0 20 20"><rect x="3" y="4.5" width="14" height="13" rx="2"/><path d="M6.5 2.5v4M13.5 2.5v4M3 8h14"/></svg></span><div class="k">Due today</div></div>
      <div class="v" id="stat-due"><?= $dueToday ?> <small>scheduled today</small></div>
    </div>
    <div class="stat">
      <div class="stat-head"><span class="stat-icon" aria-hidden="true"><svg viewBox="0 0 20 20"><path d="m11.5 2.5-7 8h4.3l-.4 7 7.1-9h-4.3z"/></svg></span><div class="k">XP this month</div></div>
      <div class="v" id="stat-xp"><?= (int) $s['xp_month'] ?> <small>XP earned</small></div>
    </div>
  </section>

  <section class="daily-progress" aria-label="Daily completion progress">
    <div class="daily-progress-copy"><span>Daily completion</span><strong id="today-progress-copy"><?= $dueToday > 0 ? $doneToday . ' of ' . $dueToday . ' completed' : 'No habits scheduled today' ?></strong></div>
    <div class="progress-track" id="today-progress" role="progressbar" aria-label="Today's completion" aria-valuemin="0" aria-valuemax="<?= max(1, $dueToday) ?>" aria-valuenow="<?= $doneToday ?>"><span id="today-progress-fill" style="width:<?= $dueToday > 0 ? (int) round($doneToday * 100 / $dueToday) : 0 ?>%"></span></div>
  </section>

  <section class="achievement-callout" id="today-achievement" aria-live="polite">
    <?php $todayAchievement = $s['today_achievement'] ?? null; ?>
    <span class="achievement-mark" id="today-achievement-mark"><?= $todayAchievement ? hh_e($todayAchievement['emoji']) : '—' ?></span>
    <div><span class="eyebrow-small">TODAY’S ACHIEVEMENT</span>
      <strong id="today-achievement-title"><?= $todayAchievement ? hh_e($todayAchievement['name'] . ' · ' . $s['success_pct_today'] . '%') : 'No scheduled habits' ?></strong>
      <span class="achievement-copy" id="today-achievement-copy"><?= $todayAchievement ? hh_e($todayAchievement['copy']) : 'Nothing is due today.' ?></span>
    </div>
  </section>

  <div class="section-head">
    <h2>Scheduled habits</h2>
    <span class="hint">Select a habit to record it. Your progress saves immediately.</span>
  </div>
  <div class="habit-list" id="habit-list" data-view="today"></div>

<?php elseif ($activeTab === 'week'): ?>

  <header class="page-intro">
    <div class="eyebrow"><span class="eyebrow-dot"></span> WEEKLY REVIEW <span class="eyebrow-divider">/</span> <?= hh_e($weekStartLabel) ?> – <?= hh_e($weekEndLabel) ?></div>
    <h1>This week</h1>
    <p>See the rhythm across seven days. Unscheduled dates stay disabled.</p>
  </header>

  <section class="stats" aria-label="Progress summary">
    <div class="stat"><div class="stat-head"><span class="stat-icon" aria-hidden="true"><svg viewBox="0 0 20 20"><path d="m4 10 4 4 8-8"/></svg></span><div class="k">Done today</div></div><div class="v" id="stat-done"><?= $doneToday ?></div></div>
    <div class="stat"><div class="stat-head"><span class="stat-icon" aria-hidden="true"><svg viewBox="0 0 20 20"><rect x="3" y="4.5" width="14" height="13" rx="2"/><path d="M6.5 2.5v4M13.5 2.5v4M3 8h14"/></svg></span><div class="k">Due today</div></div><div class="v" id="stat-due"><?= $dueToday ?> <small>scheduled today</small></div></div>
    <div class="stat"><div class="stat-head"><span class="stat-icon" aria-hidden="true"><svg viewBox="0 0 20 20"><path d="m11.5 2.5-7 8h4.3l-.4 7 7.1-9h-4.3z"/></svg></span><div class="k">XP this month</div></div><div class="v" id="stat-xp"><?= (int) $s['xp_month'] ?> <small>XP earned</small></div></div>
  </section>

  <div class="section-head">
    <h2>Seven-day schedule</h2>
    <span class="hint"><?= $state['is_current_month'] ? 'Current ISO week.' : 'First week of ' . hh_e($state['month_label']) . '.' ?> Dates can cross month boundaries.</span>
  </div>
  <div class="habit-list" id="habit-list" data-view="week"></div>

<?php elseif ($activeTab === 'month'): ?>

  <header class="page-intro">
    <div class="eyebrow"><span class="eyebrow-dot"></span> MONTHLY GRID <span class="eyebrow-divider">/</span> <?= hh_e($state['month_label']) ?></div>
    <h1>Month at a glance</h1>
    <p>A complete, database-backed view of every scheduled day in <?= hh_e($state['month_label']) ?>.</p>
  </header>

  <section class="stats" aria-label="Monthly progress summary">
    <div class="stat"><div class="stat-head"><span class="stat-icon" aria-hidden="true"><svg viewBox="0 0 20 20"><path d="m4 10 4 4 8-8"/></svg></span><div class="k">Month completion</div></div><div class="v" id="stat-month"><?= (int) $s['month_pct'] ?>% <small><?= (int) $s['month_hit'] ?>/<?= (int) $s['month_scheduled'] ?> scheduled</small></div></div>
    <div class="stat"><div class="stat-head"><span class="stat-icon" aria-hidden="true"><svg viewBox="0 0 20 20"><path d="m11.5 2.5-7 8h4.3l-.4 7 7.1-9h-4.3z"/></svg></span><div class="k">XP this month</div></div><div class="v" id="stat-xp"><?= (int) $s['xp_month'] ?> <small>XP earned</small></div></div>
    <div class="stat"><div class="stat-head"><span class="stat-icon" aria-hidden="true"><svg viewBox="0 0 20 20"><rect x="3" y="4.5" width="14" height="13" rx="2"/><path d="M6.5 2.5v4M13.5 2.5v4M3 8h14"/></svg></span><div class="k">Due today</div></div><div class="v" id="stat-due"><?= $dueToday ?> <small>scheduled today</small></div></div>
  </section>

  <div class="section-head">
    <h2>Habit grid</h2>
    <span class="hint">Each column is a calendar day. On a phone, scroll inside the grid.</span>
  </div>

  <div class="grid-wrap">
    <table class="grid">
      <thead>
        <tr>
          <th class="col-habit">Habit</th>
          <?php foreach ($state['days'] as $d): ?>
            <?php $achievement = $state['daily'][$d['day'] - 1]['achievement'] ?? null; ?>
            <th class="cell" title="<?= hh_e($d['iso']) ?><?= $achievement ? ' · ' . hh_e($achievement['name'] . ': ' . $achievement['copy']) : '' ?>">
              <?= (int) $d['day'] ?><br>
              <span class="small faint"><?= hh_e(substr(hh_date($d['iso'])->format('D'), 0, 1)) ?></span>
              <?php if ($achievement): ?><span class="grid-achievement" aria-label="<?= hh_e($achievement['name']) ?>"><?= hh_e($achievement['emoji']) ?></span><?php endif; ?>
            </th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody id="grid-body"></tbody>
      <tfoot>
        <tr class="pct-row" id="grid-pct"></tr>
      </tfoot>
    </table>
  </div>

  <div class="legend">
    <span><i class="on"></i> checked</span>
    <span><i></i> scheduled, unchecked</span>
    <span><i class="off"></i> not scheduled</span>
    <span>Rows with no schedule at all are dashed — that is the sheet's behaviour too.</span>
  </div>

  <div class="section-head" style="margin-top:1.6rem">
    <h2>Cards</h2>
    <span class="hint">Day buttons scroll inside the card.</span>
  </div>
  <div class="habit-list" id="habit-list" data-view="month"></div>

<?php elseif ($activeTab === 'habits'): ?>

  <?php require __DIR__ . '/habits.php'; ?>

<?php elseif ($activeTab === 'profile'): ?>

  <?php require __DIR__ . '/profile.php'; ?>

<?php elseif ($activeTab === 'logs'): ?>

  <?php require __DIR__ . '/logs.php'; ?>

<?php else: /* look */ ?>

  <header class="page-intro">
    <div class="eyebrow"><span class="eyebrow-dot"></span> ACCOUNT SETTINGS</div>
    <h1>Look &amp; account</h1>
    <p>Choose a palette, manage your password, and review your account status.</p>
  </header>

  <div class="section-head">
    <h2>Theme</h2>
    <span class="hint">Your choice is saved to your account and follows you to any device.</span>
  </div>

  <div class="swatches" style="margin-bottom:.4rem">
    <?php
    $themeMeta = [
        'ember' => ['Ember', 'Default. Champagne gold on obsidian.', '#e3a857', '#08090b'],
        'ink'   => ['Ink', 'Sapphire blue on midnight.', '#7cb2ff', '#05070d'],
        'moss'  => ['Moss', 'Jade green on deep forest.', '#78d6a0', '#060b09'],
        'paper' => ['Paper', 'Light theme. Warm ivory, burnt accent.', '#b4501f', '#f4efe4'],
    ];
    foreach ($themeMeta as $id => [$name, $desc, $accent, $bg]):
        $isActive = ($user['theme'] ?? 'ember') === $id;
    ?>
      <button type="button" class="swatch <?= $isActive ? 'is-active' : '' ?>" data-theme="<?= hh_e($id) ?>">
        <span class="dot" style="--sw-bg: <?= hh_e($bg) ?>; --sw-accent: <?= hh_e($accent) ?>;" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i></span>
        <span class="nm"><?= hh_e($name) ?></span>
        <span class="ds"><?= hh_e($desc) ?></span>
      </button>
    <?php endforeach; ?>
  </div>

  <div class="settings-grid">
    <section>
      <div class="section-head"><h2>Password</h2></div>
      <div class="card">
        <form id="password-form" autocomplete="off">
          <div class="field">
            <label for="current">Current password</label>
            <input type="password" id="current" name="current" required autocomplete="current-password">
          </div>
          <div class="field">
            <label for="newpw">New password (min 8 characters)</label>
            <input type="password" id="newpw" name="newpw" minlength="8" required autocomplete="new-password">
          </div>
          <button type="submit" class="btn-primary">Update password</button>
          <p class="small muted" id="password-msg" role="status" aria-live="polite" style="margin:.7rem 0 0"></p>
        </form>
      </div>
    </section>

    <section>
      <div class="section-head"><h2>Account</h2></div>
      <div class="card">
        <div class="kv"><span>Username</span><span class="v"><?= hh_e((string) $user['username']) ?></span></div>
        <?php if (!empty($user['email'])): ?>
          <div class="kv"><span>Email</span><span class="v"><?= hh_e((string) $user['email']) ?></span></div>
        <?php endif; ?>
        <div class="kv"><span>Trial ends</span><span class="v"><?= hh_e(hh_date((string) $user['trial_ends_at'])->format('d M Y')) ?></span></div>
        <div class="kv"><span>Paid until</span><span class="v"><?= $user['paid_until'] ? hh_e(hh_date((string) $user['paid_until'])->format('d M Y')) : '—' ?></span></div>
        <div class="kv"><span>Price</span><span class="v">₹199 / year</span></div>
        <div class="kv"><span>Logs this month</span><span class="v"><?= (int) ($stats['logs_in_month'] ?? 0) ?></span></div>
      </div>
    </section>
  </div>

<?php endif; ?>

<noscript>
  <div class="empty" style="margin-top:1rem">This dashboard needs JavaScript for the day grid. The database rules still apply — open the Month tab in a JS-enabled browser.</div>
</noscript>

<?php require __DIR__ . '/_shell_bottom.php'; ?>
