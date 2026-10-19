<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' https://cdn.jsdelivr.net https://fonts.googleapis.com 'unsafe-inline'; font-src 'self' https://cdn.jsdelivr.net https://fonts.gstatic.com data:; script-src 'self' https://cdn.jsdelivr.net; base-uri 'self'; form-action 'self'; frame-ancestors 'self'");

$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_name('GARANTSESS');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => $https,
]);
session_start();

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/escrow.php';

set_exception_handler(static function (Throwable $e): void {
    app_log($e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
    }
    if (APP_DEBUG) {
        echo '<pre>' . e($e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine()) . '</pre>';
        return;
    }
    echo 'Произошла внутренняя ошибка. Запись сохранена в storage/error.log.';
});

ensure_dirs();
db();

// Срок эскроу проверяется при обычных заходах на сайт, cron.php для этого не обязателен.
escrow_process_timeouts();
