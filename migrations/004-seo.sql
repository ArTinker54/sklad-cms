-- ---------------------------------------------------------------------------
-- Управление мета-тегами.
--
-- С 247 позициями заполнить мета руками нельзя — их не заполнят вовсе.
-- Поэтому шаблон задаётся у раздела и применяется ко всем товарам
-- внутри, а собственное значение товара, если оно есть, шаблон
-- перебивает. Правится точечно там, где шаблон промахнулся.
-- ---------------------------------------------------------------------------

ALTER TABLE category
    -- Шаблоны для товаров этого раздела. Подстановки: {название},
    -- {артикул}, {раздел}, {цена} и любая характеристика по её ключу —
    -- {capacity}, {lift_height}.
    ADD COLUMN product_title_tpl VARCHAR(255) NOT NULL DEFAULT '' AFTER meta_description,
    ADD COLUMN product_description_tpl VARCHAR(500) NOT NULL DEFAULT '' AFTER product_title_tpl,
    -- Раздел можно закрыть от поиска целиком: например, служебный или
    -- пока не наполненный.
    ADD COLUMN is_indexable TINYINT(1) NOT NULL DEFAULT 1 AFTER is_published,
    -- Когда раздел дублирует другой по смыслу, склеиваем их для
    -- поисковика, а не удаляем: по адресу могут ходить люди.
    ADD COLUMN canonical VARCHAR(500) NOT NULL DEFAULT '' AFTER is_indexable;

ALTER TABLE product
    -- Заголовок на странице отдельно от названия в списке: в плитке
    -- нужно коротко, в заголовке — полно и с ключевым словом.
    ADD COLUMN h1 VARCHAR(255) NOT NULL DEFAULT '' AFTER title,
    ADD COLUMN is_indexable TINYINT(1) NOT NULL DEFAULT 1 AFTER is_published,
    ADD COLUMN canonical VARCHAR(500) NOT NULL DEFAULT '' AFTER is_indexable;

-- Дубли ищутся по этим полям, поэтому им нужен индекс: без него
-- проверка на 247 товарах превращается в перебор пар.
CREATE INDEX product_meta_title ON product (meta_title);
CREATE INDEX product_h1 ON product (h1);

-- robots.txt правится из админки: закрыть от обхода служебный раздел
-- или открыть новый — обычная задача, разработчик для неё не нужен.
INSERT INTO setting (`key`, value, title, hint, `group`, type, sort) VALUES
('robots_txt', '', 'robots.txt', 'Пусто — отдаётся стандартный. Строка Sitemap подставляется сама.', 'Поиск', 'textarea', 10),
('meta_title_suffix', '', 'Приписка к заголовку окна', 'Добавляется в конец, если заголовок собран по шаблону. Например: « — Бренд»', 'Поиск', 'text', 20),
('sitemap_enabled', '1', 'Отдавать карту сайта', '', 'Поиск', 'flag', 30),
('yml_enabled', '1', 'Отдавать выгрузку для Яндекса', 'В неё попадают только товары с ценой: без цены Яндекс отклоняет предложение', 'Поиск', 'flag', 40);
