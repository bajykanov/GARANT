<?php
declare(strict_types=1);
$pageTitle = $pageTitle ?? APP_NAME;
$documentTitle = $pageTitle === APP_NAME ? APP_NAME : $pageTitle . ' — ' . APP_NAME;
$navActive = $navActive ?? '';
$current = current_user();
$unread = $current ? unread_count() : 0;
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($documentTitle) ?></title>
    <meta name="description" content="ГАРАНТ — маркетплейс продажи и обмена. Платформа замораживает оплату и переводит её продавцу только после подтверждения получения.">
    <link rel="icon" href="<?= e(url('assets/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Literata:opsz,wght@7..72,560;7..72,680&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(url('assets/css/style.css')) ?>" rel="stylesheet">
</head>
<body>
<header class="site-header">
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="brand" href="<?= e(url('index.php')) ?>">
                <span class="brand-mark" aria-hidden="true">
                    <svg viewBox="0 0 32 32" width="28" height="28">
                        <path fill="#f4e2b8" d="M16 2.5 27 7v8.2c0 6.4-4.2 11.4-11 13.8C9.2 26.6 5 21.6 5 15.2V7L16 2.5z"/>
                        <path fill="none" stroke="#1b4332" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" d="M11 16.2 14.2 19.4 21.2 12.2"/>
                    </svg>
                </span>
                <span class="brand-text">
                    <strong>ГАРАНТ</strong>
                    <small>эскроу сделок</small>
                </span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Меню">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav ms-auto align-items-lg-center">
                    <li class="nav-item"><a class="nav-link<?= $navActive === 'catalog' ? ' active' : '' ?>" href="<?= e(url('catalog.php')) ?>">Каталог</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('index.php')) ?>#escrow">Как работает гарант</a></li>
                    <?php if ($current): ?>
                        <li class="nav-item">
                            <a class="nav-link<?= $navActive === 'messages' ? ' active' : '' ?>" href="<?= e(url('cabinet/messages.php')) ?>">
                                Сообщения<?php if ($unread > 0): ?><span class="msg-badge"><?= (int) $unread ?></span><?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false"><?= e($current['name']) ?></a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="<?= e(url('cabinet/index.php')) ?>">Кабинет</a></li>
                                <li><a class="dropdown-item" href="<?= e(url('cabinet/deals.php')) ?>">Сделки</a></li>
                                <li><a class="dropdown-item" href="<?= e(url('cabinet/listings.php')) ?>">Мои объявления</a></li>
                                <li><a class="dropdown-item" href="<?= e(url('user.php?id=' . (int) $current['id'])) ?>">Публичный профиль</a></li>
                                <?php if ($current['role'] === 'admin'): ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="<?= e(url('admin/index.php')) ?>">Админка</a></li>
                                <?php endif; ?>
                            </ul>
                        </li>
                        <li class="nav-item">
                            <form method="post" action="<?= e(url('logout.php')) ?>" class="d-inline">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-outline-light ms-lg-2" type="submit">Выйти</button>
                            </form>
                        </li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link" href="<?= e(url('login.php')) ?>">Войти</a></li>
                        <li class="nav-item"><a class="btn btn-sm btn-amber ms-lg-2" href="<?= e(url('register.php')) ?>">Регистрация</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
</header>
<main>
<?php foreach (take_flashes() as $flash): ?>
    <?php $type = in_array($flash['type'] ?? '', ['success', 'danger', 'warning', 'info'], true) ? $flash['type'] : 'info'; ?>
    <div class="container mt-3">
        <div class="alert alert-<?= e($type) ?> mb-0"><?= e($flash['message'] ?? '') ?></div>
    </div>
<?php endforeach; ?>
