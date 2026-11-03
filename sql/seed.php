<?php
declare(strict_types=1);

/**
 * Hector Habit — first-run seed. CLI ONLY.
 *
 *   HECTOR_ADMIN_PASSWORD='choose-a-strong-one' php sql/seed.php
 *
 * Refuses to run when HECTOR_ADMIN_PASSWORD is missing, so no password is ever
 * committed to the repo, to a template, to a comment, or to JavaScript.
 *
 * What it does:
 *   1. Creates the first user (`afzal`) with a hashed password + 3-day trial.
 *   2. Seeds the 10 habits exactly as they were in the Google Sheet.
 *   3. Imports the existing log rows (duplicates collapse onto the unique key).
 *
 * Safe to re-run: existing users/habits/logs are detected, not duplicated.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("seed.php is CLI only.\n");
}

require_once __DIR__ . '/../src/db.php';

$cfg = hh_config();

$password = hh_env('HECTOR_ADMIN_PASSWORD');
if ($password === null || trim($password) === '') {
    fwrite(STDERR, "Refusing to seed: set HECTOR_ADMIN_PASSWORD first.\n");
    fwrite(STDERR, "  HECTOR_ADMIN_PASSWORD='your-password' php sql/seed.php\n");
    exit(1);
}
if (strlen($password) < 8) {
    fwrite(STDERR, "Refusing to seed: HECTOR_ADMIN_PASSWORD must be at least 8 characters.\n");
    exit(1);
}

$pdo = hh_db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "Hector Habit seed\n";
echo "Database: {$cfg['db_name']} @ {$cfg['db_host']}\n\n";

/* ---------------------------------------------------------------- 1. user */
$username = 'afzal';
$stmt = $pdo->prepare('SELECT id, paid_until, trial_ends_at FROM users WHERE username = ? LIMIT 1');
$stmt->execute([$username]);
$user = $stmt->fetch();

if ($user) {
    $user_id = (int) $user['id'];
    $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
        ->execute([password_hash($password, PASSWORD_DEFAULT), $user_id]);
    echo "• user '{$username}' already exists (id {$user_id}) — password hash refreshed from HECTOR_ADMIN_PASSWORD.\n";
} else {
    $stmt = $pdo->prepare(
        'INSERT INTO users (username, password_hash, theme, trial_ends_at, paid_until)
         VALUES (?, ?, "ember", DATE_ADD(CURDATE(), INTERVAL ? DAY), NULL)'
    );
    $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), (int) $cfg['trial_days']]);
    $user_id = (int) $pdo->lastInsertId();
    echo "• user '{$username}' created (id {$user_id}), trial {$cfg['trial_days']} days, theme ember.\n";
}

/* -------------------------------------------------------------- 2. habits */
// sort_order 1..10, xp 5 each, start_date 2026-01-01, flags exactly as in the
// sheet (note: habit 2 has every weekday flag false, so it is due on 0 days —
// that is intentional, do not "fix" it).
$habits = [
    [1,  '💻 Deep Work / Study (4+ Hours)',            'every_day',    null, 0,0,0,0,0,0,0],
    [2,  '📖 Read & Upskill (30+ Mins)',               'days_of_week', null, 0,0,0,0,0,0,0],
    [3,  '🚫 Dopamine Detox (No Porn / Fap)',          'every_day',    null, 0,0,0,0,0,0,0],
    [4,  '🏋️‍♂️ Gym',                                    'days_of_week', null, 1,1,1,1,1,1,0],
    [5,  '🚶‍♂️ Daily Movement (6,000+ Steps)',           'every_day',    null, 0,0,0,0,0,0,0],
    [6,  '🍎 Clean Fuel (No Sugar / Fast Food)',       'days_of_week', null, 1,1,1,1,1,0,0],
    [7,  '💧 Hydration (4+ Liters of Water)',          'every_day',    null, 0,0,0,0,0,0,0],
    [8,  '🧘‍♂️ Meditation / Breathwork (10 Mins)',       'every_day',    null, 0,0,0,0,0,0,0],
    [9,  '📵 Digital Curfew (No Screens Before Bed)',  'every_day',    null, 0,0,0,0,0,0,0],
    [10, '🗓️ Plan Tomorrow (Tonight)',                 'every_x_days', 2,    0,0,0,0,0,0,0],
];

