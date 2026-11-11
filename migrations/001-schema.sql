-- ---------------------------------------------------------------------------
-- Схема каталога складской техники.
--
-- Один сайт — одна база. Общего между сайтами нет ничего, поэтому нигде
-- нет site_id: так проще запросы, права и резервные копии, а цена —
-- отдельная установка на каждый сайт, чего и хотели.
--
-- utf8mb4 везде: в названиях техники встречаются ё, тире и знак ×.
-- ---------------------------------------------------------------------------

-- ---------------------------------------------------------------------------
-- Разделы каталога
--
-- path хранится готовой строкой, а не собирается рекурсией: по нему идёт
-- поиск «этот раздел и всё под ним», и делать это рекурсивным запросом на
-- каждый показ страницы незачем. Пересобирается при смене родителя.
-- ---------------------------------------------------------------------------
CREATE TABLE category (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    parent_id       INT UNSIGNED NULL,
    slug            VARCHAR(190) NOT NULL,
    path            VARCHAR(500) NOT NULL,           -- /shtabelery/shtabelery-soprovozhdaemye/
    title           VARCHAR(255) NOT NULL,           -- название в меню и хлебных крошках
    h1              VARCHAR(255) NOT NULL DEFAULT '',
    meta_title      VARCHAR(255) NOT NULL DEFAULT '',
    meta_description VARCHAR(500) NOT NULL DEFAULT '',
    intro           TEXT NULL,                       -- текст над списком товаров
    body            MEDIUMTEXT NULL,                 -- текст под списком, для поисковика
    image           VARCHAR(500) NOT NULL DEFAULT '',
    sort            INT NOT NULL DEFAULT 0,
    is_published    TINYINT(1) NOT NULL DEFAULT 1,
    created_at      DATETIME NOT NULL,
    updated_at      DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY path (path),
    KEY parent_id (parent_id),
    KEY sort (sort),
    CONSTRAINT category_parent FOREIGN KEY (parent_id) REFERENCES category (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- Товары
--
-- Товар не удаляют, а снимают с витрины: удалённый уносит с собой адрес,
-- а на адресе висят ссылки и позиции в выдаче. Для этого is_published и
-- deleted_at по отдельности — снятый с витрины виден в админке, удалённый
-- нет, но адрес занят и редирект работает.
-- ---------------------------------------------------------------------------
CREATE TABLE product (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    category_id     INT UNSIGNED NULL,
    slug            VARCHAR(190) NOT NULL,
    path            VARCHAR(500) NOT NULL,
    article         VARCHAR(100) NOT NULL DEFAULT '',
    title           VARCHAR(255) NOT NULL,           -- он же H1
    meta_title      VARCHAR(255) NOT NULL DEFAULT '',
    meta_description VARCHAR(500) NOT NULL DEFAULT '',
    intro           VARCHAR(500) NOT NULL DEFAULT '',-- короткое описание для плитки
    body            MEDIUMTEXT NULL,
    price           DECIMAL(13,2) NOT NULL DEFAULT 0,
    old_price       DECIMAL(13,2) NOT NULL DEFAULT 0,
    promo_until     DATE NULL,                       -- пусто при бессрочной скидке
    -- Остатков в реальном времени нет и не планируется, поэтому наличие —
    -- три состояния, которые ставит менеджер, а не число.
    availability    ENUM('in_stock','on_order','out') NOT NULL DEFAULT 'on_order',
    vendor          VARCHAR(190) NOT NULL DEFAULT '',
    made_in         VARCHAR(190) NOT NULL DEFAULT '',
    weight          DECIMAL(10,2) NULL,
    sort            INT NOT NULL DEFAULT 0,
    is_published    TINYINT(1) NOT NULL DEFAULT 1,
    deleted_at      DATETIME NULL,
    created_at      DATETIME NOT NULL,
    updated_at      DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY path (path),
    KEY category_id (category_id),
    KEY article (article),
    KEY is_published (is_published, deleted_at),
    KEY price (price),
    FULLTEXT KEY search (title, article, intro),
    CONSTRAINT product_category FOREIGN KEY (category_id) REFERENCES category (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- Характеристики
--
-- Справочник, а не свободный текст в карточке. На переносе Brand в
-- исходных данных «Грузоподъёмность, кг» и «Грузоподъемность, кг» лежали
-- как разные поля — из-за одной буквы. Через полгода такого ввода фильтр
-- в каталоге чинить будет уже нечем.
-- ---------------------------------------------------------------------------
CREATE TABLE `option` (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `key`           VARCHAR(100) NOT NULL,           -- capacity, lift_height
    title           VARCHAR(190) NOT NULL,           -- Грузоподъёмность
    unit            VARCHAR(50) NOT NULL DEFAULT '', -- кг
    `group`         VARCHAR(100) NOT NULL DEFAULT '',-- Основные, Габариты
    type            ENUM('number','text','enum') NOT NULL DEFAULT 'text',
    is_filterable   TINYINT(1) NOT NULL DEFAULT 0,
    sort            INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY `key` (`key`),
    KEY sort (sort)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Заранее заданные значения для типа enum: чтобы «полиуретан» и
-- «Полиуретан» не расползлись по каталогу.
CREATE TABLE option_choice (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    option_id       INT UNSIGNED NOT NULL,
    value           VARCHAR(190) NOT NULL,
    sort            INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY option_value (option_id, value),
    CONSTRAINT choice_option FOREIGN KEY (option_id) REFERENCES `option` (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- value_num заполняется, когда значение числовое: по нему идёт фильтр
-- «от и до». Сравнивать диапазон по строке нельзя — «1000» окажется
-- меньше «900».
CREATE TABLE product_option (
    product_id      INT UNSIGNED NOT NULL,
    option_id       INT UNSIGNED NOT NULL,
    value           VARCHAR(500) NOT NULL,
    value_num       DECIMAL(15,3) NULL,
    PRIMARY KEY (product_id, option_id),
    KEY option_value (option_id, value(100)),
    KEY option_num (option_id, value_num),
    CONSTRAINT po_product FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE,
    CONSTRAINT po_option FOREIGN KEY (option_id) REFERENCES `option` (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Какие характеристики показывать в подборе конкретного раздела.
-- У тележек нет ёмкости батареи, и пустой фильтр там читается как поломка.
CREATE TABLE category_option (
    category_id     INT UNSIGNED NOT NULL,
    option_id       INT UNSIGNED NOT NULL,
    is_filterable   TINYINT(1) NOT NULL DEFAULT 1,
    sort            INT NOT NULL DEFAULT 0,
    PRIMARY KEY (category_id, option_id),
    CONSTRAINT co_category FOREIGN KEY (category_id) REFERENCES category (id) ON DELETE CASCADE,
    CONSTRAINT co_option FOREIGN KEY (option_id) REFERENCES `option` (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- Медиа
--
-- Общая библиотека, а не файлы «внутри товара»: один и тот же снимок
-- нередко нужен нескольким позициям. hash ловит повторную загрузку того
-- же файла.
-- ---------------------------------------------------------------------------
CREATE TABLE media (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    file            VARCHAR(500) NOT NULL,           -- путь от корня сайта
    original_name   VARCHAR(255) NOT NULL DEFAULT '',
    mime            VARCHAR(100) NOT NULL DEFAULT '',
    width           INT UNSIGNED NULL,
    height          INT UNSIGNED NULL,
    size            INT UNSIGNED NULL,
    hash            CHAR(40) NOT NULL DEFAULT '',
    created_at      DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY hash (hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE product_image (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id      INT UNSIGNED NOT NULL,
    media_id        INT UNSIGNED NOT NULL,
    alt             VARCHAR(255) NOT NULL DEFAULT '',
    sort            INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY product_media (product_id, media_id),
    KEY sort (product_id, sort),
    CONSTRAINT pi_product FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE,
    CONSTRAINT pi_media FOREIGN KEY (media_id) REFERENCES media (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- Содержимое сайта
-- ---------------------------------------------------------------------------
CREATE TABLE page (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug            VARCHAR(190) NOT NULL,
    path            VARCHAR(500) NOT NULL,
    title           VARCHAR(255) NOT NULL,
    meta_title      VARCHAR(255) NOT NULL DEFAULT '',
    meta_description VARCHAR(500) NOT NULL DEFAULT '',
    body            MEDIUMTEXT NULL,
    template        VARCHAR(100) NOT NULL DEFAULT 'page',
    is_published    TINYINT(1) NOT NULL DEFAULT 1,
    is_indexable    TINYINT(1) NOT NULL DEFAULT 1,   -- корзина и сравнение — нет
    sort            INT NOT NULL DEFAULT 0,
    created_at      DATETIME NOT NULL,
    updated_at      DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY path (path)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Куски текста вне страниц: телефон в шапке, реквизиты, оговорка про
-- оферту. Меняются чаще всего и не должны требовать разработчика.
CREATE TABLE block (
    `key`           VARCHAR(100) NOT NULL,
    title           VARCHAR(190) NOT NULL,
    hint            VARCHAR(500) NOT NULL DEFAULT '',
    body            MEDIUMTEXT NULL,
    updated_at      DATETIME NOT NULL,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE setting (
    `key`           VARCHAR(100) NOT NULL,
    value           TEXT NULL,
    title           VARCHAR(190) NOT NULL DEFAULT '',
    hint            VARCHAR(500) NOT NULL DEFAULT '',
    `group`         VARCHAR(100) NOT NULL DEFAULT 'Общие',
    type            ENUM('text','textarea','number','flag','select') NOT NULL DEFAULT 'text',
    sort            INT NOT NULL DEFAULT 0,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- Заявки
-- ---------------------------------------------------------------------------
CREATE TABLE request (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    number          VARCHAR(20) NOT NULL,
    type            VARCHAR(32) NOT NULL DEFAULT 'request',
    name            VARCHAR(190) NOT NULL DEFAULT '',
    phone           VARCHAR(64) NOT NULL DEFAULT '',
    email           VARCHAR(190) NOT NULL DEFAULT '',
    company         VARCHAR(190) NOT NULL DEFAULT '',
    inn             VARCHAR(20) NOT NULL DEFAULT '',
    city            VARCHAR(190) NOT NULL DEFAULT '',
    comment         TEXT NULL,
    product_id      INT UNSIGNED NULL,
    items_total     DECIMAL(13,2) NULL,
    page_url        VARCHAR(500) NOT NULL DEFAULT '',
    ip              VARCHAR(45) NOT NULL DEFAULT '',
    user_agent      VARCHAR(500) NOT NULL DEFAULT '',
    status          ENUM('new','in_work','done','spam') NOT NULL DEFAULT 'new',
    manager_id      INT UNSIGNED NULL,
    note            TEXT NULL,                       -- заметка менеджера, не видна клиенту
    created_at      DATETIME NOT NULL,
    updated_at      DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY number (number),
    KEY status (status, created_at),
    KEY created_at (created_at),
    KEY ip (ip, created_at),                         -- предел заявок с адреса в час
    CONSTRAINT request_product FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Название и цену копируем в позицию, а не берём из товара по ссылке:
-- заявка должна остаться такой, какой её оформили, даже если товар потом
-- переименовали, подорожал или снят с витрины.
CREATE TABLE request_item (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_id      INT UNSIGNED NOT NULL,
    product_id      INT UNSIGNED NULL,
    title           VARCHAR(255) NOT NULL DEFAULT '',
    url             VARCHAR(500) NOT NULL DEFAULT '',
    price           DECIMAL(13,2) NULL,
    qty             INT NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    KEY request_id (request_id),
    CONSTRAINT ri_request FOREIGN KEY (request_id) REFERENCES request (id) ON DELETE CASCADE,
    CONSTRAINT ri_product FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- Согласия на обработку персональных данных
--
-- Отдельной таблицей, а не галочкой в заявке. По ст. 9 152-ФЗ доказывать
-- наличие согласия обязан оператор, и доказательством считается
-- зафиксированный факт: когда, с какого адреса, под каким текстом и на
-- какие именно данные. Формулировку берём из настроек сайта на момент
-- отправки — присланная браузером доказательством быть не может.
--
-- Записи не удаляются вместе с заявкой: срок хранения согласия и срок
-- хранения заявки — разные вещи.
-- ---------------------------------------------------------------------------
CREATE TABLE consent (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_id      INT UNSIGNED NULL,
    created_at      DATETIME NOT NULL,
    ip              VARCHAR(45) NOT NULL DEFAULT '',
    user_agent      VARCHAR(500) NOT NULL DEFAULT '',
    policy_url      VARCHAR(500) NOT NULL DEFAULT '',
    policy_version  VARCHAR(64) NOT NULL DEFAULT '',
    wording         TEXT NULL,
    subject         TEXT NULL,                       -- json: какие данные даны
    PRIMARY KEY (id),
    KEY request_id (request_id),
    KEY created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- Адреса
--
-- Самая недооценённая таблица. Переименовали товар или перенесли в другой
-- раздел — адрес изменился, а на старом висят ссылки и позиции в выдаче.
-- Система заводит запись сама, is_auto отличает такие от заведённых руками
-- при переезде со старого сайта.
-- ---------------------------------------------------------------------------
CREATE TABLE redirect (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    from_path       VARCHAR(500) NOT NULL,
    to_path         VARCHAR(500) NOT NULL,
    code            SMALLINT NOT NULL DEFAULT 301,
    is_auto         TINYINT(1) NOT NULL DEFAULT 0,
    hits            INT UNSIGNED NOT NULL DEFAULT 0,
    last_hit_at     DATETIME NULL,
    created_at      DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY from_path (from_path),
    KEY hits (hits)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Обращения к несуществующим адресам: видно, что чинить редиректом.
CREATE TABLE not_found (
    path            VARCHAR(500) NOT NULL,
    hits            INT UNSIGNED NOT NULL DEFAULT 1,
    referer         VARCHAR(500) NOT NULL DEFAULT '',
    first_at        DATETIME NOT NULL,
    last_at         DATETIME NOT NULL,
    PRIMARY KEY (path)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- Люди и след их работы
-- ---------------------------------------------------------------------------
CREATE TABLE user (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    email           VARCHAR(190) NOT NULL,
    password_hash   VARCHAR(255) NOT NULL,
    name            VARCHAR(190) NOT NULL DEFAULT '',
    -- admin видит всё; manager — заявки, но не цены; content — тексты и
    -- товары, но не заявки: там персональные данные.
    role            ENUM('admin','manager','content') NOT NULL DEFAULT 'manager',
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at   DATETIME NULL,
    created_at      DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Кто и когда поменял цену. Когда с каталогом работают двое, это
-- единственный способ разобраться, откуда взялась цифра.
CREATE TABLE audit (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED NULL,
    entity          VARCHAR(64) NOT NULL,
    entity_id       INT UNSIGNED NULL,
    action          VARCHAR(32) NOT NULL,            -- create, update, delete, login
    changes         MEDIUMTEXT NULL,                 -- json: было и стало, только изменённое
    ip              VARCHAR(45) NOT NULL DEFAULT '',
    created_at      DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY entity (entity, entity_id),
    KEY created_at (created_at),
    CONSTRAINT audit_user FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Если заявка не ушла на почту, узнать об этом надо не от клиента.
CREATE TABLE mail_log (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    recipient       VARCHAR(500) NOT NULL,
    subject         VARCHAR(500) NOT NULL DEFAULT '',
    request_id      INT UNSIGNED NULL,
    status          ENUM('sent','failed') NOT NULL,
    error           TEXT NULL,
    created_at      DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Таблицы migration здесь намеренно нет: её заводит сам накатчик перед
-- первой миграцией. Иначе первая же миграция пыталась бы создать
-- таблицу, которая к этому моменту уже нужна и уже есть.
