<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

/** HTML-escape any string, including habit names (they contain emoji). */
function hh_e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Base path of the app, so it can live in a subfolder (cPanel: /app,
 * localhost: /hector-habit/public, ...) without any configuration.
 */
function hh_base_path(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
    if ($dir === '.' || $dir === '/') {
        $dir = '';
    }
    $base = $dir;
    return $base;
}

/** Build an app URL: hh_url('/login') => /base/login */
function hh_url(string $path = '/'): string
{
    if ($path === '') {
        $path = '/';
    }
    if (!str_starts_with($path, '/')) {
        $path = '/' . $path;
    }
    return hh_base_path() . $path;
}

/** Absolute URL of this installation (used by Razorpay and redirects). */
function hh_absolute_url(string $path = '/'): string
{
    $configured = trim((string) hh_cfg('app_base_url', ''));
    if ($configured !== '') {
        return rtrim($configured, '/') . ($path === '/' ? '' : '/' . ltrim($path, '/'));
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . hh_url($path);
}

function hh_redirect(string $path): never
{
    header('Location: ' . (preg_match('#^https?://#', $path) ? $path : hh_url($path)));
    exit;
}

function hh_json(array $payload, int $status = 200): never
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function hh_is_post(): bool
{
    return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST';
}

/** Request path with the installation base stripped: '/app' */
function hh_request_path(): string
{
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $path = parse_url($uri, PHP_URL_PATH) ?: '/';
    $base = hh_base_path();
    if ($base !== '' && str_starts_with($path, $base)) {
        $path = substr($path, strlen($base));
    }
    $path = '/' . ltrim($path, '/');
    $trimmed = rtrim($path, '/');
    return $trimmed === '' ? '/' : $trimmed;
}

function hh_raw_body(): string
{
    static $body = null;
    if ($body === null) {
        $body = (string) file_get_contents('php://input');
    }
    return $body;
}

function hh_minutes_label(int $minutes): string
{
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    return $h > 0 ? sprintf('%dh %02dm', $h, $m) : sprintf('%dm', $m);
}
