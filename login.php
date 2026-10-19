<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

if (current_user()) {
    redirect('cabinet/index.php');
}

$step = (string) ($_GET['step'] ?? '');
$pendingId = (int) ($_SESSION['2fa_pending'] ?? 0);

if (is_post()) {
    csrf_check();
    $action = field('action');

    if ($action === 'password') {
        if ((int) ($_SESSION['login_fails'] ?? 0) >= 8) {
            flash('danger', 'Слишком много попыток. Подождите и попробуйте снова.');
            redirect('login.php');
        }

        $email = mb_strtolower(field('email'));
        $password = (string) ($_POST['password'] ?? '');
        $user = query_one('SELECT * FROM users WHERE email = ?', [$email]);
        $dummy = '$2y$10$4eX.IgX2dnQJ41EczJDCMeEMHowMSX/VcLR4Ic6pLHplcn4qD8m86';
        $hash = $user['password_hash'] ?? $dummy;
        $valid = password_verify($password, $hash);

        if (!$user || !$valid) {
            $_SESSION['login_fails'] = (int) ($_SESSION['login_fails'] ?? 0) + 1;
            flash('danger', 'Неверный email или пароль.');
            redirect('login.php');
        }
        if ((int) $user['is_blocked'] === 1) {
            flash('danger', 'Аккаунт заблокирован администратором.');
            redirect('login.php');
        }

        $_SESSION['login_fails'] = 0;
        if ((int) $user['twofa_enabled'] === 1) {
            send_twofa_code($user);
            $_SESSION['2fa_pending'] = (int) $user['id'];
            $_SESSION['2fa_attempts'] = 0;
            flash('info', 'Код отправлен на email. Если почта на компьютере не настроена, письмо лежит в storage/outbox.');
            redirect('login.php?step=2fa');
        }

        login_user($user);
        flash('success', 'Добро пожаловать.');
        redirect_after_login();
    }

    if ($action === '2fa') {
        $pending = $pendingId > 0 ? query_one('SELECT * FROM users WHERE id = ?', [$pendingId]) : null;
        if (!$pending) {
            flash('danger', 'Сессия подтверждения истекла. Войдите снова.');
            redirect('login.php');
        }
        if ((int) ($_SESSION['2fa_attempts'] ?? 0) >= 5) {
            unset($_SESSION['2fa_pending']);
            flash('danger', 'Слишком много попыток ввода кода.');
            redirect('login.php');
        }
        $code = preg_replace('/\D+/', '', field('code')) ?? '';
        if (!twofa_code_matches($pending, $code)) {
            $_SESSION['2fa_attempts'] = (int) ($_SESSION['2fa_attempts'] ?? 0) + 1;
            flash('danger', 'Код неверный или просрочен.');
            redirect('login.php?step=2fa');
        }
        login_user($pending);
        flash('success', 'Вход подтверждён.');
        redirect_after_login();
    }
}

$pageTitle = 'Вход';
require __DIR__ . '/templates/header.php';
?>
<div class="container py-4" style="max-width: 560px;">
    <div class="panel">
        <?php if ($step === '2fa' && $pendingId > 0): ?>
            <h1 class="h3">Код из письма</h1>
            <p class="muted">Второй шаг включён в безопасности профиля. Код живёт 10 минут.</p>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="2fa">
                <label class="form-label" for="code">6 цифр</label>
                <input class="form-control mb-3" id="code" name="code" inputmode="numeric" autocomplete="one-time-code" required>
                <button class="btn btn-garant" type="submit">Подтвердить вход</button>
            </form>
        <?php else: ?>
            <h1 class="h3">Вход</h1>
            <form method="post" class="mt-3">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="password">
                <label class="form-label" for="email">Email</label>
                <input class="form-control mb-3" type="email" id="email" name="email" value="<?= e((string) old('email')) ?>" autocomplete="username" required>
                <label class="form-label" for="password">Пароль</label>
                <input class="form-control mb-3" type="password" id="password" name="password" autocomplete="current-password" required>
                <button class="btn btn-garant" type="submit">Войти</button>
                <a class="ms-2" href="<?= e(url('register.php')) ?>">Нет аккаунта</a>
            </form>
            <?php if (SHOW_DEMO_HINTS): ?>
                <div class="warning-card mt-4">
                    <strong>Учебные входы</strong>
                    <p class="mb-1">Администратор: admin@garant.local / admin123</p>
                    <p class="mb-1">Продавец: bbermetova@garant.local / seller123</p>
                    <p class="mb-0">Покупатель: aamanov@garant.local / buyer123</p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/templates/footer.php'; ?>
