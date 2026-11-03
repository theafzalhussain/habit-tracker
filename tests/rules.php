<?php
declare(strict_types=1);

/**
 * Hector Habit — rule tests (CLI). These are the rules from the build prompt,
 * checked one by one against the real database.
 *
 *   HECTOR_DB_* env vars (or src/config.local.php) + RAZORPAY_KEY_SECRET=test
 *   php tests/rules.php
 */

// Razorpay secrets for the signature test must be visible to hh_config()
// BEFORE it is first read (it caches on first call, like a real request).
putenv('RAZORPAY_MODE=test');
putenv('RAZORPAY_KEY_ID=rzp_test_key');
putenv('RAZORPAY_KEY_SECRET=test_secret');

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/habits.php';
require_once __DIR__ . '/../src/billing.php';

$pass = 0;
$fail = 0;

function ok(string $label): void
{
    global $pass;
    $pass++;
    echo "  \033[32m✓\033[0m {$label}\n";
}
function bad(string $label, string $detail = ''): void
{
    global $fail;
    $fail++;
    echo "  \033[31m✗\033[0m {$label}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
}
function is_true(bool $cond, string $label, string $detail = ''): void
{
    $cond ? ok($label) : bad($label, $detail);
}
function same($got, $want, string $label): void
{
    if ($got === $want) {
        ok("{$label} = " . var_export($got, true));
    } else {
        bad($label, 'expected ' . var_export($want, true) . ', got ' . var_export($got, true));
    }
}

$pdo = hh_db();
$habits = [];
foreach (hh_habits($pdo, 1) as $h) {
    $habits[$h['name']] = $h;
}
$gym    = $habits['🏋️‍♂️ Gym'] ?? null;
$read   = $habits['📖 Read & Upskill (30+ Mins)'] ?? null;
$fuel   = $habits['🍎 Clean Fuel (No Sugar / Fast Food)'] ?? null;
$plan   = $habits['🗓️ Plan Tomorrow (Tonight)'] ?? null;
$deep   = $habits['💻 Deep Work / Study (4+ Hours)'] ?? null;

echo "Hector Habit rule tests\n\n— due-date rules (§6)\n";

is_true($gym !== null && $read !== null && $fuel !== null && $plan !== null && $deep !== null, 'all seed habits found');

// Gym: Mon–Sat, never Sunday
same(hh_is_due($gym, '2026-08-10'), true,  'Gym on Monday 2026-08-10');
same(hh_is_due($gym, '2026-08-15'), true,  'Gym on Saturday 2026-08-15');
same(hh_is_due($gym, '2026-08-16'), false, 'Gym on Sunday 2026-08-16');

// Clean Fuel: Mon–Fri
same(hh_is_due($fuel, '2026-08-14'), true,  'Clean Fuel on Friday 2026-08-14');
same(hh_is_due($fuel, '2026-08-15'), false, 'Clean Fuel on Saturday 2026-08-15');

// Read & Upskill: every weekday flag false in the sheet → 0 due days, on purpose
$readDue = 0;
for ($d = 1; $d <= 31; $d++) {
    if (hh_is_due($read, sprintf('2026-08-%02d', $d))) {
        $readDue++;
    }
}
same($readDue, 0, 'Read & Upskill due days in August 2026 (flags stay as seeded)');

// Plan Tomorrow: every 2 days from 2026-01-01 inclusive
same(hh_is_due($plan, '2026-01-01'), true,  'Plan Tomorrow on its start date');
same(hh_is_due($plan, '2026-01-02'), false, 'Plan Tomorrow the day after start');
same(hh_is_due($plan, '2026-01-03'), true,  'Plan Tomorrow two days after start');
same(hh_is_due($plan, '2026-12-31'), true,  'Plan Tomorrow 364 days after start (even offset)');
same(hh_is_due($plan, '2026-12-30'), false, 'Plan Tomorrow off-cycle day (2)');

// start_date gate
same(hh_is_due($deep, '2025-12-31'), false, 'Nothing is due before its start date');