$insertHabit = $pdo->prepare(
    'INSERT INTO habits
        (user_id, name, xp, freq, interval_days, start_date, mon, tue, wed, thu, fri, sat, sun, sort_order)
     VALUES (?, ?, 5, ?, ?, "2026-01-01", ?, ?, ?, ?, ?, ?, ?, ?)'
);

$existing = $pdo->prepare('SELECT COUNT(*) FROM habits WHERE user_id = ?');
$existing->execute([$user_id]);
$created = 0;

if ((int) $existing->fetchColumn() === 0) {
    foreach ($habits as $h) {
        [$sort, $name, $freq, $interval, $mon, $tue, $wed, $thu, $fri, $sat, $sun] = $h;
        $insertHabit->execute([$user_id, $name, $freq, $interval, $mon, $tue, $wed, $thu, $fri, $sat, $sun, $sort]);
        $created++;
    }
    echo "• {$created} habits seeded (xp 5 each, start 2026-01-01, sort 1-10).\n";
} else {
    echo "• habits already present — skipped.\n";
}

/* ---------------------------------------------------------------- 3. logs */
$ids = [];
$stmt = $pdo->prepare('SELECT id, name FROM habits WHERE user_id = ?');
$stmt->execute([$user_id]);
foreach ($stmt->fetchAll() as $row) {
    $ids[(string) $row['name']] = (int) $row['id'];
}

// Date + habit name. Identical rows in the sheet collapse onto the unique key.
$seedLogs = [
    ['2026-07-02', '🏋️‍♂️ Gym'],
    ['2026-07-12', '💻 Deep Work / Study (4+ Hours)'],
    ['2026-07-12', '📖 Read & Upskill (30+ Mins)'],
    ['2026-07-12', '🚫 Dopamine Detox (No Porn / Fap)'],
    ['2026-07-12', '🚶‍♂️ Daily Movement (6,000+ Steps)'],
    ['2026-07-12', '💧 Hydration (4+ Liters of Water)'],
    ['2026-07-12', '🧘‍♂️ Meditation / Breathwork (10 Mins)'],
    ['2026-07-12', '📵 Digital Curfew (No Screens Before Bed)'],
    ['2026-07-12', '🗓️ Plan Tomorrow (Tonight)'],
    ['2026-08-10', '📵 Digital Curfew (No Screens Before Bed)'],
    ['2026-08-10', '🍎 Clean Fuel (No Sugar / Fast Food)'],
    ['2026-08-10', '🏋️‍♂️ Gym'],
    ['2026-08-10', '🚶‍♂️ Daily Movement (6,000+ Steps)'],
    ['2026-08-10', '💧 Hydration (4+ Liters of Water)'],
    ['2026-08-10', '🧘‍♂️ Meditation / Breathwork (10 Mins)'],
    ['2026-08-10', '💻 Deep Work / Study (4+ Hours)'],
    ['2026-08-03', '💻 Deep Work / Study (4+ Hours)'],
    ['2026-08-03', '🚫 Dopamine Detox (No Porn / Fap)'],
];

$insertLog = $pdo->prepare(
    'INSERT INTO logs (user_id, habit_id, log_date, xp) VALUES (?, ?, ?, 5)
     ON DUPLICATE KEY UPDATE xp = VALUES(xp)'
);

$inserted = 0;
$pdo->beginTransaction();
foreach ($seedLogs as [$date, $habitName]) {
    if (!isset($ids[$habitName])) {
        continue;
    }
    $insertLog->execute([$user_id, $ids[$habitName], $date]);
    $inserted++;
}
$pdo->commit();
echo "• {$inserted} log rows imported (xp 5 each, duplicates collapsed).\n";

$count = $pdo->prepare('SELECT COUNT(*) FROM logs WHERE user_id = ?');
$count->execute([$user_id]);
echo "• logs in database for {$username}: " . (int) $count->fetchColumn() . "\n";

echo "\nDone. Sign in at /login with username '{$username}'. Change the password after first login (Look → Password).\n";
