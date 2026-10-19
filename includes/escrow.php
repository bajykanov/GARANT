<?php
declare(strict_types=1);

/**
 * Эскроу-ядро платформы. Платформа не отдаёт деньги продавцу, пока покупатель
 * не подтвердит получение. До этого сумма висит записью hold.
 *
 * Жизненный цикл сделки:
 * 1. paid       — оплата заморожена, единица товара снята с витрины (резерв).
 * 2. shipped    — продавец отметил отправку, пошёл срок ESCROW_DAYS.
 * 3. confirmed  — только обмен: покупатель получил товар, ждём встречное подтверждение.
 * 4. completed  — продажа: покупатель подтвердил, запись release переводит деньги продавцу.
 *                 Обмен: подтвердили обе стороны.
 * 5. dispute    — спор открыл участник или срок вышел (если ESCROW_ON_TIMEOUT = dispute).
 * 6. refunded   — гарант вернул деньги (запись refund) и снял резерв товара.
 * 7. cancelled  — стороны отменили сделку до отправки, деньги тоже возвращаются.
 *
 * Отзыв возможен только из completed. Покупатель двигает рейтинг продавца,
 * продавец — рейтинг покупателя.
 */

function find_deal(int $id): ?array
{
    return query_one(
        'SELECT d.*,
                p.title AS product_title, p.city AS product_city,
                buyer.name AS buyer_name,
                seller.name AS seller_name,
                del.name AS delivery_name, del.kind AS delivery_kind
         FROM deals d
         JOIN products p ON p.id = d.product_id
         JOIN users buyer ON buyer.id = d.buyer_id
         JOIN users seller ON seller.id = d.seller_id
         LEFT JOIN delivery_options del ON del.id = d.delivery_option_id
         WHERE d.id = ?',
        [$id]
    );
}

function deal_party_role(array $deal, int $userId): ?string
{
    if ((int) $deal['buyer_id'] === $userId) {
        return 'buyer';
    }
    if ((int) $deal['seller_id'] === $userId) {
        return 'seller';
    }
    return null;
}

function deal_log(int $dealId, string $text): void
{
    execute(
        'INSERT INTO messages (deal_id, sender_id, body, is_read) VALUES (?, NULL, ?, 1)',
        [$dealId, $text]
    );
}

function escrow_settled(int $dealId): bool
{
    $row = query_one(
        'SELECT id FROM escrow_transactions WHERE deal_id = ? AND type IN (?, ?) LIMIT 1',
        [$dealId, 'release', 'refund']
    );
    return $row !== null;
}

function escrow_hold(int $dealId, string $amount, string $comment): void
{
    if ((float) $amount <= 0) {
        return;
    }
    execute(
        'INSERT INTO escrow_transactions (deal_id, type, amount, comment) VALUES (?, ?, ?, ?)',
        [$dealId, 'hold', $amount, $comment]
    );
}

function escrow_release(int $dealId, string $amount, string $comment): void
{
    if ((float) $amount <= 0 || escrow_settled($dealId)) {
        return;
    }
    execute(
        'INSERT INTO escrow_transactions (deal_id, type, amount, comment) VALUES (?, ?, ?, ?)',
        [$dealId, 'release', $amount, $comment]
    );
}

function escrow_refund(int $dealId, string $amount, string $comment): void
{
    if ((float) $amount <= 0 || escrow_settled($dealId)) {
        return;
    }
    execute(
        'INSERT INTO escrow_transactions (deal_id, type, amount, comment) VALUES (?, ?, ?, ?)',
        [$dealId, 'refund', $amount, $comment]
    );
}

/** Резерв снимается при отмене, возврате и проигрыше спора покупателем в пользу возврата. */
function restore_stock(int $productId): void
{
    execute(
        "UPDATE products
         SET quantity = quantity + 1,
             status = IF(status = 'sold', 'active', status)
         WHERE id = ?",
        [$productId]
    );
}

