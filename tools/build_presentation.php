<?php
declare(strict_types=1);

/**
 * Собирает презентацию ГАРАНТ (10 слайдов, 16:9) в формате PPTX.
 * Запуск: php tools/build_presentation.php
 */

const SLIDE_W = 12192000;
const SLIDE_H = 6858000;

function emu(float $inches): int
{
    return (int) round($inches * 914400);
}

function x(string $text): string
{
    return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function para(string $text, int $size, string $color, bool $bold = false, string $font = 'Calibri', string $align = 'l', int $after = 0, int $before = 0): array
{
    return compact('text', 'size', 'color', 'bold', 'font', 'align', 'after', 'before');
}

final class SlideBuilder
{
    private int $id = 2;
    private string $xml = '';

    public function rect(float $x, float $y, float $w, float $h, string $fill, ?string $line = null, int $radius = 0): void
    {
        $id = $this->id++;
        $geom = $radius > 0
            ? '<a:prstGeom prst="roundRect"><a:avLst><a:gd name="adj" fmla="val ' . $radius . '"/></a:avLst></a:prstGeom>'
            : '<a:prstGeom prst="rect"><a:avLst/></a:prstGeom>';
        $ln = $line === null
            ? '<a:ln><a:noFill/></a:ln>'
            : '<a:ln w="12700"><a:solidFill><a:srgbClr val="' . $line . '"/></a:solidFill></a:ln>';
        $this->xml .= '<p:sp>'
            . '<p:nvSpPr><p:cNvPr id="' . $id . '" name="r' . $id . '"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>'
            . '<p:spPr><a:xfrm><a:off x="' . emu($x) . '" y="' . emu($y) . '"/><a:ext cx="' . emu($w) . '" cy="' . emu($h) . '"/></a:xfrm>'
            . $geom
            . '<a:solidFill><a:srgbClr val="' . $fill . '"/></a:solidFill>'
            . $ln
            . '</p:spPr>'
            . '<p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:endParaRPr lang="ru-RU"/></a:p></p:txBody>'
            . '</p:sp>';
    }

    /** @param array<int, array<string, mixed>> $paragraphs */
    public function text(float $x, float $y, float $w, float $h, array $paragraphs, string $anchor = 't'): void
    {
        $id = $this->id++;
        $body = '';
        foreach ($paragraphs as $p) {
            $bold = !empty($p['bold']) ? ' b="1"' : '';
            $before = (int) ($p['before'] ?? 0);
            $after = (int) ($p['after'] ?? 0);
            $spc = '';
            if ($before > 0) {
                $spc .= '<a:spcBef><a:spcPts val="' . ($before * 100) . '"/></a:spcBef>';
            }
            if ($after > 0) {
                $spc .= '<a:spcAft><a:spcPts val="' . ($after * 100) . '"/></a:spcAft>';
            }
            $body .= '<a:p><a:pPr algn="' . ($p['align'] ?? 'l') . '">' . $spc . '</a:pPr>'
                . '<a:r><a:rPr lang="ru-RU" sz="' . ((int) $p['size'] * 100) . '"' . $bold . ' dirty="0">'
                . '<a:solidFill><a:srgbClr val="' . $p['color'] . '"/></a:solidFill>'
                . '<a:latin typeface="' . $p['font'] . '"/><a:ea typeface="' . $p['font'] . '"/><a:cs typeface="' . $p['font'] . '"/>'
                . '</a:rPr><a:t>' . x((string) $p['text']) . '</a:t></a:r></a:p>';
        }
        $this->xml .= '<p:sp>'
            . '<p:nvSpPr><p:cNvPr id="' . $id . '" name="t' . $id . '"/><p:cNvSpPr txBox="1"/><p:nvPr/></p:nvSpPr>'
            . '<p:spPr><a:xfrm><a:off x="' . emu($x) . '" y="' . emu($y) . '"/><a:ext cx="' . emu($w) . '" cy="' . emu($h) . '"/></a:xfrm>'
            . '<a:prstGeom prst="rect"><a:avLst/></a:prstGeom><a:noFill/><a:ln><a:noFill/></a:ln></p:spPr>'
            . '<p:txBody><a:bodyPr wrap="square" anchor="' . $anchor . '" lIns="0" tIns="0" rIns="0" bIns="0"/><a:lstStyle/>'
            . $body
            . '</p:txBody></p:sp>';
    }

    public function footer(int $page): void
    {
        $this->rect(0, 7.28, 13.333, 0.22, '12281E');
        $this->text(0.45, 7.28, 8, 0.22, [para('ГАРАНТ  ·  маркетплейс с эскроу', 11, 'E7DECE', false, 'Calibri', 'l')], 'ctr');
        $this->text(11.4, 7.28, 1.5, 0.22, [para($page . '  /  10', 11, 'E09F3E', true, 'Calibri', 'r')], 'ctr');
    }

    public function header(string $kicker, string $title): void
    {
        $this->rect(0, 0, 13.333, 0.12, 'E09F3E');
        $this->rect(0, 0.12, 0.16, 7.16, '1B4332');
        $this->text(0.48, 0.32, 12.3, 0.28, [para($kicker, 12, '9A6B16', true, 'Calibri')]);
        $this->text(0.46, 0.58, 12.3, 0.62, [para($title, 30, '1B4332', true, 'Georgia')]);
    }

    public function xml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<p:sld xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main">'
            . '<p:cSld><p:spTree>'
            . '<p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>'
            . '<p:grpSpPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="0" cy="0"/><a:chOff x="0" y="0"/><a:chExt cx="0" cy="0"/></a:xfrm></p:grpSpPr>'
            . $this->xml
            . '</p:spTree></p:cSld><p:clrMapOvr><a:masterClrMapping/></p:clrMapOvr></p:sld>';
    }
}

