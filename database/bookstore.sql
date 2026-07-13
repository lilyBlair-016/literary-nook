-- =============================================================================
--  THE LITERARY NOOK  ·  Bookstore Management System
-- =============================================================================

-- Module 6: Database creation ------------------------------------------------
DROP DATABASE IF EXISTS bookstore_db;
CREATE DATABASE bookstore_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE bookstore_db;

SET FOREIGN_KEY_CHECKS = 0;   -- allow clean re-import ordering

-- =============================================================================
--  1. USERS  (customers + admins in one table, separated by `role`)
--     Demonstrates: PRIMARY KEY, AUTO_INCREMENT, UNIQUE, NOT NULL, ENUM,
--     DEFAULT, TIMESTAMP, index on search column (email).
-- =============================================================================
CREATE TABLE users (
    user_id            INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    first_name         VARCHAR(50)      NOT NULL,
    last_name          VARCHAR(50)      NOT NULL,
    email              VARCHAR(120)     NOT NULL,
    password_hash      VARCHAR(255)     NOT NULL,      -- bcrypt via password_hash()
    phone              VARCHAR(20)      DEFAULT NULL,
    avatar             VARCHAR(255)     DEFAULT NULL,  -- profile picture filename in assets/uploads
    role               ENUM('customer','admin') NOT NULL DEFAULT 'customer',
    membership_status  ENUM('regular','silver','gold','vip') NOT NULL DEFAULT 'regular',
    is_active          TINYINT(1)       NOT NULL DEFAULT 1,
    created_at         TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
--  2. ADDRESSES  (Address management — 1 user : many addresses)
-- =============================================================================
CREATE TABLE addresses (
    address_id     INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    user_id        INT UNSIGNED  NOT NULL,
    label          VARCHAR(40)   NOT NULL DEFAULT 'Home',   -- Home, Work, etc.
    recipient_name VARCHAR(100)  NOT NULL,
    line1          VARCHAR(150)  NOT NULL,
    line2          VARCHAR(150)  DEFAULT NULL,
    city           VARCHAR(60)   NOT NULL,
    state          VARCHAR(60)   NOT NULL,
    postal_code    VARCHAR(20)   NOT NULL,
    country        VARCHAR(60)   NOT NULL DEFAULT 'Philippines',
    phone          VARCHAR(20)   DEFAULT NULL,
    is_default     TINYINT(1)    NOT NULL DEFAULT 0,
    PRIMARY KEY (address_id),
    KEY idx_addr_user (user_id),
    CONSTRAINT fk_addr_user FOREIGN KEY (user_id)
        REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
--  3. REMEMBER-ME TOKENS  (Module 5: cookies. Stores hashed selector/token
--     for the "Remember Me" login cookie — never store raw tokens.)
-- =============================================================================
CREATE TABLE remember_tokens (
    token_id    INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED  NOT NULL,
    selector    CHAR(32)      NOT NULL,          -- public half (goes in cookie)
    token_hash  CHAR(64)      NOT NULL,          -- SHA-256 of secret half
    expires_at  DATETIME      NOT NULL,
    created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (token_id),
    UNIQUE KEY uq_selector (selector),
    KEY idx_rt_user (user_id),
    CONSTRAINT fk_rt_user FOREIGN KEY (user_id)
        REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
--  4. PASSWORD RESETS  (Forgot / recover password flow — Module 5)
-- =============================================================================
CREATE TABLE password_resets (
    reset_id    INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED  NOT NULL,
    token_hash  CHAR(64)      NOT NULL,          -- SHA-256 of the emailed token
    expires_at  DATETIME      NOT NULL,
    used        TINYINT(1)    NOT NULL DEFAULT 0,
    created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (reset_id),
    KEY idx_pr_user (user_id),
    CONSTRAINT fk_pr_user FOREIGN KEY (user_id)
        REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
--  5. LOOKUP TABLES  (authors, publishers, genres, categories)
--     3NF: removes transitive dependencies from `books`.
-- =============================================================================
CREATE TABLE authors (
    author_id  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    name       VARCHAR(120)  NOT NULL,
    bio        TEXT          DEFAULT NULL,
    PRIMARY KEY (author_id),
    KEY idx_author_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE publishers (
    publisher_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name         VARCHAR(120) NOT NULL,
    PRIMARY KEY (publisher_id),
    UNIQUE KEY uq_publisher_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE genres (
    genre_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name     VARCHAR(60)  NOT NULL,
    PRIMARY KEY (genre_id),
    UNIQUE KEY uq_genre_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categories (
    category_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(60)  NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (category_id),
    UNIQUE KEY uq_category_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
--  6. BOOKS  (title-level catalog data — one row per work)
--     FKs to author / publisher / category. Genres handled M:N below.
-- =============================================================================
CREATE TABLE books (
    book_id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    title            VARCHAR(200)  NOT NULL,
    isbn             VARCHAR(20)   NOT NULL,
    author_id        INT UNSIGNED  NOT NULL,
    publisher_id     INT UNSIGNED  DEFAULT NULL,
    category_id      INT UNSIGNED  DEFAULT NULL,
    publication_year SMALLINT      DEFAULT NULL,
    description      TEXT          DEFAULT NULL,
    cover_image      VARCHAR(255)  DEFAULT NULL,   -- filename under assets/uploads
    is_active        TINYINT(1)    NOT NULL DEFAULT 1,
    created_at       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (book_id),
    UNIQUE KEY uq_book_isbn (isbn),
    KEY idx_book_title (title),                    -- speeds up search
    KEY idx_book_author (author_id),
    KEY idx_book_publisher (publisher_id),
    KEY idx_book_category (category_id),
    KEY idx_book_year (publication_year),
    CONSTRAINT fk_book_author FOREIGN KEY (author_id)
        REFERENCES authors (author_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_book_publisher FOREIGN KEY (publisher_id)
        REFERENCES publishers (publisher_id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_book_category FOREIGN KEY (category_id)
        REFERENCES categories (category_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
--  7. BOOK_GENRES  (M:N junction — a book may have many genres. 1NF/2NF.)
-- =============================================================================
CREATE TABLE book_genres (
    book_id  INT UNSIGNED NOT NULL,
    genre_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (book_id, genre_id),              -- composite key, no partial dep.
    KEY idx_bg_genre (genre_id),
    CONSTRAINT fk_bg_book FOREIGN KEY (book_id)
        REFERENCES books (book_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_bg_genre FOREIGN KEY (genre_id)
        REFERENCES genres (genre_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
--  8. BOOK_FORMATS  (one book : many formats, each with its own price + stock)
--     Directly satisfies "support various formats" + "stock per title AND
--     format". Digital formats keep stock NULL (unlimited).
-- =============================================================================
CREATE TABLE book_formats (
    format_id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    book_id      INT UNSIGNED NOT NULL,
    format_type  ENUM('hardcover','paperback','ebook','audiobook') NOT NULL,
    is_digital   TINYINT(1)    NOT NULL DEFAULT 0,
    price        DECIMAL(10,2) NOT NULL,
    stock_qty    INT           DEFAULT NULL,      -- NULL = unlimited (digital)
    sku          VARCHAR(40)   DEFAULT NULL,
    PRIMARY KEY (format_id),
    UNIQUE KEY uq_book_format (book_id, format_type),
    KEY idx_fmt_book (book_id),
    CONSTRAINT fk_fmt_book FOREIGN KEY (book_id)
        REFERENCES books (book_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT chk_price_positive CHECK (price >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
--  9. WISHLISTS  (customer wishes a title — 1 row per user+book)
-- =============================================================================
CREATE TABLE wishlists (
    wishlist_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED NOT NULL,
    book_id     INT UNSIGNED NOT NULL,
    added_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (wishlist_id),
    UNIQUE KEY uq_wish_user_book (user_id, book_id),
    KEY idx_wish_book (book_id),
    CONSTRAINT fk_wish_user FOREIGN KEY (user_id)
        REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_wish_book FOREIGN KEY (book_id)
        REFERENCES books (book_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 10. CART_ITEMS  (persistent cart — references a specific FORMAT, not title)
-- =============================================================================
CREATE TABLE cart_items (
    cart_item_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id      INT UNSIGNED NOT NULL,
    format_id    INT UNSIGNED NOT NULL,
    quantity     INT          NOT NULL DEFAULT 1,
    added_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (cart_item_id),
    UNIQUE KEY uq_cart_user_fmt (user_id, format_id),
    KEY idx_cart_fmt (format_id),
    CONSTRAINT fk_cart_user FOREIGN KEY (user_id)
        REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_cart_fmt FOREIGN KEY (format_id)
        REFERENCES book_formats (format_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT chk_qty_positive CHECK (quantity > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 11. PROMOTIONS / DISCOUNTS  (coupon codes + promotional offers)
-- =============================================================================
CREATE TABLE promotions (
    promo_id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code           VARCHAR(30)  NOT NULL,
    description    VARCHAR(200) DEFAULT NULL,
    discount_type  ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
    discount_value DECIMAL(10,2) NOT NULL,
    min_order      DECIMAL(10,2) NOT NULL DEFAULT 0,
    start_date     DATE          DEFAULT NULL,
    end_date       DATE          DEFAULT NULL,
    is_active      TINYINT(1)    NOT NULL DEFAULT 1,
    PRIMARY KEY (promo_id),
    UNIQUE KEY uq_promo_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 12. ORDERS  (order header — money is stored as computed snapshots)
-- =============================================================================
CREATE TABLE orders (
    order_id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_number        VARCHAR(20)  NOT NULL,
    user_id             INT UNSIGNED NOT NULL,
    shipping_address_id INT UNSIGNED DEFAULT NULL,
    promo_id            INT UNSIGNED DEFAULT NULL,
    status              ENUM('pending','confirmed','shipped','delivered','cancelled')
                                     NOT NULL DEFAULT 'pending',
    subtotal            DECIMAL(10,2) NOT NULL DEFAULT 0,
    discount_amount     DECIMAL(10,2) NOT NULL DEFAULT 0,
    shipping_fee        DECIMAL(10,2) NOT NULL DEFAULT 0,
    total_amount        DECIMAL(10,2) NOT NULL DEFAULT 0,
    placed_at           TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (order_id),
    UNIQUE KEY uq_order_number (order_number),
    KEY idx_order_user (user_id),
    KEY idx_order_status (status),
    CONSTRAINT fk_order_user FOREIGN KEY (user_id)
        REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_order_addr FOREIGN KEY (shipping_address_id)
        REFERENCES addresses (address_id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_order_promo FOREIGN KEY (promo_id)
        REFERENCES promotions (promo_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 13. ORDER_ITEMS  (line items — SNAPSHOT title/format/price so history is
--     immutable even if the catalog later changes.)
-- =============================================================================
CREATE TABLE order_items (
    order_item_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id      INT UNSIGNED NOT NULL,
    format_id     INT UNSIGNED DEFAULT NULL,      -- SET NULL if format deleted
    book_title    VARCHAR(200) NOT NULL,          -- snapshot
    format_type   VARCHAR(20)  NOT NULL,          -- snapshot
    unit_price    DECIMAL(10,2) NOT NULL,         -- snapshot
    quantity      INT           NOT NULL,
    line_total    DECIMAL(10,2) NOT NULL,
    PRIMARY KEY (order_item_id),
    KEY idx_oi_order (order_id),
    KEY idx_oi_fmt (format_id),
    CONSTRAINT fk_oi_order FOREIGN KEY (order_id)
        REFERENCES orders (order_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_oi_fmt FOREIGN KEY (format_id)
        REFERENCES book_formats (format_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 14. PAYMENTS  (1 order : many payment attempts; simulated gateway)
-- =============================================================================
CREATE TABLE payments (
    payment_id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id        INT UNSIGNED NOT NULL,
    payment_method  ENUM('credit_card','debit_card','paypal','gcash','cod') NOT NULL,
    amount          DECIMAL(10,2) NOT NULL,
    status          ENUM('pending','completed','failed','refunded') NOT NULL DEFAULT 'pending',
    transaction_ref VARCHAR(40)  DEFAULT NULL,
    paid_at         TIMESTAMP    NULL DEFAULT NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (payment_id),
    KEY idx_pay_order (order_id),
    KEY idx_pay_status (status),
    CONSTRAINT fk_pay_order FOREIGN KEY (order_id)
        REFERENCES orders (order_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 15. SHIPMENTS  (basic shipping + tracking for physical orders)
-- =============================================================================
CREATE TABLE shipments (
    shipment_id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id        INT UNSIGNED NOT NULL,
    carrier         VARCHAR(60)  DEFAULT NULL,
    tracking_number VARCHAR(60)  DEFAULT NULL,
    status          ENUM('preparing','in_transit','out_for_delivery','delivered')
                                 NOT NULL DEFAULT 'preparing',
    shipped_at      DATETIME     DEFAULT NULL,
    delivered_at    DATETIME     DEFAULT NULL,
    PRIMARY KEY (shipment_id),
    KEY idx_ship_order (order_id),
    CONSTRAINT fk_ship_order FOREIGN KEY (order_id)
        REFERENCES orders (order_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 16. NOTIFICATIONS  (in-app bell + a record of every email we "send")
-- =============================================================================
CREATE TABLE notifications (
    notification_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED NOT NULL,
    type            ENUM('registration','order','shipping','promo','system') NOT NULL,
    subject         VARCHAR(150) NOT NULL,
    message         TEXT         NOT NULL,
    is_read         TINYINT(1)   NOT NULL DEFAULT 0,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (notification_id),
    KEY idx_notif_user (user_id),
    CONSTRAINT fk_notif_user FOREIGN KEY (user_id)
        REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 17. USER_PREFERRED_GENRES  (M:N — customers' preferred genres from spec 1.1)
-- =============================================================================
CREATE TABLE user_preferred_genres (
    user_id  INT UNSIGNED NOT NULL,
    genre_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (user_id, genre_id),
    KEY idx_upg_genre (genre_id),
    CONSTRAINT fk_upg_user FOREIGN KEY (user_id)
        REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_upg_genre FOREIGN KEY (genre_id)
        REFERENCES genres (genre_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- #############################################################################
--  SAMPLE DATA
--  Passwords (bcrypt-hashed):  admin => Admin@123   ·   customers => Password123
-- #############################################################################

-- Users -----------------------------------------------------------------------
INSERT INTO users (first_name,last_name,email,password_hash,phone,role,membership_status) VALUES
('Site','Administrator','admin@literarynook.com','$2y$10$.M8o9i7323I.yO6w5gR/z.PXLvi9nn1qPhRJ1QdNT6ze/3aIXsiTy','09170000001','admin','vip'),
('Jane','Doe','jane@example.com','$2y$10$Su0h/f8wynhrSpErFiWOVeOyKrH8.qejxIDbHoOK1MOmGrUlmNjUm','09170000002','customer','gold'),
('Mark','Santos','mark@example.com','$2y$10$g/HZhLFnTVuWjMO5IHuNteVAxRDGR69o0fXzDb8doca9bGA4FEZaG','09170000003','customer','silver'),
('Lucy','Reyes','lucy@example.com','$2y$10$gZqJufkM.b32QkZqzaoWGupUtc5NX0Q9CYxyKPmPX4wXVcZ8gnNMa','09170000004','customer','regular');

-- Addresses -------------------------------------------------------------------
INSERT INTO addresses (user_id,label,recipient_name,line1,city,state,postal_code,phone,is_default) VALUES
(2,'Home','Jane Doe','12 Rizal St., Brgy. Malinta','Quezon City','Metro Manila','1100','09170000002',1),
(3,'Home','Mark Santos','88 Mabini Ave.','Cebu City','Cebu','6000','09170000003',1);

-- Authors ---------------------------------------------------------------------
INSERT INTO authors (name,bio) VALUES
('Harper Lee','American novelist best known for To Kill a Mockingbird.'),
('George Orwell','English novelist and essayist, journalist and critic.'),
('J.K. Rowling','British author, creator of the Harry Potter series.'),
('Paulo Coelho','Brazilian lyricist and novelist.'),
('Yuval Noah Harari','Israeli historian and author of Sapiens.'),
('Agatha Christie','English writer known for detective novels.');

-- Publishers ------------------------------------------------------------------
INSERT INTO publishers (name) VALUES
('J.B. Lippincott & Co.'),('Secker & Warburg'),('Bloomsbury'),
('HarperOne'),('Harvill Secker'),('Collins Crime Club');

-- Genres ----------------------------------------------------------------------
INSERT INTO genres (name) VALUES
('Fiction'),('Classic'),('Dystopian'),('Fantasy'),('Adventure'),
('Non-Fiction'),('History'),('Mystery'),('Self-Help');

-- Categories ------------------------------------------------------------------
INSERT INTO categories (name,description) VALUES
('Literature & Fiction','Novels, classics and literary works'),
('Science & History','Non-fiction, science and historical titles'),
('Mystery & Thriller','Crime, detective and suspense'),
('Young Adult','Books for teen and YA readers');

-- Books -----------------------------------------------------------------------
INSERT INTO books (title,isbn,author_id,publisher_id,category_id,publication_year,description) VALUES
('To Kill a Mockingbird','9780061120084',1,1,1,1960,'A novel about racial injustice in the Deep South, seen through the eyes of a child.'),
('1984','9780451524935',2,2,1,1949,'A dystopian social science fiction novel about totalitarian surveillance.'),
('Animal Farm','9780451526342',2,2,1,1945,'A satirical allegorical novella reflecting events leading up to the Russian Revolution.'),
('Harry Potter and the Sorcerer''s Stone','9780590353427',3,3,4,1997,'A young wizard discovers his magical heritage on his eleventh birthday.'),
('The Alchemist','9780061122415',4,4,1,1988,'A shepherd boy journeys to Egypt after a recurring dream of finding treasure.'),
('Sapiens: A Brief History of Humankind','9780062316097',5,5,2,2011,'Explores the history and impact of Homo sapiens.'),
('Murder on the Orient Express','9780062073501',6,6,3,1934,'Detective Hercule Poirot investigates a murder aboard a snowbound train.');

-- Book <-> Genre (M:N) --------------------------------------------------------
INSERT INTO book_genres (book_id,genre_id) VALUES
(1,1),(1,2),
(2,1),(2,2),(2,3),
(3,1),(3,2),
(4,1),(4,4),(4,5),
(5,1),(5,5),
(6,6),(6,7),
(7,1),(7,8);

-- Book Formats (price + stock per format) ------------------------------------
INSERT INTO book_formats (book_id,format_type,is_digital,price,stock_qty,sku) VALUES
(1,'paperback',0,755.00,40,'TKM-PB'),
(1,'hardcover',0,1305.00,15,'TKM-HC'),
(1,'ebook',1,465.00,NULL,'TKM-EB'),
(2,'paperback',0,635.00,60,'1984-PB'),
(2,'ebook',1,375.00,NULL,'1984-EB'),
(2,'audiobook',1,870.00,NULL,'1984-AB'),
(3,'paperback',0,550.00,35,'AF-PB'),
(4,'hardcover',0,1740.00,25,'HP1-HC'),
(4,'paperback',0,925.00,50,'HP1-PB'),
(4,'ebook',1,695.00,NULL,'HP1-EB'),
(5,'paperback',0,780.00,45,'ALC-PB'),
(5,'audiobook',1,985.00,NULL,'ALC-AB'),
(6,'hardcover',0,1450.00,20,'SAP-HC'),
(6,'ebook',1,755.00,NULL,'SAP-EB'),
(7,'paperback',0,665.00,30,'MOE-PB');

-- Wishlists -------------------------------------------------------------------
INSERT INTO wishlists (user_id,book_id) VALUES
(2,4),(2,6),(3,2);

-- Preferred genres ------------------------------------------------------------
INSERT INTO user_preferred_genres (user_id,genre_id) VALUES
(2,4),(2,1),(3,3),(3,8);

-- Promotions ------------------------------------------------------------------
INSERT INTO promotions (code,description,discount_type,discount_value,min_order,start_date,end_date,is_active) VALUES
('WELCOME10','10% off your first order','percent',10.00,0,'2026-01-01','2026-12-31',1),
('READMORE','₱100 off orders over ₱1000','fixed',100.00,1000,'2026-01-01','2026-12-31',1),
('SUMMER25','25% summer sale','percent',25.00,500,'2026-06-01','2026-08-31',1);

-- A completed sample order (for reports/order-history in later phases) --------
INSERT INTO orders (order_number,user_id,shipping_address_id,promo_id,status,subtotal,discount_amount,shipping_fee,total_amount,placed_at) VALUES
('ORD-2026-0001',2,1,1,'delivered',35.49,3.55,5.00,36.94,'2026-06-10 09:30:00');

INSERT INTO order_items (order_id,format_id,book_title,format_type,unit_price,quantity,line_total) VALUES
(1,1,'To Kill a Mockingbird','paperback',12.99,1,12.99),
(1,9,'Harry Potter and the Sorcerer''s Stone','paperback',15.99,1,15.99),
(1,7,'Animal Farm','paperback',9.50,1,9.50);

INSERT INTO payments (order_id,payment_method,amount,status,transaction_ref,paid_at) VALUES
(1,'gcash',36.94,'completed','TXN-20260610-0001','2026-06-10 09:31:00');

INSERT INTO shipments (order_id,carrier,tracking_number,status,shipped_at,delivered_at) VALUES
(1,'LBC Express','LBC123456789','delivered','2026-06-11 08:00:00','2026-06-13 14:20:00');

INSERT INTO notifications (user_id,type,subject,message,is_read) VALUES
(2,'registration','Welcome to The Literary Nook!','Your account has been created successfully.',1),
(2,'order','Order ORD-2026-0001 confirmed','Thank you! Your order has been confirmed and is being prepared.',1),
(2,'shipping','Your order has shipped','Order ORD-2026-0001 is on the way via LBC Express (LBC123456789).',0);