function create_deal(
    int $productId,
    int $buyerId,
    string $dealType,
    int $deliveryId,
    string $address,
    string $note,
    string $exchangeOffer
): int {
    return (int) db_transaction(static function () use (
        $productId,
        $buyerId,
        $dealType,
        $deliveryId,
        $address,
        $note,
        $exchangeOffer
    ): int {
        $product = query_one(
            'SELECT p.*, u.is_blocked
             FROM products p
             JOIN users u ON u.id = p.user_id
             WHERE p.id = ?
             FOR UPDATE',
            [$productId]
        );
        if (!$product || $product['status'] !== 'active' || (int) $product['quantity'] < 1) {
            throw new RuntimeException('Объявление недоступно для сделки.');
        }
        if ((int) $product['is_blocked'] === 1) {
            throw new RuntimeException('Продавец заблокирован, сделка невозможна.');
        }
        if ((int) $product['user_id'] === $buyerId) {
            throw new RuntimeException('Нельзя заключить сделку по своему объявлению.');
        }

        $allowed = $product['deal_type'] === 'both' ? ['sale', 'exchange'] : [$product['deal_type']];
        if (!in_array($dealType, $allowed, true)) {
            throw new RuntimeException('Этот тип сделки не разрешён в объявлении.');
        }

        $delivery = query_one(
            'SELECT d.*
             FROM delivery_options d
             JOIN product_delivery pd ON pd.delivery_option_id = d.id
             WHERE pd.product_id = ? AND d.id = ?',
            [$productId, $deliveryId]
        );
        if (!$delivery) {
            throw new RuntimeException('Выберите доставку из списка продавца.');
        }
        if ($delivery['kind'] !== 'pickup' && mb_strlen($address) < 5) {
            throw new RuntimeException('Укажите адрес доставки — не короче 5 символов.');
        }
        if ($delivery['kind'] === 'pickup' && $address === '') {
            $address = 'Самовывоз';
        }

        if ($dealType === 'exchange' && mb_strlen($exchangeOffer) < 3) {
            throw new RuntimeException('Опишите, что предлагаете в обмен.');
        }
        if ($dealType === 'sale') {
            $exchangeOffer = '';
            if ($product['price'] === null || (float) $product['price'] <= 0) {
                throw new RuntimeException('У объявления нет цены, покупка невозможна.');
            }
        }

        // Списываем единицу сразу, чтобы два покупателя не оплатили последний экземпляр.
        $reserved = execute(
            "UPDATE products
             SET quantity = quantity - 1,
                 status = IF(quantity <= 1, 'sold', status)
             WHERE id = ? AND status = 'active' AND quantity > 0",
            [$productId]
        );
        if ($reserved !== 1) {
            throw new RuntimeException('Товар только что закончился.');
        }

        $amount = $dealType === 'sale' ? (string) $product['price'] : '0.00';
        $dealId = insert_row(
            'INSERT INTO deals
                (product_id, buyer_id, seller_id, deal_type, amount, exchange_offer, delivery_option_id, delivery_address, buyer_note, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $productId,
                $buyerId,
                (int) $product['user_id'],
                $dealType,
                $amount,
                $exchangeOffer !== '' ? $exchangeOffer : null,
                $deliveryId,
                $address,
                $note !== '' ? $note : null,
                'paid',
            ]
        );

        if ($dealType === 'sale') {
            escrow_hold($dealId, $amount, 'Оплата заморожена на счёте гаранта');
            deal_log($dealId, 'Покупатель оплатил заказ. ' . money($amount) . ' заморожены на счёте гаранта. Товар зарезервирован.');
        } else {
            deal_log($dealId, 'Гарант зафиксировал обмен. Деньги не списываются: сделка закроется, когда обе стороны подтвердят получение.');
        }

        return $dealId;
    });
}

