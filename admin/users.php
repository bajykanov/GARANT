<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';

$admin = require_admin();

if (is_post()) {
    csrf_check();
    $id = (int) field('id');
    $target = query_one('SELECT * FROM users WHERE id = ?', [$id]);
    if (!$target) {
        flash('danger', 'Пользователь не найден.');
        redirect('admin/users.php');
    }
    if ((int) $target['id'] === (int) $admin['id']) {
        flash('danger', 'Нельзя заблокировать свою сессию.');
        redirect('admin/users.php');
    }
    $willBlock = (int) $target['is_blocked'] === 0;
    if ($willBlock && $target['role'] === 'admin') {
        $alive = query_one("SELECT COUNT(*) AS c FROM users WHERE role = 'admin' AND is_blocked = 0");
        if ((int) ($alive['c'] ?? 0) <= 1) {
            flash('danger', 'Это последний активный администратор.');
            redirect('admin/users.php');
        }
    }
    execute('UPDATE users SET is_blocked = ? WHERE id = ?', [$willBlock ? 1 : 0, $id]);
    flash('success', $willBlock ? 'Пользователь заблокирован.' : 'Блокировка снята.');
    redirect('admin/users.php' . (isset($_GET['q']) ? '' : ''));
}

$q = trim((string) ($_GET['q'] ?? ''));
$params = [];
$sql = 'SELECT * FROM users';
if ($q !== '') {
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $sql .= " WHERE name LIKE ? ESCAPE '\\\\' OR email LIKE ? ESCAPE '\\\\'";
    $params = [$like, $like];
}
$sql .= ' ORDER BY id DESC LIMIT 200';
$rows = query_all($sql, $params);

$pageTitle = 'Пользователи';
$adminActive = 'users';
require __DIR__ . '/../templates/header.php';
?>
<div class="container py-4">
    <h1 class="h2">Пользователи</h1>
    <?php require __DIR__ . '/../templates/admin-nav.php'; ?>
    <form class="row g-2 mb-3" method="get">
        <div class="col-md-6"><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Имя или email"></div>
        <div class="col-auto"><button class="btn btn-garant" type="submit">Найти</button></div>
    </form>
    <div class="panel table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Имя</th><th>Роль</th><th>Рейтинги</th><th>Статус</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td>
                        <a href="<?= e(url('user.php?id=' . (int) $row['id'])) ?>"><?= e($row['name']) ?></a>
                        <div class="muted"><?= e($row['email']) ?></div>
                    </td>
                    <td><?= $row['role'] === 'admin' ? 'Администратор' : 'Участник' ?></td>
                    <td>П <?= e(number_format((float) $row['seller_rating'], 1, ',', '')) ?> / К <?= e(number_format((float) $row['buyer_rating'], 1, ',', '')) ?></td>
                    <td><?= (int) $row['is_blocked'] === 1 ? 'Заблокирован' : 'Активен' ?></td>
                    <td>
                        <?php if ((int) $row['id'] !== (int) $admin['id']): ?>
                            <form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                <button class="btn btn-sm btn-line" type="submit"><?= (int) $row['is_blocked'] === 1 ? 'Разблокировать' : 'Заблокировать' ?></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../templates/footer.php'; ?>
