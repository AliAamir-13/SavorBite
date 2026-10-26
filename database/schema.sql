-- =====================================================================
-- DATABASE: food_ordering_system
-- Complete DDL Schema with Tables, Constraints, Indexes & Foreign Keys
-- Suitable for XAMPP / MySQL 8.0+ / MariaDB
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `food_ordering_system` 
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `food_ordering_system`;

-- Drop tables in reverse dependency order if recreating
DROP TABLE IF EXISTS `payments`;
DROP TABLE IF EXISTS `order_status_history`;
DROP TABLE IF EXISTS `order_items`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `cart_items`;
DROP TABLE IF EXISTS `food_items`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `addresses`;
DROP TABLE IF EXISTS `users`;

-- ---------------------------------------------------------------------
-- 1. USERS TABLE
-- Stores customers and administrator accounts.
-- Demonstrates: Primary Key, Unique Email Constraint, Role Enum
-- ---------------------------------------------------------------------
CREATE TABLE `users` (
    `user_id` INT AUTO_INCREMENT PRIMARY KEY,
    `full_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `phone` VARCHAR(20) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('customer', 'admin') NOT NULL DEFAULT 'customer',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_users_email` (`email`),
    INDEX `idx_users_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. ADDRESSES TABLE
-- Stores multiple delivery addresses per customer (1:N relationship).
-- Normalized to 3NF to prevent repetitive address storage in Users.
-- Demonstrates: Foreign Key with ON DELETE CASCADE
-- ---------------------------------------------------------------------
CREATE TABLE `addresses` (
    `address_id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `address_label` VARCHAR(50) NOT NULL DEFAULT 'Home',
    `address_line` VARCHAR(255) NOT NULL,
    `city` VARCHAR(100) NOT NULL,
    `postal_code` VARCHAR(20) NOT NULL,
    `is_default` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_addresses_user` 
        FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) 
        ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX `idx_addresses_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3. CATEGORIES TABLE
-- Stores food categories (Pizza, Burgers, Asian, Desserts, etc.)
-- Demonstrates: Primary Key, Unique Category Name
-- ---------------------------------------------------------------------
CREATE TABLE `categories` (
    `category_id` INT AUTO_INCREMENT PRIMARY KEY,
    `category_name` VARCHAR(50) NOT NULL UNIQUE,
    `description` TEXT NULL,
    `image_url` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4. FOOD_ITEMS TABLE
-- Stores all restaurant menu items.
-- Demonstrates: Foreign Key to categories, Check Constraint on price,
-- composite indexing on availability and category.
-- ---------------------------------------------------------------------
CREATE TABLE `food_items` (
    `food_id` INT AUTO_INCREMENT PRIMARY KEY,
    `category_id` INT NOT NULL,
    `food_name` VARCHAR(100) NOT NULL,
    `description` TEXT NOT NULL,
    `price` DECIMAL(10,2) NOT NULL,
    `image_path` VARCHAR(255) NOT NULL,
    `is_available` TINYINT(1) NOT NULL DEFAULT 1,
    `is_popular` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `chk_food_price_positive` CHECK (`price` > 0),
    CONSTRAINT `fk_food_category` 
        FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) 
        ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX `idx_food_category_id` (`category_id`),
    INDEX `idx_food_name` (`food_name`),
    INDEX `idx_food_available` (`is_available`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5. CART_ITEMS TABLE
-- Persists cart state for logged-in users across devices.
-- Demonstrates: Composite Unique Key (`user_id`, `food_id`) to prevent duplicates,
-- Check Constraint on quantity.
-- ---------------------------------------------------------------------
CREATE TABLE `cart_items` (
    `cart_item_id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `food_id` INT NOT NULL,
    `quantity` INT NOT NULL DEFAULT 1,
    `added_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_cart_qty_positive` CHECK (`quantity` > 0),
    CONSTRAINT `fk_cart_user` 
        FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) 
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_cart_food` 
        FOREIGN KEY (`food_id`) REFERENCES `food_items` (`food_id`) 
        ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY `unique_user_food` (`user_id`, `food_id`),
    INDEX `idx_cart_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6. ORDERS TABLE
-- Stores high-level order headers.
-- Demonstrates: Foreign Key, Check Constraints, Snapshot Address
-- preserving historical integrity when user address changes.
-- ---------------------------------------------------------------------
CREATE TABLE `orders` (
    `order_id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_number` VARCHAR(30) NOT NULL UNIQUE,
    `user_id` INT NOT NULL,
    `delivery_address_text` TEXT NOT NULL,
    `order_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `subtotal` DECIMAL(10,2) NOT NULL,
    `delivery_fee` DECIMAL(10,2) NOT NULL DEFAULT 49.00,
    `total_amount` DECIMAL(10,2) NOT NULL,
    `payment_method` ENUM('Cash on Delivery', 'Demo Online Payment') NOT NULL,
    `payment_status` ENUM('Pending', 'Paid', 'Failed') NOT NULL DEFAULT 'Pending',
    `order_status` ENUM('Pending', 'Confirmed', 'Preparing', 'Out for Delivery', 'Delivered', 'Cancelled') NOT NULL DEFAULT 'Pending',
    `special_instructions` TEXT NULL,
    CONSTRAINT `chk_order_subtotal` CHECK (`subtotal` >= 0),
    CONSTRAINT `chk_order_total` CHECK (`total_amount` >= 0),
    CONSTRAINT `fk_orders_user` 
        FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) 
        ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX `idx_orders_user_id` (`user_id`),
    INDEX `idx_orders_order_date` (`order_date`),
    INDEX `idx_orders_status` (`order_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7. ORDER_ITEMS TABLE
-- Stores individual food items per order (1:N with orders).
-- Historical price is frozen into `unit_price` so subsequent menu changes
-- never mutate previous financial records.
-- ---------------------------------------------------------------------
CREATE TABLE `order_items` (
    `order_item_id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL,
    `food_id` INT NOT NULL,
    `quantity` INT NOT NULL,
    `unit_price` DECIMAL(10,2) NOT NULL,
    `line_total` DECIMAL(10,2) NOT NULL,
    CONSTRAINT `chk_order_item_qty` CHECK (`quantity` > 0),
    CONSTRAINT `chk_order_item_price` CHECK (`unit_price` > 0),
    CONSTRAINT `chk_order_item_line_total` CHECK (`line_total` > 0),
    CONSTRAINT `fk_order_items_order` 
        FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) 
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_order_items_food` 
        FOREIGN KEY (`food_id`) REFERENCES `food_items` (`food_id`) 
        ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX `idx_order_items_order_id` (`order_id`),
    INDEX `idx_order_items_food_id` (`food_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 8. PAYMENTS TABLE
-- Stores simulated payment records with unique transaction references.
-- Demonstrates: 1:1 or 1:N relationship, auditability
-- ---------------------------------------------------------------------
CREATE TABLE `payments` (
    `payment_id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL UNIQUE,
    `payment_method` VARCHAR(50) NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `payment_status` ENUM('Pending', 'Completed', 'Failed') NOT NULL DEFAULT 'Pending',
    `transaction_reference` VARCHAR(100) NOT NULL UNIQUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `chk_payment_amount` CHECK (`amount` >= 0),
    CONSTRAINT `fk_payments_order` 
        FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) 
        ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX `idx_payments_order_id` (`order_id`),
    INDEX `idx_payments_tx_ref` (`transaction_reference`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 9. ORDER_STATUS_HISTORY TABLE
-- Audit log populated automatically by database Trigger on Order Status Update.
-- Demonstrates: Triggers, Audit Logging, Data Integrity
-- ---------------------------------------------------------------------
CREATE TABLE `order_status_history` (
    `history_id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL,
    `old_status` VARCHAR(50) NULL,
    `new_status` VARCHAR(50) NOT NULL,
    `changed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `notes` VARCHAR(255) NULL,
    CONSTRAINT `fk_status_history_order` 
        FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) 
        ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX `idx_history_order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
