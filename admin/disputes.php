<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';

require_admin();

if (is_post()) {
    csrf_check();
    $error = admin_resolve_dispute((int) field('dispute_id'), field('resolution'), field('admin_comment'));
    if ($error) {
        flash('danger', $error);
    }
    redirect('admin/disputes.php');
}

$rows = query_all(
    "SELECT d.id, d.reason, d.status, d.resolution, d.admin_comment, d.created_at, d.resolved_at,
            d.opened_by, u.name AS opener_name,
            deals.id AS deal_id, deals.amount, deals.deal_type, deals.status AS deal_status,
            p.title
     FROM disputes d
     JOIN deals ON deals.id = d.deal_id
     JOIN products p ON p.id = deals.product_id
     LEFT JOIN users u ON u.id = d.opened_by
     ORDER BY d.status = 'open' DESC, d.id DESC"
);

$pageTitle = 'Споры';
$adminActive = 'disputes';
require __DIR__ . '/../templates/header.php';
?>
<div class="container py-4">
    <h1 class="h2">Споры</h1>
    <?php require __DIR__ . '/../templates/admin-nav.php'; ?>
    <?php if (!$rows): ?>
        <p class="muted">Открытых и закрытых споров нет.</p>
    <?php endif; ?>
    <div class="stack">
        <?php foreach ($rows as $row): ?>
            <article class="panel">
                <div class="d-flex justify-content-between gap-3 flex-wrap">
                    <div>
                        <h2 class="h5 mb-1"><?= e($row['title']) ?></h2>
                        <div class="muted">Сделка №<?= (int) $row['deal_id'] ?> · <?= e(money($row['amount'])) ?> · открыл <?= e($row['opener_name'] ?: 'система') ?> · <?= e(dt($row['created_at'])) ?></div>
                    </div>
                    <span class="badge-status st-<?= $row['status'] === 'open' ? 'dispute' : 'completed' ?>"><?= $row['status'] === 'open' ? 'Открыт' : 'Закрыт' ?></span>
                </div>
                <p class="mt-3"><?= nl($row['reason']) ?></p>
                <a href="<?= e(url('cabinet/deal.php?id=' . (int) $row['deal_id'])) ?>">Карточка сделки</a>
                <?php if ($row['status'] === 'open'): ?>
                    <form class="mt-3" method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="dispute_id" value="<?= (int) $row['id'] ?>">
                        <label class="form-label" for="comment-<?= (int) $row['id'] ?>">Комментарий решения</label>
                        <textarea class="form-control mb-2" id="comment-<?= (int) $row['id'] ?>" name="admin_comment" rows="2" required></textarea>
                        <div class="d-flex gap-2 flex-wrap">
                            <button class="btn btn-line" name="resolution" value="refund" type="submit">Вернуть покупателю</button>
                            <button class="btn btn-garant" name="resolution" value="release" type="submit">Перевести продавцу и закрыть</button>
                        </div>
                    </form>
                <?php else: ?>
                    <p class="mb-0 mt-3 muted">Решение: <?= $row['resolution'] === 'refund' ? 'возврат' : 'перевод продавцу' ?>. <?= e($row['admin_comment']) ?></p>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
</div>
<?php require __DIR__ . '/../templates/footer.php'; ?>
