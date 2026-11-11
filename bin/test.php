<?php

declare(strict_types=1);

// Только из командной строки.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * Проверки без базы и без сети.
 *
 *   php bin/test.php
 *
 * Здесь то, что можно проверить чистым вызовом: очистка разметки,
 * адреса, мета-теги, часы работы, обезвреживание выгрузки. Прогон
 * экранов (bin/smoke.php) отвечает на вопрос «собирается ли», эти
 * проверки — на вопрос «правильно ли считает».
 *
 * Заведены не для порядка. За время работы я дважды ломал уже
 * работающее — синтаксической ошибкой в шаблоне главной и порядком
 * объединения массивов в ссылке «Сбросить». Первое поймал прогон
 * экранов, второе — только ручная проверка. Такие вещи должны падать
 * при выкладке, а не всплывать у заказчика.
 *
 * Свой крошечный запускатель, а не PHPUnit: composer в этой системе нет
 * намеренно, разворачивается она копированием файлов.
 */

$root = dirname(__DIR__);
require $root . '/src/Core/App.php';
Sklad\Core\App::registerAutoload($root);

use Sklad\Core\View;
use Sklad\Model\Html;
use Sklad\Model\Meta;
use Sklad\Model\Photo;
use Sklad\Model\Promo;
use Sklad\Model\Slug;

$passed = 0;
$failed = [];
$group = '';

function group(string $name): void
{
    global $group;
    $group = $name;
}

function is_it(string $what, $actual, $expected): void
{
    global $passed, $failed, $group;

    if ($actual === $expected) {
        $passed++;

        return;
    }
    $failed[] = sprintf(
        "%s — %s\n      ждали:  %s\n      вышло:  %s",
        $group,
        $what,
        var_export($expected, true),
        var_export($actual, true)
    );
}

function ok(string $what, bool $condition): void
{
    is_it($what, $condition, true);
}

// ---------------------------------------------------------------------------
// Очистка разметки
// ---------------------------------------------------------------------------
group('очистка разметки');

ok('скрипт вырезается', !str_contains(Html::clean('<p>текст</p><script>alert(1)</script>'), '<script'));
ok('содержимое скрипта не остаётся текстом', !str_contains(Html::clean('<script>alert(1)</script>'), 'alert'));
ok('обработчик события снимается', !str_contains(Html::clean('<p onclick="alert(1)">a</p>'), 'onclick'));
ok('схема javascript снимается', !str_contains(Html::clean('<a href="javascript:alert(1)">a</a>'), 'javascript'));
ok('схема в разном регистре снимается', !str_contains(
    mb_strtolower(Html::clean('<a href="jAvAsCrIpT:alert(1)">a</a>')),
    'javascript'
));
ok('пробел перед схемой не спасает', !str_contains(Html::clean('<a href="  javascript:alert(1)">a</a>'), 'javascript'));
ok('data-адрес снимается', !str_contains(Html::clean('<a href="data:text/html,x">a</a>'), 'data:'));
ok('обычная ссылка остаётся', str_contains(Html::clean('<a href="/katalog/">к</a>'), 'href="/katalog/"'));
ok('внешняя ссылка получает rel', str_contains(
    Html::clean('<a href="https://example.com" target="_blank">к</a>'),
    'rel="noopener"'
));
ok('заголовок и список остаются', Html::clean('<h2>Т</h2><ul><li>раз</li></ul>') === '<h2>Т</h2><ul><li>раз</li></ul>');
ok('текст из чужого тега не теряется', str_contains(Html::clean('<marquee>важное</marquee>'), 'важное'));
ok('картинка без адреса выбрасывается', !str_contains(Html::clean('<img src=x onerror=alert(1)>'), '<img'));
ok('картинка с адресом остаётся', str_contains(Html::clean('<img src="/upload/a.jpg" alt="а">'), '<img'));
ok('чужой класс снимается', !str_contains(Html::clean('<div class="chuzhoy">т</div>'), 'chuzhoy'));
ok('свой класс остаётся', str_contains(Html::clean('<div class="prose">т</div>'), 'class="prose"'));
is_it('пустое остаётся пустым', Html::clean('   '), '');
ok('комментарий вырезается', !str_contains(Html::clean('<!-- тайна --><p>т</p>'), 'тайна'));
ok('таблица остаётся', str_contains(Html::clean('<table><tr><td>1</td></tr></table>'), '<td>1</td>'));

// Лишний </div> в переносимых текстах встречается сплошь и рядом. Он не
// должен уносить с собой остаток страницы — а раньше уносил, и молча.
ok('лишний закрывающий тег не съедает хвост',
    str_contains(Html::clean('<p>Начало</p></div>Хвост страницы'), 'Хвост страницы'));
ok('хвост после лишнего тега тоже чистится',
    !str_contains(Html::clean('<p>т</p></div><script>alert(1)</script>дальше'), 'alert'));
ok('обработчик в хвосте снимается',
    !str_contains(Html::clean('<p>т</p></div><p onclick="alert(1)">Б</p>'), 'onclick'));
ok('обёртку не закрыть изнутри',
    !str_contains(Html::clean('<p>т</p></sklad-root><script>alert(1)</script>'), 'alert'));

// ---------------------------------------------------------------------------
// Заголовок окна
// ---------------------------------------------------------------------------
// Приписка много месяцев ничего не делала; когда заработала, главная
// второго сайта стала «TOR — официальный дилерTOR».
group('заголовок окна');

