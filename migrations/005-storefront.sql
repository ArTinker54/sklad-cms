-- ---------------------------------------------------------------------------
-- Витрина: страницы под свои шаблоны и настройки, на которые они ссылаются.
--
-- Главная заводится обычной страницей с адресом «/». Так её тексты
-- правятся там же, где остальные, а не оказываются единственными,
-- ради которых нужно звать разработчика.
-- ---------------------------------------------------------------------------

INSERT INTO page (slug, path, title, meta_title, meta_description, body, template,
                  is_published, is_indexable, sort, created_at, updated_at)
VALUES ('', '/', 'Главная', '', '', '', 'home', 1, 1, 0, NOW(), NOW())
ON DUPLICATE KEY UPDATE template = 'home';

INSERT INTO page (slug, path, title, meta_title, meta_description, body, template,
                  is_published, is_indexable, sort, created_at, updated_at)
VALUES
('katalog', '/katalog/', 'Каталог техники', '', '', '', 'catalog', 1, 1, 5, NOW(), NOW()),
('akcii', '/akcii/', 'Акции и спецпредложения', '', '', '', 'promo', 1, 1, 6, NOW(), NOW()),
('o-brende', '/o-brende/', 'О компании', '', '', '', 'page', 1, 1, 55, NOW(), NOW()),
('dostavka-i-oplata', '/dostavka-i-oplata/', 'Доставка и оплата', '', '', '', 'page', 1, 1, 56, NOW(), NOW()),
('garantiya', '/garantiya/', 'Гарантия и сервис', '', '', '', 'page', 1, 1, 57, NOW(), NOW())
ON DUPLICATE KEY UPDATE title = VALUES(title);

-- Страница контактов рисуется своим шаблоном: телефон и почта на ней
-- должны браться из настроек, иначе при смене телефона его правят в двух
-- местах и однажды правят только в одном.
UPDATE page SET template = 'contacts' WHERE path = '/kontakty/';

-- ---------------------------------------------------------------------------
-- Реквизиты
--
-- Заготовка с пустыми полями, а не выдуманные значения: пустая строка
-- видна и её заполнят, а правдоподобный ИНН из ниоткуда попадёт в счёт.
-- ---------------------------------------------------------------------------
UPDATE block SET body = CONCAT(
    '<table class="specs"><tbody>',
    '<tr><th scope="row">Полное наименование</th><td>—</td></tr>',
    '<tr><th scope="row">ИНН</th><td>—</td></tr>',
    '<tr><th scope="row">КПП</th><td>—</td></tr>',
    '<tr><th scope="row">ОГРН</th><td>—</td></tr>',
    '<tr><th scope="row">Юридический адрес</th><td>—</td></tr>',
    '<tr><th scope="row">Расчётный счёт</th><td>—</td></tr>',
    '</tbody></table>'
), updated_at = NOW()
WHERE `key` = 'requisites' AND (body IS NULL OR body = '');

-- ---------------------------------------------------------------------------
-- Настройки, на которые ссылается витрина
-- ---------------------------------------------------------------------------
INSERT INTO setting (`key`, value, title, hint, `group`, type, sort) VALUES
('brand', '', 'Марка техники', 'Подставляется в заголовок первого экрана', 'Общие', 'text', 25),
('work_hours', 'Пн–Пт, 9:00–18:00 по Москве', 'Время работы', 'Показывается на главной и в контактах', 'Контакты', 'text', 45)
ON DUPLICATE KEY UPDATE title = VALUES(title);