// Streak: consecutive due-days, non-due days do not break it
$dateMap = [
    '2026-08-10' => [(int) $gym['id'] => 5],
    '2026-08-11' => [(int) $gym['id'] => 5],
    '2026-08-12' => [(int) $gym['id'] => 5],
    '2026-08-13' => [(int) $gym['id'] => 5],
];
same(hh_streak($gym, $dateMap, '2026-08-13'), 4, 'Gym streak counts only checked due-days');
$dateMap['2026-08-13'] = [];
same(hh_streak($gym, $dateMap, '2026-08-13'), 0, 'Latest due-day unchecked → streak 0');

echo "\n— monthly state (§8)\n";
$state = hh_state($pdo, ['id' => 1, 'theme' => 'ember'], 2026, 8);
same($state['days_in_month'], 31, 'August 2026 has 31 days');
same($state['is_current_month'], false, 'August 2026 is not the current month');
$checkedDays = [];
foreach ($state['habits'] as $h) {
    foreach ($h['cells'] as $c) {
        if ($c['checked']) {
            $checkedDays[$c['day']] = true;
        }
    }
}
same(array_keys($checkedDays), [3, 10], 'checked days in August 2026 are only the 3rd and the 10th');
same($state['stats']['xp_month'] > 0, true, 'XP this month is a positive number');
same($state['daily'][2]['pct'], 22, 'August 3 daily success rounds to 22%');
same($state['daily'][2]['achievement']['name'], 'Swords', 'August 3 daily badge is Swords');
same($state['daily'][9]['pct'], 88, 'August 10 daily success rounds to 88%');
same($state['daily'][9]['achievement']['name'], 'Lightning', 'August 10 daily badge is Lightning');
same(count($state['week']), 7, 'week view contains exactly seven calendar dates');
same(isset($state['week'][0]['achievement']), true, 'week dates include the daily achievement badge');
same($state['week'][0]['iso'], '2026-07-27', 'August week begins on its Monday before the month boundary');
same($state['week'][6]['iso'], '2026-08-02', 'August week includes Sunday at the month boundary');
$deepState = null;
$gymState = null;
$readState = null;
foreach ($state['habits'] as $habitState) {
    if ($habitState['name'] === '💻 Deep Work / Study (4+ Hours)') $deepState = $habitState;
    if ($habitState['name'] === '🏋️‍♂️ Gym') $gymState = $habitState;
    if ($habitState['name'] === '📖 Read & Upskill (30+ Mins)') $readState = $habitState;
}
same(count($deepState['week_cells']), 7, 'week cells include all seven days for every habit');
same($deepState['week_cells'][0]['due'], true, 'every-day habit is due on the week boundary before the month');
same($gymState['week_cells'][0]['due'], true, 'weekday habit uses its weekday rule across month boundaries');
same($gymState['week_cells'][6]['due'], false, 'Gym remains not due on Sunday across month boundaries');
same($readState['week_scheduled'], 0, 'zero weekday flags stay zero in week view');
same($deepState['today_cell']['iso'], hh_today(), 'today cell remains the real current date when viewing another month');
same($deepState['today_cell']['due'], true, 'daily habit remains due today when viewing another month');
same($state['stats']['due_today'] > 0, true, 'today stats are independent from the selected historical month');

