<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';

$user = require_login();
$id = max(0, (int) ($_GET['id'] ?? $_POST['deal_id'] ?? 0));
$deal = find_deal($id);
$role = $deal ? deal_party_role($deal, (int) $user['id']) : null;
if (!$deal || (!$role && $user['role'] !== 'admin')) {
    abort_404('Сделка не найдена.');
}

if (is_post()) {
    csrf_check();
    if (!$role) {
        flash('danger', 'Действия доступны только сторонам сделки.');
        redirect('cabinet/deal.php?id=' . $id);
    }
    $action = field('action');
    $error = match ($action) {
        'ship' => ship_deal($id, (int) $user['id']),
        'confirm' => confirm_receipt($id, (int) $user['id']),
        'confirm_exchange' => confirm_exchange($id, (int) $user['id']),
        'cancel' => cancel_deal($id, (int) $user['id']),
        'dispute' => open_dispute($id, (int) $user['id'], field('reason')),
        'message' => add_deal_message($id, (int) $user['id'], field('body')),
        'review' => add_review($id, (int) $user['id'], (int) field('rating'), field('comment')),
        default => 'Неизвестное действие.',
    };
    if ($error) {
        flash('danger', $error);
    }
    redirect('cabinet/deal.php?id=' . $id);
}

if ($role) {
    execute(
        'UPDATE messages SET is_read = 1 WHERE deal_id = ? AND sender_id IS NOT NULL AND sender_id <> ?',
        [$id, (int) $user['id']]
    );
}

$messages = query_all(
    'SELECT m.*, u.name AS sender_name
     FROM messages m
     LEFT JOIN users u ON u.id = m.sender_id
     WHERE m.deal_id = ?
     ORDER BY m.id ASC',
    [$id]
);
$ledger = query_all('SELECT * FROM escrow_transactions WHERE deal_id = ? ORDER BY id ASC', [$id]);
$myReview = $role ? query_one('SELECT * FROM reviews WHERE deal_id = ? AND author_id = ?', [$id, (int) $user['id']]) : null;
$counterpartyId = $role === 'buyer' ? (int) $deal['seller_id'] : (int) $deal['buyer_id'];
$counterparty = $role ? query_one('SELECT name, phone, email FROM users WHERE id = ?', [$counterpartyId]) : null;

