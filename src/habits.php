<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/http.php';

/**
 * Habit domain logic. Every rule here mirrors the Google Sheet script that this
 * app replaces (section 6 of the build prompt):
 *
 *   due = has a name AND real day of that month AND start_date <= day AND
 *         ( every_day | days_of_week flag | every_x_days interval )
 */

const HH_FREQ_LABELS = [
    'every_day'     => 'Every day',
    'days_of_week'  => 'Days of the week',
    'every_x_days'  => 'Repeat every X days',
];

function hh_habits(PDO $pdo, int $user_id): array
{
    $stmt = $pdo->prepare('SELECT * FROM habits WHERE user_id = ? AND archived_at IS NULL ORDER BY sort_order, id');
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

/** Archived rows remain visible in Profile and Logs; their history is never deleted. */
function hh_archived_habits(PDO $pdo, int $user_id): array
{
    $stmt = $pdo->prepare('SELECT * FROM habits WHERE user_id = ? AND archived_at IS NOT NULL ORDER BY sort_order, id');
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

function hh_habit_by_id(PDO $pdo, int $user_id, int $habit_id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM habits WHERE id = ? AND user_id = ? AND archived_at IS NULL LIMIT 1');
    $stmt->execute([$habit_id, $user_id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function hh_freq_label(array $habit): string
{
    $freq = (string) $habit['freq'];
    if ($freq === 'every_x_days') {
        $n = (int) ($habit['interval_days'] ?? 0);
        return $n > 0 ? 'Every ' . $n . ' days' : 'Repeat every X days';
    }
    return HH_FREQ_LABELS[$freq] ?? $freq;
}

function hh_date(string $date): DateTimeImmutable
{
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    $errors = DateTimeImmutable::getLastErrors();
    if (!$d || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $d->format('Y-m-d') !== $date) {
        throw new InvalidArgumentException('Bad date: ' . $date);
    }
    return $d;
}

function hh_today(): string
{
    return (new DateTimeImmutable('today'))->format('Y-m-d');
}

/** Is this habit due on this calendar day? */
function hh_is_due(array $habit, string $date): bool
{
    $name = trim((string) $habit['name']);
    if ($name === '' || empty($habit['start_date'])) {
        return false;
    }
    $day = hh_date($date);
    $start = hh_date((string) $habit['start_date']);
    if ($day < $start) {
        return false;
    }

    switch ((string) $habit['freq']) {
        case 'every_day':
            return true;

        case 'days_of_week':
            $key = strtolower($day->format('D')); // mon..sun
            return (int) ($habit[$key] ?? 0) === 1;

        case 'every_x_days':
            $interval = (int) ($habit['interval_days'] ?? 0);
            if ($interval <= 0) {
                return false;
            }
            $diff = (int) $start->diff($day)->days;
            return $diff % $interval === 0;

        default:
            return false;
    }
}

function hh_month_bounds(int $year, int $month): array
{
    $days = (int) (new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month)))->format('t');
    return [
        'days'  => $days,
        'first' => sprintf('%04d-%02d-01', $year, $month),
        'last'  => sprintf('%04d-%02d-%02d', $year, $month, $days),
    ];
}

/** All logs of a user as a lookup set: date => habit_id => xp */
function hh_log_map(PDO $pdo, int $user_id, ?string $from = null, ?string $to = null): array
{
    $sql = 'SELECT habit_id, log_date, xp FROM logs WHERE user_id = ?';
    $args = [$user_id];
    if ($from !== null && $to !== null) {
        $sql .= ' AND log_date BETWEEN ? AND ?';
        $args[] = $from;
        $args[] = $to;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($args);

    $map = [];
    foreach ($stmt->fetchAll() as $row) {
        $map[(string) $row['log_date']][(int) $row['habit_id']] = (int) $row['xp'];
    }
    return $map;
}

/**
 * Streak = consecutive due-days, ending at the latest due-day on or before
 * today, that are checked. An unchecked latest due-day means streak 0.
 * Non-due days never break a streak.
 */
function hh_streak(array $habit, array $date_map, string $today): int
{
    $start = hh_date((string) $habit['start_date']);
    $cursor = hh_date($today);
    $id = (int) $habit['id'];

    while ($cursor >= $start && !hh_is_due($habit, $cursor->format('Y-m-d'))) {
        $cursor = $cursor->modify('-1 day');
    }
    if ($cursor < $start) {
        return 0; // nothing is ever due
    }

    $streak = 0;
    while ($cursor >= $start) {
        $key = $cursor->format('Y-m-d');
        if (hh_is_due($habit, $key)) {
            if (isset($date_map[$key][$id])) {
                $streak++;
            } else {
                break;
            }
        }
        $cursor = $cursor->modify('-1 day');
    }
    return $streak;
}

/**
 * Full state for one month, shaped exactly like what the browser needs to
 * render every view (today / week / month / desktop grid) and to re-sync after
 * a toggle.
 */
function hh_state(PDO $pdo, array $user, int $year, int $month): array
{
    $user_id = (int) $user['id'];
    $bounds = hh_month_bounds($year, $month);
    $today = hh_today();
    $todayObj = hh_date($today);

    $habits = hh_habits($pdo, $user_id);
    $monthLogs = hh_log_map($pdo, $user_id, $bounds['first'], $bounds['last']);
    $allLogs = hh_log_map($pdo, $user_id);

    $days = [];
    for ($d = 1; $d <= $bounds['days']; $d++) {
        $iso = sprintf('%04d-%02d-%02d', $year, $month, $d);
        $days[] = [
            'day'     => $d,
            'iso'     => $iso,
            'weekday' => (int) hh_date($iso)->format('N'), // 1 = Mon .. 7 = Sun
            'future'  => $iso > $today,
            'today'   => $iso === $today,
        ];
    }

    // Build a complete seven-day window independently of month boundaries.
    // The active month determines the anchor, not the dates we can render.
    $isCurrentMonth = ((int) $todayObj->format('n') === $month && (int) $todayObj->format('Y') === $year);
    $anchor = $isCurrentMonth ? hh_date($today) : hh_date($bounds['first']);
    $weekStart = $anchor->modify('monday this week');
    $week = [];
    for ($i = 0; $i < 7; $i++) {
        $d = $weekStart->modify('+' . $i . ' day');
        $week[] = [
            'iso'      => $d->format('Y-m-d'),
            'day'      => (int) $d->format('j'),
            'weekday'  => (int) $d->format('N'),
            'future'   => $d->format('Y-m-d') > $today,
            'today'    => $d->format('Y-m-d') === $today,
            'in_month' => (int) $d->format('n') === $month && (int) $d->format('Y') === $year,
        ];
    }

    $xp_month = 0;
    foreach ($monthLogs as $habitMap) {
        foreach ($habitMap as $xp) {
            $xp_month += (int) $xp;
        }
    }

    $habitStates = [];
    $perDay = []; // day => ['due' => n, 'done' => n]
    $weekPerDay = []; // ISO date => ['due' => n, 'done' => n]
    // Today is a calendar day, regardless of which month is selected in the UI.
    $dueToday = 0;
    $doneToday = 0;
    foreach ($habits as $habit) {
        if (hh_is_due($habit, $today)) {
            $dueToday++;
            if (isset($allLogs[$today][(int) $habit['id']])) {
                $doneToday++;
            }
        }
    }

    foreach ($habits as $habit) {
        $cells = [];
        $scheduled = 0;
        $hit = 0;
        $id = (int) $habit['id'];

        foreach ($days as $dayInfo) {
            $due = hh_is_due($habit, $dayInfo['iso']);
            $checked = $due && isset($monthLogs[$dayInfo['iso']][$id]);
            if ($due) {
                $scheduled++;
                if ($checked) {
                    $hit++;
                }
                $perDay[$dayInfo['day']]['due'] = ($perDay[$dayInfo['day']]['due'] ?? 0) + 1;
                if ($checked) {
                    $perDay[$dayInfo['day']]['done'] = ($perDay[$dayInfo['day']]['done'] ?? 0) + 1;
                }
            }
            $cells[] = [
                'day'     => $dayInfo['day'],
                'iso'     => $dayInfo['iso'],
                'due'     => $due,
                'checked' => $checked,
                'future'  => $dayInfo['future'],
                'today'   => $dayInfo['today'],
            ];
        }

        $todayDue = hh_is_due($habit, $today);
        $todayChecked = $todayDue && isset($allLogs[$today][$id]);
        $weekCells = [];
        $weekScheduled = 0;
        $weekHit = 0;
        foreach ($week as $weekDay) {
            $weekDue = hh_is_due($habit, $weekDay['iso']);
            $weekChecked = $weekDue && isset($allLogs[$weekDay['iso']][$id]);
            if ($weekDue) {
                $weekScheduled++;
                $weekPerDay[$weekDay['iso']]['due'] = ($weekPerDay[$weekDay['iso']]['due'] ?? 0) + 1;
                if ($weekChecked) {
                    $weekHit++;
                    $weekPerDay[$weekDay['iso']]['done'] = ($weekPerDay[$weekDay['iso']]['done'] ?? 0) + 1;
                }
            }
            $weekCells[] = [
                'day'      => $weekDay['day'],
                'iso'      => $weekDay['iso'],
                'weekday'  => $weekDay['weekday'],
                'due'      => $weekDue,
                'checked'  => $weekChecked,
                'future'   => $weekDay['future'],
                'today'    => $weekDay['today'],
                'in_month' => $weekDay['in_month'],
            ];
        }

        $habitStates[] = [
            'id'             => $id,
            'name'           => (string) $habit['name'],
            'xp'             => (int) $habit['xp'],
            'freq'           => (string) $habit['freq'],
            'freq_label'     => hh_freq_label($habit),
            'start_date'     => (string) $habit['start_date'],
            'streak'         => hh_streak($habit, $allLogs, $today),
            'scheduled'      => $scheduled,
            'hit'            => $hit,
            'cells'          => $cells,
            'today_cell'     => [
                'day'     => (int) $todayObj->format('j'),
                'iso'     => $today,
                'due'     => $todayDue,
                'checked' => $todayChecked,
                'future'  => false,
                'today'   => true,
            ],
            'week_scheduled' => $weekScheduled,
            'week_hit'       => $weekHit,
            'week_cells'     => $weekCells,
        ];
    }

    // Daily success % (the sheet's "Daily Success %" row).
    $daily = [];
    foreach ($days as $dayInfo) {
        $due = $perDay[$dayInfo['day']]['due'] ?? 0;
        $done = $perDay[$dayInfo['day']]['done'] ?? 0;
        $pct = $due > 0 ? (int) round($done * 100 / $due) : null;
        $daily[] = [
            'day'          => $dayInfo['day'],
            'iso'          => $dayInfo['iso'],
            'due'          => $due,
            'done'         => $done,
            'pct'          => $pct,
            'achievement'  => hh_achievement_for_percent($pct),
            'future'       => $dayInfo['future'],
        ];
    }

    // Attach per-day achievement badges to each week cell after all habits are aggregated.
    $weekSummary = [];
    foreach ($week as $index => $weekDay) {
        $due = (int) ($weekPerDay[$weekDay['iso']]['due'] ?? 0);
        $done = (int) ($weekPerDay[$weekDay['iso']]['done'] ?? 0);
        $pct = $due > 0 ? (int) round($done * 100 / $due) : null;
        $week[$index]['due'] = $due;
        $week[$index]['done'] = $done;
        $week[$index]['pct'] = $pct;
        $week[$index]['achievement'] = hh_achievement_for_percent($pct);
        $weekSummary[$weekDay['iso']] = $week[$index]['achievement'];
    }
    foreach ($habitStates as &$habitState) {
        foreach ($habitState['week_cells'] as &$weekCell) {
            $weekCell['achievement'] = $weekSummary[$weekCell['iso']] ?? null;
        }
        unset($weekCell);
    }
    unset($habitState);

    $totalScheduled = 0;
    $totalHit = 0;
    foreach ($habitStates as $h) {
        $totalScheduled += $h['scheduled'];
        $totalHit += $h['hit'];
    }
    $todayPct = $dueToday > 0 ? (int) round($doneToday * 100 / $dueToday) : null;

    return [
        'year'             => $year,
        'month'            => $month,
        'month_key'        => sprintf('%04d-%02d', $year, $month),
        'month_label'      => hh_date($bounds['first'])->format('F Y'),
        'days_in_month'    => $bounds['days'],
        'is_current_month' => $isCurrentMonth,
        'today'            => $today,
        'today_day'        => (int) $todayObj->format('j'),
        'first_day'        => $bounds['first'],
        'last_day'         => $bounds['last'],
        'days'             => $days,
        'week'             => $week,
        'habits'           => $habitStates,
        'daily'            => $daily,
        'stats'            => [
            'done_today'         => $doneToday,
            'due_today'          => $dueToday,
            'xp_month'           => $xp_month,
            'success_pct_today'  => $todayPct,
            'today_achievement'  => hh_achievement_for_percent($todayPct),
            'month_hit'          => $totalHit,
            'month_scheduled'    => $totalScheduled,
            'month_pct'          => $totalScheduled > 0 ? (int) round($totalHit * 100 / $totalScheduled) : 0,
        ],
    ];
}

/**
 * Check or uncheck one day.
 * Checking: INSERT IGNORE (the unique key makes duplicates impossible).
 * Unchecking: DELETE that exact row.
 * A day the habit is not due never gets a log row.
 */
function hh_toggle(PDO $pdo, int $user_id, int $habit_id, string $date, bool $checked): bool
{
    $habit = hh_habit_by_id($pdo, $user_id, $habit_id);
    if (!$habit || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return false;
    }
    if ($checked && !hh_is_due($habit, $date)) {
        return false; // not a due day — refuse to write a log
    }

    if ($checked) {
        $stmt = $pdo->prepare(
            'INSERT IGNORE INTO logs (user_id, habit_id, log_date, xp) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$user_id, $habit_id, $date, (int) $habit['xp']]);
    } else {
        $stmt = $pdo->prepare('DELETE FROM logs WHERE user_id = ? AND habit_id = ? AND log_date = ?');
        $stmt->execute([$user_id, $habit_id, $date]);
    }
    return true;
}

/** Month <select> options: 23 months back through 3 months ahead. */
function hh_month_options(int $selected_year, int $selected_month): array
{
    $cursor = (new DateTimeImmutable('first day of this month'))->modify('-23 months');
    $end = (new DateTimeImmutable('first day of this month'))->modify('+3 months');
    $out = [];
    while ($cursor <= $end) {
        $out[] = [
            'value'    => $cursor->format('Y-m'),
            'label'    => $cursor->format('F Y'),
            'selected' => ((int) $cursor->format('n') === $selected_month && (int) $cursor->format('Y') === $selected_year),
        ];
        $cursor = $cursor->modify('+1 month');
    }
    return $out;
}

function hh_weekday_labels(): array
{
    return [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];
}

/** Achievement tier used by the Sheet's daily-success row. */
function hh_achievement_for_percent(?int $percent): ?array
{
    if ($percent === null) {
        return null; // no habit was scheduled for that date
    }
    $percent = max(0, min(100, $percent));
    if ($percent === 100) return ['emoji' => '👑', 'name' => 'Crown', 'copy' => 'Absolute perfection', 'tier' => 'crown'];
    if ($percent >= 80) return ['emoji' => '⚡', 'name' => 'Lightning', 'copy' => 'Excellent momentum', 'tier' => 'lightning'];
    if ($percent >= 60) return ['emoji' => '🔥', 'name' => 'Fire', 'copy' => 'Good consistency', 'tier' => 'fire'];
    if ($percent >= 40) return ['emoji' => '🛡️', 'name' => 'Shield', 'copy' => 'Holding the line', 'tier' => 'shield'];
    if ($percent >= 20) return ['emoji' => '⚔️', 'name' => 'Swords', 'copy' => 'Fighting back', 'tier' => 'swords'];
    if ($percent > 0) return ['emoji' => '🩸', 'name' => 'Drop', 'copy' => 'You survived, but barely', 'tier' => 'drop'];
    return ['emoji' => '💀', 'name' => 'Skull', 'copy' => 'Penalty zone — reset tomorrow', 'tier' => 'skull'];
}

function hh_rank_for_level(int $level): array
{
    $level = max(1, $level);
    if ($level < 5) return ['label' => 'Novice', 'emoji' => '🥉'];
    if ($level < 10) return ['label' => 'Adept', 'emoji' => '🥈'];
    if ($level < 20) return ['label' => 'Elite', 'emoji' => '🥇'];
    if ($level < 50) return ['label' => 'Master', 'emoji' => '💎'];
    return ['label' => 'Legend', 'emoji' => '👑'];
}

/** Global Profile totals and per-habit lifetime stats, including archived habits. */
function hh_profile_stats(PDO $pdo, int $user_id): array
{
    $stmt = $pdo->prepare('SELECT COUNT(*) AS completions, COALESCE(SUM(xp), 0) AS xp FROM logs WHERE user_id = ?');
    $stmt->execute([$user_id]);
    $totals = $stmt->fetch() ?: ['completions' => 0, 'xp' => 0];
    $totalXp = (int) $totals['xp'];
    $level = intdiv($totalXp, 100) + 1;
    $rank = hh_rank_for_level($level);

    $stmt = $pdo->prepare(
        'SELECT h.id, h.name, h.xp, h.archived_at,
                COUNT(l.id) AS lifetime_completions, COALESCE(SUM(l.xp), 0) AS total_xp
           FROM habits h
           LEFT JOIN logs l ON l.habit_id = h.id AND l.user_id = h.user_id
          WHERE h.user_id = ?
          GROUP BY h.id, h.name, h.xp, h.archived_at
          ORDER BY h.sort_order, h.id'
    );
    $stmt->execute([$user_id]);
    $perHabit = $stmt->fetchAll();

    return [
        'total_completions' => (int) $totals['completions'],
        'total_xp' => $totalXp,
        'level' => $level,
        'rank' => $rank,
        'xp_into_level' => $totalXp % 100,
        'xp_to_next' => 100 - ($totalXp % 100),
        'level_progress_pct' => $totalXp % 100,
        'active_habits' => count(hh_habits($pdo, $user_id)),
        'habits' => $perHabit,
    ];
}

/** Paged logbook. Passing a YYYY-MM value filters one month; null is all history. */
function hh_user_logs(PDO $pdo, int $user_id, int $page = 1, int $perPage = 50, ?string $monthKey = null): array
{
    $page = max(1, $page);
    $perPage = max(10, min(100, $perPage));
    $where = ' WHERE l.user_id = ?';
    $args = [$user_id];
    if ($monthKey !== null && preg_match('/^(\d{4})-(\d{1,2})$/', $monthKey, $m)) {
        $year = max(1900, min(2200, (int) $m[1]));
        $month = max(1, min(12, (int) $m[2]));
        $bounds = hh_month_bounds($year, $month);
        $where .= ' AND l.log_date BETWEEN ? AND ?';
        $args[] = $bounds['first'];
        $args[] = $bounds['last'];
    } else {
        $monthKey = null;
    }
    $count = $pdo->prepare('SELECT COUNT(*) FROM logs l' . $where);
    $count->execute($args);
    $total = (int) $count->fetchColumn();
    $offset = ($page - 1) * $perPage;
    $stmt = $pdo->prepare(
        'SELECT l.id, l.created_at, l.log_date, l.habit_id, h.name AS habit_name,
                l.xp, "Completed" AS status
           FROM logs l
           JOIN habits h ON h.id = l.habit_id AND h.user_id = l.user_id'
        . $where . ' ORDER BY l.log_date DESC, l.id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset
    );
    $stmt->execute($args);
    return [
        'rows' => $stmt->fetchAll(),
        'page' => $page,
        'per_page' => $perPage,
        'total' => $total,
        'pages' => max(1, (int) ceil($total / $perPage)),
        'month' => $monthKey,
    ];
}

/** Create or edit one owner-scoped habit. Each habit has its own XP reward and due rule. */
function hh_save_habit(PDO $pdo, int $user_id, array $input): array
{
    $name = trim((string) ($input['name'] ?? ''));
    $chars = preg_match_all('/./us', $name, $matches);
    if ($name === '' || $chars === false || $chars > 191 || strlen($name) > 764) {
        return ['ok' => false, 'error' => 'Habit name is required and must be 191 characters or fewer.'];
    }
    $freq = (string) ($input['freq'] ?? '');
    if (!in_array($freq, ['every_day', 'days_of_week', 'every_x_days'], true)) {
        return ['ok' => false, 'error' => 'Choose a valid schedule.'];
    }
    $startDate = (string) ($input['start_date'] ?? '');
    try {
        hh_date($startDate);
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'Choose a valid start date.'];
    }
    $xp = filter_var($input['xp'] ?? 5, FILTER_VALIDATE_INT);
    if ($xp === false || $xp < 1 || $xp > 100) {
        return ['ok' => false, 'error' => 'XP reward must be between 1 and 100.'];
    }
    $interval = null;
    $flags = ['mon' => 0, 'tue' => 0, 'wed' => 0, 'thu' => 0, 'fri' => 0, 'sat' => 0, 'sun' => 0];
    if ($freq === 'every_x_days') {
        $interval = filter_var($input['interval_days'] ?? null, FILTER_VALIDATE_INT);
        if ($interval === false || $interval < 1 || $interval > 365) {
            return ['ok' => false, 'error' => 'Repeat interval must be between 1 and 365 days.'];
        }
    } elseif ($freq === 'days_of_week') {
        foreach (array_keys($flags) as $day) {
            $flags[$day] = in_array((string) ($input[$day] ?? ''), ['1', 'on', 'true'], true) ? 1 : 0;
        }
        if (array_sum($flags) === 0) {
            return ['ok' => false, 'error' => 'Select at least one weekday.'];
        }
    }

    $id = (int) ($input['id'] ?? 0);
    if ($id > 0) {
        $check = $pdo->prepare('SELECT id FROM habits WHERE id = ? AND user_id = ? AND archived_at IS NULL LIMIT 1');
        $check->execute([$id, $user_id]);
        if (!$check->fetchColumn()) {
            return ['ok' => false, 'error' => 'That active habit was not found.'];
        }
        try {
            $stmt = $pdo->prepare(
                'UPDATE habits
                    SET name = ?, xp = ?, freq = ?, interval_days = ?, start_date = ?,
                        mon = ?, tue = ?, wed = ?, thu = ?, fri = ?, sat = ?, sun = ?
                  WHERE id = ? AND user_id = ? AND archived_at IS NULL'
            );
            $stmt->execute([$name, $xp, $freq, $interval, $startDate,
                $flags['mon'], $flags['tue'], $flags['wed'], $flags['thu'], $flags['fri'], $flags['sat'], $flags['sun'], $id, $user_id]);
            return ['ok' => true, 'id' => $id, 'message' => 'Habit updated. Existing log history is retained.'];
        } catch (PDOException $e) {
            return ['ok' => false, 'error' => 'That habit name is already in use.'];
        }
    }

    $count = $pdo->prepare('SELECT COUNT(*) FROM habits WHERE user_id = ? AND archived_at IS NULL');
    $count->execute([$user_id]);
    if ((int) $count->fetchColumn() >= 20) {
        return ['ok' => false, 'error' => 'The Sheet layout supports up to 20 active habits. Archive one before adding another.'];
    }
    $dup = $pdo->prepare('SELECT id FROM habits WHERE user_id = ? AND name = ? LIMIT 1');
    $dup->execute([$user_id, $name]);
    if ($dup->fetchColumn()) {
        return ['ok' => false, 'error' => 'A habit with that name already exists in this account or its archive. Restore it or choose another name.'];
    }
    $order = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM habits WHERE user_id = ?');
    $order->execute([$user_id]);
    $sortOrder = (int) $order->fetchColumn();
    $stmt = $pdo->prepare(
        'INSERT INTO habits (user_id, name, xp, freq, interval_days, start_date,
             mon, tue, wed, thu, fri, sat, sun, sort_order)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$user_id, $name, $xp, $freq, $interval, $startDate,
        $flags['mon'], $flags['tue'], $flags['wed'], $flags['thu'], $flags['fri'], $flags['sat'], $flags['sun'], $sortOrder]);
    return ['ok' => true, 'id' => (int) $pdo->lastInsertId(), 'message' => 'Habit added.'];
}

/** Archive, do not delete: lifetime XP and logbook rows stay available. */
function hh_archive_habit(PDO $pdo, int $user_id, int $habit_id): bool
{
    $stmt = $pdo->prepare('UPDATE habits SET archived_at = CURDATE() WHERE id = ? AND user_id = ? AND archived_at IS NULL');
    $stmt->execute([$habit_id, $user_id]);
    return $stmt->rowCount() === 1;
}

function hh_restore_habit(PDO $pdo, int $user_id, int $habit_id): bool
{
    $count = $pdo->prepare('SELECT COUNT(*) FROM habits WHERE user_id = ? AND archived_at IS NULL');
    $count->execute([$user_id]);
    if ((int) $count->fetchColumn() >= 20) {
        return false;
    }
    $stmt = $pdo->prepare('UPDATE habits SET archived_at = NULL WHERE id = ? AND user_id = ? AND archived_at IS NOT NULL');
    $stmt->execute([$habit_id, $user_id]);
    return $stmt->rowCount() === 1;
}

/** Years covered by this account's habit start dates/logs plus two planning years. */
function hh_year_options(PDO $pdo, int $user_id, int $selectedYear): array
{
    $stmt = $pdo->prepare('SELECT MIN(start_date) FROM habits WHERE user_id = ?');
    $stmt->execute([$user_id]);
    $firstHabit = (string) ($stmt->fetchColumn() ?: '');
    $stmt = $pdo->prepare('SELECT MIN(log_date) FROM logs WHERE user_id = ?');
    $stmt->execute([$user_id]);
    $firstLog = (string) ($stmt->fetchColumn() ?: '');
    $dates = array_values(array_filter([$firstHabit, $firstLog], static fn ($d) => $d !== ''));
    $currentYear = (int) (new DateTimeImmutable('today'))->format('Y');
    $firstYear = $dates ? (int) substr(min($dates), 0, 4) : $currentYear;
    $firstYear = min($firstYear, $selectedYear);
    $lastYear = max($currentYear + 2, $selectedYear);
    $years = [];
    for ($year = $firstYear; $year <= $lastYear; $year++) {
        $years[] = $year;
    }
    return $years;
}
