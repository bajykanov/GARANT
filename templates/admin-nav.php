<?php
declare(strict_types=1);
$adminActive = $adminActive ?? '';
$items = [
    'home' => ['admin/index.php', 'Сводка'],
    'users' => ['admin/users.php', 'Пользователи'],
    'categories' => ['admin/categories.php', 'Категории'],
    'disputes' => ['admin/disputes.php', 'Споры'],
];
?>
<nav class="subnav" aria-label="Админка">
    <?php foreach ($items as $key => [$path, $label]): ?>
        <a class="<?= $adminActive === $key ? 'active' : '' ?>" href="<?= e(url($path)) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</nav>
