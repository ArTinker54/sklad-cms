-- ---------------------------------------------------------------------------
-- Подраздел в нескольких разделах (27.10.2026).
--
-- Заказчик (Партнёр): «чтоб подкатегория жила в одном месте, а показывалась в
-- разных категориях». Подраздел остаётся там, где лежит: адрес, хлебные
-- крошки и товары у него одни, правится он в одном месте. В другом разделе
-- он встаёт плиткой, а его товары — в сетку, подбор и счётчики того
-- раздела (Sklad\Model\Also).
--
-- Отдельной таблицей, а не вторым родителем у раздела: от родителя
-- строится адрес, и второй родитель значил бы второй адрес той же страницы.
-- Здесь только «где ещё показать».
--
-- В таблице одни числа: правило сравнения строк базы (см. 024) ей не важно.
-- ---------------------------------------------------------------------------
CREATE TABLE category_also (
    parent_id        INT UNSIGNED NOT NULL,           -- раздел, где показываем ещё
    category_id      INT UNSIGNED NOT NULL,           -- какой подраздел показываем
    sort             INT NOT NULL DEFAULT 0,          -- порядок плиток среди показанных
    created_at       DATETIME NOT NULL,
    PRIMARY KEY (parent_id, category_id),
    KEY category (category_id),
    CONSTRAINT ca_parent FOREIGN KEY (parent_id) REFERENCES category (id) ON DELETE CASCADE,
    CONSTRAINT ca_category FOREIGN KEY (category_id) REFERENCES category (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
