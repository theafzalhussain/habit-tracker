<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

const HH_THEMES = ['ember', 'ink', 'moss', 'paper'];

function hh_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    // Auto-detect https (directly or behind a TLS-terminating proxy), with an
    // escape hatch for odd host setups: HECTOR_COOKIE_SECURE = 1 | 0 | auto.
    $override = hh_cfg('cookie_secure');
    if ($override === '1') {
        $secure = true;
    } elseif ($override === '0') {
        $secure = false;
    } else {
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    }
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => $secure,
    ]);
    session_name('hector_sid');
    session_start();
}

function hh_csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function hh_csrf_ok(?string $token): bool
{
    return is_string($token) && $token !== '' && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}

/** Abort the request unless a valid CSRF token was sent. */
function hh_require_csrf(): void
{
    $token = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if (!hh_csrf_ok(is_string($token) ? $token : null)) {
        hh_json(['ok' => false, 'error' => 'Your session expired. Reload the page.'], 419);
    }
}

function hh_current_user(): ?array
{
    static $user = null;
    static $loaded = false;
    if ($loaded) {
        return $user;
    }
    $loaded = true;

    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $stmt = hh_db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $_SESSION['user_id']]);
    $row = $stmt->fetch();
    $user = $row ?: null;
    if ($row && !in_array($row['theme'], HH_THEMES, true)) {
        $user['theme'] = 'ember';
    }
    return $user;
}

function hh_login(string $username, string $password): bool
{
    $stmt = hh_db()->prepare('SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1');
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        usleep(350000); // slow down brute force a little
        return false;
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
    $_SESSION['user_id']  = (int) $user['id'];
    $_SESSION['username'] = $user['username'];
    return true;
}

function hh_seed_starter_habits(int $userId): void
{
    $db = hh_db();
    $existing = $db->prepare('SELECT COUNT(*) FROM habits WHERE user_id = ?');
    $existing->execute([$userId]);
    if ((int) $existing->fetchColumn() > 0) {
        return;
    }

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

    $today = (new DateTimeImmutable('today'))->format('Y-m-d');
    $stmt = $db->prepare(
        'INSERT INTO habits
            (user_id, name, xp, freq, interval_days, start_date, mon, tue, wed, thu, fri, sat, sun, sort_order)
         VALUES (?, ?, 5, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    foreach ($habits as $h) {
        [$sort, $name, $freq, $interval, $mon, $tue, $wed, $thu, $fri, $sat, $sun] = $h;
        $stmt->execute([$userId, $name, $freq, $interval, $today, $mon, $tue, $wed, $thu, $fri, $sat, $sun, $sort]);
    }
}

function hh_register(string $username, string $email, string $password, string $confirm): array
{
    $fail = static fn (string $error): array => ['ok' => false, 'error' => $error];

    $username = trim($username);
    $email = trim(strtolower($email));

    if ($username === '' || $email === '' || $password === '' || $confirm === '') {
        return $fail('All fields are required.');
    }

    if (strlen($username) < 3 || strlen($username) > 32) {
        return $fail('Username must be between 3 and 32 characters.');
    }

    if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $username)) {
        return $fail('Username can only contain letters, numbers, underscores, and hyphens.');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return $fail('Please enter a valid email address.');
    }

    if (strlen($password) < 8) {
        return $fail('Password must be at least 8 characters.');
    }

    if (!preg_match('/[A-Z]/', $password)) {
        return $fail('Password must contain at least one uppercase letter (A-Z).');
    }

    if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
        return $fail('Password must contain at least one special character (!@#$% etc.).');
    }

    if ($password !== $confirm) {
        return $fail('The passwords do not match.');
    }

    $db = hh_db();

    // Check if username already exists
    $stmt = $db->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        return $fail('That username is already taken. Please choose another.');
    }

    // Check if email already exists
    $stmt = $db->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return $fail('An account with that email already exists.');
    }

    $trialDays = (int) (hh_cfg('trial_days') ?? 3);
    $hash = password_hash($password, PASSWORD_DEFAULT);

    $ins = $db->prepare(
        'INSERT INTO users (username, email, password_hash, theme, trial_ends_at, paid_until)
         VALUES (?, ?, ?, "ember", DATE_ADD(CURDATE(), INTERVAL ? DAY), NULL)'
    );
    $ins->execute([$username, $email, $hash, $trialDays]);
    $userId = (int) $db->lastInsertId();

    // Seed default starter habits for the new user
    hh_seed_starter_habits($userId);

    // Auto-login new user
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
    $_SESSION['user_id']  = $userId;
    $_SESSION['username'] = $username;

    return ['ok' => true, 'user_id' => $userId, 'username' => $username];
}

