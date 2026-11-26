-- ==========================================================
-- FoodFlow database  |  File 1 of 4: schema (tables + keys)
-- Run order: 01_schema.sql -> 02_sample_data.sql -> 03_queries_crud.sql -> 04_constraint_tests.sql
-- Works seamlessly on XAMPP MySQL and MariaDB 10.2+.
-- WARNING: this file DROPS and recreates the foodflow database.
-- ==========================================================
DROP DATABASE IF EXISTS foodflow;
CREATE DATABASE foodflow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE foodflow;

-- 1. USERS: all roles live in one table
CREATE TABLE users (
    user_id       INT AUTO_INCREMENT PRIMARY KEY,
    full_name     VARCHAR(100) NOT NULL,
    email         VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role          ENUM('customer','restaurant','delivery','admin','restaurant_staff','delivery_person') NOT NULL DEFAULT 'customer',
    phone         VARCHAR(20) DEFAULT NULL,
    address       VARCHAR(255) DEFAULT NULL,
    is_active     TINYINT(1) NOT NULL DEFAULT 1,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_users_role (role)
) ENGINE=InnoDB;

-- 2. RESTAURANTS: restaurant information
CREATE TABLE restaurants (
    restaurant_id   INT AUTO_INCREMENT PRIMARY KEY,
    restaurant_name VARCHAR(150) NOT NULL,
    address         VARCHAR(255) DEFAULT NULL,
    city            VARCHAR(100) DEFAULT NULL,
    phone           VARCHAR(20) DEFAULT NULL,
    email           VARCHAR(150) DEFAULT NULL,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 3. CATEGORIES: Burgers, Pizza, Drinks, etc.
CREATE TABLE categories (
    id   INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- 4. FOOD: menu items. restaurant_id = staff user or restaurant who manages the item.
CREATE TABLE food (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    category_id   INT NULL,
    restaurant_id INT NULL,
    name          VARCHAR(100) NOT NULL,
    description   VARCHAR(255) DEFAULT NULL,
    price         DECIMAL(8,2) NOT NULL,
    stock         INT NOT NULL DEFAULT 50,
    available     TINYINT(1) NOT NULL DEFAULT 1,
    is_veg        TINYINT(1) NOT NULL DEFAULT 1,
    image         VARCHAR(255) NULL,
    CONSTRAINT chk_food_price CHECK (price >= 0),
    CONSTRAINT chk_food_stock CHECK (stock >= 0),
    CONSTRAINT fk_food_category   FOREIGN KEY (category_id)   REFERENCES categories(id) ON DELETE SET NULL,
    CONSTRAINT fk_food_restaurant FOREIGN KEY (restaurant_id) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_food_available (available)
) ENGINE=InnoDB;

-- 5. ORDERS: one row per order placed by a customer
CREATE TABLE orders (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    customer_id      INT NOT NULL,
    customer_name    VARCHAR(100) NULL,
    total            DECIMAL(10,2) NOT NULL,
    payment_method   VARCHAR(50) NOT NULL DEFAULT 'Cash on delivery',
    status           ENUM('placed','preparing','ready','out_for_delivery','delivered','cancelled','New','Preparing','Ready','Completed','Cancelled') NOT NULL DEFAULT 'placed',
    delivery_address VARCHAR(255) NOT NULL,
    notes            VARCHAR(255) NULL,
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_orders_total CHECK (total >= 0),
    CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id) REFERENCES users(user_id) ON DELETE RESTRICT,
    INDEX idx_orders_status (status),
    INDEX idx_orders_created (created_at)
) ENGINE=InnoDB;

-- 6. ORDER_ITEMS: which food, how many, at what price
CREATE TABLE order_items (
    id       INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    food_id  INT NOT NULL,
    quantity INT NOT NULL,
    price    DECIMAL(8,2) NOT NULL,
    CONSTRAINT chk_items_qty   CHECK (quantity > 0),
    CONSTRAINT chk_items_price CHECK (price >= 0),
    CONSTRAINT uq_order_food   UNIQUE (order_id, food_id),
    CONSTRAINT fk_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_items_food  FOREIGN KEY (food_id)  REFERENCES food(id)   ON DELETE RESTRICT
) ENGINE=InnoDB;

-- 7. PAYMENTS: one payment per order (simulated)
CREATE TABLE payments (
    id       INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL UNIQUE,
    amount   DECIMAL(10,2) NOT NULL,
    method   VARCHAR(50) NOT NULL DEFAULT 'Cash on delivery',
    status   VARCHAR(30) NOT NULL DEFAULT 'pending',
    paid_at  TIMESTAMP NULL,
    CONSTRAINT chk_pay_amount CHECK (amount >= 0),
    CONSTRAINT fk_pay_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 8. DELIVERIES: one delivery per order, handled by delivery partner
CREATE TABLE deliveries (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    order_id           INT NOT NULL UNIQUE,
    delivery_person_id INT NULL,
    status             VARCHAR(50) NOT NULL DEFAULT 'assigned',
    assigned_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    delivered_at       TIMESTAMP NULL,
    CONSTRAINT fk_del_order  FOREIGN KEY (order_id)           REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_del_person FOREIGN KEY (delivery_person_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 9. REVIEWS: a customer rates a food item once
CREATE TABLE reviews (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    food_id     INT NOT NULL,
    rating      TINYINT NOT NULL,
    comment     VARCHAR(255),
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_review_rating CHECK (rating BETWEEN 1 AND 5),
    CONSTRAINT uq_review UNIQUE (customer_id, food_id),
    CONSTRAINT fk_rev_customer FOREIGN KEY (customer_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_rev_food     FOREIGN KEY (food_id)     REFERENCES food(id)        ON DELETE CASCADE
) ENGINE=InnoDB;

-- 10. PASSWORD_RESETS: stores temporary tokens for password reset functionality
CREATE TABLE password_resets (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    token_hash VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_reset_token (token_hash),
    CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;