function ship_deal(int $dealId, int $sellerId): ?string
{
    try {
        db_transaction(static function () use ($dealId, $sellerId): void {
            $deal = query_one('SELECT * FROM deals WHERE id = ? FOR UPDATE', [$dealId]);
            if (!$deal || (int) $deal['seller_id'] !== $sellerId) {
                throw new RuntimeException('Отметить отправку может только продавец.');
            }
            if ($deal['status'] !== 'paid') {
                throw new RuntimeException('Отправка доступна, пока сделка оплачена и ещё не отправлена.');
            }
            $days = max(1, (int) ESCROW_DAYS);
            execute(
                "UPDATE deals
                 SET status = 'shipped', shipped_at = NOW(), escrow_deadline = DATE_ADD(NOW(), INTERVAL {$days} DAY)
                 WHERE id = ? AND status = 'paid'",
                [$dealId]
            );
            deal_log($dealId, 'Продавец отметил отправку. Подтвердите получение в течение ' . $days . ' дн., иначе откроется спор или возврат — как задано в настройках гаранта.');
        });
    } catch (RuntimeException $e) {
        return $e->getMessage();
    }

    flash('success', 'Отправка отмечена. Срок подтверждения — ' . (int) ESCROW_DAYS . ' дн.');
    return null;
}

function confirm_receipt(int $dealId, int $buyerId): ?string
{
    try {
        db_transaction(static function () use ($dealId, $buyerId): void {
            $deal = query_one('SELECT * FROM deals WHERE id = ? FOR UPDATE', [$dealId]);
            if (!$deal || (int) $deal['buyer_id'] !== $buyerId) {
                throw new RuntimeException('Подтвердить получение может только покупатель.');
            }
            if ($deal['status'] !== 'shipped') {
                throw new RuntimeException('Сначала продавец должен отметить отправку.');
            }

            if ($deal['deal_type'] === 'exchange') {
                execute(
                    "UPDATE deals
                     SET status = 'confirmed', buyer_confirmed = 1, confirmed_at = NOW()
                     WHERE id = ? AND status = 'shipped'",
                    [$dealId]
                );
                deal_log($dealId, 'Покупатель подтвердил получение. Для закрытия обмена продавец подтверждает, что получил встречный товар.');
                return;
            }

            $updated = execute(
                "UPDATE deals
                 SET status = 'completed', buyer_confirmed = 1, confirmed_at = NOW(), completed_at = NOW()
                 WHERE id = ? AND status = 'shipped'",
                [$dealId]
            );
            if ($updated !== 1) {
                throw new RuntimeException('Статус сделки уже изменился. Обновите страницу.');
            }
            escrow_release((int) $deal['id'], (string) $deal['amount'], 'Покупатель подтвердил получение');
            deal_log($dealId, 'Покупатель подтвердил получение. ' . money($deal['amount']) . ' переведены продавцу, сделка завершена. Можно оставить отзыв.');
        });
    } catch (RuntimeException $e) {
        return $e->getMessage();
    }

    flash('success', 'Получение подтверждено.');
    return null;
}

function confirm_exchange(int $dealId, int $sellerId): ?string
{
    try {
        db_transaction(static function () use ($dealId, $sellerId): void {
            $deal = query_one('SELECT * FROM deals WHERE id = ? FOR UPDATE', [$dealId]);
            if (!$deal || (int) $deal['seller_id'] !== $sellerId) {
                throw new RuntimeException('Встречное подтверждение доступно продавцу.');
            }
            if ($deal['deal_type'] !== 'exchange' || $deal['status'] !== 'confirmed' || (int) $deal['buyer_confirmed'] !== 1) {
                throw new RuntimeException('Сначала покупатель подтверждает свой товар.');
            }
            execute(
                "UPDATE deals
                 SET status = 'completed', seller_confirmed = 1, completed_at = NOW()
                 WHERE id = ? AND status = 'confirmed'",
                [$dealId]
            );
            deal_log($dealId, 'Обе стороны подтвердили обмен. Сделка завершена, можно оставить отзывы.');
        });
    } catch (RuntimeException $e) {
        return $e->getMessage();
    }

    flash('success', 'Обмен завершён.');
    return null;
}