function card(SlideBuilder $s, float $x, float $y, float $w, float $h, string $num, string $title, string $body): void
{
    $s->rect($x, $y, $w, $h, 'FFFDF8', 'E5DCCB', 8000);
    $s->rect($x + 0.18, $y + 0.18, 0.42, 0.42, '1B4332', null, 14000);
    $s->text($x + 0.18, $y + 0.18, 0.42, 0.42, [para($num, 14, 'F4E2B8', true, 'Georgia', 'ctr')], 'ctr');
    $s->text($x + 0.72, $y + 0.16, $w - 0.9, 0.46, [para($title, 16, '1B4332', true, 'Georgia')], 'ctr');
    $s->text($x + 0.2, $y + 0.74, $w - 0.4, $h - 0.9, [para($body, 14, '3E3A33', false, 'Calibri')]);
}

$slides = [];

// 1. Титул
$s = new SlideBuilder();
$s->rect(0, 0, 13.333, 7.5, '12281E');
$s->rect(0, 0, 0.22, 7.5, 'E09F3E');
$s->rect(0.85, 1.35, 1.15, 1.15, '2D6A4F', null, 16000);
$s->text(0.85, 1.35, 1.15, 1.15, [para('Г', 36, 'F4E2B8', true, 'Georgia', 'ctr')], 'ctr');
$s->text(2.2, 1.35, 9.5, 0.4, [para('УЧЕБНЫЙ МАРКЕТПЛЕЙС', 14, 'E09F3E', true)]);
$s->text(2.15, 1.75, 10, 1.15, [para('ГАРАНТ', 60, 'F6F1E7', true, 'Georgia')]);
$s->text(2.2, 3.05, 9.2, 1.15, [para('Продажа и обмен товаров, где платформа держит деньги до подтверждения получения.', 22, 'E7DECE', false, 'Calibri')]);
$s->rect(2.2, 4.45, 2.3, 0.06, 'E09F3E');
$facts = [
    ['01', 'Эскроу', 'Оплата заморожена, пока покупатель не подтвердит товар'],
    ['02', 'Два рынка', 'Продажа, обмен или оба варианта в одном объявлении'],
    ['03', 'PHP + MySQL', 'Чистый PHP, PDO и журнал проводок гаранта'],
];
$x0 = 0.85;
foreach ($facts as $i => $fact) {
    $x = $x0 + $i * 4.0;
    $s->text($x, 5.05, 3.6, 0.35, [para($fact[0] . '   ' . $fact[1], 16, 'F4E2B8', true, 'Georgia')]);
    $s->text($x, 5.45, 3.6, 0.9, [para($fact[2], 14, 'D9CBB4')]);
}
$slides[] = $s->xml();