echo "\n— Sheet Profile, achievement tiers, and logbook parity\n";
$tiers = [
    100 => 'Crown', 99 => 'Lightning', 80 => 'Lightning', 79 => 'Fire', 60 => 'Fire',
    59 => 'Shield', 40 => 'Shield', 39 => 'Swords', 20 => 'Swords', 19 => 'Drop', 1 => 'Drop', 0 => 'Skull',
];
foreach ($tiers as $pct => $name) {
    same(hh_achievement_for_percent($pct)['name'], $name, "{$pct}% daily achievement");
}
same(hh_achievement_for_percent(null), null, 'no scheduled habits have no achievement badge');
$rankTests = [1 => 'Novice', 4 => 'Novice', 5 => 'Adept', 9 => 'Adept', 10 => 'Elite', 19 => 'Elite', 20 => 'Master', 49 => 'Master', 50 => 'Legend'];
foreach ($rankTests as $level => $name) {
    same(hh_rank_for_level($level)['label'], $name, "level {$level} rank");
}
$profile = hh_profile_stats($pdo, 1);
$dbTotalXp = (int) $pdo->query('SELECT COALESCE(SUM(xp), 0) FROM logs WHERE user_id = 1')->fetchColumn();
same($profile['total_xp'], $dbTotalXp, 'Profile total XP matches lifetime logbook XP');
same($profile['level'], intdiv($dbTotalXp, 100) + 1, 'Profile level uses 100 XP per level');
$logbook = hh_user_logs($pdo, 1, 1, 50, null);
same($logbook['total'], (int) $pdo->query('SELECT COUNT(*) FROM logs WHERE user_id = 1')->fetchColumn(), 'Logs view includes every unique stored completion');
same($logbook['page'], 1, 'Logs view starts on page one');

// Habit manager round-trip: add → edit → log → archive → restore, preserving history.
$testHabitName = '__HH parity test habit__';
$old = $pdo->prepare('SELECT id FROM habits WHERE user_id = 1 AND name = ?');
$old->execute([$testHabitName]);
foreach ($old->fetchAll(PDO::FETCH_COLUMN) as $oldId) {
    $pdo->prepare('DELETE FROM logs WHERE habit_id = ?')->execute([(int) $oldId]);
    $pdo->prepare('DELETE FROM habits WHERE id = ?')->execute([(int) $oldId]);
}
$invalidHabit = hh_save_habit($pdo, 1, ['name' => 'Invalid weekday test', 'freq' => 'days_of_week', 'start_date' => '2026-10-09', 'xp' => 5]);
same($invalidHabit['ok'], false, 'weekday habit requires at least one weekday');
$addedHabit = hh_save_habit($pdo, 1, ['name' => $testHabitName, 'freq' => 'every_day', 'start_date' => '2026-10-09', 'xp' => 7]);
is_true($addedHabit['ok'], 'new habit can be created');
$testHabitId = (int) ($addedHabit['id'] ?? 0);
$storedHabit = hh_habit_by_id($pdo, 1, $testHabitId);
same((int) $storedHabit['xp'], 7, 'habit XP reward is stored');
same(hh_toggle($pdo, 1, $testHabitId, '2026-10-09', true), true, 'new habit can be logged');
$editedHabit = hh_save_habit($pdo, 1, ['id' => $testHabitId, 'name' => $testHabitName, 'freq' => 'every_x_days', 'start_date' => '2026-10-09', 'xp' => 9, 'interval_days' => 2]);
is_true($editedHabit['ok'], 'existing habit can be edited');
$storedHabit = hh_habit_by_id($pdo, 1, $testHabitId);
same(hh_is_due($storedHabit, '2026-10-11'), true, 'edited repeat-every-two-days rule is active');
$beforeArchiveXp = (int) $pdo->query('SELECT xp FROM logs WHERE habit_id = ' . $testHabitId . ' AND log_date = "2026-10-09"')->fetchColumn();
same($beforeArchiveXp, 7, 'editing a reward does not rewrite historic XP');
same(hh_archive_habit($pdo, 1, $testHabitId), true, 'habit can be archived');
same(hh_habit_by_id($pdo, 1, $testHabitId), null, 'archived habit cannot be toggled');
$archivedInProfile = false;
foreach (hh_profile_stats($pdo, 1)['habits'] as $row) if ((int) $row['id'] === $testHabitId && $row['archived_at'] !== null) $archivedInProfile = true;
same($archivedInProfile, true, 'archived habit remains in lifetime Profile');
same(hh_restore_habit($pdo, 1, $testHabitId), true, 'archived habit can be restored');
$pdo->prepare('DELETE FROM logs WHERE habit_id = ?')->execute([$testHabitId]);
$pdo->prepare('DELETE FROM habits WHERE id = ?')->execute([$testHabitId]);

