<?php
declare(strict_types=1);
/** @var array $product */
$slug = (string) ($product['category_slug'] ?? '');
$image = (string) ($product['image'] ?? '');
$dealType = (string) $product['deal_type'];
?>
<a class="pcard" href="<?= e(url('product.php?id=' . (int) $product['id'])) ?>">
    <div class="pcard-media">
        <?php if ($image !== ''): ?>
            <img src="<?= e(url('uploads/' . $image)) ?>" alt="">
        <?php else: ?>
            <div class="pcard-ph"><i class="bi <?= e(category_icon($slug)) ?>"></i></div>
        <?php endif; ?>
        <span class="deal-pill"><?= e(deal_type_label($dealType)) ?></span>
    </div>
    <div class="pcard-body">
        <div class="pcard-cat"><?= e($product['category_name'] ?? '') ?></div>
        <h3><?= e($product['title']) ?></h3>
        <div class="pcard-price">
            <?php if ($dealType === 'exchange'): ?>
                Обмен
            <?php else: ?>
                <?= e(money($product['price'])) ?>
                <?php if ($dealType === 'both'): ?><small>или обмен</small><?php endif; ?>
            <?php endif; ?>
        </div>
        <div class="pcard-meta">
            <span><?= e($product['city'] ?: 'Город не указан') ?></span>
            <span class="pcard-rating"><?= stars($product['seller_rating'] ?? 5) ?></span>
        </div>
    </div>
</a>
