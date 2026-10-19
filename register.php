<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

if (current_user()) {
    redirect('cabinet/index.php');
}

if (is_post()) {
    csrf_check();
    $name = field('name');
    $email = mb_strtolower(field('email'));
    $phone = field('phone');
    $city = field('city');
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['password_confirm'] ?? '');
    $errors = [];

    if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
        $errors['name'] = 'Имя — от 2 до 120 символов.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Нужен корректный email.';
    }
    if (mb_strlen($password) < 8 || strlen($password) > 72) {
        $errors['password'] = 'Пароль от 8 до 72 символов. Bcrypt всё равно учитывает только первые 72 байта.';
    }
    if ($password !== $confirm) {
        $errors['password_confirm'] = 'Пароли не совпадают.';
    }
    if ($phone !== '' && !preg_match('/^[0-9+\-\s()]{6,30}$/', $phone)) {
        $errors['phone'] = 'Телефон выглядит необычно.';
    }
    if ($email && empty($errors['email'])) {
        $exists = query_one('SELECT id FROM users WHERE email = ?', [$email]);
        if ($exists) {
            $errors['email'] = 'Такой email уже зарегистрирован.';
        }
    }

    if ($errors) {
        remember_form($errors);
        flash('danger', 'Проверьте выделенные поля.');
        redirect('register.php');
    }

    $id = insert_row(
        'INSERT INTO users (email, password_hash, name, phone, city, role) VALUES (?, ?, ?, ?, ?, ?)',
        [$email, password_hash($password, PASSWORD_DEFAULT), $name, $phone !== '' ? $phone : null, $city !== '' ? $city : null, 'user']
    );
    $user = query_one('SELECT * FROM users WHERE id = ?', [$id]);
    clear_form_state();
    login_user($user);
    flash('success', 'Аккаунт создан. Стартовый рейтинг продавца и покупателя — 5.0.');
    redirect('cabinet/index.php');
}

$pageTitle = 'Регистрация';
require __DIR__ . '/templates/header.php';
?>
<div class="container py-4" style="max-width: 640px;">
    <div class="panel">
        <h1 class="h3">Регистрация</h1>
        <p class="muted">После входа можно публиковать объявления и проводить сделки через гаранта. Пароль хранится только как password_hash.</p>
        <form method="post">
            <?= csrf_field() ?>
            <label class="form-label" for="name">Имя</label>
            <input class="form-control mb-1<?= error_class('name') ?>" id="name" name="name" value="<?= e((string) old('name')) ?>" required>
            <?= error_text('name') ?>

            <label class="form-label mt-3" for="email">Email</label>
            <input class="form-control mb-1<?= error_class('email') ?>" type="email" id="email" name="email" value="<?= e((string) old('email')) ?>" autocomplete="username" required>
            <?= error_text('email') ?>

            <div class="row mt-3">
                <div class="col-md-6">
                    <label class="form-label" for="phone">Телефон</label>
                    <input class="form-control<?= error_class('phone') ?>" id="phone" name="phone" value="<?= e((string) old('phone')) ?>">
                    <?= error_text('phone') ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="city">Город</label>
                    <input class="form-control" id="city" name="city" value="<?= e((string) old('city')) ?>">
                </div>
            </div>

            <label class="form-label mt-3" for="password">Пароль</label>
            <input class="form-control mb-1<?= error_class('password') ?>" type="password" id="password" name="password" autocomplete="new-password" required>
            <?= error_text('password') ?>

            <label class="form-label mt-3" for="password_confirm">Пароль ещё раз</label>
            <input class="form-control mb-1<?= error_class('password_confirm') ?>" type="password" id="password_confirm" name="password_confirm" autocomplete="new-password" required>
            <?= error_text('password_confirm') ?>

            <button class="btn btn-garant mt-4" type="submit">Создать аккаунт</button>
        </form>
    </div>
</div>
<?php require __DIR__ . '/templates/footer.php'; ?>
