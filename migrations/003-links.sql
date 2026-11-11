-- ---------------------------------------------------------------------------
-- Похожие товары.
--
-- Связь однонаправленная и задаётся руками. Автоматический подбор
-- остаётся запасным: пока для товара ничего не выбрано, сайт сам
-- показывает соседей по разделу. Иначе на 247 позициях блок «похожие»
-- пришлось бы заполнять вручную 247 раз, и его просто не заполнили бы.
--
-- Обратную связь не заводим намеренно: «к ричтраку подходит вот этот
-- аккумулятор» не означает «к аккумулятору подходит вот этот ричтрак».
-- ---------------------------------------------------------------------------
CREATE TABLE product_link (
    product_id      INT UNSIGNED NOT NULL,
    related_id      INT UNSIGNED NOT NULL,
    sort            INT NOT NULL DEFAULT 0,
    PRIMARY KEY (product_id, related_id),
    KEY sort (product_id, sort),
    CONSTRAINT pl_product FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE,
    CONSTRAINT pl_related FOREIGN KEY (related_id) REFERENCES product (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