// 2. Задача
$s = new SlideBuilder();
$s->rect(0, 0, 13.333, 7.5, 'F4EFE4');
$s->header('ЗАДАЧА', 'Почему сделке нужен гарант');
$s->rect(0.48, 1.55, 6.0, 5.35, 'FFFDF8', 'E5DCCB', 7000);
$s->rect(6.85, 1.55, 6.0, 5.35, '1B4332', null, 7000);
$s->text(0.75, 1.8, 5.5, 0.5, [para('Без платформы', 22, '9C3D2F', true, 'Georgia')]);
$s->text(0.75, 2.5, 5.45, 3.9, [
    para('Покупатель платит незнакомцу и ждёт, что товар приедет.', 16, '1D1A15', false, 'Calibri', 'l', 14),
    para('Продавец отгружает первым и может не увидеть оплату.', 16, '1D1A15', false, 'Calibri', 'l', 14),
    para('Обмен держится только на слове: никто не фиксирует, что обе стороны получили своё.', 16, '1D1A15', false, 'Calibri', 'l', 14),
    para('Спор негде разбирать: деньги уже у одной из сторон.', 16, '1D1A15'),
]);
$s->text(7.12, 1.8, 5.5, 0.5, [para('С ГАРАНТ', 22, 'F4E2B8', true, 'Georgia')]);
$s->text(7.12, 2.5, 5.45, 3.9, [
    para('Покупатель платит платформе. Продавец денег ещё не видит.', 16, 'F6F1E7', false, 'Calibri', 'l', 14),
    para('Единица товара сразу снимается с витрины — её не купят второй раз.', 16, 'F6F1E7', false, 'Calibri', 'l', 14),
    para('Перевод продавцу происходит только после подтверждения получения.', 16, 'F6F1E7', false, 'Calibri', 'l', 14),
    para('Если срок вышел, спор решает администратор гаранта.', 16, 'F6F1E7'),
]);
$s->footer(2);
$slides[] = $s->xml();

// 3. Возможности
$s = new SlideBuilder();
$s->rect(0, 0, 13.333, 7.5, 'F4EFE4');
$s->header('СОСТАВ СИСТЕМЫ', 'Шесть частей площадки');
$items = [
    ['1', 'Вход и профиль', 'Регистрация по email, пароль через password_hash, два рейтинга и раздел безопасности.'],
    ['2', 'Каталог', 'Категории, поиск, цена, тип сделки и рейтинг продавца. Карточка товара открыта гостю.'],
    ['3', 'Сделка', 'Покупка или обмен через гаранта. Деньги и резерв товара живут в одной транзакции.'],
    ['4', 'Доставка', 'Службы вроде СДЭК и Яндекса, самовывоз и спецтехника: грузовик, манипулятор, трал.'],
    ['5', 'Отзывы', 'Оценка 1–5 только после завершённой сделки. Отдельно для продавца и для покупателя.'],
    ['6', 'Кабинет и админ', 'Объявления, переписка по сделке, споры, пользователи и категории.'],
];
foreach ($items as $i => $item) {
    $col = $i % 3;
    $row = intdiv($i, 3);
    card($s, 0.48 + $col * 4.2, 1.55 + $row * 2.7, 4.0, 2.5, $item[0], $item[1], $item[2]);
}
$s->footer(3);
$slides[] = $s->xml();

// 4. Эскроу шаги
$s = new SlideBuilder();
$s->rect(0, 0, 13.333, 7.5, 'F4EFE4');
$s->header('ЭСКРОУ', 'Четыре шага одной сделки');
$steps = [
    ['1', 'Фиксация', 'Покупатель оплачивает заказ. В журнале появляется hold. Для обмена денег нет: гарант фиксирует обязательства сторон.'],
    ['2', 'Отправка', 'Продавец передаёт товар выбранным способом и отмечает отправку. С этого момента идут 7 дней на подтверждение.'],
    ['3', 'Подтверждение', 'Покупатель закрывает продажу — пишется release, деньги считаются переведёнными. Обмен ждёт вторую сторону.'],
    ['4', 'Спор или отзыв', 'Если срок вышел, открывается спор. Отзыв можно оставить только когда сделка уже завершена.'],
];
foreach ($steps as $i => $step) {
    $x = 0.42 + $i * 3.22;
    $s->rect($x, 1.6, 3.05, 4.85, 'FFFDF8', 'E5DCCB', 8000);
    $s->rect($x, 1.6, 3.05, 0.12, 'E09F3E');
    $s->text($x + 0.18, 1.9, 2.7, 0.7, [para($step[0], 32, '1B4332', true, 'Georgia')]);
    $s->text($x + 0.18, 2.65, 2.7, 0.7, [para($step[1], 18, '1B4332', true, 'Georgia')]);
    $s->text($x + 0.18, 3.45, 2.7, 2.7, [para($step[2], 14, '3E3A33')]);
}
$s->footer(4);
$slides[] = $s->xml();

