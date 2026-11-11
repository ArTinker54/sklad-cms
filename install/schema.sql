-- =============================================================================
-- Sklad CMS / B2B Storefront & Fleet Engine
-- Consolidated Full Database Schema (MySQL 8.0+ / MariaDB 10.4+)
-- Charset: utf8mb4, Collation: utf8mb4_unicode_ci
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- 1. Настройки системы
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `setting`;
CREATE TABLE `setting` (
  `key` VARCHAR(64) NOT NULL,
  `value` MEDIUMTEXT NULL,
  `title` VARCHAR(255) NOT NULL,
  `hint` VARCHAR(255) NOT NULL DEFAULT '',
  `group` VARCHAR(64) NOT NULL DEFAULT 'Общие',
  `type` ENUM('text','textarea','number','select','color') NOT NULL DEFAULT 'text',
  `sort` INT NOT NULL DEFAULT 100,
  PRIMARY KEY (`key`),
  KEY `idx_group_sort` (`group`, `sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 2. Текстовые блоки
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `block`;
CREATE TABLE `block` (
  `key` VARCHAR(64) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `hint` VARCHAR(255) NOT NULL DEFAULT '',
  `body` MEDIUMTEXT NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 3. Статические страницы
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `page`;
CREATE TABLE `page` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(128) NOT NULL,
  `path` VARCHAR(255) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `meta_title` VARCHAR(255) NOT NULL DEFAULT '',
  `meta_description` TEXT NULL,
  `body` MEDIUMTEXT NOT NULL,
  `template` VARCHAR(64) NOT NULL DEFAULT 'page',
  `is_published` TINYINT(1) NOT NULL DEFAULT 1,
  `is_indexable` TINYINT(1) NOT NULL DEFAULT 1,
  `sort` INT NOT NULL DEFAULT 100,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  `deleted_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_path` (`path`),
  KEY `idx_slug` (`slug`),
  KEY `idx_published_sort` (`is_published`, `sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 4. Категории каталога
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `category`;
CREATE TABLE `category` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id` INT UNSIGNED NULL,
  `slug` VARCHAR(128) NOT NULL,
  `path` VARCHAR(255) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `meta_title` VARCHAR(255) NOT NULL DEFAULT '',
  `meta_description` TEXT NULL,
  `body` MEDIUMTEXT NULL,
  `image` VARCHAR(255) NULL,
  `is_published` TINYINT(1) NOT NULL DEFAULT 1,
  `sort` INT NOT NULL DEFAULT 100,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  `deleted_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_path` (`path`),
  KEY `idx_parent` (`parent_id`),
  KEY `idx_published_sort` (`is_published`, `sort`),
  CONSTRAINT `fk_category_parent` FOREIGN KEY (`parent_id`) REFERENCES `category` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 5. Товары
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `product`;
CREATE TABLE `product` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` INT UNSIGNED NOT NULL,
  `slug` VARCHAR(128) NOT NULL,
  `path` VARCHAR(255) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `sku` VARCHAR(64) NOT NULL DEFAULT '',
  `price` DECIMAL(12,2) NULL,
  `old_price` DECIMAL(12,2) NULL,
  `currency` VARCHAR(8) NOT NULL DEFAULT 'RUB',
  `meta_title` VARCHAR(255) NOT NULL DEFAULT '',
  `meta_description` TEXT NULL,
  `summary` TEXT NULL,
  `body` MEDIUMTEXT NULL,
  `is_available` TINYINT(1) NOT NULL DEFAULT 1,
  `is_published` TINYINT(1) NOT NULL DEFAULT 1,
  `is_popular` TINYINT(1) NOT NULL DEFAULT 0,
  `is_promo` TINYINT(1) NOT NULL DEFAULT 0,
  `sort` INT NOT NULL DEFAULT 100,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  `deleted_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_path` (`path`),
  KEY `idx_category` (`category_id`),
  KEY `idx_sku` (`sku`),
  KEY `idx_published_sort` (`is_published`, `sort`),
  CONSTRAINT `fk_product_category` FOREIGN KEY (`category_id`) REFERENCES `category` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 6. Характеристики и фильтры
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `option`;
CREATE TABLE `option` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `key` VARCHAR(64) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `unit` VARCHAR(32) NOT NULL DEFAULT '',
  `type` ENUM('text','number','select') NOT NULL DEFAULT 'text',
  `is_filterable` TINYINT(1) NOT NULL DEFAULT 1,
  `sort` INT NOT NULL DEFAULT 100,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `product_option`;
CREATE TABLE `product_option` (
  `product_id` INT UNSIGNED NOT NULL,
  `option_id` INT UNSIGNED NOT NULL,
  `value` VARCHAR(255) NOT NULL,
  `num_value` DECIMAL(12,4) NULL,
  PRIMARY KEY (`product_id`, `option_id`),
  KEY `idx_option_value` (`option_id`, `value`),
  KEY `idx_option_num` (`option_id`, `num_value`),
  CONSTRAINT `fk_po_product` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_po_option` FOREIGN KEY (`option_id`) REFERENCES `option` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `category_option`;
CREATE TABLE `category_option` (
  `category_id` INT UNSIGNED NOT NULL,
  `option_id` INT UNSIGNED NOT NULL,
  `is_filterable` TINYINT(1) NOT NULL DEFAULT 1,
  `sort` INT NOT NULL DEFAULT 100,
  PRIMARY KEY (`category_id`, `option_id`),
  CONSTRAINT `fk_co_category` FOREIGN KEY (`category_id`) REFERENCES `category` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_co_option` FOREIGN KEY (`option_id`) REFERENCES `option` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 7. Фотографии
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `photo`;
CREATE TABLE `photo` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `url` VARCHAR(255) NOT NULL,
  `thumb_url` VARCHAR(255) NOT NULL DEFAULT '',
  `width` INT UNSIGNED NOT NULL DEFAULT 0,
  `height` INT UNSIGNED NOT NULL DEFAULT 0,
  `alt` VARCHAR(255) NOT NULL DEFAULT '',
  `sort` INT NOT NULL DEFAULT 100,
  PRIMARY KEY (`id`),
  KEY `idx_product_sort` (`product_id`, `sort`),
  CONSTRAINT `fk_photo_product` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 8. Слайды первого экрана (Hero Slider)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `hero_slide`;
CREATE TABLE `hero_slide` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `image` VARCHAR(255) NOT NULL,
  `title` VARCHAR(255) NOT NULL DEFAULT '',
  `eyebrow` VARCHAR(255) NOT NULL DEFAULT '',
  `lead` TEXT NULL,
  `btn_text` VARCHAR(64) NOT NULL DEFAULT '',
  `btn_url` VARCHAR(255) NOT NULL DEFAULT '',
  `focus` VARCHAR(32) NOT NULL DEFAULT 'center center',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `sort` INT NOT NULL DEFAULT 100,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_active_sort` (`is_active`, `sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 9. Теговые страницы (SEO Tag Pages)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `tag_page`;
CREATE TABLE `tag_page` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` INT UNSIGNED NOT NULL,
  `slug` VARCHAR(128) NOT NULL,
  `path` VARCHAR(255) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `h1` VARCHAR(255) NOT NULL DEFAULT '',
  `meta_title` VARCHAR(255) NOT NULL DEFAULT '',
  `meta_description` TEXT NULL,
  `body` MEDIUMTEXT NULL,
  `filter_query` VARCHAR(255) NOT NULL DEFAULT '',
  `is_published` TINYINT(1) NOT NULL DEFAULT 1,
  `sort` INT NOT NULL DEFAULT 100,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_path` (`path`),
  KEY `idx_category` (`category_id`),
  CONSTRAINT `fk_tag_category` FOREIGN KEY (`category_id`) REFERENCES `category` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 10. Сопутствующие товары и категории (Cross-Sell)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `category_also`;
CREATE TABLE `category_also` (
  `category_id` INT UNSIGNED NOT NULL,
  `also_id` INT UNSIGNED NOT NULL,
  `sort` INT NOT NULL DEFAULT 100,
  PRIMARY KEY (`category_id`, `also_id`),
  CONSTRAINT `fk_ca_cat` FOREIGN KEY (`category_id`) REFERENCES `category` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ca_also` FOREIGN KEY (`also_id`) REFERENCES `category` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `product_also`;
CREATE TABLE `product_also` (
  `product_id` INT UNSIGNED NOT NULL,
  `also_id` INT UNSIGNED NOT NULL,
  `sort` INT NOT NULL DEFAULT 100,
  PRIMARY KEY (`product_id`, `also_id`),
  CONSTRAINT `fk_pa_prod` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pa_also` FOREIGN KEY (`also_id`) REFERENCES `product` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 11. Городской контент (Мульти-гео)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `city_content`;
CREATE TABLE `city_content` (
  `city_slug` VARCHAR(64) NOT NULL,
  `category_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `h1` VARCHAR(255) NULL,
  `meta_title` VARCHAR(255) NULL,
  `meta_description` TEXT NULL,
  `body` MEDIUMTEXT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`city_slug`, `category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 12. B2B Заявки (Request for Quote)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `request`;
CREATE TABLE `request` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `number` VARCHAR(32) NOT NULL DEFAULT '',
  `name` VARCHAR(128) NOT NULL DEFAULT '',
  `phone` VARCHAR(64) NOT NULL DEFAULT '',
  `email` VARCHAR(128) NOT NULL DEFAULT '',
  `company` VARCHAR(128) NOT NULL DEFAULT '',
  `city` VARCHAR(64) NOT NULL DEFAULT '',
  `comment` TEXT NULL,
  `items` MEDIUMTEXT NULL,
  `status` ENUM('new','in_progress','done','spam') NOT NULL DEFAULT 'new',
  `ip` VARCHAR(45) NOT NULL DEFAULT '',
  `user_agent` VARCHAR(255) NOT NULL DEFAULT '',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status_created` (`status`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 13. Журнал согласий на обработку ПДн (152-ФЗ / GDPR)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `consent`;
CREATE TABLE `consent` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `request_id` INT UNSIGNED NULL,
  `ip` VARCHAR(45) NOT NULL,
  `user_agent` VARCHAR(255) NOT NULL,
  `policy_version` VARCHAR(64) NOT NULL DEFAULT '',
  `wording` TEXT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_request` (`request_id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 14. Переадресации и 404
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `redirect`;
CREATE TABLE `redirect` (
  `from_path` VARCHAR(255) NOT NULL,
  `to_path` VARCHAR(255) NOT NULL,
  `code` SMALLINT UNSIGNED NOT NULL DEFAULT 301,
  `hits` INT UNSIGNED NOT NULL DEFAULT 0,
  `last_hit_at` DATETIME NULL,
  PRIMARY KEY (`from_path`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `not_found`;
CREATE TABLE `not_found` (
  `path` VARCHAR(255) NOT NULL,
  `hits` INT UNSIGNED NOT NULL DEFAULT 1,
  `referer` VARCHAR(255) NOT NULL DEFAULT '',
  `first_hit_at` DATETIME NOT NULL,
  `last_hit_at` DATETIME NOT NULL,
  PRIMARY KEY (`path`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 15. Пользователи админки и Аудит
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `user`;
CREATE TABLE `user` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(128) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `name` VARCHAR(128) NOT NULL DEFAULT '',
  `role` ENUM('admin','manager','editor') NOT NULL DEFAULT 'admin',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `login_attempt`;
CREATE TABLE `login_attempt` (
  `ip` VARCHAR(45) NOT NULL,
  `email` VARCHAR(128) NOT NULL,
  `attempted_at` DATETIME NOT NULL,
  KEY `idx_ip_time` (`ip`, `attempted_at`),
  KEY `idx_email_time` (`email`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `audit`;
CREATE TABLE `audit` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NULL,
  `action` VARCHAR(64) NOT NULL,
  `entity` VARCHAR(64) NOT NULL,
  `entity_id` INT UNSIGNED NULL,
  `details` MEDIUMTEXT NULL,
  `ip` VARCHAR(45) NOT NULL DEFAULT '',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_entity` (`entity`, `entity_id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
