<?php
/** Sheet parity: searchable-by-month, paginated read-only logbook. */
declare(strict_types=1);
$logData = $logsData ?? ['rows' => [], 'page' => 1, 'pages' => 1, 'total' => 0, 'month' => null];
$logMonthKey = $logData['month'];
$rangeStart = $logData['total'] > 0 ? (($logData['page'] - 1) * $logData['per_page'] + 1) : 0;
$rangeEnd = min($logData['total'], $logData['page'] * $logData['per_page']);
$logHref = static function (int $page) use ($logMonthKey): string {
    $href = '/app?v=logs&page=' . $page;
    return $logMonthKey ? $href . '&m=' . rawurlencode($logMonthKey) : $href . '&m=all';
};
?>
<header class="page-intro">
  <div class="eyebrow"><span class="eyebrow-dot"></span> DATABASE HISTORY <span class="eyebrow-divider">/</span> LOGS</div>
  <h1>Completion log</h1>
  <p>One row per completed habit and date. Unchecking a day removes that log; duplicate rows cannot be created.</p>
</header>

<div class="log-toolbar">
  <div class="log-filter-label">
    <strong><?= $logMonthKey ? hh_e(hh_date($state['first_day'])->format('F Y')) : 'All history' ?></strong>
    <span class="small muted"><?= number_format((int) $logData['total']) ?> total completion<?= (int) $logData['total'] === 1 ? '' : 's' ?></span>
  </div>
  <div class="log-filter-actions">
    <a class="btn btn-sm <?= $logMonthKey ? 'btn-ghost' : 'btn-primary' ?>" href="<?= hh_e(hh_url('/app?v=logs&m=all')) ?>">All history</a>
    <a class="btn btn-sm <?= $logMonthKey ? 'btn-primary' : 'btn-ghost' ?>" href="<?= hh_e(hh_url('/app?v=logs&m=' . $state['month_key'])) ?>">Selected month</a>
  </div>
</div>

<?php if ($logData['rows']): ?>
  <div class="table-wrap card">
    <table class="mini log-table">
      <thead><tr><th>Timestamp</th><th>Date</th><th>Habit ID</th><th>Habit name</th><th>Status</th><th>XP earned</th></tr></thead>
      <tbody>
        <?php foreach ($logData['rows'] as $row): ?>
          <tr>
            <td class="mono small"><?= hh_e(date('d M Y H:i', strtotime((string) $row['created_at']))) ?></td>
            <td class="mono"><?= hh_e(hh_date((string) $row['log_date'])->format('d M Y')) ?></td>
            <td class="mono small">HH-<?= (int) $row['habit_id'] ?></td>
            <td><?= hh_e((string) $row['habit_name']) ?></td>
            <td><span class="badge ok"><?= hh_e((string) $row['status']) ?></span></td>
            <td class="mono">+<?= (int) $row['xp'] ?> XP</td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="pagination">
    <span class="small muted">Showing <?= $rangeStart ?>–<?= $rangeEnd ?> of <?= number_format((int) $logData['total']) ?></span>
    <div class="row">
      <?php if ($logData['page'] > 1): ?><a class="btn btn-sm btn-ghost" href="<?= hh_e(hh_url($logHref($logData['page'] - 1))) ?>">← Newer</a><?php endif; ?>
      <span class="small muted">Page <?= (int) $logData['page'] ?> of <?= (int) $logData['pages'] ?></span>
      <?php if ($logData['page'] < $logData['pages']): ?><a class="btn btn-sm btn-ghost" href="<?= hh_e(hh_url($logHref($logData['page'] + 1))) ?>">Older →</a><?php endif; ?>
    </div>
  </div>
<?php else: ?>
  <div class="empty">No completion logs in this view yet. Mark a scheduled habit on Today, Week, or Month to create the first one.</div>
<?php endif; ?>