$invalidDateRejected = false;
try { hh_date('2026-02-31'); } catch (InvalidArgumentException $e) { $invalidDateRejected = true; }
same($invalidDateRejected, true, 'invalid calendar dates are rejected');

// Test-mode configuration is explicit and guards against accidentally using live keys.
same(hh_razorpay_mode(), 'test', 'Razorpay test mode is active in the test suite');
same(hh_payments_configured(), true, 'valid test key pair enables sandbox checkout');
same(hh_razorpay_key_matches_mode('rzp_test_key_123', 'test'), true, 'test key accepted in test mode');
same(hh_razorpay_key_matches_mode('rzp_live_key_123', 'test'), false, 'live key rejected in test mode');
same(hh_razorpay_key_matches_mode('rzp_live_key_123', 'live'), true, 'live key accepted only in live mode');

 echo "\n— billing (§9)\n";
$price = hh_price_for('HECTOR20');
same($price['amount'], 15920, 'HECTOR20 amount in paise');
same($price['discount'], 3980, 'HECTOR20 discount in paise');

$price = hh_price_for(null);
same($price['amount'], 19900, 'no coupon amount in paise');

$price = hh_price_for('NOPE');
same(isset($price['error']), true, 'unknown coupon returns an error');
same($price['amount'], 19900, 'unknown coupon does not silently discount');

$price = hh_price_for('HECTOR20');
same($price['coupon'], 'HECTOR20', 'coupon is upper-cased for lookup');
same(hh_price_for('hector20')['amount'], 15920, 'lower-case coupon works too');

// signature maths: order|payment HMAC
$order = 'order_TEST123';
$payment = 'pay_TEST123';
$goodSig = hash_hmac('sha256', $order . '|' . $payment, 'test_secret');
same(hh_verify_checkout_signature($order, $payment, $goodSig), true, 'valid signature accepted');
same(hh_verify_checkout_signature($order, $payment, 'deadbeef'), false, 'forged signature rejected');

// fulfilment is idempotent: paying twice never stacks a second year
$pdo->exec("DELETE FROM payments WHERE razorpay_order_id = 'order_TESTFULFIL'");
$pdo->prepare('INSERT INTO payments (user_id, razorpay_order_id, amount_paise, coupon_code, status) VALUES (1, ?, 15920, "HECTOR20", "created")')
    ->execute(['order_TESTFULFIL']);
$pdo->exec('UPDATE users SET paid_until = NULL WHERE id = 1');

hh_fulfil_payment('order_TESTFULFIL', 'pay_TESTFULFIL', 'test');
$first = (string) $pdo->query('SELECT paid_until FROM users WHERE id = 1')->fetchColumn();
$expect = (new DateTimeImmutable('today'))->modify('+1 year')->format('Y-m-d');
same($first, $expect, 'one year added from today');

$uses1 = (int) $pdo->query("SELECT uses_left FROM coupons WHERE code = 'HECTOR20'")->fetchColumn();
hh_fulfil_payment('order_TESTFULFIL', 'pay_TESTFULFIL', 'test');
$uses2 = (int) $pdo->query("SELECT uses_left FROM coupons WHERE code = 'HECTOR20'")->fetchColumn();
$second = (string) $pdo->query('SELECT paid_until FROM users WHERE id = 1')->fetchColumn();
same($second, $first, 'a second fulfilment call does not extend again');
same($uses2, $uses1, 'a second fulfilment call does not spend another coupon use');
same($uses2, 49, 'coupon use decremented exactly once (50 → 49)');

// clean up the test rows
$pdo->exec("DELETE FROM payments WHERE razorpay_order_id = 'order_TESTFULFIL'");
$pdo->exec('UPDATE users SET paid_until = NULL, trial_ends_at = DATE_ADD(CURDATE(), INTERVAL 3 DAY) WHERE id = 1');
$pdo->exec("UPDATE coupons SET uses_left = 50 WHERE code = 'HECTOR20'");

echo "\npassed {$pass}, failed {$fail}\n";
exit($fail === 0 ? 0 : 1);
