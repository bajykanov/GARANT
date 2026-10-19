<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';

$user = require_login();
$id = max(0, (int) ($_GET['id'] ?? $_POST['id'] ?? 0));
$product = null;
if ($id > 0) {
    $product = query_one('SELECT * FROM products WHERE id = ? AND user_id = ?', [$id, (int) $user['id']]);
    if (!$product) {
        abort_404('Объявление не найдено.');
    }
}

if (is_post() && field('action') === 'delete_image') {
    csrf_check();
    $imageId = (int) field('image_id');
    $image = query_one(
        'SELECT i.* FROM product_images i JOIN products p ON p.id = i.product_id WHERE i.id = ? AND p.user_id = ?',
        [$imageId, (int) $user['id']]
    );
    if (!$image) {
        flash('danger', 'Фото не найдено.');
        redirect('cabinet/listing.php?id=' . $id);
    }
    remove_upload((string) $image['path']);
    execute('DELETE FROM product_images WHERE id = ?', [$imageId]);
    if ((int) $image['is_main'] === 1) {
        $next = query_one('SELECT id FROM product_images WHERE product_id = ? ORDER BY id ASC LIMIT 1', [(int) $image['product_id']]);
        if ($next) {
            execute('UPDATE product_images SET is_main = 1 WHERE id = ?', [(int) $next['id']]);
        }
    }
    flash('success', 'Фото удалено.');
    redirect('cabinet/listing.php?id=' . (int) $image['product_id']);
}

if (is_post()) {
    csrf_check();
    $title = field('title');
    $categoryId = (int) field('category_id');
    $description = field('description');
    $dealType = field('deal_type');
    $priceRaw = str_replace(',', '.', field('price'));
    $exchangeFor = field('exchange_for');
    $quantity = (int) field('quantity');
    $city = field('city');
    $visibility = field('visibility');
    $delivery = array_values(array_unique(array_map('intval', (array) ($_POST['delivery'] ?? []))));
    $_POST['delivery'] = $delivery;

    $errors = [];
    if (mb_strlen($title) < 3 || mb_strlen($title) > 200) {
        $errors['title'] = 'Заголовок — от 3 до 200 символов.';
    }
    if (!category_by_id($categoryId)) {
        $errors['category_id'] = 'Выберите категорию.';
    }
    if (mb_strlen($description) < 10 || mb_strlen($description) > 5000) {
        $errors['description'] = 'Описание — от 10 до 5000 символов.';
    }
    if (!in_array($dealType, ['sale', 'exchange', 'both'], true)) {
        $errors['deal_type'] = 'Выберите тип сделки.';
    }
    $price = null;
    if ($dealType === 'sale' || $dealType === 'both') {
        if ($priceRaw === '' || !is_numeric($priceRaw) || (float) $priceRaw <= 0 || (float) $priceRaw > 99999999) {
            $errors['price'] = 'Укажите цену больше нуля.';
        } else {
            $price = number_format((float) $priceRaw, 2, '.', '');
        }
    }
    if (($dealType === 'exchange' || $dealType === 'both') && mb_strlen($exchangeFor) < 3) {
        $errors['exchange_for'] = 'Напишите, на что готовы менять.';
    }
    if ($quantity < 1 || $quantity > 100000) {
        $errors['quantity'] = 'Количество — от 1 до 100000.';
    }
    if (mb_strlen($city) > 120) {
        $errors['city'] = 'Слишком длинный город.';
    }
    $knownDelivery = array_map(static fn (array $row): int => (int) $row['id'], delivery_options());
    $delivery = array_values(array_filter($delivery, static fn (int $item): bool => in_array($item, $knownDelivery, true)));
    if (!$delivery) {
        $errors['delivery'] = 'Отметьте хотя бы один способ доставки.';
    }

    $files = normalize_uploads('images');
    $existingCount = $product ? count(product_images((int) $product['id'])) : 0;
    if ($existingCount + count($files) > 5) {
        $errors['images'] = 'Не больше 5 фотографий на объявление.';
    }
    if (!$errors) {
        foreach ($files as $file) {
            try {
                inspect_image($file);
            } catch (RuntimeException $e) {
                $errors['images'] = $e->getMessage();
                break;
            }
        }
    }

    if ($errors) {
        remember_form($errors);
        flash('danger', 'Проверьте выделенные поля.');
        redirect('cabinet/listing.php' . ($id ? '?id=' . $id : ''));
    }

    $status = match ($visibility) {
        'hidden' => 'hidden',
        'sold' => 'sold',
        default => 'active',
    };

    $savedId = (int) db_transaction(static function () use (
        $product, $user, $categoryId, $title, $description, $price, $dealType, $exchangeFor, $quantity, $city, $status, $delivery
    ): int {
        if ($product) {
            execute(
                'UPDATE products
                 SET category_id = ?, title = ?, description = ?, price = ?, deal_type = ?, exchange_for = ?, quantity = ?, city = ?, status = ?
                 WHERE id = ? AND user_id = ?',
                [
                    $categoryId, $title, $description, $price, $dealType,
                    $exchangeFor !== '' ? $exchangeFor : null, $quantity,
                    $city !== '' ? $city : null, $status, (int) $product['id'], (int) $user['id'],
                ]
            );
            $productId = (int) $product['id'];
        } else {
            $productId = insert_row(
                'INSERT INTO products (user_id, category_id, title, description, price, deal_type, exchange_for, quantity, city, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    (int) $user['id'], $categoryId, $title, $description, $price, $dealType,
                    $exchangeFor !== '' ? $exchangeFor : null, $quantity, $city !== '' ? $city : null, $status,
                ]
            );
        }
        execute('DELETE FROM product_delivery WHERE product_id = ?', [$productId]);
        foreach ($delivery as $optionId) {
            execute('INSERT INTO product_delivery (product_id, delivery_option_id) VALUES (?, ?)', [$productId, $optionId]);
        }
        return $productId;
    });

    $hasMain = (bool) query_one('SELECT id FROM product_images WHERE product_id = ? AND is_main = 1', [$savedId]);
    foreach ($files as $index => $file) {
        try {
            store_product_image($savedId, $file, !$hasMain && $index === 0);
        } catch (RuntimeException $e) {
            flash('warning', $e->getMessage());
        }
    }

    clear_form_state();
    flash('success', $product ? 'Объявление обновлено.' : 'Объявление опубликовано.');
    redirect('cabinet/listing.php?id=' . $savedId);
}