// 5. Статусы
$s = new SlideBuilder();
$s->rect(0, 0, 13.333, 7.5, 'F4EFE4');
$s->header('ЖУРНАЛ ГАРАНТА', 'Статусы, которые видит кабинет');
$statuses = [
    ['Оплачено', 'Деньги заморожены. У обмена этот же шаг называется «Зафиксировано гарантом».'],
    ['Отправлено', 'Продавец отметил отгрузку. Запущен срок подтверждения.'],
    ['Подтверждено', 'В обмене покупатель уже получил товар, продавец ещё нет.'],
    ['Завершено', 'Продажа закрыта переводом. Обмен закрыт двумя подтверждениями.'],
    ['Спор', 'Деньги остаются у гаранта, пока администратор не решит.'],
    ['Возврат / отмена', 'До отправки сделку можно отменить. После спора возможен возврат.'],
];
foreach ($statuses as $i => $st) {
    $col = $i % 2;
    $row = intdiv($i, 2);
    $x = 0.48 + $col * 6.4;
    $y = 1.55 + $row * 1.45;
    $s->rect($x, $y, 6.15, 1.32, 'FFFDF8', 'E5DCCB', 7000);
    $s->rect($x, $y, 0.1, 1.32, $i === 4 ? '9C3D2F' : '2D6A4F');
    $s->text($x + 0.28, $y + 0.12, 5.7, 0.4, [para($st[0], 16, '1B4332', true, 'Georgia')]);
    $s->text($x + 0.28, $y + 0.55, 5.7, 0.65, [para($st[1], 13, '3E3A33')]);
}
$s->rect(0.48, 6.05, 12.37, 0.95, '12281E', null, 7000);
$s->text(0.7, 6.18, 12.0, 0.7, [
    para('Три проводки журнала:  hold — заморозка у гаранта,   release — перевод продавцу,   refund — возврат покупателю.', 15, 'F6F1E7', false, 'Calibri', 'l'),
]);
$s->footer(5);
$slides[] = $s->xml();

// 6. Каталог и доставка
$s = new SlideBuilder();
$s->rect(0, 0, 13.333, 7.5, 'F4EFE4');
$s->header('ВИТРИНА', 'Что продают и как это везут');
$s->rect(0.48, 1.55, 6.15, 5.35, 'FFFDF8', 'E5DCCB', 7000);
$s->text(0.72, 1.75, 5.7, 0.45, [para('Три ветки каталога', 20, '1B4332', true, 'Georgia')]);
$s->text(0.72, 2.35, 5.7, 4.2, [
    para('Сельхозугодья и продукция: зерно, овощи, фрукты, молоко, мёд, земля.', 15, '1D1A15', false, 'Calibri', 'l', 10),
    para('Прочие продукты: бакалея и консервация.', 15, '1D1A15', false, 'Calibri', 'l', 10),
    para('Товары: техника, инструмент, спецтехника, стройматериалы.', 15, '1D1A15', false, 'Calibri', 'l', 12),
    para('Объявление бывает трёх типов: продажа, обмен, продажа и обмен.', 15, '1D1A15', true, 'Calibri', 'l', 8),
    para('Фильтры: категория с подкатегориями, цена, рейтинг продавца, тип сделки, поиск.', 15, '1D1A15'),
]);
$s->rect(6.85, 1.55, 6.0, 5.35, '1B4332', null, 7000);
$s->text(7.1, 1.75, 5.55, 0.45, [para('Доставку задаёт продавец', 20, 'F4E2B8', true, 'Georgia')]);
$s->text(7.1, 2.4, 5.5, 4.1, [
    para('Логистика: СДЭК, Boxberry, Яндекс Доставка, Почта России, доставка продавца.', 15, 'F6F1E7', false, 'Calibri', 'l', 12),
    para('Самовывоз — адрес можно не писать.', 15, 'F6F1E7', false, 'Calibri', 'l', 12),
    para('Спецтехника: грузовик, манипулятор, трактор, трал. Так уезжают зерно, земля и мини-трактор.', 15, 'F6F1E7', false, 'Calibri', 'l', 12),
    para('Покупатель выбирает только из того, что продавец отметил в объявлении.', 15, 'F4E2B8', true),
]);
$s->footer(6);
$slides[] = $s->xml();