is_it('приписка добавляется', Meta::withSuffix('Тележки электрические', ' — TOR'), 'Тележки электрические — TOR');
is_it('второй раз не добавляется', Meta::withSuffix('TOR — официальный дилер', 'TOR'), 'TOR — официальный дилер');
is_it('русское название узнаётся', Meta::withSuffix('Тележка Партнёр 2500', ' — Тор'), 'Тележка Партнёр 2500');
is_it('пустая приписка ничего не меняет', Meta::withSuffix('Контакты', ''), 'Контакты');
is_it('приписка без пробела не слипается', Meta::withSuffix('Тележки', 'Склад-Техника'), 'Тележки Склад-Техника');
ok('длинный заголовок с припиской влезает в предел',
    mb_strlen(Meta::withSuffix(str_repeat('Штабелёр ', 12), ' — Бренд')) <= Meta::TITLE_LIMIT);

// ---------------------------------------------------------------------------
// Адреса
// ---------------------------------------------------------------------------
group('адреса');

is_it('кириллица в латиницу', Slug::make('Штабелёр CDDK20'), 'shtabeler-cddk20');
is_it('знаки препинания выбрасываются', Slug::make('Тележка, 2.5 т!'), 'telezhka-2-5-t');
is_it('из одних знаков — пусто', Slug::make('!!!'), '');
is_it('щ и ъ по правилам', Slug::make('Подъёмный ящик'), 'podemnyy-yaschik');
ok('длинный адрес режется', strlen(Slug::make(str_repeat('складская техника ', 20))) <= 90);

// ---------------------------------------------------------------------------
// Снимки
// ---------------------------------------------------------------------------
group('снимки');

is_it('плитка из большого', Photo::card('/upload/products/a.jpg'), '/upload/products/a-card.jpg');
is_it('webp плитки', Photo::cardWebp('/upload/products/a.jpg'), '/upload/products/a-card.webp');
is_it('webp большого', Photo::fullWebp('/upload/products/a.jpg'), '/upload/products/a.webp');
is_it('пусто — заглушка', Photo::card(''), Photo::PLACEHOLDER);
ok('пустое опознаётся', Photo::isEmpty(null) && Photo::isEmpty('  '));
ok('непустое опознаётся', !Photo::isEmpty('/upload/a.jpg'));

// ---------------------------------------------------------------------------
// Часы работы для поисковика
// ---------------------------------------------------------------------------
group('часы работы');

is_it('обычная запись', View::schemaHours('Пн–Вс, 9:00–18:00'), 'Mo-Su 09:00-18:00');
is_it('через дефис', View::schemaHours('Пн-Пт, 9:00-18:00'), 'Mo-Fr 09:00-18:00');
is_it('через точку', View::schemaHours('Пн-Вс 9.00-18.00'), 'Mo-Su 09:00-18:00');
is_it('не разобралось — пусто', View::schemaHours('круглосуточно'), '');
is_it('словами — пусто', View::schemaHours('Пн–Сб с 10:00 до 19:30'), '');

// ---------------------------------------------------------------------------
// Прочее
// ---------------------------------------------------------------------------
group('прочее');

is_it('экранирование кавычек', View::e('<a href="x">'), '&lt;a href=&quot;x&quot;&gt;');
is_it('цена по-человечески', View::price(1250000), '1 250 000 ₽');
is_it('дата', View::date('2026-07-29 18:30:00'), '29.07.2026');
is_it('пустая дата', View::date(null), '');
is_it('нулевая дата', View::date('0000-00-00 00:00:00'), '');


// ---------------------------------------------------------------------------
// Действующая скидка
// ---------------------------------------------------------------------------
group('скидка');

$vchera = date('Y-m-d', strtotime('-1 day'));
$zavtra = date('Y-m-d', strtotime('+1 day'));
$segodnya = date('Y-m-d');

ok('бессрочная идёт', Promo::active(['price' => 100, 'old_price' => 200, 'promo_until' => null]));
ok('со сроком впереди идёт', Promo::active(['price' => 100, 'old_price' => 200, 'promo_until' => $zavtra]));
ok('последний день идёт', Promo::active(['price' => 100, 'old_price' => 200, 'promo_until' => $segodnya]));
ok('вчерашняя не идёт', !Promo::active(['price' => 100, 'old_price' => 200, 'promo_until' => $vchera]));
ok('без старой цены не идёт', !Promo::active(['price' => 100, 'old_price' => 0, 'promo_until' => $zavtra]));
ok('старая ниже текущей — не скидка', !Promo::active(['price' => 200, 'old_price' => 100]));
ok('без цены не идёт', !Promo::active(['price' => 0, 'old_price' => 200]));
ok('нулевая дата как бессрочная', Promo::active(['price' => 100, 'old_price' => 200, 'promo_until' => '0000-00-00']));

// ---------------------------------------------------------------------------
// Итог
// ---------------------------------------------------------------------------
if ($failed) {
    echo "\n[test] НЕ СОШЛОСЬ:\n";
    foreach ($failed as $line) {
        echo '  · ' . $line . "\n";
    }
}
printf("[test] проверок %d, из них не сошлось %d\n", $passed + count($failed), count($failed));

exit($failed ? 1 : 0);
