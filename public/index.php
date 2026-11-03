<?php
declare(strict_types=1);

/**
 * Hector Habit — front controller.
 *
 * Routes (relative to wherever /public is mounted; the app works in a subfolder
 * without any configuration):
 *
 *   GET  /            marketing preview page (the WordPress landing page copy)
 *   GET  /login       username + password
 *   POST /login       sign in
 *   GET  /signup      register new account
 *   POST /signup      create new user
 *   GET  /forgot      forgot password: username + recovery key + new password
 *   POST /forgot      reset the password with a recovery key (no email)
 *   GET  /logout      destroy session
 *   GET  /app         dashboard  (Today | Week | Month | Plan | Look)
 *   POST /toggle      JSON: check/uncheck one habit for one date
 *   GET  /state       JSON: month state for the grid / week / month views
 *   POST /theme       JSON: persist the chosen theme
 *   POST /password    change password
 *   POST /billing/order           JSON: Razorpay order for ₹199 (or coupon price)
 *   POST /billing/verify          JSON: checkout callback -> verify signature
 *   POST /billing/webhook         server-to-server authority for paid orders
 */

// PHP's built-in dev server: let real files (CSS, JS, icons) pass through.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
    if (is_file($file)) {
        return false;
    }
}

require_once __DIR__ . '/../src/http.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/habits.php';
require_once __DIR__ . '/../src/billing.php';

hh_session_start();

$path = hh_request_path();
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

/* --------------------------------------------------------------- webhook --- */
// Public, unauthenticated on purpose: Razorpay signs the raw body.
if ($path === '/billing/webhook' && $method === 'POST') {
    $raw = hh_raw_body();
    $signature = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? null;
    if (!hh_verify_webhook($raw, $signature)) {
        hh_json(['ok' => false, 'error' => 'Bad webhook signature.'], 400);
    }
    $payload = json_decode($raw, true) ?: [];
    $event = (string) ($payload['event'] ?? '');
    $orderId = (string) ($payload['payload']['payment']['entity']['order_id'] ?? '');
    $paymentId = (string) ($payload['payload']['payment']['entity']['id'] ?? '');

    if (in_array($event, ['payment.captured', 'order.paid', 'payment.authorized'], true) && $orderId !== '') {
        $res = hh_fulfil_payment($orderId, $paymentId, 'webhook');
        hh_json($res, $res['ok'] ? 200 : 400);
    }
    hh_json(['ok' => true, 'ignored' => $event]);
}

/* ------------------------------------------------------------------- / ----- */
if ($path === '/') {
    // The marketing page itself lives in WordPress (wordpress/landing.html).
    // This route is the same page rendered by PHP so the product is complete
    // even before WordPress is installed. The CTA points at {APP}/login.
    require hh_path('templates/landing.php');
    exit;
}

/* --------------------------------------------------------------- /login ---- */
if ($path === '/login') {
    $user = hh_current_user();
    if ($user) {
        hh_redirect('/app');
    }

    $error = null;
    $notice = null;
    $username = '';
    if ($method !== 'POST' && !empty($_SESSION['hh_login_flash']) && is_array($_SESSION['hh_login_flash'])) {
        // Set by /forgot after a successful reset: confirm it and prefill the username.
        $notice = (string) ($_SESSION['hh_login_flash']['notice'] ?? '');
        $username = (string) ($_SESSION['hh_login_flash']['username'] ?? '');
        unset($_SESSION['hh_login_flash']);
    }
    if ($method === 'POST') {
        $username = trim((string) ($_POST['username'] ?? ''));
        $token = $_POST['csrf'] ?? null;
        if (!hh_csrf_ok(is_string($token) ? $token : null)) {
            // Login is a regular HTML form, so stale sessions should return a
            // fresh, readable login page rather than a raw JSON 419 response.
            $error = 'Your sign-in page expired. Reload it and try again.';
            require hh_path('templates/login.php');
            exit;
        }
        $password = (string) ($_POST['password'] ?? '');
        if (hh_login($username, $password)) {
            hh_redirect('/app');
        }
        $error = "Those credentials don't match.";
    }

    require hh_path('templates/login.php');
    exit;
}

