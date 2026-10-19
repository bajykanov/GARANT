<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';

require_admin();

if (is_post()) {
    csrf_check();
    $action = field('action');
    $name = field('name');
    $parentId = (int) field('parent_id');
    $sort = (int) field('sort_order');
    $id = (int) field('id');

    if ($action === 'delete') {
        $children = query_one('SELECT COUNT(*) AS c FROM categories WHERE parent_id = ?', [$id]);
        $products = query_one('SELECT COUNT(*) AS c FROM products WHERE category_id = ?', [$id]);
        if ((int) ($children['c'] ?? 0) > 0 || (int) ($products['c'] ?? 0) > 0) {
            flash('danger', 'Категория занята подкатегориями или объявлениями.');
        } else {
            execute('DELETE FROM categories WHERE id = ?', [$id]);
            flash('success', 'Категория удалена.');
        }
        redirect('admin/categories.php');
    }

    $errors = [];
    if (mb_strlen($name) < 2 || mb_strlen($name) > 160) {
        $errors[] = 'Название — от 2 до 160 символов.';
    }
    if ($parentId !== 0 && !category_by_id($parentId)) {
        $errors[] = 'Родительская категория не найдена.';
    }
    if ($action === 'save' && $id > 0 && in_array($parentId, category_descendant_ids($id), true)) {
        $errors[] = 'Нельзя вложить категорию саму в себя.';
    }
    if ($errors) {
        flash('danger', implode(' ', $errors));
        redirect('admin/categories.php' . ($id ? '?edit=' . $id : ''));
    }

    $parent = $parentId > 0 ? $parentId : null;
    if ($action === 'save' && $id > 0) {
        execute(
            'UPDATE categories SET parent_id = ?, name = ?, slug = ?, sort_order = ? WHERE id = ?',
            [$parent, $name, unique_slug($name, $id), $sort, $id]
        );
        flash('success', 'Категория обновлена.');
    } else {
        execute(
            'INSERT INTO categories (parent_id, name, slug, sort_order) VALUES (?, ?, ?, ?)',
            [$parent, $name, unique_slug($name), $sort]
        );
        flash('success', 'Категория добавлена.');
    }
    redirect('admin/categories.php');
}

$editId = max(0, (int) ($_GET['edit'] ?? 0));
$edit = $editId ? category_by_id($editId) : null;
$rows = categories_all();

$pageTitle = 'Категории';
$adminActive = 'categories';
require __DIR__ . '/../templates/header.php';
?>
<div class="container py-4">
    <h1 class="h2">Категории</h1>
    <?php require __DIR__ . '/../templates/admin-nav.php'; ?>
    <div class="row g-4">
        <div class="col-lg-5">
            <form class="panel" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="<?= $edit ? 'save' : 'create' ?>">
                <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
                <h2 class="h5"><?= $edit ? 'Изменить' : 'Новая категория' ?></h2>
                <label class="form-label" for="name">Название</label>
                <input class="form-control mb-3" id="name" name="name" value="<?= e((string) ($edit['name'] ?? '')) ?>" required>
                <label class="form-label" for="parent_id">Родитель</label>
                <select class="form-select mb-3" id="parent_id" name="parent_id">
                    <option value="0">Верхний уровень</option>
                    <?php render_category_options(isset($edit['parent_id']) ? (int) $edit['parent_id'] : null, $edit ? (int) $edit['id'] : null); ?>
                </select>
                <label class="form-label" for="sort_order">Порядок</label>
                <input class="form-control mb-3" id="sort_order" name="sort_order" type="number" value="<?= e((string) ($edit['sort_order'] ?? 0)) ?>">
                <button class="btn btn-garant" type="submit"><?= $edit ? 'Сохранить' : 'Добавить' ?></button>
                <?php if ($edit): ?><a class="btn btn-line ms-2" href="<?= e(url('admin/categories.php')) ?>">Отмена</a><?php endif; ?>
            </form>
        </div>
        <div class="col-lg-7">
            <div class="panel">
                <?php foreach ($rows as $row): ?>
                    <?php $depth = $row['parent_id'] ? 1 : 0; ?>
                    <div class="list-row" style="padding-left: <?= $depth * 18 ?>px;">
                        <div>
                            <h3><?= e($row['name']) ?></h3>
                            <div class="muted"><?= e($row['slug']) ?></div>
                        </div>
                        <div></div>
                        <div class="d-flex gap-2">
                            <a class="btn btn-sm btn-line" href="<?= e(url('admin/categories.php?edit=' . (int) $row['id'])) ?>">Изменить</a>
                            <form method="post" data-confirm="Удалить категорию?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><button class="btn btn-sm btn-danger-soft" type="submit">Удалить</button></form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../templates/footer.php'; ?>