$savedDelivery = $product ? product_delivery_ids((int) $product['id']) : [];
$pickedDelivery = old('delivery', null);
if (!is_array($pickedDelivery)) {
    $pickedDelivery = $savedDelivery;
}
$images = $product ? product_images((int) $product['id']) : [];
$currentType = (string) old('deal_type', $product['deal_type'] ?? 'sale');
$currentCategory = (int) old('category_id', $product['category_id'] ?? 0);
$visibility = (string) old('visibility', (string) ($product['status'] ?? 'active'));

$pageTitle = $product ? 'Редактирование объявления' : 'Новое объявление';
$cabinetActive = 'listings';
require __DIR__ . '/../templates/header.php';
?>
<div class="container py-4" style="max-width: 860px;">
    <h1 class="h2"><?= $product ? 'Редактирование' : 'Новое объявление' ?></h1>
    <?php require __DIR__ . '/../templates/cabinet-nav.php'; ?>
    <form class="panel" method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $id ?>">
        <label class="form-label" for="title">Заголовок</label>
        <input class="form-control<?= error_class('title') ?>" id="title" name="title" value="<?= e((string) old('title', $product['title'] ?? '')) ?>" required>
        <?= error_text('title') ?>

        <label class="form-label mt-3" for="category_id">Категория</label>
        <select class="form-select<?= error_class('category_id') ?>" id="category_id" name="category_id" required>
            <option value="">Выберите</option>
            <?php render_category_options($currentCategory > 0 ? $currentCategory : null); ?>
        </select>
        <?= error_text('category_id') ?>

        <label class="form-label mt-3" for="description">Описание</label>
        <textarea class="form-control<?= error_class('description') ?>" id="description" name="description" rows="6" required><?= e((string) old('description', $product['description'] ?? '')) ?></textarea>
        <?= error_text('description') ?>

        <div class="row mt-3">
            <div class="col-md-4">
                <label class="form-label" for="deal_type">Тип сделки</label>
                <select class="form-select" id="deal_type" name="deal_type">
                    <?php foreach (['sale' => 'Продажа', 'exchange' => 'Обмен', 'both' => 'Продажа и обмен'] as $value => $label): ?>
                        <option value="<?= e($value) ?>"<?= selected($currentType === $value) ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="price">Цена, ₽</label>
                <input class="form-control<?= error_class('price') ?>" id="price" name="price" inputmode="decimal" value="<?= e((string) old('price', $product['price'] ?? '')) ?>">
                <?= error_text('price') ?>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="quantity">В наличии</label>
                <input class="form-control" id="quantity" name="quantity" type="number" min="1" value="<?= e((string) old('quantity', (string) ($product['quantity'] ?? 1))) ?>">
                <div class="form-text">Столько ещё можно зарезервировать. Уже оплаченные штуки сюда не входят.</div>
            </div>
        </div>

        <label class="form-label mt-3" for="exchange_for">На что меняете</label>
        <input class="form-control<?= error_class('exchange_for') ?>" id="exchange_for" name="exchange_for" value="<?= e((string) old('exchange_for', $product['exchange_for'] ?? '')) ?>">
        <?= error_text('exchange_for') ?>

        <label class="form-label mt-3" for="city">Город</label>
        <input class="form-control" id="city" name="city" value="<?= e((string) old('city', $product['city'] ?? (string) ($user['city'] ?? ''))) ?>">

        <fieldset class="mt-3">
            <legend class="form-label">Доставка, которую вы готовы обеспечить</legend>
            <?php foreach (delivery_options() as $option): ?>
                <label class="choice">
                    <input type="checkbox" name="delivery[]" value="<?= (int) $option['id'] ?>"<?= checked(in_array((int) $option['id'], array_map('intval', $pickedDelivery), true)) ?>>
                    <?= e($option['name']) ?> <span class="muted">— <?= e(delivery_kind_label((string) $option['kind'])) ?></span>
                </label>
            <?php endforeach; ?>
            <?= error_text('delivery') ?>
        </fieldset>

        <?php if ($product): ?>
            <label class="form-label mt-3" for="visibility">Публикация</label>
            <select class="form-select" id="visibility" name="visibility">
                <?php if (($product['status'] ?? '') === 'sold'): ?>
                    <option value="sold"<?= selected($visibility === 'sold') ?>>Нет в наличии</option>
                <?php endif; ?>
                <option value="active"<?= selected($visibility === 'active') ?>>Показывать в каталоге</option>
                <option value="hidden"<?= selected($visibility === 'hidden') ?>>Скрыть</option>
            </select>
        <?php endif; ?>

        <label class="form-label mt-3" for="images">Фото, до 5 штук, до 2 МБ</label>
        <input class="form-control<?= error_class('images') ?>" id="images" type="file" name="images[]" accept="image/jpeg,image/png,image/webp,image/gif" multiple>
        <?= error_text('images') ?>
        <button class="btn btn-garant mt-4" type="submit"><?= $product ? 'Сохранить' : 'Опубликовать' ?></button>
    </form>

    <?php if ($images): ?>
        <div class="panel mt-3">
            <h2 class="h5">Загруженные фото</h2>
            <div class="d-flex gap-3 flex-wrap">
                <?php foreach ($images as $image): ?>
                    <div>
                        <img src="<?= e(url('uploads/' . $image['path'])) ?>" alt="" width="120" height="120" style="object-fit:cover;border-radius:12px;">
                        <form method="post" class="mt-1">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete_image">
                            <input type="hidden" name="id" value="<?= (int) $id ?>">
                            <input type="hidden" name="image_id" value="<?= (int) $image['id'] ?>">
                            <button class="btn btn-sm btn-line" type="submit">Удалить<?= (int) $image['is_main'] === 1 ? ' обложку' : '' ?></button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../templates/footer.php'; ?>
