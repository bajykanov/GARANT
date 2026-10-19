<?php
declare(strict_types=1);

/**
 * Единое PDO-подключение.
 * Запросы к базе идут только через prepare/execute: так отсекается SQL-инъекция.
 * ATTR_EMULATE_PREPARES = false — плейсхолдеры уходят на сервер как настоящие параметры,
 * а не подставляются в текст запроса на стороне PHP.
 */

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $e) {
        app_log('DB: ' . $e->getMessage());
        http_response_code(500);
        echo '<!doctype html><meta charset="utf-8"><title>ГАРАНТ — нет базы</title>';
        echo '<div style="font-family:Segoe UI,sans-serif;max-width:640px;margin:48px auto;line-height:1.5">';
        echo '<h1>Нет подключения к MySQL</h1>';
        echo '<p>Импортируйте <code>sql/schema.sql</code> и проверьте хост, имя базы, логин и пароль в <code>config.php</code>.</p>';
        if (APP_DEBUG) {
            echo '<pre>' . e($e->getMessage()) . '</pre>';
        }
        echo '</div>';
        exit;
    }

    return $pdo;
}

function query_all(string $sql, array $params = []): array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function query_one(string $sql, array $params = []): ?array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function execute(string $sql, array $params = []): int
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

function insert_row(string $sql, array $params = []): int
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return (int) db()->lastInsertId();
}

/**
 * Обёртка транзакции. Бизнес-ошибки кидаем как RuntimeException —
 * откат уже сделан, вызывающий код показывает текст пользователю.
 */
function db_transaction(callable $callback): mixed
{
    $pdo = db();
    if ($pdo->inTransaction()) {
        return $callback();
    }

    $pdo->beginTransaction();
    try {
        $result = $callback();
        $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
