-- ---------------------------------------------------------------------------
-- Теги — страницы-подборки внутри раздела (05.10.2026).
--
-- Заказчик: «надо, чтобы мы выбирали товары, которые туда будут
-- дублироваться». Как на b2b-store.example.ru: тег — своя страница под разделом
-- (/telezhki/rohli-2-tonny/) со своими заголовком и текстами, а товары в
-- неё отбирают руками; сам товар остаётся в своём разделе и по своему
-- адресу. Ссылка на тег сама встаёт в «Популярные запросы» раздела.
--
-- Отдельная таблица, а не раздел с пометкой: разделы читают меню, плитки,
-- подбор, выгрузки и счётчики — десяток мест, и в каждом тег пришлось бы
-- отсеивать. Пропусти одно — и подборка вылезет в меню вторым разделом.
-- ---------------------------------------------------------------------------
CREATE TABLE tag (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    -- Раздел, внутри которого тег живёт: от него адрес, хлебные крошки и
    -- место в «Популярных запросах».
    category_id      INT UNSIGNED NOT NULL,
    slug             VARCHAR(190) NOT NULL,
    path             VARCHAR(500) NOT NULL,           -- /telezhki/rohli-2-tonny/
    title            VARCHAR(255) NOT NULL,           -- подпись в «Популярных запросах» и в крошках
    h1               VARCHAR(255) NOT NULL DEFAULT '',
    meta_title       VARCHAR(255) NOT NULL DEFAULT '',
    meta_description VARCHAR(500) NOT NULL DEFAULT '',
    intro            TEXT NULL,                       -- текст над списком товаров
    body             MEDIUMTEXT NULL,                 -- текст под списком
    sort             INT NOT NULL DEFAULT 0,
    is_published     TINYINT(1) NOT NULL DEFAULT 0,
    is_indexable     TINYINT(1) NOT NULL DEFAULT 1,
    deleted_at       DATETIME NULL,
    created_at       DATETIME NOT NULL,
    updated_at       DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY path (path),
    KEY category (category_id, sort),
    CONSTRAINT tag_category FOREIGN KEY (category_id) REFERENCES category (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Какие товары в теге и в каком порядке. Порядок свой у каждого тега:
-- в «Рохлях 2 тонны» первой ставят ходовую модель, а не ту, что первая в
-- разделе.
CREATE TABLE tag_product (
    tag_id           INT UNSIGNED NOT NULL,
    product_id       INT UNSIGNED NOT NULL,
    sort             INT NOT NULL DEFAULT 0,
    PRIMARY KEY (tag_id, product_id),
    KEY product (product_id),
    KEY sort (tag_id, sort),
    CONSTRAINT tp_tag FOREIGN KEY (tag_id) REFERENCES tag (id) ON DELETE CASCADE,
    CONSTRAINT tp_product FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
