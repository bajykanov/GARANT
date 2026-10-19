<?php
declare(strict_types=1);

/** Корень проекта. Файл лежит в includes/, поэтому на уровень выше. */
function root_dir(): string
{
    return dirname(__DIR__);
}

function ensure_dirs(): void
{
    foreach (['/uploads', '/storage', '/storage/outbox'] as $relative) {
        $path = root_dir() . $relative;
        if (!is_dir($path)) {
            mkdir($path, 0775, true);
        }
    }
}

function app_log(string $message): void
{
    $dir = root_dir() . '/storage';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    @file_put_contents($dir . '/error.log', date('c') . ' ' . $message . PHP_EOL, FILE_APPEND);
}

/**
 * Вывод в HTML. Единственная правильная точка против XSS:
 * любой текст пользователя проходит здесь, прежде чем попасть в разметку.
 */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function nl(mixed $value): string
{
    return nl2br(e($value), false);
}

function base_path(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }

    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $dir = str_replace('\\', '/', dirname($script));
    if (preg_match('#/(cabinet|admin)$#', $dir)) {
        $dir = str_replace('\\', '/', dirname($dir));
    }
    if ($dir === '/' || $dir === '.' || $dir === '') {
        return $base = '';
    }
    return $base = rtrim($dir, '/');
}

function url(string $path = ''): string
{
    $path = ltrim($path, '/');
    $base = base_path();
    if ($path === '') {
        return $base === '' ? '/' : $base . '/';
    }
    return ($base === '' ? '' : $base) . '/' . $path;
}

function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
}

