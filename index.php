<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

$parents = category_children_map()[0] ?? [];
$children = category_children_map();
$latest = query_all(
    "SELECT p.*, u.seller_rating, c.name AS category_name, c.slug AS category_slug,
            (SELECT path FROM product_images i WHERE i.product_id = p.id ORDER BY i.is_main DESC, i.id ASC LIMIT 1) AS image
     FROM products p
     JOIN users u ON u.id = p.user_id
     JOIN categories c ON c.id = p.category_id
     WHERE p.status = 'active' AND u.is_blocked = 0
     ORDER BY p.created_at DESC
     LIMIT 6"
);

$pageTitle = APP_NAME;
$navActive = 'home';
require __DIR__ . '/templates/header.php';
?>
<div class="container">
    <section class="hero">
        <div class="row align-items-center g-4">
            <div class="col-lg-7">
                <p class="mb-2">Маркетплейс сельхозпродукции, товаров и техники</p>
                <h1>Сделка, в которой платформа держит деньги</h1>
                <p class="lead">Продажа, обмен или оба варианта. Покупатель платит гаранту, продавец отправляет товар, деньги уходят продавцу только после подтверждения получения.</p>
                <form class="hero-search" method="get" action="<?= e(url('catalog.php')) ?>">
                    <input type="search" name="q" placeholder="Пшеница, трактор, инструмент…" aria-label="Поиск">
                    <button class="btn btn-amber" type="submit">Найти</button>
                </form>
            </div>
            <div class="col-lg-5">
                <div class="panel" style="background: rgba(255,253,248,.95); color: var(--ink);">
                    <strong class="serif">Сейчас у гаранта</strong>
                    <p class="muted mb-2">Учебный журнал, не банковский счёт. Статусы настоящие: заморозка, отправка, спор, завершение.</p>
                    <div class="d-flex gap-2 flex-wrap">
                        <a class="btn btn-garant" href="<?= e(url('catalog.php')) ?>">Смотреть объявления</a>
                        <a class="btn btn-line" href="<?= e(url('register.php')) ?>">Стать участником</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="section-head">
        <div>
            <h2>Категории</h2>
            <p>От зерна и угодий до спецтехники</p>
        </div>
    </div>
    <div class="cat-grid">
        <?php foreach ($parents as $parent): ?>
            <?php $kids = $children[(int) $parent['id']] ?? []; ?>
            <a class="cat-tile" href="<?= e(url('catalog.php?category=' . (int) $parent['id'])) ?>">
                <i class="bi <?= e(category_icon((string) $parent['slug'])) ?>"></i>
                <span>
                    <strong><?= e($parent['name']) ?></strong>
                    <span><?= count($kids) ?> <?= e(count($kids) % 10 === 1 && count($kids) % 100 !== 11 ? 'подкатегория' : (in_array(count($kids) % 10, [2, 3, 4], true) && !in_array(count($kids) % 100, [12, 13, 14], true) ? 'подкатегории' : 'подкатегорий')) ?></span>
                </span>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="section-head" id="escrow">
        <div>
            <h2>Как работает гарант</h2>
            <p>Один порядок и для покупки, и для обмена</p>
        </div>
    </div>
    <div class="step-grid">
        <article class="step"><b>1</b><h3>Фиксация</h3><p>Покупатель оплачивает заказ. Сумма получает запись hold и не доступна продавцу. Товар резервируется.</p></article>
        <article class="step"><b>2</b><h3>Отправка</h3><p>Продавец передаёт товар выбранной службой или спецтехникой и отмечает отправку. Стартует срок в <?= (int) ESCROW_DAYS ?> дн.</p></article>
        <article class="step"><b>3</b><h3>Подтверждение</h3><p>Покупатель подтверждает получение — платформа делает release и закрывает продажу. В обмене нужно подтверждение обеих сторон.</p></article>
        <article class="step"><b>4</b><h3>Спор или отзыв</h3><p>Если срок вышел, открывается спор. Отзыв 1–5 можно оставить только по завершённой сделке, он пересчитывает рейтинг.</p></article>
    </div>

    <div class="section-head">
        <div>
            <h2>Свежие объявления</h2>
            <p>Продавцы с рейтингом. Старт у всех — 5.0, пока нет отзывов</p>
        </div>
        <a href="<?= e(url('catalog.php')) ?>">Весь каталог</a>
    </div>
    <div class="pcard-grid">
        <?php foreach ($latest as $product): ?>
            <?php render_product_card($product); ?>
        <?php endforeach; ?>
    </div>
</div>
<?php require __DIR__ . '/templates/footer.php'; ?>