/* ------------------------------------------------------------- /signup ---- */
if ($path === '/signup') {
    if (hh_current_user()) {
        hh_redirect('/app');
    }

    $error = null;
    $username = '';
    $email = '';
    if ($method === 'POST') {
        $username = trim((string) ($_POST['username'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');
        $token = $_POST['csrf'] ?? null;

        if (!hh_csrf_ok(is_string($token) ? $token : null)) {
            $error = 'Your sign-up page expired. Reload it and try again.';
        } else {
            $res = hh_register($username, $email, $password, $confirm);
            if ($res['ok']) {
                hh_redirect('/app');
            }
            $error = (string) $res['error'];
        }
    }

    require hh_path('templates/signup.php');
    exit;
}

/* -------------------------------------------------------------- /forgot ---- */
// Public: username or email + new password + confirm password.
if ($path === '/forgot') {
    header('Cache-Control: no-store, max-age=0');
    if (hh_current_user()) {
        hh_redirect('/app?v=look');
    }

    $error = null;
    $username = '';
    if ($method === 'POST') {
        $username = trim((string) ($_POST['username'] ?? ''));
        $token = $_POST['csrf'] ?? null;
        if (!hh_csrf_ok(is_string($token) ? $token : null)) {
            $error = 'This reset page expired. Reload it and try again.';
        } else {
            $result = hh_reset_password(
                $username,
                (string) ($_POST['new_password'] ?? ''),
                (string) ($_POST['confirm_password'] ?? '')
            );
            if ($result['ok']) {
                $_SESSION['hh_login_flash'] = [
                    'notice'   => 'Password updated successfully! Please login with your new password.',
                    'username' => $result['username'],
                ];
                hh_redirect('/login');
            }
            $error = (string) $result['error'];
        }
    }

    require hh_path('templates/forgot.php');
    exit;
}

/* -------------------------------------------------------------- /logout ---- */
if ($path === '/logout') {
    hh_logout();
    hh_redirect('/login');
}

/* --------------------------------------------------------------- guarded --- */
// Only these paths exist. Anything else is a 404 — including /signup, which
// does not and will not exist.
$known = ['/app', '/billing', '/toggle', '/state', '/theme', '/password', '/habit/save', '/habit/archive', '/habit/restore', '/billing/order', '/billing/verify'];
if (!in_array($path, $known, true)) {
    http_response_code(404);
    require hh_path('templates/404.php');
    exit;
}

$user = hh_current_user();
if (!$user) {
    hh_redirect('/login');
}
$access = hh_access($user);

if ($path === '/app' && $method === 'GET') {
    if (!$access['active']) {
        hh_redirect('/billing');
    }
    $today = hh_date(hh_today());

    $monthParam = (string) ($_GET['m'] ?? '');
    $year = (int) $today->format('Y');
    $month = (int) $today->format('n');
    if (preg_match('/^(\d{4})-(\d{1,2})$/', $monthParam, $m)) {
        $year = max(1900, min(2200, (int) $m[1]));
        $month = max(1, min(12, (int) $m[2]));
    }

    $view = (string) ($_GET['v'] ?? 'today');
    if (!in_array($view, ['today', 'week', 'month', 'habits', 'profile', 'logs', 'look'], true)) {
        $view = 'today';
    }
    $state = hh_state(hh_db(), $user, $year, $month);
    $stats = hh_stats_for_user(hh_db(), (int) $user['id'], $year, $month);
    $profileData = hh_profile_stats(hh_db(), (int) $user['id']);
    $habitRows = $view === 'habits' ? hh_habits(hh_db(), (int) $user['id']) : [];
    $archivedHabitRows = $view === 'habits' ? hh_archived_habits(hh_db(), (int) $user['id']) : [];
    $logMonth = null;
    if ($view === 'logs') {
        $requestedLogMonth = (string) ($_GET['m'] ?? 'all');
        $logMonth = preg_match('/^\d{4}-\d{1,2}$/', $requestedLogMonth) ? $requestedLogMonth : null;
        $logsData = hh_user_logs(hh_db(), (int) $user['id'], max(1, (int) ($_GET['page'] ?? 1)), 50, $logMonth);
    } else {
        $logsData = null;
    }

    require hh_path('templates/app.php');
    exit;
}

if ($path === '/billing') {
    $recent = hh_recent_payments((int) $user['id']);

    // The shell shows the month picker while the subscription is live.
    if ($access['active']) {
        $todayDate = hh_date(hh_today());
        $state = hh_state(hh_db(), $user, (int) $todayDate->format('Y'), (int) $todayDate->format('n'));
    } else {
        $state = null;
    }

    require hh_path('templates/billing.php');
    exit;
}

// Expired trial (and not paid): everything else is closed.
if (!$access['active']) {
    if (str_starts_with($path, '/billing')) {
        hh_redirect('/billing');
    }
    hh_redirect('/billing');
}

/* ------------------------------------------------------------------- JSON -- */
if ($path === '/toggle' && $method === 'POST') {
    hh_require_csrf();
    $habitId = (int) ($_POST['habit_id'] ?? 0);
    $date = (string) ($_POST['date'] ?? '');
    $checked = (string) ($_POST['checked'] ?? '') === '1';

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        hh_json(['ok' => false, 'error' => 'Bad date.'], 422);
    }
    $habit = hh_habit_by_id(hh_db(), (int) $user['id'], $habitId);
    if (!$habit) {
        hh_json(['ok' => false, 'error' => 'Unknown habit.'], 404);
    }
    if (!hh_is_due($habit, $date)) {
        hh_json(['ok' => false, 'error' => 'That habit is not due on that day.'], 422);
    }

    hh_toggle(hh_db(), (int) $user['id'], $habitId, $date, $checked);
    hh_json(['ok' => true]);
}

if ($path === '/state' && $method === 'GET') {
    $monthParam = (string) ($_GET['m'] ?? '');
    $today = hh_date(hh_today());
    $year = (int) $today->format('Y');
    $month = (int) $today->format('n');
    if (preg_match('/^(\d{4})-(\d{1,2})$/', $monthParam, $m)) {
        $year = max(1900, min(2200, (int) $m[1]));
        $month = max(1, min(12, (int) $m[2]));
    }
    hh_json(hh_state(hh_db(), $user, $year, $month));
}

if ($path === '/habit/save' && $method === 'POST') {
    hh_require_csrf();
    $result = hh_save_habit(hh_db(), (int) $user['id'], $_POST);
    hh_json($result, $result['ok'] ? 200 : 422);
}

if ($path === '/habit/archive' && $method === 'POST') {
    hh_require_csrf();
    $habitId = (int) ($_POST['habit_id'] ?? 0);
    $ok = $habitId > 0 && hh_archive_habit(hh_db(), (int) $user['id'], $habitId);
    hh_json(['ok' => $ok, 'message' => $ok ? 'Habit archived; its history is preserved.' : 'Habit could not be archived.'], $ok ? 200 : 404);
}

if ($path === '/habit/restore' && $method === 'POST') {
    hh_require_csrf();
    $habitId = (int) ($_POST['habit_id'] ?? 0);
    $ok = $habitId > 0 && hh_restore_habit(hh_db(), (int) $user['id'], $habitId);
    hh_json(['ok' => $ok, 'message' => $ok ? 'Habit restored.' : 'Habit could not be restored. The 20-active-habit limit may have been reached.'], $ok ? 200 : 422);
}

if ($path === '/theme' && $method === 'POST') {
    hh_require_csrf();
    $theme = (string) ($_POST['theme'] ?? '');
    if (!hh_set_theme((int) $user['id'], $theme)) {
        hh_json(['ok' => false, 'error' => 'Unknown theme.'], 422);
    }
    hh_json(['ok' => true, 'theme' => $theme]);
}

if ($path === '/password' && $method === 'POST') {
    hh_require_csrf();
    $current = (string) ($_POST['current'] ?? '');
    $new = (string) ($_POST['new'] ?? '');
    [$ok, $message] = hh_change_password((int) $user['id'], $current, $new);
    hh_json(['ok' => $ok, 'message' => $message], $ok ? 200 : 422);
}

if ($path === '/billing/order' && $method === 'POST') {
    hh_require_csrf();
    $coupon = isset($_POST['coupon']) ? (string) $_POST['coupon'] : null;
    $result = hh_create_order((int) $user['id'], $coupon);
    hh_json($result, $result['ok'] ? 200 : 422);
}

if ($path === '/billing/verify' && $method === 'POST') {
    hh_require_csrf();
    $orderId = (string) ($_POST['razorpay_order_id'] ?? '');
    $paymentId = (string) ($_POST['razorpay_payment_id'] ?? '');
    $signature = (string) ($_POST['razorpay_signature'] ?? '');

    if ($orderId === '' || $paymentId === '' || $signature === '') {
        hh_json(['ok' => false, 'error' => 'Missing payment details.'], 422);
    }
    if (!hh_verify_checkout_signature($orderId, $paymentId, $signature)) {
        hh_db()->prepare('UPDATE payments SET status = "failed" WHERE razorpay_order_id = ? AND status = "created"')
            ->execute([$orderId]);
        hh_json(['ok' => false, 'error' => 'Signature verification failed.'], 422);
    }
    $result = hh_fulfil_payment($orderId, $paymentId, 'checkout');
    hh_json($result, $result['ok'] ? 200 : 500);
}

$view = (string) ($_GET['v'] ?? '');
if (in_array($view, ['today', 'week', 'month', 'plan', 'look'], true)) {
    hh_redirect('/app?v=' . $view);
}

http_response_code(404);
require hh_path('templates/404.php');

/* -------------------------------------------------------------- helpers ---- */

/**
 * Small extra aggregate used by the app header (success %. days left in trial).
 */
function hh_stats_for_user(PDO $pdo, int $user_id, int $year, int $month): array
{
    $bounds = hh_month_bounds($year, $month);
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM logs WHERE user_id = ? AND log_date BETWEEN ? AND ?');
    $stmt->execute([$user_id, $bounds['first'], $bounds['last']]);
    return ['logs_in_month' => (int) $stmt->fetchColumn()];
}
