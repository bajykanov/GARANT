<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

$user = require_login();
$productId = max(0, (int) ($_GET['id'] ?? $_POST['product_id'] ?? 0));
$product = query_one(
    'SELECT p.*, u.name AS seller_name, u.is_blocked, c.name AS category_name
     FROM products p
     JOIN users u ON u.id = p.user_id
     JOIN categories c ON c.id = p.category_id
     WHERE p.id = ?',
    [$productId]
);
if (!$product || (int) $product['is_blocked'] === 1 || $product['status'] !== 'active') {
    abort_404('Это объявление нельзя оформить.');
}

$deliveryIds = product_delivery_ids($productId);
$options = array_values(array_filter(
    delivery_options(),
    static fn (array $option): bool => in_array((int) $option['id'], $deliveryIds, true)
));
$own = (int) $product['user_id'] === (int) $user['id'];

if (is_post() && !$own) {
    csrf_check();
    try {
        $dealId = create_deal(
            $productId,
            (int) $user['id'],
            field('deal_type'),
            (int) field('delivery_id'),
            field('address'),
            field('note'),
            field('exchange_offer')
        );
        clear_form_state();
        flash('success', 'Сделка создана. Гарант зафиксировал условия.');
        redirect('cabinet/deal.php?id=' . $dealId);
    } catch (RuntimeException $e) {
        $_POST['delivery'] = $_POST['delivery'] ?? [];
        remember_form(['form' => $e->getMessage()]);
        flash('danger', $e->getMessage());
        redirect('checkout.php?id=' . $productId);
    }
}

$dealType = (string) old('deal_type', $product['deal_type'] === 'exchange' ? 'exchange' : 'sale');
if ($product['deal_type'] !== 'both') {
    $dealType = (string) $product['deal_type'];
}
$selectedDelivery = (int) old('delivery_id', $options[0]['id'] ?? 0);

$pageTitle = 'Оформление сделки';
require __DIR__ . '/templates/header.php';
?>
<div class="container py-4">
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="panel">
                <h1 class="h3">Оформление через гаранта</h1>
                <p><a href="<?= e(url('product.php?id=' . $productId)) ?>"><?= e($product['title']) ?></a> · <?= e($product['seller_name']) ?></p>
                <?php if ($own): ?>
                    <div class="alert alert-warning">Это ваше объявление. Сделку с самим собой открыть нельзя.</div>
                <?php elseif (!$options): ?>
                    <div class="alert alert-warning">Продавец не указал доставку, оформить сделку нельзя.</div>
                <?php else: ?>
                    <div class="warning-card mb-3">Оплата демонстрационная. Полный номер карты не нужен и не списывается: платформа только создаёт запись «заморожено» в журнале эскроу<?php if ($user['card_last4']): ?> с пометкой карты <?= e($user['card_brand']) ?> •••• <?= e($user['card_last4']) ?><?php endif; ?>.</div>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="product_id" value="<?= (int) $productId ?>">
                        <?php if ($product['deal_type'] === 'both'): ?>
                            <p class="mb-2"><strong>Тип сделки</strong></p>
                            <label class="choice"><input type="radio" name="deal_type" value="sale"<?= checked($dealType === 'sale') ?>> Продажа<?= $product['price'] !== null ? ' · ' . e(money($product['price'])) : '' ?></label>
                            <label class="choice"><input type="radio" name="deal_type" value="exchange"<?= checked($dealType === 'exchange') ?>> Обмен<?= $product['exchange_for'] ? ' · продавец хочет: ' . e($product['exchange_for']) : '' ?></label>
                        <?php else: ?>
                            <input type="hidden" name="deal_type" value="<?= e((string) $product['deal_type']) ?>">
                            <p>Тип: <strong><?= e(deal_type_label((string) $product['deal_type'])) ?></strong></p>
                        <?php endif; ?>

                        <?php if ($product['deal_type'] !== 'sale'): ?>
                            <div class="mt-3">
                                <label class="form-label" for="exchange_offer">Что предлагаете в обмен</label>
                                <textarea class="form-control" id="exchange_offer" name="exchange_offer" rows="3"><?= e((string) old('exchange_offer')) ?></textarea>
                                <?php if ($product['deal_type'] === 'both'): ?>
                                    <div class="form-text">Нужно только если выбран обмен. Для продажи поле можно оставить пустым.</div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <p class="mt-3 mb-2"><strong>Доставка</strong></p>
                        <?php foreach ($options as $option): ?>
                            <label class="choice">
                                <input type="radio" name="delivery_id" value="<?= (int) $option['id'] ?>"<?= checked($selectedDelivery === (int) $option['id']) ?>>
                                <?= e($option['name']) ?>
                                <span class="muted">— <?= e(delivery_kind_label((string) $option['kind'])) ?><?= $option['description'] ? '. ' . e($option['description']) : '' ?></span>
                            </label>
                        <?php endforeach; ?>

                        <label class="form-label mt-3" for="address">Адрес или место самовывоза</label>
                        <textarea class="form-control" id="address" name="address" rows="2"><?= e((string) old('address')) ?></textarea>
                        <label class="form-label mt-3" for="note">Комментарий продавцу</label>
                        <textarea class="form-control" id="note" name="note" rows="2"><?= e((string) old('note')) ?></textarea>
                        <button class="btn btn-amber mt-3" type="submit"><?= $dealType === 'exchange' && $product['deal_type'] !== 'both' ? 'Зафиксировать обмен' : 'Передать гаранту' ?></button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="panel">
                <h2 class="h5">Что произойдёт</h2>
                <ol class="mb-0">
                    <li>Сделка получит статус «оплачено / зафиксировано».</li>
                    <li>Одна единица товара пропадёт из наличия, пока сделка жива.</li>
                    <li>Продавец отмечает отправку, у вас будет <?= (int) ESCROW_DAYS ?> дн. на подтверждение.</li>
                    <li>До отправки сделку можно отменить — резерв и деньги вернутся.</li>
                </ol>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/templates/footer.php'; ?>
