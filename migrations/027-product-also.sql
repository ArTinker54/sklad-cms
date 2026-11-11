-- ---------------------------------------------------------------------------
-- Товар в нескольких разделах (28.10.2026).
--
-- Заказчик (Партнёр): «я создаю новую категорию и в ней новые подкатегории, а
-- чтоб наполнить их товарами, просто внутри товара ставлю галочки
-- (показывать в категориях) — как на b2b-store.example.ru и в OpenCart. Товар
-- живёт в одном месте, а показывается в разных категориях».
--
-- Товар остаётся там, где лежит: адрес, хлебные крошки, canonical и тексты у
-- него одни. В отмеченном разделе (и во всех разделах над ним) он встаёт в
-- сетку, подбор и счётчики, как свой (Sklad\Model\Also). Копия товара с
-- canonical для этого больше не нужна.
--
-- Отдельной таблицей, а не вторым category_id у товара: от раздела строится
-- адрес, и второй раздел значил бы второй адрес той же карточки.
--
-- В таблице одни числа: правило сравнения строк базы (см. 024) ей не важно.
-- ---------------------------------------------------------------------------
CREATE TABLE product_also (
    product_id       INT UNSIGNED NOT NULL,           -- какой товар показываем ещё
    category_id      INT UNSIGNED NOT NULL,           -- в каком разделе
    created_at       DATETIME NOT NULL,
    PRIMARY KEY (product_id, category_id),
    KEY category (category_id),
    CONSTRAINT pa_product FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE,
    CONSTRAINT pa_category FOREIGN KEY (category_id) REFERENCES category (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
