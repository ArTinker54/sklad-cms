-- ---------------------------------------------------------------------------
-- Теги: правило сравнения строк — как у разделов (26.10.2026).
--
-- 018 заводит таблицы тегов с одним DEFAULT CHARSET=utf8mb4, и правило
-- сравнения им даёт сама база. На dealer.example.ru база старая, от OpenCart
-- (utf8_general_ci), и tag получила utf8mb4_general_ci, а таблицы движка там
-- utf8mb4_unicode_ci — их заливали дампом со стенда. Адрес тега сравнивается
-- с адресами разделов (Visible::tag — LIKE, «Популярные запросы» — UNION), и
-- MySQL отказывает: «Illegal mix of collations». На выкладке тегов на Партнёр
-- 26.10.2026 разделы и карточки товаров отдали 500, пока код не откатили.
--
-- Правило берём у category.path этой же базы, а не вписываем своё: на других
-- сайтах сети таблицы движка могут быть заведены с другим, и жёстко
-- вписанное здесь сломало бы уже их. Где правила и так совпадают, таблицы
-- просто пересоберутся — они маленькие.
-- ---------------------------------------------------------------------------
SET @tag_charset = (
    SELECT CHARACTER_SET_NAME FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'category' AND COLUMN_NAME = 'path'
);
SET @tag_collation = (
    SELECT COLLATION_NAME FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'category' AND COLUMN_NAME = 'path'
);

SET @tag_sql = CONCAT('ALTER TABLE tag CONVERT TO CHARACTER SET ', @tag_charset, ' COLLATE ', @tag_collation);
PREPARE tag_collation FROM @tag_sql;
EXECUTE tag_collation;
DEALLOCATE PREPARE tag_collation;

SET @tag_sql = CONCAT('ALTER TABLE tag_product CONVERT TO CHARACTER SET ', @tag_charset, ' COLLATE ', @tag_collation);
PREPARE tag_collation FROM @tag_sql;
EXECUTE tag_collation;
DEALLOCATE PREPARE tag_collation;
