<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';

$user = require_login();

if (is_post()) {
    csrf_check();
    $action = field('action');

    if ($action === 'password') {
        $current = (string) ($_POST['current_password'] ?? '');
        $next = (string) ($_POST['password_new'] ?? '');
        $confirm = (string) ($_POST['password_confirm'] ?? '');
        $fresh = query_one('SELECT password_hash FROM users WHERE id = ?', [(int) $user['id']]);
        if (!$fresh || !password_verify($current, (string) $fresh['password_hash'])) {
            flash('danger', 'Текущий пароль не подошёл.');
        } elseif (mb_strlen($next) < 8 || strlen($next) > 72) {
            flash('danger', 'Новый пароль — от 8 до 72 символов.');
        } elseif ($next !== $confirm) {
            flash('danger', 'Новый пароль повторён с ошибкой.');
        } else {
            execute('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($next, PASSWORD_DEFAULT), (int) $user['id']]);
            login_user(query_one('SELECT * FROM users WHERE id = ?', [(int) $user['id']]));
            flash('success', 'Пароль изменён, сессия обновлена.');
        }
        redirect('cabinet/security.php');
    }

    if ($action === 'send_2fa') {
        send_twofa_code($user);
        flash('info', 'Код отправлен на ' . $user['email'] . '. Локальная копия — в storage/outbox.');
        redirect('cabinet/security.php');
    }

    if ($action === 'enable_2fa') {
        $fresh = query_one('SELECT * FROM users WHERE id = ?', [(int) $user['id']]);
        $code = preg_replace('/\D+/', '', field('code')) ?? '';
        if (!$fresh || !twofa_code_matches($fresh, $code)) {
            flash('danger', 'Код неверный или просрочен. Запросите новый.');
        } else {
            execute('UPDATE users SET twofa_enabled = 1 WHERE id = ?', [(int) $user['id']]);
            flash('success', 'Вход по коду из письма включён.');
        }
        redirect('cabinet/security.php');
    }

    if ($action === 'disable_2fa') {
        $fresh = query_one('SELECT password_hash FROM users WHERE id = ?', [(int) $user['id']]);
        if (!$fresh || !password_verify((string) ($_POST['current_password'] ?? ''), (string) $fresh['password_hash'])) {
            flash('danger', 'Чтобы выключить код, нужен текущий пароль.');
        } else {
            execute(
                'UPDATE users SET twofa_enabled = 0, twofa_code_hash = NULL, twofa_expires_at = NULL WHERE id = ?',
                [(int) $user['id']]
            );
            flash('success', 'Код при входе выключен.');
        }
        redirect('cabinet/security.php');
    }
}

$user = current_user(true) ?? $user;
$pageTitle = 'Безопасность';
$cabinetActive = 'security';
require __DIR__ . '/../templates/header.php';
?>
<div class="container py-4" style="max-width: 760px;">
    <h1 class="h2">Безопасность</h1>
    <?php require __DIR__ . '/../templates/cabinet-nav.php'; ?>
    <div class="panel mb-3">
        <h2 class="h5">Смена пароля</h2>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="password">
            <label class="form-label" for="current_password">Текущий пароль</label>
            <input class="form-control mb-3" type="password" id="current_password" name="current_password" autocomplete="current-password" required>
            <label class="form-label" for="password_new">Новый пароль</label>
            <input class="form-control mb-3" type="password" id="password_new" name="password_new" autocomplete="new-password" required>
            <label class="form-label" for="password_confirm">Новый пароль ещё раз</label>
            <input class="form-control mb-3" type="password" id="password_confirm" name="password_confirm" autocomplete="new-password" required>
            <button class="btn btn-garant" type="submit">Обновить пароль</button>
        </form>
    </div>
    <div class="panel">
        <h2 class="h5">Код на email</h2>
        <p>Сейчас: <strong><?= (int) $user['twofa_enabled'] === 1 ? 'включён' : 'выключен' ?></strong>. Это второй шаг входа, не приложение-аутентификатор: шестизначный код уходит на почту и дублируется в storage/outbox, если сервер не отправляет письма.</p>
        <?php if ((int) $user['twofa_enabled'] === 1): ?>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="disable_2fa">
                <label class="form-label" for="disable_password">Текущий пароль</label>
                <input class="form-control mb-3" type="password" id="disable_password" name="current_password" required>
                <button class="btn btn-danger-soft" type="submit">Выключить</button>
            </form>
        <?php else: ?>
            <form method="post" class="mb-3">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="send_2fa">
                <button class="btn btn-line" type="submit">Прислать код на <?= e($user['email']) ?></button>
            </form>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="enable_2fa">
                <label class="form-label" for="code">Код из письма</label>
                <input class="form-control mb-3" id="code" name="code" inputmode="numeric" required>
                <button class="btn btn-garant" type="submit">Включить</button>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/../templates/footer.php'; ?>