function cancel_deal(int $dealId, int $userId): ?string
{
    try {
        db_transaction(static function () use ($dealId, $userId): void {
            $deal = query_one('SELECT * FROM deals WHERE id = ? FOR UPDATE', [$dealId]);
            if (!$deal) {
                throw new RuntimeException('Сделка не найдена.');
            }
            $party = deal_party_role($deal, $userId);
            if ($party === null) {
                throw new RuntimeException('Отменить сделку может только её участник.');
            }
            if ($deal['status'] !== 'paid') {
                throw new RuntimeException('После отправки сделку нельзя отменить — откройте спор.');
            }
            $updated = execute(
                "UPDATE deals SET status = 'cancelled' WHERE id = ? AND status = 'paid'",
                [$dealId]
            );
            if ($updated !== 1) {
                throw new RuntimeException('Статус сделки уже изменился.');
            }
            escrow_refund((int) $deal['id'], (string) $deal['amount'], 'Отмена до отправки');
            restore_stock((int) $deal['product_id']);
            deal_log($dealId, 'Сделка отменена до отправки. Резерв товара снят'
                . ((float) $deal['amount'] > 0 ? ', деньги возвращены покупателю.' : '.'));
        });
    } catch (RuntimeException $e) {
        return $e->getMessage();
    }

    flash('success', 'Сделка отменена.');
    return null;
}

function open_dispute(int $dealId, int $userId, string $reason): ?string
{
    $reason = trim($reason);
    if (mb_strlen($reason) < 10) {
        return 'Опишите причину спора — хотя бы 10 символов.';
    }
    if (mb_strlen($reason) > 2000) {
        return 'Текст спора длиннее 2000 символов.';
    }

    try {
        db_transaction(static function () use ($dealId, $userId, $reason): void {
            $deal = query_one('SELECT * FROM deals WHERE id = ? FOR UPDATE', [$dealId]);
            if (!$deal || deal_party_role($deal, $userId) === null) {
                throw new RuntimeException('Спор может открыть только участник сделки.');
            }
            if (!in_array($deal['status'], ['shipped', 'confirmed'], true)) {
                throw new RuntimeException('Спор открывается после отправки. До отправки сделку можно просто отменить.');
            }
            $exists = query_one(
                "SELECT id FROM disputes WHERE deal_id = ? AND status = 'open' LIMIT 1",
                [$dealId]
            );
            if ($exists) {
                throw new RuntimeException('По этой сделке уже есть открытый спор.');
            }
            $updated = execute(
                "UPDATE deals SET status = 'dispute' WHERE id = ? AND status = ?",
                [$dealId, $deal['status']]
            );
            if ($updated !== 1) {
                throw new RuntimeException('Статус сделки уже изменился.');
            }
            execute(
                'INSERT INTO disputes (deal_id, opened_by, reason, status) VALUES (?, ?, ?, ?)',
                [$dealId, $userId, $reason, 'open']
            );
            deal_log($dealId, 'Открыт спор. Деньги остаются у гаранта, пока администратор не вернёт их покупателю или не переведёт продавцу.');
        });
    } catch (RuntimeException $e) {
        return $e->getMessage();
    }

    flash('success', 'Спор открыт. Деньги остаются замороженными.');
    return null;
}

function add_deal_message(int $dealId, int $senderId, string $body): ?string
{
    $body = trim($body);
    if ($body === '') {
        return 'Введите текст сообщения.';
    }
    if (mb_strlen($body) > 2000) {
        return 'Сообщение длиннее 2000 символов.';
    }

    $deal = find_deal($dealId);
    if (!$deal || deal_party_role($deal, $senderId) === null) {
        return 'Писать в сделку могут только её стороны.';
    }

    execute(
        'INSERT INTO messages (deal_id, sender_id, body, is_read) VALUES (?, ?, ?, 0)',
        [$dealId, $senderId, $body]
    );
    flash('success', 'Сообщение отправлено.');
    return null;
}

