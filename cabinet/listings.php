<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';

$user = require_login();

if (is_post()) {
    csrf_check();
    $id = (int) field('id');
    $product = query_one('SELECT * FROM products WHERE id = ? AND user_id = ?', [$id, (int) $user['id']]);
    if (!$product) {
        flash('danger', 'Объявление не найдено.');
        redirect('cabinet/listings.php');
    }
    $action = field('action');
    if ($action === 'hide') {
        execute("UPDATE products SET status = 'hidden' WHERE id = ? AND status <> 'sold'", [$id]);
        flash('success', 'Объявление скрыто.');
    } elseif ($action === 'publish') {
        if ((int) $product['quantity'] < 1) {
            flash('danger', 'Чтобы опубликовать, укажите количество больше нуля.');
        } else {
            execute("UPDATE products SET status = 'active' WHERE id = ?", [$id]);
            flash('success', 'Объявление снова в каталоге.');
        }
    } elseif ($action === 'delete') {
        $used = query_one('SELECT COUNT(*) AS c FROM deals WHERE product_id = ?', [$id]);
        if ((int) ($used['c'] ?? 0) > 0) {
            execute("UPDATE products SET status = 'hidden' WHERE id = ?", [$id]);
            flash('warning', 'По объявлению есть сделки, поэтому оно скрыто, а не удалено.');
        } else {
            foreach (product_images($id) as $image) {
                remove_upload((string) $image['path']);
            }
            execute('DELETE FROM products WHERE id = ? AND user_id = ?', [$id, (int) $user['id']]);
            flash('success', 'Объявление удалено.');
        }
    }
    redirect('cabinet/listings.php');
}

$rows = query_all(
    'SELECT p.*, c.name AS category_name
     FROM products p
     JOIN categories c ON c.id = p.category_id
     WHERE p.user_id = ?
     ORDER BY p.created_at DESC',
    [(int) $user['id']]
);

$pageTitle = 'Мои объявления';
$cabinetActive = 'listings';
require __DIR__ . '/../templates/header.php';
?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center gap-3">
        <h1 class="h2 mb-0">Мои объявления</h1>
        <a class="btn btn-garant" href="<?= e(url('cabinet/listing.php')) ?>">Новое</a>
    </div>
    <?php require __DIR__ . '/../templates/cabinet-nav.php'; ?>
    <div class="panel">
        <?php if (!$rows): ?>
            <p class="mb-0 muted">Пока пусто. Первое объявление задаёт цену, тип сделки и доступную доставку.</p>
        <?php endif; ?>
        <?php foreach ($rows as $row): ?>
            <div class="list-row">
                <div>
                    <h3><a href="<?= e(url('product.php?id=' . (int) $row['id'])) ?>"><?= e($row['title']) ?></a></h3>
                    <div class="muted"><?= e($row['category_name']) ?> · <?= e(deal_type_label((string) $row['deal_type'])) ?> · <?= (int) $row['quantity'] ?> шт.</div>
                </div>
                <div><span class="badge-status st-<?= e($row['status'] === 'active' ? 'completed' : ($row['status'] === 'sold' ? 'dispute' : 'cancelled')) ?>"><?= e(product_status_label((string) $row['status'])) ?></span></div>
                <div class="d-flex gap-2 flex-wrap justify-content-end">
                    <a class="btn btn-sm btn-line" href="<?= e(url('cabinet/listing.php?id=' . (int) $row['id'])) ?>">Изменить</a>
                    <?php if ($row['status'] === 'active'): ?>
                        <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><input type="hidden" name="action" value="hide"><button class="btn btn-sm btn-line" type="submit">Скрыть</button></form>
                    <?php else: ?>
                        <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><input type="hidden" name="action" value="publish"><button class="btn btn-sm btn-line" type="submit">Открыть</button></form>
                    <?php endif; ?>
                    <form method="post" data-confirm="Удалить объявление? Если по нему были сделки, оно только скроется."><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><input type="hidden" name="action" value="delete"><button class="btn btn-sm btn-danger-soft" type="submit">Удалить</button></form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php require __DIR__ . '/../templates/footer.php'; ?>
