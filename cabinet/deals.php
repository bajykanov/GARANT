<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';

$user = require_login();
$tab = (string) ($_GET['tab'] ?? 'all');
if (!in_array($tab, ['all', 'buy', 'sell'], true)) {
    $tab = 'all';
}
$status = (string) ($_GET['status'] ?? '');
$statuses = ['paid', 'shipped', 'confirmed', 'completed', 'dispute', 'refunded', 'cancelled'];
if (!in_array($status, $statuses, true)) {
    $status = '';
}

$where = [];
$params = [];
if ($tab === 'buy') {
    $where[] = 'd.buyer_id = ?';
    $params[] = (int) $user['id'];
} elseif ($tab === 'sell') {
    $where[] = 'd.seller_id = ?';
    $params[] = (int) $user['id'];
} else {
    $where[] = '(d.buyer_id = ? OR d.seller_id = ?)';
    $params[] = (int) $user['id'];
    $params[] = (int) $user['id'];
}
if ($status !== '') {
    $where[] = 'd.status = ?';
    $params[] = $status;
}

$rows = query_all(
    'SELECT d.*, p.title, buyer.name AS buyer_name, seller.name AS seller_name
     FROM deals d
     JOIN products p ON p.id = d.product_id
     JOIN users buyer ON buyer.id = d.buyer_id
     JOIN users seller ON seller.id = d.seller_id
     WHERE ' . implode(' AND ', $where) . '
     ORDER BY d.updated_at DESC
     LIMIT 100',
    $params
);

$pageTitle = 'Сделки';
$cabinetActive = 'deals';
require __DIR__ . '/../templates/header.php';
?>
<div class="container py-4">
    <h1 class="h2">Сделки</h1>
    <?php require __DIR__ . '/../templates/cabinet-nav.php'; ?>
    <div class="subnav">
        <?php
        $tabs = ['all' => 'Все', 'buy' => 'Покупки', 'sell' => 'Продажи'];
        foreach ($tabs as $key => $label):
            $href = 'cabinet/deals.php?tab=' . $key . ($status !== '' ? '&status=' . urlencode($status) : '');
        ?>
            <a class="<?= $tab === $key ? 'active' : '' ?>" href="<?= e(url($href)) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </div>
    <div class="subnav">
        <a class="<?= $status === '' ? 'active' : '' ?>" href="<?= e(url('cabinet/deals.php?tab=' . $tab)) ?>">Любой статус</a>
        <?php foreach ($statuses as $item): ?>
            <a class="<?= $status === $item ? 'active' : '' ?>" href="<?= e(url('cabinet/deals.php?tab=' . $tab . '&status=' . $item)) ?>"><?= e(deal_status_label($item)) ?></a>
        <?php endforeach; ?>
    </div>
    <div class="panel">
        <?php if (!$rows): ?>
            <p class="mb-0 muted">Таких сделок нет.</p>
        <?php endif; ?>
        <?php foreach ($rows as $deal): ?>
            <?php $role = (int) $deal['buyer_id'] === (int) $user['id'] ? 'Покупка' : 'Продажа'; ?>
            <a class="deal-row" href="<?= e(url('cabinet/deal.php?id=' . (int) $deal['id'])) ?>">
                <div>
                    <h3><?= e($deal['title']) ?></h3>
                    <div class="muted"><?= e($role) ?> · <?= e($role === 'Покупка' ? $deal['seller_name'] : $deal['buyer_name']) ?> · <?= e(dt($deal['created_at'])) ?></div>
                </div>
                <div><?= (float) $deal['amount'] > 0 ? e(money($deal['amount'])) : 'Без оплаты' ?></div>
                <span class="badge-status st-<?= e($deal['status']) ?>"><?= e(deal_status_label((string) $deal['status'], (string) $deal['deal_type'], $deal['amount'])) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</div>
<?php require __DIR__ . '/../templates/footer.php'; ?>