function field(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/** Сравнение через hash_equals, чтобы время проверки не зависело от совпавших символов. */
function csrf_check(): void
{
    $sent = $_POST['csrf'] ?? '';
    $saved = $_SESSION['csrf'] ?? '';
    if (!is_string($sent) || !is_string($saved) || $saved === '' || !hash_equals($saved, $sent)) {
        http_response_code(403);
        exit('Запрос отклонён: неверный CSRF-токен. Обновите страницу и повторите действие.');
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return is_array($items) ? $items : [];
}

function remember_form(array $errors): void
{
    $old = $_POST;
    unset(
        $old['csrf'],
        $old['password'],
        $old['password_new'],
        $old['password_confirm'],
        $old['current_password'],
        $old['card_number']
    );
    $_SESSION['old'] = $old;
    $_SESSION['errors'] = $errors;
}

function clear_form_state(): void
{
    unset($_SESSION['old'], $_SESSION['errors']);
}

function old(string $key, mixed $default = ''): mixed
{
    if (!isset($_SESSION['old']) || !is_array($_SESSION['old']) || !array_key_exists($key, $_SESSION['old'])) {
        return $default;
    }
    return $_SESSION['old'][$key];
}

function form_errors(): array
{
    $errors = $_SESSION['errors'] ?? [];
    return is_array($errors) ? $errors : [];
}

function error_class(string $field): string
{
    return isset(form_errors()[$field]) ? ' is-invalid' : '';
}

function error_text(string $field): string
{
    $errors = form_errors();
    if (!isset($errors[$field])) {
        return '';
    }
    return '<div class="invalid-feedback">' . e($errors[$field]) . '</div>';
}

function selected(bool $condition): string
{
    return $condition ? ' selected' : '';
}

function checked(bool $condition): string
{
    return $condition ? ' checked' : '';
}

function money(mixed $value): string
{
    if ($value === null || $value === '') {
        return '—';
    }
    $number = (float) $value;
    $decimals = abs($number - round($number)) < 0.001 ? 0 : 2;
    return number_format($number, $decimals, ',', ' ') . ' ₽';
}

function dt(?string $value): string
{
    if ($value === null || $value === '') {
        return '—';
    }
    $timestamp = strtotime($value);
    return $timestamp ? date('d.m.Y H:i', $timestamp) : '—';
}

function excerpt(string $text, int $length = 140): string
{
    $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    if (mb_strlen($text) <= $length) {
        return $text;
    }
    return rtrim(mb_substr($text, 0, $length - 1)) . '…';
}

function stars(mixed $rating): string
{
    $value = (float) $rating;
    $percent = max(0, min(100, $value / 5 * 100));
    $label = number_format($value, 1, ',', '');
    return '<span class="stars" style="--p:' . e((string) round($percent, 2)) . '%" title="Оценка ' . e($label) . '">'
        . '<span class="stars-base">★★★★★</span><span class="stars-fill">★★★★★</span></span> '
        . '<span class="rating-num">' . e($label) . '</span>';
}

function deal_type_label(string $type): string
{
    return match ($type) {
        'sale' => 'Продажа',
        'exchange' => 'Обмен',
        'both' => 'Продажа и обмен',
        default => $type,
    };
}

function deal_status_label(string $status, string $dealType = 'sale', mixed $amount = 0): string
{
    if ($status === 'paid' && $dealType === 'exchange') {
        return 'Зафиксировано гарантом';
    }
    if ($status === 'refunded' && (float) $amount <= 0) {
        return 'Обмен отменён';
    }
    return match ($status) {
        'paid' => 'Оплачено (заморожено)',
        'shipped' => 'Отправлено',
        'confirmed' => 'Подтверждено',
        'completed' => 'Завершено',
        'dispute' => 'Спор',
        'refunded' => 'Возврат',
        'cancelled' => 'Отменено',
        default => $status,
    };
}

function delivery_kind_label(string $kind): string
{
    return match ($kind) {
        'logistics' => 'Логистика',
        'special' => 'Спецтехника',
        'pickup' => 'Самовывоз',
        default => $kind,
    };
}

function product_status_label(string $status): string
{
    return match ($status) {
        'active' => 'Опубликовано',
        'hidden' => 'Скрыто',
        'sold' => 'Нет в наличии',
        default => $status,
    };
}

function category_icon(string $slug): string
{
    return match ($slug) {
        'zernovye' => 'bi-grid-3x3-gap',
        'ovoshchi' => 'bi-flower1',
        'frukty' => 'bi-apple',
        'molochka' => 'bi-droplet',
        'med' => 'bi-droplet-half',
        'ugodia' => 'bi-map',
        'bakaleya' => 'bi-basket',
        'konservy' => 'bi-archive',
        'tehnika' => 'bi-plug',
        'instrumenty' => 'bi-tools',
        'spectehnika' => 'bi-truck',
        'stroymaterialy' => 'bi-bricks',
        'selhoz' => 'bi-tree',
        'prochie' => 'bi-shop',
        'tovary' => 'bi-bag',
        default => 'bi-box-seam',
    };
}

function categories_all(): array
{
    static $rows = null;
    if ($rows === null) {
        $rows = query_all('SELECT * FROM categories ORDER BY sort_order, name');
    }
    return $rows;
}

function category_children_map(): array
{
    $map = [];
    foreach (categories_all() as $category) {
        $parent = $category['parent_id'] === null ? 0 : (int) $category['parent_id'];
        $map[$parent][] = $category;
    }
    return $map;
}

function category_by_id(int $id): ?array
{
    foreach (categories_all() as $category) {
        if ((int) $category['id'] === $id) {
            return $category;
        }
    }
    return null;
}

function category_descendant_ids(int $id): array
{
    $map = category_children_map();
    $ids = [];
    $stack = [$id];
    while ($stack) {
        $current = array_pop($stack);
        if (in_array($current, $ids, true)) {
            continue;
        }
        $ids[] = $current;
        foreach ($map[$current] ?? [] as $child) {
            $stack[] = (int) $child['id'];
        }
    }
    return $ids;
}

function render_category_options(?int $selected = null, ?int $excludeId = null): void
{
    $map = category_children_map();
    $excluded = $excludeId ? category_descendant_ids($excludeId) : [];
    $walk = static function (int $parent, int $depth) use (&$walk, $map, $selected, $excluded): void {
        foreach ($map[$parent] ?? [] as $category) {
            $id = (int) $category['id'];
            if (in_array($id, $excluded, true)) {
                continue;
            }
            $prefix = str_repeat('— ', $depth);
            echo '<option value="' . $id . '"' . selected($selected !== null && $id === $selected) . '>'
                . e($prefix . $category['name']) . '</option>';
            $walk($id, $depth + 1);
        }
    };
    $walk(0, 0);
}

function slugify(string $text): string
{
    $map = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e',
        'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm',
        'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
        'ф' => 'f', 'х' => 'h', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ъ' => '',
        'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
    ];
    $text = mb_strtolower(trim($text));
    $text = strtr($text, $map);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    $text = trim($text, '-');
    return $text !== '' ? $text : 'category';
}

function unique_slug(string $name, ?int $ignoreId = null): string
{
    $base = slugify($name);
    $slug = $base;
    for ($i = 2; $i < 40; $i++) {
        $row = query_one('SELECT id FROM categories WHERE slug = ?', [$slug]);
        if (!$row || ($ignoreId !== null && (int) $row['id'] === $ignoreId)) {
            return $slug;
        }
        $slug = $base . '-' . $i;
    }
    return $base . '-' . bin2hex(random_bytes(2));
}

function delivery_options(): array
{
    static $rows = null;
    if ($rows === null) {
        $rows = query_all('SELECT * FROM delivery_options ORDER BY sort_order, id');
    }
    return $rows;
}

function product_delivery_ids(int $productId): array
{
    $rows = query_all('SELECT delivery_option_id FROM product_delivery WHERE product_id = ?', [$productId]);
    return array_map(static fn (array $row): int => (int) $row['delivery_option_id'], $rows);
}

function product_images(int $productId): array
{
    return query_all(
        'SELECT * FROM product_images WHERE product_id = ? ORDER BY is_main DESC, id ASC',
        [$productId]
    );
}

/**
 * Рейтинг — среднее оценок этой роли. Пока отзывов нет, остаётся стартовое 5.00.
 * Начальная пятёрка в среднее не входит: иначе первый отзыв «3» стал бы 4.0.
 */
function recalc_user_ratings(int $userId): void
{
    $rows = query_all(
        'SELECT as_role, ROUND(AVG(rating), 2) AS avg_rating, COUNT(*) AS cnt
         FROM reviews WHERE target_id = ? GROUP BY as_role',
        [$userId]
    );

    $seller = 5.00;
    $sellerCount = 0;
    $buyer = 5.00;
    $buyerCount = 0;
    foreach ($rows as $row) {
        if ($row['as_role'] === 'seller') {
            $seller = (float) $row['avg_rating'];
            $sellerCount = (int) $row['cnt'];
        }
        if ($row['as_role'] === 'buyer') {
            $buyer = (float) $row['avg_rating'];
            $buyerCount = (int) $row['cnt'];
        }
    }

    execute(
        'UPDATE users
         SET seller_rating = ?, buyer_rating = ?, seller_reviews_count = ?, buyer_reviews_count = ?
         WHERE id = ?',
        [$seller, $buyer, $sellerCount, $buyerCount, $userId]
    );
}

function render_pagination(int $page, int $pages, string $path): void
{
    if ($pages <= 1) {
        return;
    }
    echo '<nav class="mt-4" aria-label="Страницы"><ul class="pagination">';
    for ($i = 1; $i <= $pages; $i++) {
        $query = $_GET;
        $query['page'] = $i;
        $href = url($path . '?' . http_build_query($query));
        $class = $i === $page ? ' page-item active' : ' page-item';
        echo '<li class="' . trim($class) . '"><a class="page-link" href="' . e($href) . '">' . $i . '</a></li>';
    }
    echo '</ul></nav>';
}

function render_product_card(array $product): void
{
    include root_dir() . '/templates/product-card.php';
}

function abort_404(string $message = 'Страница не найдена или недоступна.'): void
{
    http_response_code(404);
    $pageTitle = 'Не найдено';
    require root_dir() . '/templates/header.php';
    echo '<div class="container py-5"><div class="empty-state"><h1>Не найдено</h1><p>' . e($message) . '</p>';
    echo '<a class="btn btn-garant" href="' . e(url('catalog.php')) . '">В каталог</a></div></div>';
    require root_dir() . '/templates/footer.php';
    exit;
}

function normalize_uploads(string $field): array
{
    if (!isset($_FILES[$field]) || !is_array($_FILES[$field]['name'] ?? null)) {
        return [];
    }
    $files = [];
    foreach ($_FILES[$field]['name'] as $index => $name) {
        $error = (int) $_FILES[$field]['error'][$index];
        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $files[] = [
            'name' => (string) $name,
            'tmp_name' => (string) $_FILES[$field]['tmp_name'][$index],
            'error' => $error,
            'size' => (int) $_FILES[$field]['size'][$index],
        ];
    }
    return $files;
}

/**
 * Проверяем не расширение из имени файла, а содержимое: MIME и getimagesize.
 * Имя на диске генерируем сами, чтобы нельзя было записать .php.
 */
function inspect_image(array $file): string
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Не удалось загрузить изображение.');
    }
    if ($file['size'] <= 0 || $file['size'] > UPLOAD_MAX_BYTES) {
        throw new RuntimeException('Каждое фото должно быть не больше 2 МБ.');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']) ?: '';
    $map = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    if (!isset($map[$mime]) || getimagesize($file['tmp_name']) === false) {
        throw new RuntimeException('Нужен файл JPG, PNG, WEBP или GIF.');
    }
    return $map[$mime];
}