$shipped = $deal['shipped_at'] !== null || in_array($deal['status'], ['shipped', 'confirmed', 'completed'], true);
$pageTitle = 'Сделка ' . $deal['product_title'];
$cabinetActive = 'deals';
require __DIR__ . '/../templates/header.php';
?>
<div class="container py-4">
    <h1 class="h2">Сделка №<?= (int) $deal['id'] ?></h1>
    <?php require __DIR__ . '/../templates/cabinet-nav.php'; ?>
    <div class="row g-4">
        <div class="col-lg-8 stack">
            <section class="panel">
                <div class="d-flex justify-content-between gap-3 flex-wrap">
                    <div>
                        <a href="<?= e(url('product.php?id=' . (int) $deal['product_id'])) ?>"><?= e($deal['product_title']) ?></a>
                        <h2 class="h4 mt-2 mb-1"><?= e(deal_type_label((string) $deal['deal_type'])) ?></h2>
                        <div><?= (float) $deal['amount'] > 0 ? e(money($deal['amount'])) : 'Денежный расчёт не требуется' ?></div>
                    </div>
                    <span class="badge-status st-<?= e($deal['status']) ?>"><?= e(deal_status_label((string) $deal['status'], (string) $deal['deal_type'], $deal['amount'])) ?></span>
                </div>
                <ul class="timeline mt-3">
                    <li class="done">Зафиксировано гарантом <span class="muted"><?= e(dt($deal['created_at'])) ?></span></li>
                    <li class="<?= $shipped ? 'done' : '' ?>">Отправлено <?= $deal['shipped_at'] ? '<span class="muted">' . e(dt($deal['shipped_at'])) . '</span>' : '' ?></li>
                    <li class="<?= (int) $deal['buyer_confirmed'] === 1 ? 'done' : '' ?>">Покупатель подтвердил получение</li>
                    <?php if ($deal['deal_type'] === 'exchange'): ?>
                        <li class="<?= (int) $deal['seller_confirmed'] === 1 ? 'done' : '' ?>">Продавец подтвердил встречный товар</li>
                    <?php endif; ?>
                    <li class="<?= $deal['status'] === 'completed' ? 'done' : '' ?><?= in_array($deal['status'], ['dispute', 'refunded', 'cancelled'], true) ? ' warn' : '' ?>">
                        <?php if ($deal['status'] === 'completed'): ?>
                            Завершено <?= e(dt($deal['completed_at'])) ?>
                        <?php elseif ($deal['status'] === 'dispute'): ?>
                            Спор, деньги остаются у гаранта
                        <?php elseif (in_array($deal['status'], ['refunded', 'cancelled'], true)): ?>
                            <?= e(deal_status_label((string) $deal['status'], (string) $deal['deal_type'], $deal['amount'])) ?>
                        <?php else: ?>
                            Ожидает завершения
                        <?php endif; ?>
                    </li>
                </ul>
                <?php if ($deal['escrow_deadline'] && in_array($deal['status'], ['shipped', 'confirmed'], true)): ?>
                    <p class="mb-0 mt-2">Подтвердить до <?= e(dt($deal['escrow_deadline'])) ?>. Потом платформа откроет спор или вернёт оплату — сейчас в настройках: <?= ESCROW_ON_TIMEOUT === 'refund' ? 'автовозврат' : 'спор' ?>.</p>
                <?php endif; ?>
            </section>

            <section class="panel">
                <h2 class="h5">Доставка и условия</h2>
                <p><?= e($deal['delivery_name'] ?: 'Способ не указан') ?><?php if ($deal['delivery_kind']): ?> · <?= e(delivery_kind_label((string) $deal['delivery_kind'])) ?><?php endif; ?></p>
                <p><?= nl($deal['delivery_address']) ?></p>
                <?php if ($deal['buyer_note']): ?><p class="mb-1"><strong>Комментарий покупателя.</strong> <?= nl($deal['buyer_note']) ?></p><?php endif; ?>
                <?php if ($deal['exchange_offer']): ?><p class="mb-0"><strong>Предложение обмена.</strong> <?= nl($deal['exchange_offer']) ?></p><?php endif; ?>
                <p class="mb-0 mt-3">
                    Покупатель: <a href="<?= e(url('user.php?id=' . (int) $deal['buyer_id'])) ?>"><?= e($deal['buyer_name']) ?></a><br>
                    Продавец: <a href="<?= e(url('user.php?id=' . (int) $deal['seller_id'])) ?>"><?= e($deal['seller_name']) ?></a>
                </p>
                <?php if ($counterparty): ?>
                    <p class="muted mb-0">Контакт контрагента внутри сделки: <?= e($counterparty['phone'] ?: 'телефон не указан') ?>, <?= e($counterparty['email']) ?></p>
                <?php endif; ?>
            </section>

            <section class="panel">
                <h2 class="h5">Журнал гаранта</h2>
                <?php if (!$ledger): ?>
                    <p class="muted mb-0">Денежных проводок нет — это обмен без доплаты.</p>
                <?php else: ?>
                    <table class="table mb-0">
                        <thead><tr><th>Когда</th><th>Операция</th><th>Сумма</th><th>Комментарий</th></tr></thead>
                        <tbody>
                        <?php foreach ($ledger as $row): ?>
                            <tr>
                                <td><?= e(dt($row['created_at'])) ?></td>
                                <td><?= e($row['type'] === 'hold' ? 'Заморозка' : ($row['type'] === 'release' ? 'Перевод продавцу' : 'Возврат')) ?></td>
                                <td><?= e(money($row['amount'])) ?></td>
                                <td><?= e($row['comment']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>

            <section class="panel">
                <h2 class="h5">Переписка</h2>
                <div class="chat mb-3">
                    <?php foreach ($messages as $message): ?>
                        <?php
                        $mine = $message['sender_id'] !== null && (int) $message['sender_id'] === (int) $user['id'];
                        $class = $message['sender_id'] === null ? 'system' : ($mine ? 'mine' : '');
                        ?>
                        <div class="bubble <?= e($class) ?>">
                            <?= nl($message['body']) ?>
                            <small><?= $message['sender_id'] === null ? 'ГАРАНТ' : e($message['sender_name'] ?? '') ?> · <?= e(dt($message['created_at'])) ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if ($role): ?>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="deal_id" value="<?= (int) $id ?>">
                        <input type="hidden" name="action" value="message">
                        <label class="form-label" for="body">Сообщение контрагенту</label>
                        <textarea class="form-control" id="body" name="body" rows="3" required></textarea>
                        <button class="btn btn-garant mt-2" type="submit">Отправить</button>
                    </form>
                <?php endif; ?>
            </section>
        </div>
        <div class="col-lg-4">
            <aside class="panel stack">
                <h2 class="h5 mb-0">Действия</h2>
                <?php if ($role === 'seller' && $deal['status'] === 'paid'): ?>
                    <form method="post" data-confirm="Отметить товар отправленным? После этого отменить сделку уже нельзя.">
                        <?= csrf_field() ?>
                        <input type="hidden" name="deal_id" value="<?= (int) $id ?>">
                        <input type="hidden" name="action" value="ship">
                        <button class="btn btn-garant w-100" type="submit">Товар отправлен</button>
                    </form>
                <?php endif; ?>
                <?php if ($role === 'buyer' && $deal['status'] === 'shipped'): ?>
                    <form method="post" data-confirm="<?= $deal['deal_type'] === 'exchange' ? 'Подтвердить, что вы получили товар?' : 'Подтвердить получение? Деньги сразу уйдут продавцу.' ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="deal_id" value="<?= (int) $id ?>">
                        <input type="hidden" name="action" value="confirm">
                        <button class="btn btn-amber w-100" type="submit">Подтвердить получение</button>
                    </form>
                <?php endif; ?>
                <?php if ($role === 'seller' && $deal['deal_type'] === 'exchange' && $deal['status'] === 'confirmed'): ?>
                    <form method="post" data-confirm="Подтвердить, что встречный товар получен, и закрыть обмен?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="deal_id" value="<?= (int) $id ?>">
                        <input type="hidden" name="action" value="confirm_exchange">
                        <button class="btn btn-amber w-100" type="submit">Я получил обмен</button>
                    </form>
                <?php endif; ?>
                <?php if ($role && $deal['status'] === 'paid'): ?>
                    <form method="post" data-confirm="Отменить сделку и снять резерв?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="deal_id" value="<?= (int) $id ?>">
                        <input type="hidden" name="action" value="cancel">
                        <button class="btn btn-line w-100" type="submit">Отменить до отправки</button>
                    </form>
                <?php endif; ?>
                <?php if ($role && in_array($deal['status'], ['shipped', 'confirmed'], true)): ?>
                    <form method="post" data-confirm="Открыть спор? Деньги останутся у гаранта до решения администратора.">
                        <?= csrf_field() ?>
                        <input type="hidden" name="deal_id" value="<?= (int) $id ?>">
                        <input type="hidden" name="action" value="dispute">
                        <label class="form-label" for="reason">Причина спора</label>
                        <textarea class="form-control mb-2" id="reason" name="reason" rows="3" required></textarea>
                        <button class="btn btn-danger-soft w-100" type="submit">Открыть спор</button>
                    </form>
                <?php endif; ?>
                <?php if ($deal['status'] === 'dispute'): ?>
                    <p class="mb-0">Спор рассматривает администратор. До решения проводка release или refund не создаётся.</p>
                    <?php if ($user['role'] === 'admin'): ?>
                        <a class="btn btn-line" href="<?= e(url('admin/disputes.php')) ?>">К спорам</a>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if ($deal['status'] === 'completed' && $role && !$myReview): ?>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="deal_id" value="<?= (int) $id ?>">
                        <input type="hidden" name="action" value="review">
                        <label class="form-label" for="rating">Оценка <?= $role === 'buyer' ? 'продавца' : 'покупателя' ?></label>
                        <select class="form-select mb-2" id="rating" name="rating" required>
                            <option value="5">5 — отлично</option>
                            <option value="4">4 — хорошо</option>
                            <option value="3">3 — нормально</option>
                            <option value="2">2 — плохо</option>
                            <option value="1">1 — очень плохо</option>
                        </select>
                        <label class="form-label" for="comment">Комментарий</label>
                        <textarea class="form-control mb-2" id="comment" name="comment" rows="3" required></textarea>
                        <button class="btn btn-garant w-100" type="submit">Оставить отзыв</button>
                    </form>
                <?php elseif ($myReview): ?>
                    <p class="mb-0">Ваш отзыв: <?= stars($myReview['rating']) ?></p>
                <?php endif; ?>
                <?php if ($deal['status'] === 'paid' && $role === 'buyer'): ?>
                    <p class="muted mb-0">Продавец ещё не отметил отправку. До этого момента оплату можно отменить.</p>
                <?php endif; ?>
            </aside>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../templates/footer.php'; ?>
