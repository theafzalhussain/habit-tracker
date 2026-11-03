<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function hh_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $cfg = hh_config();
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $cfg['db_host'], $cfg['db_port'], $cfg['db_name']);
    if (!empty($cfg['db_socket'])) {
        $dsn = sprintf('mysql:unix_socket=%s;dbname=%s;charset=utf8mb4', $cfg['db_socket'], $cfg['db_name']);
    }

    $pdo = new PDO($dsn, $cfg['db_user'], $cfg['db_pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    $pdo->exec("SET time_zone = '+00:00'");

    return $pdo;
}