// 7. Рейтинг
$s = new SlideBuilder();
$s->rect(0, 0, 13.333, 7.5, 'F4EFE4');
$s->header('РЕПУТАЦИЯ', 'Два рейтинга и одно правило');
$s->rect(0.48, 1.55, 4.0, 2.35, '1B4332', null, 8000);
$s->text(0.7, 1.75, 3.55, 0.7, [para('5,0', 40, 'F4E2B8', true, 'Georgia')]);
$s->text(0.7, 2.6, 3.55, 0.9, [para('Стартовая оценка и продавца, и покупателя, пока нет отзывов.', 14, 'E7DECE')]);
$s->rect(4.68, 1.55, 8.15, 2.35, 'FFFDF8', 'E5DCCB', 8000);
$s->text(4.95, 1.75, 7.7, 1.9, [
    para('Отзыв нельзя оставить заранее.', 18, '1B4332', true, 'Georgia', 'l', 8),
    para('Только статус «Завершено», одна оценка на сторону, шкала от 1 до 5 и короткий комментарий.', 15, '1D1A15'),
]);
$rules = [
    ['Покупатель пишет отзыв', 'Меняется рейтинг продавца'],
    ['Продавец пишет отзыв', 'Меняется рейтинг покупателя'],
    ['Пример из данных', 'Оценки 5 и 4 дают среднее 4,50'],
];
foreach ($rules as $i => $rule) {
    $x = 0.48 + $i * 4.2;
    $s->rect($x, 4.15, 4.0, 1.85, 'FFFDF8', 'E5DCCB', 8000);
    $s->text($x + 0.22, 4.35, 3.55, 0.7, [para($rule[0], 15, '9A6B16', true, 'Calibri')]);
    $s->text($x + 0.22, 5.05, 3.55, 0.7, [para($rule[1], 16, '1B4332', true, 'Georgia')]);
}
$s->text(0.5, 6.15, 12.3, 0.7, [para('Стартовая пятёрка в среднее не входит. Иначе первый отзыв «3» превратился бы в 4,0 и скрыл реальную оценку.', 14, '6F675C')]);
$s->footer(7);
$slides[] = $s->xml();

// 8. Кабинет и админ
$s = new SlideBuilder();
$s->rect(0, 0, 13.333, 7.5, 'F4EFE4');
$s->header('РОЛИ', 'Участник ведёт сделку, админ закрывает спор');
$s->rect(0.48, 1.55, 6.15, 5.35, 'FFFDF8', 'E5DCCB', 7000);
$s->text(0.72, 1.75, 5.7, 0.45, [para('Личный кабинет', 20, '1B4332', true, 'Georgia')]);
$s->text(0.72, 2.4, 5.7, 4.1, [
    para('Объявления: создать, скрыть, вернуть в каталог.', 15, '1D1A15', false, 'Calibri', 'l', 8),
    para('Сделки со всеми статусами, отдельно покупки и продажи.', 15, '1D1A15', false, 'Calibri', 'l', 8),
    para('Переписка только внутри сделки. Служебные реплики пишет сам ГАРАНТ.', 15, '1D1A15', false, 'Calibri', 'l', 8),
    para('Смена пароля и код на почту как второй шаг входа.', 15, '1D1A15', false, 'Calibri', 'l', 8),
    para('Учебная карта: в базе остаются бренд, имя, срок и 4 цифры.', 15, '1D1A15'),
]);
$s->rect(6.85, 1.55, 6.0, 5.35, 'FFFDF8', 'E5DCCB', 7000);
$s->rect(6.85, 1.55, 6.0, 0.12, 'E09F3E');
$s->text(7.1, 1.85, 5.55, 0.45, [para('Админ-панель', 20, '1B4332', true, 'Georgia')]);
$s->text(7.1, 2.5, 5.5, 3.9, [
    para('Сводка: пользователи, объявления, замороженная сумма, открытые споры.', 15, '1D1A15', false, 'Calibri', 'l', 8),
    para('Блокировка участников. Последнего администратора заблокировать нельзя.', 15, '1D1A15', false, 'Calibri', 'l', 8),
    para('Категории: добавить, переименовать, удалить пустую.', 15, '1D1A15', false, 'Calibri', 'l', 8),
    para('Спор: вернуть покупателю или перевести продавцу и закрыть сделку.', 15, '1D1A15', true),
]);
$s->footer(8);
$slides[] = $s->xml();

