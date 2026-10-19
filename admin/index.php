<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';

require_admin();

$counts = [
    'users' => (int) (query_one('SELECT COUNT(*) AS c FROM users')['c'] ?? 0),
    'products' => (int) (query_one("SELECT COUNT(*) AS c FROM products WHERE status = 'active'")['c'] ?? 0),
    'deals' => (int) (query_one('SELECT COUNT(*) AS c FROM deals')['c'] ?? 0),
    'frozen' => (float) (query_one(
        "SELECT COALESCE(SUM(t.amount), 0) AS c
         FROM escrow_transactions t
         JOIN deals d ON d.id = t.deal_id
         WHERE t.type = 'hold'
           AND d.status IN ('paid','shipped','confirmed','dispute')
           AND NOT EXISTS (
               SELECT 1 FROM escrow_transactions x WHERE x.deal_id = d.id AND x.type IN ('release','refund')
           )"
    )['c'] ?? 0),
    'disputes' => (int) (query_one("SELECT COUNT(*) AS c FROM disputes WHERE status = 'open'")['c'] ?? 0),
];

$pageTitle = 'Админка';
$adminActive = 'home';
require __DIR__ . '/../templates/header.php';
?>
<div class="container py-4">
    <h1 class="h2">Админка гаранта</h1>
    <?php require __DIR__ . '/../templates/admin-nav.php'; ?>
    <div class="stat-grid">
        <div class="stat"><span>Пользователи</span><strong><?= $counts['users'] ?></strong></div>
        <div class="stat"><span>Активные объявления</span><strong><?= $counts['products'] ?></strong></div>
        <div class="stat"><span>Все сделки</span><strong><?= $counts['deals'] ?></strong></div>
        <div class="stat"><span>Сейчас заморожено</span><strong><?= e(money($counts['frozen'])) ?></strong></div>
        <div class="stat"><span>Открытые споры</span><strong><?= $counts['disputes'] ?></strong></div>
    </div>
    <p class="mt-4 muted">Заморозка считается по сделкам, где есть hold и ещё нет release или refund. Срок без подтверждения — <?= (int) ESCROW_DAYS ?> дн., реакция на просрочку — <?= ESCROW_ON_TIMEOUT === 'refund' ? 'автовозврат' : 'спор' ?>.</p>
</div>
<?php require __DIR__ . '/../templates/footer.php'; ?>