function store_product_image(int $productId, array $file, bool $main): void
{
    $ext = inspect_image($file);
    $name = bin2hex(random_bytes(16)) . '.' . $ext;
    $target = root_dir() . '/uploads/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        throw new RuntimeException('Не удалось сохранить файл изображения.');
    }
    execute(
        'INSERT INTO product_images (product_id, path, is_main) VALUES (?, ?, ?)',
        [$productId, $name, $main ? 1 : 0]
    );
}

function remove_upload(string $path): void
{
    if (!preg_match('/^[a-f0-9]{32}\.(jpg|png|webp|gif)$/', $path)) {
        return;
    }
    $full = root_dir() . '/uploads/' . $path;
    if (is_file($full)) {
        unlink($full);
    }
}

function card_brand(string $digits): string
{
    if (str_starts_with($digits, '4')) {
        return 'Visa';
    }
    if (str_starts_with($digits, '5')) {
        return 'Mastercard';
    }
    if (preg_match('/^220[0-4]/', $digits)) {
        return 'Мир';
    }
    return 'Карта';
}

function luhn_ok(string $number): bool
{
    $sum = 0;
    $alternate = false;
    for ($i = strlen($number) - 1; $i >= 0; $i--) {
        $digit = (int) $number[$i];
        if ($alternate) {
            $digit *= 2;
            if ($digit > 9) {
                $digit -= 9;
            }
        }
        $sum += $digit;
        $alternate = !$alternate;
    }
    return $number !== '' && $sum % 10 === 0;
}

