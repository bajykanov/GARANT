<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';

$user = require_login();

if (is_post()) {
    csrf_check();
    $name = field('name');
    $phone = field('phone');
    $city = field('city');
    $about = field('about');
    $errors = [];
    if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
        $errors['name'] = 'Имя — от 2 до 120 символов.';
    }
    if ($phone !== '' && !preg_match('/^[0-9+\-\s()]{6,30}$/', $phone)) {
        $errors['phone'] = 'Проверьте телефон.';
    }
    if (mb_strlen($city) > 120) {
        $errors['city'] = 'Слишком длинное название города.';
    }
    if (mb_strlen($about) > 2000) {
        $errors['about'] = 'О себе — не длиннее 2000 символов.';
    }
    if ($errors) {
        remember_form($errors);
        flash('danger', 'Проверьте выделенные поля.');
        redirect('cabinet/index.php');
    }
    execute(
        'UPDATE users SET name = ?, phone = ?, city = ?, about = ? WHERE id = ?',
        [$name, $phone !== '' ? $phone : null, $city !== '' ? $city : null, $about !== '' ? $about : null, (int) $user['id']]
    );
    clear_form_state();
    current_user(true);
    flash('success', 'Профиль сохранён.');
    redirect('cabinet/index.php');
}

$stats = [
    'listings' => (int) (query_one('SELECT COUNT(*) AS c FROM products WHERE user_id = ? AND status = ?', [(int) $user['id'], 'active'])['c'] ?? 0),
    'deals' => (int) (query_one(
        "SELECT COUNT(*) AS c FROM deals WHERE (buyer_id = ? OR seller_id = ?) AND status IN ('paid','shipped','confirmed','dispute')",
        [(int) $user['id'], (int) $user['id']]
    )['c'] ?? 0),
];
$user = current_user(true) ?? $user;

$pageTitle = 'Кабинет';
$cabinetActive = 'home';
require __DIR__ . '/../templates/header.php';
?>
<div class="container py-4">
    <h1 class="h2">Кабинет</h1>
    <?php require __DIR__ . '/../templates/cabinet-nav.php'; ?>
    <div class="stat-grid mb-4">
        <div class="stat"><span>Активные объявления</span><strong><?= $stats['listings'] ?></strong></div>
        <div class="stat"><span>Незавершённые сделки</span><strong><?= $stats['deals'] ?></strong></div>
        <div class="stat"><span>Как продавец</span><strong><?= e(number_format((float) $user['seller_rating'], 1, ',', '')) ?></strong><span><?= (int) $user['seller_reviews_count'] ?> отзывов</span></div>
        <div class="stat"><span>Как покупатель</span><strong><?= e(number_format((float) $user['buyer_rating'], 1, ',', '')) ?></strong><span><?= (int) $user['buyer_reviews_count'] ?> отзывов</span></div>
    </div>
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="panel">
                <h2 class="h5">Данные профиля</h2>
                <form method="post">
                    <?= csrf_field() ?>
                    <label class="form-label" for="name">Имя</label>
                    <input class="form-control<?= error_class('name') ?>" id="name" name="name" value="<?= e((string) old('name', $user['name'])) ?>">
                    <?= error_text('name') ?>
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <label class="form-label" for="phone">Телефон</label>
                            <input class="form-control<?= error_class('phone') ?>" id="phone" name="phone" value="<?= e((string) old('phone', (string) ($user['phone'] ?? ''))) ?>">
                            <?= error_text('phone') ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="city">Город</label>
                            <input class="form-control" id="city" name="city" value="<?= e((string) old('city', (string) ($user['city'] ?? ''))) ?>">
                        </div>
                    </div>
                    <label class="form-label mt-3" for="about">О себе</label>
                    <textarea class="form-control" id="about" name="about" rows="4"><?= e((string) old('about', (string) ($user['about'] ?? ''))) ?></textarea>
                    <p class="form-text">Email <?= e($user['email']) ?> меняется только через администратора. Телефон виден контрагенту внутри сделки, не на витрине.</p>
                    <button class="btn btn-garant" type="submit">Сохранить</button>
                </form>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="panel stack">
                <div>
                    <h2 class="h5">Рейтинги</h2>
                    <p>Продавец <?= stars($user['seller_rating']) ?></p>
                    <p class="mb-0">Покупатель <?= stars($user['buyer_rating']) ?></p>
                    <p class="form-text">Пока отзывов нет, стоит 5.0. Первая оценка заменяет старт, а не усредняется с ним.</p>
                </div>
                <a class="btn btn-line" href="<?= e(url('cabinet/security.php')) ?>">Пароль и код на почту</a>
                <a class="btn btn-line" href="<?= e(url('cabinet/card.php')) ?>">Платёжная карта для демо</a>
                <a class="btn btn-line" href="<?= e(url('cabinet/listing.php')) ?>">Новое объявление</a>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../templates/footer.php'; ?>