// 9. Технологии
$s = new SlideBuilder();
$s->rect(0, 0, 13.333, 7.5, 'F4EFE4');
$s->header('КАК ЭТО СДЕЛАНО', 'Стек и защита');
$stack = [
    ['PHP 8', 'Страницы без фреймворка. Логика эскроу собрана в includes/escrow.php.'],
    ['PDO', 'Только подготовленные запросы, без подстановки текста в SQL.'],
    ['MySQL', 'Схема sql/schema.sql: пользователи, товары, сделки, проводки, споры.'],
    ['Bootstrap 5', 'Адаптивная вёрстка, весь интерфейс на русском.'],
];
foreach ($stack as $i => $item) {
    $y = 1.5 + $i * 1.05;
    $s->rect(0.48, $y, 6.2, 0.95, 'FFFDF8', 'E5DCCB', 7000);
    $s->text(0.7, $y + 0.08, 5.8, 0.35, [para($item[0], 16, '1B4332', true, 'Georgia')]);
    $s->text(0.7, $y + 0.44, 5.8, 0.42, [para($item[1], 13, '3E3A33')]);
}
$s->rect(6.9, 1.5, 5.95, 4.2, '12281E', null, 8000);
$s->text(7.15, 1.7, 5.5, 0.4, [para('Что закрыто', 20, 'F4E2B8', true, 'Georgia')]);
$s->text(7.15, 2.25, 5.45, 3.2, [
    para('XSS — вывод только через экранирование.', 15, 'F6F1E7', false, 'Calibri', 'l', 8),
    para('CSRF — токен на каждой форме, сверка hash_equals.', 15, 'F6F1E7', false, 'Calibri', 'l', 8),
    para('Пароли — password_hash, сессия обновляется при входе.', 15, 'F6F1E7', false, 'Calibri', 'l', 8),
    para('Фото — проверка содержимого, своё имя файла, PHP в uploads запрещён.', 15, 'F6F1E7'),
]);
$s->footer(9);
$slides[] = $s->xml();

// 10. Запуск
$s = new SlideBuilder();
$s->rect(0, 0, 13.333, 7.5, 'F4EFE4');
$s->header('ЗАПУСК', 'Три шага и учебный сценарий');
$s->rect(0.48, 1.48, 7.55, 2.55, 'FFFDF8', 'E5DCCB', 7000);
$s->text(0.7, 1.62, 7.15, 2.25, [
    para('1. Импортировать sql/schema.sql в MySQL.', 15, '1D1A15', false, 'Calibri', 'l', 6),
    para('2. Проверить хост, базу garant и пароль в config.php.', 15, '1D1A15', false, 'Calibri', 'l', 6),
    para('3. Перезапустить Open Server и открыть http://garant/', 15, '1D1A15', false, 'Calibri', 'l', 4),
    para('Каталог виден без входа. Сделка, кабинет и админка — после авторизации.', 14, '6F675C'),
]);
$accounts = [
    ['Админ', 'admin@garant.local', 'admin123'],
    ['Продавец', 'anna@garant.local', 'seller123'],
    ['Покупатель', 'igor@garant.local', 'buyer123'],
];
foreach ($accounts as $i => $acc) {
    $x = 8.2;
    $y = 1.48 + $i * 0.88;
    $s->rect($x, $y, 4.65, 0.8, '1B4332', null, 8000);
    $s->text($x + 0.16, $y + 0.06, 4.3, 0.32, [para($acc[0], 12, 'E09F3E', true)]);
    $s->text($x + 0.16, $y + 0.36, 4.3, 0.36, [para($acc[1] . '   ·   ' . $acc[2], 12, 'F6F1E7')]);
}
$s->rect(0.48, 4.2, 12.37, 2.7, 'FFFDF8', 'E5DCCB', 7000);
$s->text(0.72, 4.35, 12, 0.4, [para('Что показать на защите', 18, '1B4332', true, 'Georgia')]);
$s->text(0.72, 4.85, 11.9, 1.85, [
    para('Игорь оформляет покупку — в журнале hold, статус «Оплачено (заморожено)».', 15, '1D1A15', false, 'Calibri', 'l', 4),
    para('Анна отмечает отправку. Игорь подтверждает получение — появляется release, сделку можно оценить.', 15, '1D1A15', false, 'Calibri', 'l', 4),
    para('Админ открывает спор по холодильнику и выбирает: вернуть покупателю или перевести продавцу.', 15, '1D1A15'),
]);
$s->footer(10);
$slides[] = $s->xml();

if (count($slides) !== 10) {
    fwrite(STDERR, "Ожидалось 10 слайдов, собрано " . count($slides) . PHP_EOL);
    exit(1);
}

$out = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'ГАРАНТ — презентация.pptx';

$relsSlides = '';
$sldIds = '';
$contentSlides = '';
for ($i = 1; $i <= 10; $i++) {
    $rid = 'rId' . ($i + 1);
    $relsSlides .= '<Relationship Id="' . $rid . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide' . $i . '.xml"/>';
    $sldIds .= '<p:sldId id="' . (255 + $i) . '" r:id="' . $rid . '"/>';
    $contentSlides .= '<Override PartName="/ppt/slides/slide' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slide+xml"/>';
}

$contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
    . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
    . '<Default Extension="xml" ContentType="application/xml"/>'
    . '<Override PartName="/ppt/presentation.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.presentation.main+xml"/>'
    . '<Override PartName="/ppt/slideMasters/slideMaster1.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slideMaster+xml"/>'
    . '<Override PartName="/ppt/slideLayouts/slideLayout1.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slideLayout+xml"/>'
    . '<Override PartName="/ppt/theme/theme1.xml" ContentType="application/vnd.openxmlformats-officedocument.theme+xml"/>'
    . '<Override PartName="/ppt/presProps.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.presProps+xml"/>'
    . '<Override PartName="/ppt/viewProps.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.viewProps+xml"/>'
    . '<Override PartName="/ppt/tableStyles.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.tableStyles+xml"/>'
    . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
    . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
    . $contentSlides
    . '</Types>';

$rootRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/>'
    . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
    . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
    . '</Relationships>';

$presentation = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<p:presentation xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" saveSubsetFonts="1">'
    . '<p:sldMasterIdLst><p:sldMasterId id="2147483648" r:id="rId1"/></p:sldMasterIdLst>'
    . '<p:sldIdLst>' . $sldIds . '</p:sldIdLst>'
    . '<p:sldSz cx="' . SLIDE_W . '" cy="' . SLIDE_H . '" type="widescreen"/>'
    . '<p:notesSz cx="6858000" cy="9144000"/>'
    . '</p:presentation>';

$presentationRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideMaster" Target="slideMasters/slideMaster1.xml"/>'
    . $relsSlides
    . '<Relationship Id="rId12" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/presProps" Target="presProps.xml"/>'
    . '<Relationship Id="rId13" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/viewProps" Target="viewProps.xml"/>'
    . '<Relationship Id="rId14" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/tableStyles" Target="tableStyles.xml"/>'
    . '<Relationship Id="rId15" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/theme" Target="theme/theme1.xml"/>'
    . '</Relationships>';

$slideRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideLayout" Target="../slideLayouts/slideLayout1.xml"/>'
    . '</Relationships>';

$master = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<p:sldMaster xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main">'
    . '<p:cSld><p:bg><p:bgPr><a:solidFill><a:srgbClr val="F4EFE4"/></a:solidFill><a:effectLst/></p:bgPr></p:bg><p:spTree>'
    . '<p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>'
    . '<p:grpSpPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="0" cy="0"/><a:chOff x="0" y="0"/><a:chExt cx="0" cy="0"/></a:xfrm></p:grpSpPr>'
    . '</p:spTree></p:cSld>'
    . '<p:clrMap bg1="lt1" tx1="dk1" bg2="lt2" tx2="dk2" accent1="accent1" accent2="accent2" accent3="accent3" accent4="accent4" accent5="accent5" accent6="accent6" hlink="hlink" folHlink="folHlink"/>'
    . '<p:sldLayoutIdLst><p:sldLayoutId id="2147483649" r:id="rId1"/></p:sldLayoutIdLst>'
    . '<p:txStyles><p:titleStyle/><p:bodyStyle/><p:otherStyle/></p:txStyles>'
    . '</p:sldMaster>';

$masterRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideLayout" Target="../slideLayouts/slideLayout1.xml"/>'
    . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/theme" Target="../theme/theme1.xml"/>'
    . '</Relationships>';

$layout = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<p:sldLayout xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" type="blank" preserve="1">'
    . '<p:cSld name="Пустой"><p:spTree>'
    . '<p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>'
    . '<p:grpSpPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="0" cy="0"/><a:chOff x="0" y="0"/><a:chExt cx="0" cy="0"/></a:xfrm></p:grpSpPr>'
    . '</p:spTree></p:cSld><p:clrMapOvr><a:masterClrMapping/></p:clrMapOvr></p:sldLayout>';

$layoutRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideMaster" Target="../slideMasters/slideMaster1.xml"/>'
    . '</Relationships>';