function add_review(int $dealId, int $authorId, int $rating, string $comment): ?string
{
    $comment = trim($comment);
    if ($rating < 1 || $rating > 5) {
        return 'Оценка должна быть от 1 до 5.';
    }
    if (mb_strlen($comment) < 3) {
        return 'Добавьте короткий комментарий к оценке.';
    }
    if (mb_strlen($comment) > 2000) {
        return 'Комментарий длиннее 2000 символов.';
    }

    try {
        db_transaction(static function () use ($dealId, $authorId, $rating, $comment): void {
            $deal = query_one('SELECT * FROM deals WHERE id = ? FOR UPDATE', [$dealId]);
            if (!$deal || deal_party_role($deal, $authorId) === null) {
                throw new RuntimeException('Отзыв оставляют только участники сделки.');
            }
            if ($deal['status'] !== 'completed') {
                throw new RuntimeException('Отзыв доступен после успешного завершения сделки.');
            }
            $duplicate = query_one(
                'SELECT id FROM reviews WHERE deal_id = ? AND author_id = ?',
                [$dealId, $authorId]
            );
            if ($duplicate) {
                throw new RuntimeException('Вы уже оставили отзыв по этой сделке.');
            }

            $isBuyer = (int) $deal['buyer_id'] === $authorId;
            $targetId = $isBuyer ? (int) $deal['seller_id'] : (int) $deal['buyer_id'];
            $asRole = $isBuyer ? 'seller' : 'buyer';

            execute(
                'INSERT INTO reviews (deal_id, author_id, target_id, as_role, rating, comment) VALUES (?, ?, ?, ?, ?, ?)',
                [$dealId, $authorId, $targetId, $asRole, $rating, $comment]
            );
            recalc_user_ratings($targetId);
        });
    } catch (RuntimeException $e) {
        return $e->getMessage();
    }

    flash('success', 'Отзыв сохранён, рейтинг пересчитан.');
    return null;
}

function admin_resolve_dispute(int $disputeId, string $resolution, string $comment): ?string
{
    if (!in_array($resolution, ['refund', 'release'], true)) {
        return 'Выберите решение: возврат или перевод продавцу.';
    }
    $comment = trim($comment);
    if (mb_strlen($comment) < 3) {
        return 'Коротко запишите, почему принято такое решение.';
    }

    try {
        db_transaction(static function () use ($disputeId, $resolution, $comment): void {
            $dispute = query_one(
                "SELECT * FROM disputes WHERE id = ? AND status = 'open' FOR UPDATE",
                [$disputeId]
            );
            if (!$dispute) {
                throw new RuntimeException('Открытый спор не найден.');
            }
            $deal = query_one('SELECT * FROM deals WHERE id = ? FOR UPDATE', [(int) $dispute['deal_id']]);
            if (!$deal || $deal['status'] !== 'dispute') {
                throw new RuntimeException('Сделка уже не в статусе спора.');
            }

            if ($resolution === 'refund') {
                $updated = execute(
                    "UPDATE deals SET status = 'refunded' WHERE id = ? AND status = 'dispute'",
                    [(int) $deal['id']]
                );
                if ($updated !== 1) {
                    throw new RuntimeException('Не удалось обновить сделку.');
                }
                escrow_refund((int) $deal['id'], (string) $deal['amount'], 'Решение администратора: возврат');
                restore_stock((int) $deal['product_id']);
                $log = (float) $deal['amount'] > 0
                    ? 'Администратор вернул оплату покупателю и снял резерв товара.'
                    : 'Администратор отменил обмен и вернул товар в наличие.';
            } else {
                $updated = execute(
                    "UPDATE deals
                     SET status = 'completed',
                         buyer_confirmed = 1,
                         seller_confirmed = IF(deal_type = 'exchange', 1, seller_confirmed),
                         confirmed_at = COALESCE(confirmed_at, NOW()),
                         completed_at = NOW()
                     WHERE id = ? AND status = 'dispute'",
                    [(int) $deal['id']]
                );
                if ($updated !== 1) {
                    throw new RuntimeException('Не удалось закрыть сделку.');
                }
                escrow_release((int) $deal['id'], (string) $deal['amount'], 'Решение администратора: перевод продавцу');
                $log = (float) $deal['amount'] > 0
                    ? 'Администратор перевёл замороженную оплату продавцу и завершил сделку.'
                    : 'Администратор признал обмен состоявшимся и завершил сделку.';
            }

            execute(
                "UPDATE disputes
                 SET status = 'resolved', resolution = ?, admin_comment = ?, resolved_at = NOW()
                 WHERE id = ?",
                [$resolution, $comment, $disputeId]
            );
            deal_log((int) $deal['id'], $log . ' Комментарий: ' . $comment);
        });
    } catch (RuntimeException $e) {
        return $e->getMessage();
    }

    flash('success', 'Решение по спору сохранено.');
    return null;
}

