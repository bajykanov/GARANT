<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';

$user = require_login();
$incoming = query_all(
    'SELECT r.*, a.name AS author_name
     FROM reviews r JOIN users a ON a.id = r.author_id
     WHERE r.target_id = ? ORDER BY r.created_at DESC',
    [(int) $user['id']]
);
$outgoing = query_all(
    'SELECT r.*, t.name AS target_name
     FROM reviews r JOIN users t ON t.id = r.target_id
     WHERE r.author_id = ? ORDER BY r.created_at DESC',
    [(int) $user['id']]
);

$pageTitle = 'Отзывы';
$cabinetActive = 'reviews';
require __DIR__ . '/../templates/header.php';
?>
<div class="container py-4">
    <h1 class="h2">Отзывы</h1>
    <?php require __DIR__ . '/../templates/cabinet-nav.php'; ?>
    <div class="row g-4">
        <div class="col-lg-6">
            <h2 class="h5">Обо мне</h2>
            <?php if (!$incoming): ?><p class="muted">Пока никто не оценивал завершённые сделки с вами.</p><?php endif; ?>
            <?php foreach ($incoming as $review): ?>
                <article class="panel mb-3">
                    <strong><?= e($review['author_name']) ?></strong>
                    <div><?= stars($review['rating']) ?> <span class="muted"><?= $review['as_role'] === 'seller' ? 'как продавцу' : 'как покупателю' ?></span></div>
                    <p class="mb-0"><?= nl($review['comment']) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
        <div class="col-lg-6">
            <h2 class="h5">Мои оценки</h2>
            <?php if (!$outgoing): ?><p class="muted">Вы ещё не оставляли отзывов. Это можно сделать в завершённой сделке.</p><?php endif; ?>
            <?php foreach ($outgoing as $review): ?>
                <article class="panel mb-3">
                    <strong><?= e($review['target_name']) ?></strong>
                    <div><?= stars($review['rating']) ?></div>
                    <p class="mb-0"><?= nl($review['comment']) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../templates/footer.php'; ?>