$theme = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<a:theme xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" name="Garant">'
    . '<a:themeElements>'
    . '<a:clrScheme name="Garant">'
    . '<a:dk1><a:srgbClr val="1D1A15"/></a:dk1><a:lt1><a:srgbClr val="F6F1E7"/></a:lt1>'
    . '<a:dk2><a:srgbClr val="12281E"/></a:dk2><a:lt2><a:srgbClr val="FFFDF8"/></a:lt2>'
    . '<a:accent1><a:srgbClr val="1B4332"/></a:accent1><a:accent2><a:srgbClr val="E09F3E"/></a:accent2>'
    . '<a:accent3><a:srgbClr val="2D6A4F"/></a:accent3><a:accent4><a:srgbClr val="9C3D2F"/></a:accent4>'
    . '<a:accent5><a:srgbClr val="6F675C"/></a:accent5><a:accent6><a:srgbClr val="F4E2B8"/></a:accent6>'
    . '<a:hlink><a:srgbClr val="1B4332"/></a:hlink><a:folHlink><a:srgbClr val="9A6B16"/></a:folHlink>'
    . '</a:clrScheme>'
    . '<a:fontScheme name="Garant"><a:majorFont><a:latin typeface="Georgia"/><a:ea typeface=""/><a:cs typeface=""/></a:majorFont>'
    . '<a:minorFont><a:latin typeface="Calibri"/><a:ea typeface=""/><a:cs typeface=""/></a:minorFont></a:fontScheme>'
    . '<a:fmtScheme name="Garant"><a:fillStyleLst><a:solidFill><a:schemeClr val="phClr"/></a:solidFill><a:solidFill><a:schemeClr val="phClr"/></a:solidFill><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:fillStyleLst>'
    . '<a:lnStyleLst><a:ln w="6350"><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:ln><a:ln w="6350"><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:ln><a:ln w="6350"><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:ln></a:lnStyleLst>'
    . '<a:effectStyleLst><a:effectStyle><a:effectLst/></a:effectStyle><a:effectStyle><a:effectLst/></a:effectStyle><a:effectStyle><a:effectLst/></a:effectStyle></a:effectStyleLst>'
    . '<a:bgFillStyleLst><a:solidFill><a:schemeClr val="phClr"/></a:solidFill><a:solidFill><a:schemeClr val="phClr"/></a:solidFill><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:bgFillStyleLst>'
    . '</a:fmtScheme></a:themeElements><a:objectDefaults/><a:extraClrSchemeLst/></a:theme>';

$presProps = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><p:presentationPr xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"/>';
$viewProps = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><p:viewPr xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"><p:normalViewPr><p:restoredLeft sz="15620"/><p:restoredTop sz="94660"/></p:normalViewPr><p:slideViewPr><p:cSldViewPr><p:cViewPr varScale="1"><p:scale><a:sx n="100" d="100" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"/><a:sy n="100" d="100" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"/></p:scale><p:origin x="0" y="0"/></p:cViewPr></p:cSldViewPr></p:slideViewPr></p:viewPr>';
$tableStyles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><a:tblStyleLst xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" def="{5C22544A-7EE6-4342-B048-85BDC9FD1C3A}"/>';

$core = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
    . '<dc:title>ГАРАНТ — маркетплейс с эскроу</dc:title>'
    . '<dc:subject>Продажа и обмен с гарантией платформы</dc:subject>'
    . '<dc:creator>ГАРАНТ</dc:creator>'
    . '<cp:lastModifiedBy>ГАРАНТ</cp:lastModifiedBy>'
    . '</cp:coreProperties>';

$app = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties">'
    . '<Application>Microsoft Office PowerPoint</Application><Slides>10</Slides><PresentationFormat>Экран (16:9)</PresentationFormat></Properties>';

$zip = new ZipArchive();
if ($zip->open($out, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "Не удалось создать файл\n");
    exit(1);
}
$zip->addFromString('[Content_Types].xml', $contentTypes);
$zip->addFromString('_rels/.rels', $rootRels);
$zip->addFromString('docProps/core.xml', $core);
$zip->addFromString('docProps/app.xml', $app);
$zip->addFromString('ppt/presentation.xml', $presentation);
$zip->addFromString('ppt/_rels/presentation.xml.rels', $presentationRels);
$zip->addFromString('ppt/slideMasters/slideMaster1.xml', $master);
$zip->addFromString('ppt/slideMasters/_rels/slideMaster1.xml.rels', $masterRels);
$zip->addFromString('ppt/slideLayouts/slideLayout1.xml', $layout);
$zip->addFromString('ppt/slideLayouts/_rels/slideLayout1.xml.rels', $layoutRels);
$zip->addFromString('ppt/theme/theme1.xml', $theme);
$zip->addFromString('ppt/presProps.xml', $presProps);
$zip->addFromString('ppt/viewProps.xml', $viewProps);
$zip->addFromString('ppt/tableStyles.xml', $tableStyles);
foreach ($slides as $i => $xml) {
    $n = $i + 1;
    $zip->addFromString('ppt/slides/slide' . $n . '.xml', $xml);
    $zip->addFromString('ppt/slides/_rels/slide' . $n . '.xml.rels', $slideRels);
}
$zip->close();

echo $out . PHP_EOL;
