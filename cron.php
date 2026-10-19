<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

$key = (string) ($_GET['key'] ?? '');
if (!hash_equals(CRON_KEY, $key)) {
    http_response_code(403);
    exit('Неверный ключ.');
}

$count = escrow_process_timeouts();
header('Content-Type: text/plain; charset=UTF-8');
echo "Просроченных сделок обработано дополнительно: {$count}\n";
echo "Основная проверка уже выполняется при каждом заходе на сайт.\n";
