<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';

$user = require_login();
$uid = (int) $user['id'];
$rows = query_all(
    'SELECT d.id, p.title, m.body, m.created_at, m.sender_id, u.name AS sender_name,
            (SELECT COUNT(*) FROM messages mm
             WHERE mm.deal_id = d.id AND mm.is_read = 0 AND mm.sender_id IS NOT NULL AND mm.sender_id <> ?) AS unread
     FROM deals d
     JOIN products p ON p.id = d.product_id
     JOIN messages m ON m.id = (
         SELECT mm.id FROM messages mm WHERE mm.deal_id = d.id ORDER BY mm.id DESC LIMIT 1
     )
     LEFT JOIN users u ON u.id = m.sender_id
     WHERE d.buyer_id = ? OR d.seller_id = ?
     ORDER BY m.id DESC',
    [$uid, $uid, $uid]
);

$pageTitle = 'Сообщения';
$navActive = 'messages';
$cabinetActive = 'messages';
require __DIR__ . '/../templates/header.php';
?>
<div class="container py-4">
    <h1 class="h2">Сообщения</h1>
    <?php require __DIR__ . '/../templates/cabinet-nav.php'; ?>
    <div class="panel">
        <?php if (!$rows): ?>
            <p class="mb-0 muted">Переписка появляется внутри сделки. Пока сделок с сообщениями нет.</p>
        <?php endif; ?>
        <?php foreach ($rows as $row): ?>
            <a class="deal-row" href="<?= e(url('cabinet/deal.php?id=' . (int) $row['id'])) ?>">
                <div>
                    <h3><?= e($row['title']) ?><?php if ((int) $row['unread'] > 0): ?> <span class="msg-badge"><?= (int) $row['unread'] ?></span><?php endif; ?></h3>
                    <div class="muted"><?= e(excerpt($row['sender_id'] === null ? 'ГАРАНТ: ' . $row['body'] : ($row['sender_name'] . ': ' . $row['body']), 120)) ?></div>
                </div>
                <div></div>
                <span class="muted"><?= e(dt($row['created_at'])) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</div>
<?php require __DIR__ . '/../templates/footer.php'; ?>
