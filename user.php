<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

$id = max(0, (int) ($_GET['id'] ?? 0));
$profile = query_one('SELECT * FROM users WHERE id = ? AND is_blocked = 0', [$id]);
if (!$profile) {
    abort_404('Пользователь не найден.');
}

$listings = query_all(
    "SELECT p.*, u.seller_rating, c.name AS category_name, c.slug AS category_slug,
            (SELECT path FROM product_images i WHERE i.product_id = p.id ORDER BY i.is_main DESC, i.id ASC LIMIT 1) AS image
     FROM products p
     JOIN users u ON u.id = p.user_id
     JOIN categories c ON c.id = p.category_id
     WHERE p.user_id = ? AND p.status = 'active'
     ORDER BY p.created_at DESC",
    [$id]
);
$reviews = query_all(
    'SELECT r.*, a.name AS author_name
     FROM reviews r
     JOIN users a ON a.id = r.author_id
     WHERE r.target_id = ?
     ORDER BY r.created_at DESC
     LIMIT 50',
    [$id]
);

$pageTitle = (string) $profile['name'];
require __DIR__ . '/templates/header.php';
?>
<div class="container py-4">
    <div class="panel">
        <h1 class="h2"><?= e($profile['name']) ?></h1>
        <p class="muted"><?= e($profile['city'] ?: 'Город не указан') ?> · на платформе с <?= e(date('d.m.Y', strtotime((string) $profile['created_at']))) ?></p>
        <?php if ($profile['about']): ?><p><?= nl($profile['about']) ?></p><?php endif; ?>
        <div class="row g-3">
            <div class="col-md-6">
                <div class="stat">
                    <span>Рейтинг продавца</span>
                    <strong><?= stars($profile['seller_rating']) ?></strong>
                    <span><?= (int) $profile['seller_reviews_count'] === 0 ? 'Стартовые 5.0 — отзывов о продавце ещё нет' : ((int) $profile['seller_reviews_count'] . ' отзыв(ов) о продавце') ?></span>
                </div>
            </div>
            <div class="col-md-6">
                <div class="stat">
                    <span>Рейтинг покупателя</span>
                    <strong><?= stars($profile['buyer_rating']) ?></strong>
                    <span><?= (int) $profile['buyer_reviews_count'] === 0 ? 'Стартовые 5.0 — отзывов о покупателе ещё нет' : ((int) $profile['buyer_reviews_count'] . ' отзыв(ов) о покупателе') ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="section-head"><h2>Объявления</h2></div>
    <?php if (!$listings): ?>
        <p class="muted">Активных объявлений нет.</p>
    <?php else: ?>
        <div class="pcard-grid">
            <?php foreach ($listings as $product): ?>
                <?php render_product_card($product); ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="section-head"><h2>Отзывы</h2></div>
    <?php if (!$reviews): ?>
        <p class="muted">Отзывы появятся после завершённых сделок.</p>
    <?php else: ?>
        <div class="stack">
            <?php foreach ($reviews as $review): ?>
                <article class="panel">
                    <div class="d-flex justify-content-between gap-3">
                        <strong><?= e($review['author_name']) ?></strong>
                        <span class="muted"><?= e(dt($review['created_at'])) ?></span>
                    </div>
                    <div class="my-1"><?= stars($review['rating']) ?> <span class="muted"><?= $review['as_role'] === 'seller' ? 'как продавцу' : 'как покупателю' ?></span></div>
                    <p class="mb-0"><?= nl($review['comment']) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/templates/footer.php'; ?>