/**
 * Письмо уходит через mail() и дублируется в storage/outbox.
 * На локальном Open Server почта часто не настроена — код 2FA тогда читают из файла.
 */
function send_mail(string $to, string $subject, string $body): void
{
    $dir = root_dir() . '/storage/outbox';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $file = $dir . '/' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.txt';
    $payload = "To: {$to}\nSubject: {$subject}\n\n{$body}\n";
    file_put_contents($file, $payload);

    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $headers = "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nFrom: " . MAIL_FROM;
    @mail($to, $encodedSubject, $body, $headers);
}

function send_twofa_code(array $user): void
{
    $code = (string) random_int(100000, 999999);
    execute(
        'UPDATE users SET twofa_code_hash = ?, twofa_expires_at = DATE_ADD(NOW(), INTERVAL 10 MINUTE) WHERE id = ?',
        [password_hash($code, PASSWORD_DEFAULT), (int) $user['id']]
    );
    $body = "Здравствуйте, {$user['name']}!\n\n"
        . "Код подтверждения " . APP_NAME . ": {$code}\n"
        . "Код действует 10 минут.\n\n"
        . "Если вы не запрашивали код, смените пароль в кабинете.";
    send_mail($user['email'], 'Код подтверждения ' . APP_NAME, $body);
}

function twofa_code_matches(array $user, string $code): bool
{
    if (empty($user['twofa_code_hash']) || empty($user['twofa_expires_at'])) {
        return false;
    }
    if (strtotime((string) $user['twofa_expires_at']) < time()) {
        return false;
    }
    if (!password_verify($code, (string) $user['twofa_code_hash'])) {
        return false;
    }
    execute(
        'UPDATE users SET twofa_code_hash = NULL, twofa_expires_at = NULL WHERE id = ?',
        [(int) $user['id']]
    );
    return true;
}
