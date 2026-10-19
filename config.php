<?php
declare(strict_types=1);

/**
 * Настройки ГАРАНТ.
 * Перед первым запуском импортируйте sql/schema.sql и проверьте доступ к MySQL.
 * Учебные значения ниже совпадают с типичным Open Server: root без пароля, порт 3306.
 */

const DB_HOST = '127.0.0.1';
const DB_PORT = '3306';
const DB_NAME = 'garant';
const DB_USER = 'root';
const DB_PASS = '';
const DB_CHARSET = 'utf8mb4';

const APP_NAME = 'ГАРАНТ';

/** Сколько дней даётся на подтверждение после отметки «Отправлено». */
const ESCROW_DAYS = 7;

/**
 * Поведение по истечении срока:
 * dispute — открыть спор, решение принимает администратор;
 * refund  — сразу вернуть деньги (кроме обмена, где одна сторона уже подтвердила).
 */
const ESCROW_ON_TIMEOUT = 'dispute';

const UPLOAD_MAX_BYTES = 2097152;
const MAIL_FROM = 'noreply@garant.local';

/** Ключ для ручного запуска cron.php?key=... */
const CRON_KEY = 'garant-local-cron';

/** Учебные пароли на странице входа. На публичном сервере поставьте false. */
const SHOW_DEMO_HINTS = true;

/** Текст ошибок PHP/SQL на экране. На публичном сервере поставьте false. */
const APP_DEBUG = false;
