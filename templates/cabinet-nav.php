<?php
declare(strict_types=1);
$cabinetActive = $cabinetActive ?? '';
$items = [
    'home' => ['cabinet/index.php', 'Обзор'],
    'listings' => ['cabinet/listings.php', 'Объявления'],
    'deals' => ['cabinet/deals.php', 'Сделки'],
    'messages' => ['cabinet/messages.php', 'Сообщения'],
    'reviews' => ['cabinet/reviews.php', 'Отзывы'],
    'security' => ['cabinet/security.php', 'Безопасность'],
    'card' => ['cabinet/card.php', 'Карта'],
];
?>
<nav class="subnav" aria-label="Кабинет">
    <?php foreach ($items as $key => [$path, $label]): ?>
        <a class="<?= $cabinetActive === $key ? 'active' : '' ?>" href="<?= e(url($path)) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</nav>
