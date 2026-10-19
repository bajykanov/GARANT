<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

$id = max(0, (int) ($_GET['id'] ?? 0));
$product = query_one(
    'SELECT p.*, u.name AS seller_name, u.city AS seller_city, u.seller_rating, u.buyer_rating,
            u.seller_reviews_count, u.about AS seller_about, u.is_blocked,
            c.name AS category_name, c.slug AS category_slug
     FROM products p
     JOIN users u ON u.id = p.user_id
     JOIN categories c ON c.id = p.category_id
     WHERE p.id = ?',
    [$id]
);

$viewer = current_user();
if (!$product || (int) $product['is_blocked'] === 1) {
    abort_404('Объявление не найдено.');
}
$isOwner = $viewer && (int) $viewer['id'] === (int) $product['user_id'];
if ($product['status'] === 'hidden' && !$isOwner) {
    abort_404('Объявление снято с публикации.');
}

$images = product_images($id);
$deliveryIds = product_delivery_ids($id);
$delivery = array_values(array_filter(
    delivery_options(),
    static fn (array $option): bool => in_array((int) $option['id'], $deliveryIds, true)
));

$pageTitle = (string) $product['title'];
$navActive = 'catalog';
require __DIR__ . '/templates/header.php';
?>
<div class="container py-4">
    <p class="muted mb-2"><a href="<?= e(url('catalog.php?category=' . (int) $product['category_id'])) ?>"><?= e($product['category_name']) ?></a></p>
    <div class="row g-4">
        <div class="col-lg-7">
            <?php if ($images): ?>
                <img id="gallery-main" src="<?= e(url('uploads/' . $images[0]['path'])) ?>" alt="<?= e($product['title']) ?>">
                <?php if (count($images) > 1): ?>
                    <div class="thumbs">
                        <?php foreach ($images as $index => $image): ?>
                            <button type="button" data-full="<?= e(url('uploads/' . $image['path'])) ?>" class="<?= $index === 0 ? 'is-active' : '' ?>">
                                <img src="<?= e(url('uploads/' . $image['path'])) ?>" alt="">
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="pcard-ph" style="border-radius:18px;aspect-ratio:4/3;font-size:4rem;">
                    <i class="bi <?= e(category_icon((string) $product['category_slug'])) ?>"></i>
                </div>
            <?php endif; ?>
        </div>
        <div class="col-lg-5">
            <div class="panel stack">
                <div>
                    <span class="badge-status st-paid"><?= e(deal_type_label((string) $product['deal_type'])) ?></span>
                    <?php if ($product['status'] !== 'active'): ?>
                        <span class="badge-status st-cancelled"><?= e(product_status_label((string) $product['status'])) ?></span>
                    <?php endif; ?>
                </div>
                <h1 class="h2 mb-0"><?= e($product['title']) ?></h1>
                <div class="pcard-price">
                    <?php if ($product['deal_type'] === 'exchange'): ?>
                        Только обмен
                    <?php else: ?>
                        <?= e(money($product['price'])) ?>
                    <?php endif; ?>
                </div>
                <?php if ($product['exchange_for']): ?>
                    <p class="mb-0"><strong>Обмен на:</strong> <?= e($product['exchange_for']) ?></p>
                <?php endif; ?>
                <p class="muted mb-0"><?= e($product['city'] ?: 'Город не указан') ?> · в наличии <?= (int) $product['quantity'] ?></p>
                <div>
                    <a href="<?= e(url('user.php?id=' . (int) $product['user_id'])) ?>"><?= e($product['seller_name']) ?></a>
                    <div>Продавец <?= stars($product['seller_rating']) ?> <span class="muted">(<?= (int) $product['seller_reviews_count'] ?>)</span></div>
                </div>
                <div>
                    <strong>Доставка у этого объявления</strong>
                    <ul class="mb-0 mt-2">
                        <?php foreach ($delivery as $option): ?>
                            <li><?= e($option['name']) ?> <span class="muted">— <?= e(delivery_kind_label((string) $option['kind'])) ?></span></li>
                        <?php endforeach; ?>
                        <?php if (!$delivery): ?>
                            <li class="muted">Продавец не указал способы доставки.</li>
                        <?php endif; ?>
                    </ul>
                </div>
                <?php if ($isOwner): ?>
                    <a class="btn btn-garant" href="<?= e(url('cabinet/listing.php?id=' . $id)) ?>">Редактировать</a>
                <?php elseif ($product['status'] === 'active' && (int) $product['quantity'] > 0 && $delivery): ?>
                    <a class="btn btn-amber" href="<?= e(url('checkout.php?id=' . $id)) ?>">Оформить через гаранта</a>
                <?php else: ?>
                    <p class="mb-0 muted">Сейчас объявление нельзя оформить.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="panel mt-4">
        <h2 class="h4">Описание</h2>
        <p class="mb-0"><?= nl($product['description']) ?></p>
    </div>
</div>
<?php require __DIR__ . '/templates/footer.php'; ?>
