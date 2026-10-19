<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

$categoryId = max(0, (int) ($_GET['category'] ?? 0));
$type = (string) ($_GET['type'] ?? '');
if (!in_array($type, ['sale', 'exchange', 'both'], true)) {
    $type = '';
}
$sort = (string) ($_GET['sort'] ?? 'new');
if (!in_array($sort, ['new', 'price_asc', 'price_desc', 'rating'], true)) {
    $sort = 'new';
}
$ratingRaw = (string) ($_GET['rating'] ?? '');
$ratingMin = in_array($ratingRaw, ['4', '4.5', '5'], true) ? (float) $ratingRaw : null;
$q = trim((string) ($_GET['q'] ?? ''));
if (mb_strlen($q) > 100) {
    $q = mb_substr($q, 0, 100);
}
$minRaw = trim((string) ($_GET['price_min'] ?? ''));
$maxRaw = trim((string) ($_GET['price_max'] ?? ''));
$priceMin = $minRaw === '' || !is_numeric(str_replace(',', '.', $minRaw)) ? null : (float) str_replace(',', '.', $minRaw);
$priceMax = $maxRaw === '' || !is_numeric(str_replace(',', '.', $maxRaw)) ? null : (float) str_replace(',', '.', $maxRaw);
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 9;

$where = ["p.status = 'active'", 'u.is_blocked = 0'];
$params = [];

if ($categoryId > 0 && category_by_id($categoryId)) {
    $ids = category_descendant_ids($categoryId);
    $where[] = 'p.category_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
    foreach ($ids as $id) {
        $params[] = $id;
    }
}
if ($type !== '') {
    $where[] = 'p.deal_type = ?';
    $params[] = $type;
}
if ($priceMin !== null) {
    $where[] = 'p.price >= ?';
    $params[] = $priceMin;
}
if ($priceMax !== null) {
    $where[] = 'p.price <= ?';
    $params[] = $priceMax;
}
if ($ratingMin !== null) {
    $where[] = 'u.seller_rating >= ?';
    $params[] = $ratingMin;
}
if ($q !== '') {
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $where[] = '(p.title LIKE ? ESCAPE \'\\\\\' OR p.description LIKE ? ESCAPE \'\\\\\')';
    $params[] = $like;
    $params[] = $like;
}

$whereSql = implode(' AND ', $where);
$order = match ($sort) {
    'price_asc' => 'p.price IS NULL, p.price ASC',
    'price_desc' => 'p.price IS NULL, p.price DESC',
    'rating' => 'u.seller_rating DESC, p.created_at DESC',
    default => 'p.created_at DESC',
};

$totalRow = query_one(
    "SELECT COUNT(*) AS c
     FROM products p
     JOIN users u ON u.id = p.user_id
     JOIN categories c ON c.id = p.category_id
     WHERE {$whereSql}",
    $params
);
$total = (int) ($totalRow['c'] ?? 0);
$pages = max(1, (int) ceil($total / $perPage));
if ($page > $pages) {
    $page = $pages;
}
$offset = ($page - 1) * $perPage;

$products = query_all(
    "SELECT p.*, u.name AS seller_name, u.seller_rating, c.name AS category_name, c.slug AS category_slug,
            (SELECT path FROM product_images i WHERE i.product_id = p.id ORDER BY i.is_main DESC, i.id ASC LIMIT 1) AS image
     FROM products p
     JOIN users u ON u.id = p.user_id
     JOIN categories c ON c.id = p.category_id
     WHERE {$whereSql}
     ORDER BY {$order}
     LIMIT {$perPage} OFFSET {$offset}",
    $params
);

$pageTitle = 'Каталог';
$navActive = 'catalog';
require __DIR__ . '/templates/header.php';
?>
<div class="container py-4">
    <div class="section-head">
        <div>
            <h1>Каталог</h1>
            <p>Найдено: <?= (int) $total ?></p>
        </div>
    </div>
    <div class="row g-4">
        <div class="col-lg-3">
            <form class="filter-card" method="get" action="<?= e(url('catalog.php')) ?>">
                <label class="form-label" for="q">Поиск</label>
                <input class="form-control mb-3" id="q" name="q" value="<?= e($q) ?>">

                <label class="form-label" for="category">Категория</label>
                <select class="form-select mb-3" id="category" name="category">
                    <option value="0">Все категории</option>
                    <?php render_category_options($categoryId > 0 ? $categoryId : null); ?>
                </select>

                <label class="form-label" for="type">Тип сделки</label>
                <select class="form-select mb-3" id="type" name="type">
                    <option value="">Любой</option>
                    <option value="sale"<?= selected($type === 'sale') ?>>Продажа</option>
                    <option value="exchange"<?= selected($type === 'exchange') ?>>Обмен</option>
                    <option value="both"<?= selected($type === 'both') ?>>Продажа и обмен</option>
                </select>

                <label class="form-label">Цена, ₽</label>
                <div class="row g-2 mb-3">
                    <div class="col"><input class="form-control" name="price_min" inputmode="decimal" placeholder="от" value="<?= e($minRaw) ?>"></div>
                    <div class="col"><input class="form-control" name="price_max" inputmode="decimal" placeholder="до" value="<?= e($maxRaw) ?>"></div>
                </div>

                <label class="form-label" for="rating">Рейтинг продавца</label>
                <select class="form-select mb-3" id="rating" name="rating">
                    <option value="">Любой</option>
                    <option value="4"<?= selected($ratingRaw === '4') ?>>от 4.0</option>
                    <option value="4.5"<?= selected($ratingRaw === '4.5') ?>>от 4.5</option>
                    <option value="5"<?= selected($ratingRaw === '5') ?>>5.0</option>
                </select>

                <label class="form-label" for="sort">Сортировка</label>
                <select class="form-select mb-3" id="sort" name="sort">
                    <option value="new"<?= selected($sort === 'new') ?>>Сначала новые</option>
                    <option value="price_asc"<?= selected($sort === 'price_asc') ?>>Цена по возрастанию</option>
                    <option value="price_desc"<?= selected($sort === 'price_desc') ?>>Цена по убыванию</option>
                    <option value="rating"<?= selected($sort === 'rating') ?>>Рейтинг продавца</option>
                </select>

                <button class="btn btn-garant w-100" type="submit">Показать</button>
                <a class="btn btn-line w-100 mt-2" href="<?= e(url('catalog.php')) ?>">Сбросить</a>
            </form>
        </div>
        <div class="col-lg-9">
            <?php if (!$products): ?>
                <div class="empty-state">Ничего не нашлось. Ослабьте фильтры или загляните в соседнюю категорию.</div>
            <?php else: ?>
                <div class="pcard-grid">
                    <?php foreach ($products as $product): ?>
                        <?php render_product_card($product); ?>
                    <?php endforeach; ?>
                </div>
                <?php render_pagination($page, $pages, 'catalog.php'); ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require __DIR__ . '/templates/footer.php'; ?>