/**
 * Просрочка. Вызывается из init.php на каждом запросе и из cron.php.
 * Просроченная отправка (статус paid дольше ESCROW_DAYS) и просроченное
 * подтверждение (shipped/confirmed после escrow_deadline) обрабатываются
 * по константе ESCROW_ON_TIMEOUT.
 * Обмен, где одна сторона уже подтвердила, всегда уходит в спор:
 * автоматический возврат здесь стёр бы уже принятое подтверждение.
 */
function escrow_process_timeouts(): int
{
    $days = max(1, (int) ESCROW_DAYS);
    $rows = query_all(
        "SELECT id FROM deals
         WHERE (status IN ('shipped', 'confirmed') AND escrow_deadline IS NOT NULL AND escrow_deadline < NOW())
            OR (status = 'paid' AND created_at < DATE_SUB(NOW(), INTERVAL {$days} DAY))"
    );

    $processed = 0;
    foreach ($rows as $row) {
        try {
            $changed = db_transaction(static function () use ($row, $days): bool {
                $deal = query_one('SELECT * FROM deals WHERE id = ? FOR UPDATE', [(int) $row['id']]);
                if (!$deal) {
                    return false;
                }

                $reason = null;
                if (in_array($deal['status'], ['shipped', 'confirmed'], true)
                    && $deal['escrow_deadline'] !== null
                    && strtotime((string) $deal['escrow_deadline']) < time()
                ) {
                    $reason = 'Истёк срок подтверждения получения.';
                } elseif ($deal['status'] === 'paid' && strtotime((string) $deal['created_at']) < time() - $days * 86400) {
                    $reason = 'Продавец не отметил отправку в отведённый срок.';
                }
                if ($reason === null) {
                    return false;
                }

                $halfExchange = $deal['deal_type'] === 'exchange' && $deal['status'] === 'confirmed';
                $autoRefund = ESCROW_ON_TIMEOUT === 'refund' && !$halfExchange;

                if ($autoRefund) {
                    $updated = execute(
                        'UPDATE deals SET status = ? WHERE id = ? AND status = ?',
                        ['refunded', (int) $deal['id'], $deal['status']]
                    );
                    if ($updated !== 1) {
                        return false;
                    }
                    escrow_refund((int) $deal['id'], (string) $deal['amount'], 'Автовозврат по истечении срока');
                    restore_stock((int) $deal['product_id']);
                    deal_log((int) $deal['id'], $reason . ' Платформа автоматически вернула средства и сняла резерв.');
                    return true;
                }

                $updated = execute(
                    'UPDATE deals SET status = ? WHERE id = ? AND status = ?',
                    ['dispute', (int) $deal['id'], $deal['status']]
                );
                if ($updated !== 1) {
                    return false;
                }
                execute(
                    'INSERT INTO disputes (deal_id, opened_by, reason, status) VALUES (?, NULL, ?, ?)',
                    [(int) $deal['id'], $reason . ' Гарант открыл спор автоматически.', 'open']
                );
                deal_log((int) $deal['id'], $reason . ' Сделка переведена в спор, решение принимает администратор.');
                return true;
            });
            if ($changed) {
                $processed++;
            }
        } catch (Throwable $e) {
            app_log('escrow timeout: ' . $e->getMessage());
        }
    }

    return $processed;
}
