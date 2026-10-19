<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';

$user = require_login();

if (is_post()) {
    csrf_check();
    if (field('action') === 'delete') {
        execute(
            'UPDATE users SET card_last4 = NULL, card_brand = NULL, card_holder = NULL, card_exp = NULL WHERE id = ?',
            [(int) $user['id']]
        );
        flash('success', 'Карта отвязана. Полного номера в базе и так не было.');
        redirect('cabinet/card.php');
    }

    $digits = preg_replace('/\D+/', '', field('card_number')) ?? '';
    $holder = mb_strtoupper(field('card_holder'));
    $exp = field('card_exp');
    $errors = [];
    if (strlen($digits) < 16 || strlen($digits) > 19 || !luhn_ok($digits)) {
        $errors['card_number'] = 'Номер не проходит проверку. Для демо подойдёт 4242 4242 4242 4242.';
    }
    if (!preg_match('/^[A-ZА-ЯЁ \-]{3,80}$/u', $holder)) {
        $errors['card_holder'] = 'Имя на карте — буквы и пробел.';
    }
    if (!preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $exp)) {
        $errors['card_exp'] = 'Срок в формате ММ/ГГ.';
    } else {
        [$month, $year] = array_map('intval', explode('/', $exp));
        $expiry = strtotime(sprintf('20%02d-%02d-01 +1 month', $year, $month));
        if ($expiry !== false && $expiry < time()) {
            $errors['card_exp'] = 'Срок карты уже прошёл.';
        }
    }
    if ($errors) {
        remember_form($errors);
        flash('danger', 'Карта не сохранена.');
        redirect('cabinet/card.php');
    }

    execute(
        'UPDATE users SET card_last4 = ?, card_brand = ?, card_holder = ?, card_exp = ? WHERE id = ?',
        [substr($digits, -4), card_brand($digits), $holder, $exp, (int) $user['id']]
    );
    clear_form_state();
    flash('success', 'Сохранены только бренд, имя, срок и последние 4 цифры.');
    redirect('cabinet/card.php');
}

$user = current_user(true) ?? $user;
$pageTitle = 'Карта';
$cabinetActive = 'card';
require __DIR__ . '/../templates/header.php';
?>
<div class="container py-4" style="max-width: 720px;">
    <h1 class="h2">Платёжная карта</h1>
    <?php require __DIR__ . '/../templates/cabinet-nav.php'; ?>
    <div class="warning-card mb-3">
        Это учебная форма. Не вводите данные настоящей карты. Полный номер никуда не записывается: после проверки остаётся только то, что можно показать в чеке — имя, срок и последние 4 цифры. Списание не происходит.
    </div>
    <div class="panel">
        <?php if ($user['card_last4']): ?>
            <p>Сейчас привязано: <strong><?= e($user['card_brand']) ?> •••• <?= e($user['card_last4']) ?></strong>, <?= e($user['card_holder']) ?>, до <?= e($user['card_exp']) ?>.</p>
            <form method="post" class="mb-4" data-confirm="Отвязать демо-карту?">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <button class="btn btn-line" type="submit">Отвязать</button>
            </form>
        <?php else: ?>
            <p class="muted">Карта не привязана. Оформлять сделку можно и без неё.</p>
        <?php endif; ?>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <label class="form-label" for="card_number">Номер</label>
            <input class="form-control<?= error_class('card_number') ?>" id="card_number" name="card_number" data-card inputmode="numeric" autocomplete="off" placeholder="4242 4242 4242 4242">
            <?= error_text('card_number') ?>
            <p class="form-text" data-card-preview>На экране номер можно набрать целиком, в базу уйдут последние 4 цифры.</p>
            <div class="row">
                <div class="col-md-8">
                    <label class="form-label" for="card_holder">Имя на карте</label>
                    <input class="form-control<?= error_class('card_holder') ?>" id="card_holder" name="card_holder" value="<?= e((string) old('card_holder', (string) ($user['card_holder'] ?? ''))) ?>">
                    <?= error_text('card_holder') ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="card_exp">Срок</label>
                    <input class="form-control<?= error_class('card_exp') ?>" id="card_exp" name="card_exp" placeholder="12/28" value="<?= e((string) old('card_exp', (string) ($user['card_exp'] ?? ''))) ?>">
                    <?= error_text('card_exp') ?>
                </div>
            </div>
            <button class="btn btn-garant mt-3" type="submit">Сохранить отметку карты</button>
        </form>
    </div>
</div>
<?php require __DIR__ . '/../templates/footer.php'; ?>
