<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

if (is_post()) {
    csrf_check();
    logout_user();
    flash('success', 'Вы вышли из аккаунта.');
}
redirect('login.php');