function hh_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], (bool) $p['secure'], (bool) $p['httponly']);
    }
    session_destroy();
}

function hh_change_password(int $user_id, string $current, string $new): array
{
    if (strlen($new) < 8) {
        return [false, 'New password must be at least 8 characters.'];
    }
    $stmt = hh_db()->prepare('SELECT password_hash FROM users WHERE id = ?');
    $stmt->execute([$user_id]);
    $hash = (string) $stmt->fetchColumn();
    if (!$hash || !password_verify($current, $hash)) {
        return [false, 'Current password is wrong.'];
    }
    $upd = hh_db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
    $upd->execute([password_hash($new, PASSWORD_DEFAULT), $user_id]);
    return [true, 'Password updated.'];
}

/* ------------------------------------------------------- password reset ---- */
/**
 * Reset password using username or email without requiring recovery keys.
 */
function hh_reset_password(string $username, string $new, string $confirm): array
{
    $fail = static fn (string $error): array => ['ok' => false, 'error' => $error];
    $username = trim($username);

    if ($username === '') {
        return $fail('Please enter your username or email address.');
    }
    if (strlen($new) < 8) {
        return $fail('New password must be at least 8 characters.');
    }
    if (!preg_match('/[A-Z]/', $new)) {
        return $fail('New password must contain at least one uppercase letter (A-Z).');
    }
    if (!preg_match('/[^a-zA-Z0-9]/', $new)) {
        return $fail('New password must contain at least one special character (!@#$% etc.).');
    }
    if ($new !== $confirm) {
        return $fail("The two new passwords don't match.");
    }

    $db = hh_db();
    $stmt = $db->prepare('SELECT id, username FROM users WHERE username = ? OR email = ? LIMIT 1');
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();

    if (!$user) {
        return $fail('No account found with that username or email.');
    }

    $upd = $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
    $upd->execute([password_hash($new, PASSWORD_DEFAULT), (int) $user['id']]);

    return ['ok' => true, 'username' => (string) $user['username']];
}

/**
 * Subscription gate.
 * Active when inside the trial window, or paid_until is today or later.
 */
function hh_access(array $user): array
{
    $today = new DateTimeImmutable('today');
    $trialEnds = new DateTimeImmutable((string) $user['trial_ends_at']);
    $paidUntil = $user['paid_until'] ? new DateTimeImmutable((string) $user['paid_until']) : null;

    $trialActive = $trialEnds >= $today;
    $paidActive  = $paidUntil !== null && $paidUntil >= $today;

    if ($paidActive) {
        $until = $paidUntil;
        return [
            'active'   => true,
            'reason'   => 'paid',
            'until'    => $until,
            'days_left' => (int) $today->diff($until)->days,
        ];
    }
    if ($trialActive) {
        return [
            'active'   => true,
            'reason'   => 'trial',
            'until'    => $trialEnds,
            'days_left' => (int) $today->diff($trialEnds)->days,
        ];
    }
    return ['active' => false, 'reason' => 'expired', 'until' => $trialEnds, 'days_left' => 0];
}

function hh_set_theme(int $user_id, string $theme): bool
{
    if (!in_array($theme, HH_THEMES, true)) {
        return false;
    }
    $stmt = hh_db()->prepare('UPDATE users SET theme = ? WHERE id = ?');
    $stmt->execute([$theme, $user_id]);
    return true;
}
