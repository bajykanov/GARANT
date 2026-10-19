<?php
declare(strict_types=1);

/**
 * Сессия и доступ.
 * В сессии хранится только id пользователя. Роль и блокировка каждый раз читаются из базы,
 * чтобы админская блокировка действовала сразу, без перелогина.
 */

function current_user(bool $refresh = false): ?array
{
    static $loaded = false;
    static $user = null;

    if ($refresh) {
        $loaded = false;
        $user = null;
    }
    if ($loaded) {
        return $user;
    }

    $loaded = true;
    $id = (int) ($_SESSION['user_id'] ?? 0);
    if ($id <= 0) {
        return $user = null;
    }

    $user = query_one('SELECT * FROM users WHERE id = ?', [$id]);
    if (!$user || (int) $user['is_blocked'] === 1) {
        unset($_SESSION['user_id']);
        $user = null;
    }

    return $user;
}

function login_user(array $user): void
{
    // Новый идентификатор сессии — защита от фиксации сессии.
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    unset($_SESSION['2fa_pending'], $_SESSION['2fa_attempts'], $_SESSION['login_fails'], $_SESSION['csrf']);
    current_user(true);
}

function logout_user(): void
{
    $_SESSION = [];
    session_regenerate_id(true);
}

function require_login(): array
{
    $user = current_user();
    if ($user) {
        return $user;
    }

    $return = $_SERVER['REQUEST_URI'] ?? url('cabinet/index.php');
    if (is_string($return) && str_starts_with($return, '/') && !str_starts_with($return, '//')) {
        $_SESSION['return_to'] = $return;
    }
    flash('warning', 'Войдите, чтобы открыть этот раздел.');
    redirect('login.php');
}

function require_admin(): array
{
    $user = require_login();
    if ($user['role'] !== 'admin') {
        http_response_code(403);
        $pageTitle = 'Доступ закрыт';
        require root_dir() . '/templates/header.php';
        echo '<div class="container py-5"><h1>Доступ закрыт</h1><p>Этот раздел только для администратора гаранта.</p></div>';
        require root_dir() . '/templates/footer.php';
        exit;
    }
    return $user;
}

function redirect_after_login(): void
{
    $target = $_SESSION['return_to'] ?? '';
    unset($_SESSION['return_to']);

    $safe = is_string($target)
        && str_starts_with($target, '/')
        && !str_starts_with($target, '//')
        && !str_contains($target, '\\')
        && !str_contains($target, '://')
        && !str_contains($target, "\n")
        && !str_contains($target, "\r");

    if (!$safe) {
        redirect('cabinet/index.php');
    }

    header('Location: ' . $target);
    exit;
}

function unread_count(): int
{
    static $count = null;
    if ($count !== null) {
        return $count;
    }

    $user = current_user();
    if (!$user) {
        return $count = 0;
    }

    $row = query_one(
        'SELECT COUNT(*) AS c
         FROM messages m
         JOIN deals d ON d.id = m.deal_id
         WHERE m.is_read = 0
           AND m.sender_id IS NOT NULL
           AND m.sender_id <> ?
           AND (d.buyer_id = ? OR d.seller_id = ?)',
        [(int) $user['id'], (int) $user['id'], (int) $user['id']]
    );

    return $count = (int) ($row['c'] ?? 0);
}

function reset_unread_cache(): void
{
    // Счётчик кэшируется в static внутри unread_count. Сброс через новый запрос
    // не нужен между страницами: каждый HTTP-запрос начинает static заново.
}
