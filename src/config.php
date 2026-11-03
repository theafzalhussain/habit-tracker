<?php
declare(strict_types=1);

/**
 * Hector Habit — configuration.
 *
 * Values come from real environment variables. On shared hosting (cPanel) it is
 * often easier to create src/config.local.php instead, which returns an array of
 * the same keys. Nothing secret is ever committed: config.local.php is ignored
 * by git.
 */

function hh_local_config(): array
{
    static $local = null;
    if ($local === null) {
        $path = __DIR__ . '/config.local.php';
        $local = is_file($path) ? (array) require $path : [];
    }
    return $local;
}

function hh_env(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        $local = hh_local_config();
        $value = $local[$key] ?? null;
    }
    if ($value === null || $value === '') {
        return $default;
    }
    return (string) $value;
}

function hh_config(): array
{
    static $cfg = null;
    if ($cfg !== null) {
        return $cfg;
    }

    $cfg = [
        'db_host'    => hh_env('HECTOR_DB_HOST', '127.0.0.1'),
        'db_port'    => (int) hh_env('HECTOR_DB_PORT', '3306'),
        'db_name'    => hh_env('HECTOR_DB_NAME', 'hector_habit'),
        'db_user'    => hh_env('HECTOR_DB_USER', 'root'),
        'db_pass'    => hh_env('HECTOR_DB_PASS', ''),
        'db_socket'  => hh_env('HECTOR_DB_SOCKET'),

        // Optional. When empty the app builds it from the current request,
        // so it works on any host (including subfolder installs) without config.
        'app_base_url' => hh_env('APP_BASE_URL'),

        // Optional server-side Ashna API credential. Never expose this to JS.
        'ashna_api_key' => hh_env('ASHNA_API_KEY'),

        'razorpay_mode'          => strtolower((string) hh_env('RAZORPAY_MODE', 'test')),
        'razorpay_key_id'        => hh_env('RAZORPAY_KEY_ID'),
        'razorpay_key_secret'    => hh_env('RAZORPAY_KEY_SECRET'),
        'razorpay_webhook_secret' => hh_env('RAZORPAY_WEBHOOK_SECRET'),

        'price_paise' => (int) hh_env('HECTOR_PRICE_PAISE', '19900'), // ₹199.00
        'trial_days'  => (int) hh_env('HECTOR_TRIAL_DAYS', '3'),
        'timezone'    => hh_env('HECTOR_TIMEZONE', 'Asia/Kolkata'),
        // 'auto' (default) marks the session cookie Secure when the request is https.
        'cookie_secure' => hh_env('HECTOR_COOKIE_SECURE'),
        'debug'       => hh_env('HECTOR_DEBUG', '0') === '1',
    ];

    date_default_timezone_set($cfg['timezone']);

    return $cfg;
}

function hh_cfg(string $key, $default = null)
{
    $cfg = hh_config();
    return array_key_exists($key, $cfg) ? $cfg[$key] : $default;
}

/** Absolute filesystem path to a project directory. */
function hh_path(string $rel = ''): string
{
    return dirname(__DIR__) . ($rel !== '' ? '/' . ltrim($rel, '/') : '');
}
