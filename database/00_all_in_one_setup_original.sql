-- =====================================================================
-- DATABASE: food_ordering_system
-- MASTER ALL-IN-ONE SETUP SCRIPT (FOR XAMPP / phpMyAdmin / MySQL CLI)
-- Contains: Schema (DDL), Constraints, Indexes, Views, Triggers,
--           Stored Procedures, and Complete Seed Data.
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `food_ordering_system` 
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `food_ordering_system`;

-- Disable foreign key checks for clean teardown and rebuild
SET FOREIGN_KEY_CHECKS = 0;

DROP TRIGGER IF EXISTS `trg_before_order_item_insert`;
DROP TRIGGER IF EXISTS `trg_after_order_insert`;
DROP TRIGGER IF EXISTS `trg_after_order_status_update`;

DROP PROCEDURE IF EXISTS `PlaceOrder`;
DROP PROCEDURE IF EXISTS `UpdateOrderStatus`;
DROP PROCEDURE IF EXISTS `GetCustomerOrderStats`;

DROP VIEW IF EXISTS `top_selling_foods_view`;
DROP VIEW IF EXISTS `sales_summary_view`;
DROP VIEW IF EXISTS `order_summary_view`;
DROP VIEW IF EXISTS `food_menu_view`;

DROP TABLE IF EXISTS `payments`;
DROP TABLE IF EXISTS `order_status_history`;
DROP TABLE IF EXISTS `order_items`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `cart_items`;
DROP TABLE IF EXISTS `food_items`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `addresses`;
DROP TABLE IF EXISTS `users`;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- SECTION 1: TABLES (DDL) WITH CONSTRAINTS & INDEXES
-- =====================================================================

-- 1. USERS TABLE
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

-- 2. ADDRESSES TABLE (1:N with Users, Normalized 3NF)
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

-- 3. CATEGORIES TABLE
CREATE TABLE `categories` (
    `category_id` INT AUTO_INCREMENT PRIMARY KEY,
    `category_name` VARCHAR(50) NOT NULL UNIQUE,
    `description` TEXT NULL,
    `image_url` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. FOOD_ITEMS TABLE
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

-- 5. CART_ITEMS TABLE (Composite Unique Key prevents duplicate item lines)
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

-- 6. ORDERS TABLE
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

-- 7. ORDER_ITEMS TABLE (Frozen unit_price at time of purchase)
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

-- 8. PAYMENTS TABLE
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

-- 9. ORDER_STATUS_HISTORY TABLE (Populated via Database Trigger)
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

-- =====================================================================
-- SECTION 2: SQL VIEWS
-- =====================================================================

CREATE OR REPLACE VIEW `food_menu_view` AS
SELECT 
    f.food_id,
    f.food_name,
    f.description,
    f.price,
    f.image_path,
    f.is_available,
    f.is_popular,
    f.created_at AS item_created_at,
    c.category_id,
    c.category_name,
    c.description AS category_description
FROM `food_items` f
INNER JOIN `categories` c ON f.category_id = c.category_id;

CREATE OR REPLACE VIEW `order_summary_view` AS
SELECT 
    o.order_id,
    o.order_number,
    o.order_date,
    o.order_status,
    o.subtotal,
    o.delivery_fee,
    o.total_amount,
    o.payment_method,
    o.payment_status,
    o.delivery_address_text,
    o.special_instructions,
    u.user_id,
    u.full_name AS customer_name,
    u.email AS customer_email,
    u.phone AS customer_phone,
    COALESCE(COUNT(oi.order_item_id), 0) AS total_distinct_items,
    COALESCE(SUM(oi.quantity), 0) AS total_item_units,
    p.transaction_reference,
    p.payment_status AS actual_payment_status
FROM `orders` o
INNER JOIN `users` u ON o.user_id = u.user_id
LEFT JOIN `order_items` oi ON o.order_id = oi.order_id
LEFT JOIN `payments` p ON o.order_id = p.order_id
GROUP BY 
    o.order_id, 
    o.order_number, 
    o.order_date, 
    o.order_status, 
    o.subtotal, 
    o.delivery_fee, 
    o.total_amount, 
    o.payment_method, 
    o.payment_status, 
    o.delivery_address_text, 
    o.special_instructions, 
    u.user_id, 
    u.full_name, 
    u.email, 
    u.phone,
    p.transaction_reference,
    p.payment_status;

CREATE OR REPLACE VIEW `sales_summary_view` AS
SELECT 
    DATE(o.order_date) AS sales_date,
    COUNT(o.order_id) AS total_orders,
    SUM(CASE WHEN o.order_status != 'Cancelled' THEN 1 ELSE 0 END) AS active_orders,
    SUM(CASE WHEN o.order_status = 'Delivered' THEN 1 ELSE 0 END) AS delivered_orders,
    SUM(CASE WHEN o.order_status = 'Cancelled' THEN 1 ELSE 0 END) AS cancelled_orders,
    ROUND(SUM(CASE WHEN o.order_status != 'Cancelled' THEN o.total_amount ELSE 0 END), 2) AS net_revenue,
    ROUND(AVG(CASE WHEN o.order_status != 'Cancelled' THEN o.total_amount ELSE NULL END), 2) AS average_order_value
FROM `orders` o
GROUP BY DATE(o.order_date)
ORDER BY sales_date DESC;

CREATE OR REPLACE VIEW `top_selling_foods_view` AS
SELECT 
    f.food_id,
    f.food_name,
    c.category_name,
    f.price AS current_price,
    COALESCE(SUM(oi.quantity), 0) AS total_quantity_sold,
    COALESCE(ROUND(SUM(oi.line_total), 2), 0.00) AS total_revenue_generated
FROM `food_items` f
INNER JOIN `categories` c ON f.category_id = c.category_id
LEFT JOIN `order_items` oi ON f.food_id = oi.food_id
LEFT JOIN `orders` o ON oi.order_id = o.order_id AND o.order_status != 'Cancelled'
GROUP BY f.food_id, f.food_name, c.category_name, f.price
ORDER BY total_quantity_sold DESC;

-- =====================================================================
-- SECTION 3: DATABASE TRIGGERS
-- =====================================================================

DELIMITER $$

CREATE TRIGGER `trg_before_order_item_insert`
BEFORE INSERT ON `order_items`
FOR EACH ROW
BEGIN
    IF NEW.quantity <= 0 THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Order item quantity must be at least 1';
    END IF;

    IF NEW.unit_price <= 0 THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Order item unit price must be greater than zero';
    END IF;

    SET NEW.line_total = ROUND(NEW.quantity * NEW.unit_price, 2);
END$$

CREATE TRIGGER `trg_after_order_insert`
AFTER INSERT ON `orders`
FOR EACH ROW
BEGIN
    INSERT INTO `order_status_history` (
        `order_id`, 
        `old_status`, 
        `new_status`, 
        `changed_at`, 
        `notes`
    ) VALUES (
        NEW.order_id, 
        NULL, 
        NEW.order_status, 
        NOW(), 
        CONCAT('Order initialized via ', NEW.payment_method)
    );
END$$

CREATE TRIGGER `trg_after_order_status_update`
AFTER UPDATE ON `orders`
FOR EACH ROW
BEGIN
    IF OLD.order_status <> NEW.order_status THEN
        INSERT INTO `order_status_history` (
            `order_id`, 
            `old_status`, 
            `new_status`, 
            `changed_at`, 
            `notes`
        ) VALUES (
            NEW.order_id, 
            OLD.order_status, 
            NEW.order_status, 
            NOW(), 
            CONCAT('Status updated from ', OLD.order_status, ' to ', NEW.order_status)
        );
    END IF;
END$$

-- =====================================================================
-- SECTION 4: STORED PROCEDURES
-- =====================================================================

CREATE PROCEDURE `PlaceOrder`(
    IN p_user_id INT,
    IN p_order_number VARCHAR(30),
    IN p_address_text TEXT,
    IN p_subtotal DECIMAL(10,2),
    IN p_delivery_fee DECIMAL(10,2),
    IN p_total_amount DECIMAL(10,2),
    IN p_payment_method VARCHAR(50),
    IN p_payment_status VARCHAR(20),
    IN p_instructions TEXT,
    IN p_tx_ref VARCHAR(100),
    OUT p_new_order_id INT
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    INSERT INTO `orders` (
        `order_number`, 
        `user_id`, 
        `delivery_address_text`, 
        `order_date`, 
        `subtotal`, 
        `delivery_fee`, 
        `total_amount`, 
        `payment_method`, 
        `payment_status`, 
        `order_status`, 
        `special_instructions`
    ) VALUES (
        p_order_number,
        p_user_id,
        p_address_text,
        NOW(),
        p_subtotal,
        p_delivery_fee,
        p_total_amount,
        p_payment_method,
        p_payment_status,
        'Pending',
        p_instructions
    );

    SET p_new_order_id = LAST_INSERT_ID();

    INSERT INTO `payments` (
        `order_id`,
        `payment_method`,
        `amount`,
        `payment_status`,
        `transaction_reference`,
        `created_at`
    ) VALUES (
        p_new_order_id,
        p_payment_method,
        p_total_amount,
        IF(p_payment_status = 'Paid', 'Completed', 'Pending'),
        p_tx_ref,
        NOW()
    );

    COMMIT;
END$$

CREATE PROCEDURE `UpdateOrderStatus`(
    IN p_order_id INT,
    IN p_new_status VARCHAR(50),
    IN p_payment_status VARCHAR(20)
)
BEGIN
    DECLARE v_current_status VARCHAR(50);
    
    SELECT `order_status` INTO v_current_status 
    FROM `orders` 
    WHERE `order_id` = p_order_id;

    IF v_current_status IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Order not found';
    END IF;

    IF v_current_status IN ('Delivered', 'Cancelled') AND p_new_status != v_current_status THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot modify finalized or cancelled orders';
    END IF;

    UPDATE `orders`
    SET 
        `order_status` = p_new_status,
        `payment_status` = CASE 
            WHEN p_payment_status IS NOT NULL AND p_payment_status != '' THEN p_payment_status 
            WHEN p_new_status = 'Delivered' AND `payment_method` = 'Cash on Delivery' THEN 'Paid'
            ELSE `payment_status` 
        END
    WHERE `order_id` = p_order_id;

    IF p_new_status = 'Delivered' THEN
        UPDATE `payments`
        SET `payment_status` = 'Completed'
        WHERE `order_id` = p_order_id;
    END IF;
END$$

CREATE PROCEDURE `GetCustomerOrderStats`(
    IN p_user_id INT,
    OUT p_total_orders INT,
    OUT p_total_spent DECIMAL(10,2),
    OUT p_last_order_date DATETIME
)
BEGIN
    SELECT 
        COUNT(order_id),
        COALESCE(SUM(CASE WHEN order_status != 'Cancelled' THEN total_amount ELSE 0 END), 0.00),
        MAX(order_date)
    INTO 
        p_total_orders,
        p_total_spent,
        p_last_order_date
    FROM `orders`
    WHERE `user_id` = p_user_id;
END$$

DELIMITER ;

-- =====================================================================
-- SECTION 5: REALISTIC SAMPLE SEED DATA
-- =====================================================================

-- 1. USERS
INSERT INTO `users` (`user_id`, `full_name`, `email`, `phone`, `password_hash`, `role`, `created_at`) VALUES
(1, 'Administrator', 'admin@savorbite.com', '+1 (555) 019-2834', '$2y$10$U8yI86u8o.7UuH4eH4eFRe5L0w1Z5mXqBqG1I.iQ3f6T6tW1Y7xme', 'admin', '2026-09-01 10:00:00'),
(2, 'John Doe', 'john@example.com', '+1 (555) 432-8765', '$2y$10$zS39GZgO1u6qE5vVqY6jAeG7EaW0oK.Q5h2X8oN4mP1jK9lU8y1uO', 'customer', '2026-09-15 12:30:00'),
(3, 'Sarah Jenkins', 'sarah.j@example.com', '+1 (555) 789-1234', '$2y$10$zS39GZgO1u6qE5vVqY6jAeG7EaW0oK.Q5h2X8oN4mP1jK9lU8y1uO', 'customer', '2026-09-20 14:15:00');

-- 2. ADDRESSES
INSERT INTO `addresses` (`address_id`, `user_id`, `address_label`, `address_line`, `city`, `postal_code`, `is_default`, `created_at`) VALUES
(1, 2, 'Home', '742 Evergreen Terrace, Apt 4B', 'Springfield', '97477', 1, '2026-09-15 12:35:00'),
(2, 2, 'Work', '404 Silicon Parkway, Suite 300', 'Springfield', '97401', 0, '2026-09-18 09:10:00'),
(3, 3, 'Apartment', '120 Ocean View Drive', 'Springfield', '97402', 1, '2026-09-20 14:20:00');

-- 3. CATEGORIES (6 Categories)
INSERT INTO `categories` (`category_id`, `category_name`, `description`, `image_url`, `created_at`) VALUES
(1, 'Artisan Pizza', 'Stone-baked thin crust and rustic Neapolitan style pizzas topped with fresh mozzarella and basil.', 'https://images.unsplash.com/photo-1513104890138-7c749659a591?w=600&auto=format&fit=crop&q=80', '2026-09-01 10:00:00'),
(2, 'Gourmet Burgers', 'Flame-grilled prime angus beef and crispy chicken patties served in toasted brioche buns.', 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=600&auto=format&fit=crop&q=80', '2026-09-01 10:00:00'),
(3, 'Indian Specialties', 'Fragrant curries, rich slow-simmered biryanis, and aromatic spiced clay-oven delicacies.', 'https://images.unsplash.com/photo-1585937421612-70a008356fbe?w=600&auto=format&fit=crop&q=80', '2026-09-01 10:00:00'),
(4, 'Pan-Asian & Bowls', 'Wok-tossed noodles, steaming noodle soups, crisp dumplings, and vibrant poke bowls.', 'https://images.unsplash.com/photo-1569718212165-3a8278d5f624?w=600&auto=format&fit=crop&q=80', '2026-09-01 10:00:00'),
(5, 'Desserts & Sweets', 'Decadent molten chocolate cakes, artisanal cheesecakes, and chilled creamy desserts.', 'https://images.unsplash.com/photo-1551024709-8f23befc6f87?w=600&auto=format&fit=crop&q=80', '2026-09-01 10:00:00'),
(6, 'Craft Beverages', 'Hand-crafted lemonades, chilled iced teas, botanical sodas, and tropical smoothies.', 'https://images.unsplash.com/photo-1513558161293-cdaf765ed2fd?w=600&auto=format&fit=crop&q=80', '2026-09-01 10:00:00');

-- 4. FOOD ITEMS (24 items across 6 categories)
INSERT INTO `food_items` (`food_id`, `category_id`, `food_name`, `description`, `price`, `image_path`, `is_available`, `is_popular`, `created_at`) VALUES
-- Pizzas
(1, 1, 'Classic Margherita Rustica', 'San Marzano tomato sauce, fresh buffalo mozzarella, fragrant sweet basil, extra virgin olive oil.', 199.00, 'https://images.unsplash.com/photo-1604382355076-af4b0eb60143?w=600&auto=format&fit=crop&q=80', 1, 1, '2026-09-01 10:00:00'),
(2, 1, 'Truffle Mushroom Meltdown', 'Wood-fired dough, wild cremini mushrooms, fontina cheese, white truffle emulsion, fresh thyme.', 249.00, 'https://images.unsplash.com/photo-1574071318508-1cdbab80d002?w=600&auto=format&fit=crop&q=80', 1, 1, '2026-09-01 10:00:00'),
(3, 1, 'Diavola Hot Pepperoni', 'Spicy dry-cured pepperoni, crushed calabrian chili flakes, roasted red bell peppers, organic mozzarella.', 279.00, 'https://images.unsplash.com/photo-1628840042765-356cda07504e?w=600&auto=format&fit=crop&q=80', 1, 0, '2026-09-01 10:00:00'),
(4, 1, 'Four Cheese Quattro Formaggi', 'A rich blend of creamy gorgonzola, aged parmesan, smoked provolone, and fresh mozzarella.', 269.00, 'https://images.unsplash.com/photo-1565299624946-b28f40a0ae38?w=600&auto=format&fit=crop&q=80', 1, 0, '2026-09-01 10:00:00'),

-- Gourmet Burgers
(5, 2, 'The SavorBite Smokehouse', '100% prime angus beef patty, smoked bacon, aged cheddar, beer-battered onion ring, chipotle BBQ.', 189.00, 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=600&auto=format&fit=crop&q=80', 1, 1, '2026-09-01 10:00:00'),
(6, 2, 'Crispy Hot Buttermilk Chicken', 'Southern fried chicken breast dipped in fiery honey sauce, dill pickles, garlic mayo on brioche.', 179.00, 'https://images.unsplash.com/photo-1625813506062-0aeb1d7a094b?w=600&auto=format&fit=crop&q=80', 1, 1, '2026-09-01 10:00:00'),
(7, 2, 'Double Truffle Smash Burger', 'Two thin-seared beef smash patties, caramelized shallots, melted swiss, truffle aioli, baby arugula.', 219.00, 'https://images.unsplash.com/photo-1586190848861-99aa4a171e90?w=600&auto=format&fit=crop&q=80', 1, 0, '2026-09-01 10:00:00'),
(8, 2, 'Garden Harvest Vegan Burger', 'Housemade black bean and quinoa patty, smashed avocado, heirloom tomato, vegan cashew mayo.', 99.00, 'https://images.unsplash.com/photo-1520072959219-c595dc870360?w=600&auto=format&fit=crop&q=80', 1, 0, '2026-09-01 10:00:00'),

-- Indian Specialties
(9, 3, 'Old Delhi Butter Chicken', 'Tender tandoor-roasted chicken simmered in rich creamy tomato gravy finished with fenugreek butter.', 289.00, 'https://images.unsplash.com/photo-1588166524941-3bf61a9c41db?w=600&auto=format&fit=crop&q=80', 1, 1, '2026-09-01 10:00:00'),
(10, 3, 'Hyderabadi Dum Biryani', 'Long-grain basmati rice layered with aromatic saffron, marinated spiced chicken, fried onions & mint.', 299.00, 'https://images.unsplash.com/photo-1563379091339-03b21ab4a4f8?w=600&auto=format&fit=crop&q=80', 1, 1, '2026-09-01 10:00:00'),
(11, 3, 'Paneer Tikka Masala', 'Charcoal-grilled cottage cheese cubes cooked in spicy spiced onion-tomato gravy with bell peppers.', 249.00, 'https://images.unsplash.com/photo-1631452180519-c014fe946bc7?w=600&auto=format&fit=crop&q=80', 1, 0, '2026-09-01 10:00:00'),
(12, 3, 'Garlic Butter Naan Basket', 'Three freshly baked clay-oven leavened flatbreads brushed with roasted garlic butter and cilantro.', 79.00, 'https://images.unsplash.com/photo-1626074353765-517a681e40be?w=600&auto=format&fit=crop&q=80', 1, 0, '2026-09-01 10:00:00'),

-- Pan-Asian & Bowls
(13, 4, 'Tonkotsu Chashu Ramen', '24-hour rich pork bone broth, springy noodles, braised chashu pork belly, ajitsuke egg, nori & scallions.', 259.00, 'https://images.unsplash.com/photo-1569718212165-3a8278d5f624?w=600&auto=format&fit=crop&q=80', 1, 1, '2026-09-01 10:00:00'),
(14, 4, 'Bangkok Pad Thai', 'Stir-fried rice noodles with tiger prawns, crispy tofu, bean sprouts, crushed peanuts, tamarind glaze.', 229.00, 'https://images.unsplash.com/photo-1559314809-0d155014e29e?w=600&auto=format&fit=crop&q=80', 1, 0, '2026-09-01 10:00:00'),
(15, 4, 'Crispy Gyoza Dumplings (6 pcs)', 'Pan-seared chicken and scallion potstickers served with sesame ginger soy dipping sauce.', 159.00, 'https://images.unsplash.com/photo-1496116218417-1a781b1c416c?w=600&auto=format&fit=crop&q=80', 1, 0, '2026-09-01 10:00:00'),
(16, 4, 'Salmon Sashimi Poke Bowl', 'Fresh Atlantic salmon cubes, sushi rice, edamame, avocado, cucumber, pickled ginger, spicy ponzu.', 299.00, 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=600&auto=format&fit=crop&q=80', 1, 1, '2026-09-01 10:00:00'),

-- Desserts & Sweets
(17, 5, 'Belgian Molten Lava Cake', 'Warm dark chocolate souffle with a molten flowing center, served with Madagascan vanilla ice cream.', 149.00, 'https://images.unsplash.com/photo-1606313564200-e75d5e30476c?w=600&auto=format&fit=crop&q=80', 1, 1, '2026-09-01 10:00:00'),
(18, 5, 'New York Berry Cheesecake', 'Silky baked cream cheese cake with buttery graham cracker crust topped with wild berry compote.', 169.00, 'https://images.unsplash.com/photo-1533134242443-d4fd215305ad?w=600&auto=format&fit=crop&q=80', 1, 0, '2026-09-01 10:00:00'),
(19, 5, 'Espresso Classic Tiramisu', 'Savoiardi ladyfingers soaked in dark espresso and marsala, layered with whipped mascarpone cream.', 159.00, 'https://images.unsplash.com/photo-1571877227200-a0d98ea607e9?w=600&auto=format&fit=crop&q=80', 1, 0, '2026-09-01 10:00:00'),
(20, 5, 'Warm Churros Con Chocolate', 'Crispy cinnamon sugar Spanish churros accompanied by thick Spanish hot dipping chocolate.', 139.00, 'https://images.unsplash.com/photo-1624300629298-e9de39c13be5?w=600&auto=format&fit=crop&q=80', 1, 0, '2026-09-01 10:00:00'),

-- Craft Beverages
(21, 6, 'Passionfruit Mint Cooler', 'Crushed fresh passionfruit pulp, garden mint leaves, lime juice, sparkling mineral water.', 89.00, 'https://images.unsplash.com/photo-1513558161293-cdaf765ed2fd?w=600&auto=format&fit=crop&q=80', 1, 1, '2026-09-01 10:00:00'),
(22, 6, 'Cold Brew Salted Caramel', 'Single-origin 18-hour cold brewed arabica coffee, oat milk, artisanal salted caramel drizzle.', 99.00, 'https://images.unsplash.com/photo-1517701604599-bb29b565090c?w=600&auto=format&fit=crop&q=80', 1, 0, '2026-09-01 10:00:00'),
(23, 6, 'Mango Cardamom Lassi', 'Velvety Greek yogurt blended with sweet Alphonso mangoes and a touch of crushed green cardamom.', 89.00, 'https://images.unsplash.com/photo-1546883492-945781a78887?w=600&auto=format&fit=crop&q=80', 1, 0, '2026-09-01 10:00:00'),
(24, 6, 'Hibiscus Berry Iced Tea', 'Steeped Egyptian hibiscus flowers, crushed blackberries, organic agave, served over crushed ice.', 79.00, 'https://images.unsplash.com/photo-1556679343-c7306c1976bc?w=600&auto=format&fit=crop&q=80', 1, 0, '2026-09-01 10:00:00');

-- 5. CART ITEMS (Persisted demo cart for customer 2)
INSERT INTO `cart_items` (`cart_item_id`, `user_id`, `food_id`, `quantity`, `added_at`) VALUES
(1, 2, 1, 2, '2026-10-10 18:20:00'),
(2, 2, 5, 1, '2026-10-10 18:22:00'),
(3, 2, 21, 2, '2026-10-10 18:25:00');

-- 6. ORDERS, ORDER ITEMS, & PAYMENTS
INSERT INTO `orders` (`order_id`, `order_number`, `user_id`, `delivery_address_text`, `order_date`, `subtotal`, `delivery_fee`, `total_amount`, `payment_method`, `payment_status`, `order_status`, `special_instructions`) VALUES
(1, 'ORD-20261011-8192', 2, '742 Evergreen Terrace, Apt 4B, Springfield, 97477', '2026-10-11 19:15:00', 467.00, 49.00, 516.00, 'Demo Online Payment', 'Paid', 'Delivered', 'Please ring the doorbell and leave at door.'),
(2, 'ORD-20261012-9014', 3, '120 Ocean View Drive, Springfield, 97402', '2026-10-12 13:00:00', 825.00, 49.00, 874.00, 'Cash on Delivery', 'Pending', 'Preparing', 'Extra spicy on the curries please.'),
(3, 'ORD-20261013-1102', 2, '742 Evergreen Terrace, Apt 4B, Springfield, 97477', '2026-10-13 20:45:00', 407.00, 49.00, 456.00, 'Demo Online Payment', 'Paid', 'Pending', 'Please include extra napkins.');

-- Order 1 items
INSERT INTO `order_items` (`order_item_id`, `order_id`, `food_id`, `quantity`, `unit_price`, `line_total`) VALUES
(1, 1, 1, 1, 199.00, 199.00),
(2, 1, 5, 1, 189.00, 189.00),
(3, 1, 24, 1, 79.00, 79.00);

-- Order 2 items
INSERT INTO `order_items` (`order_item_id`, `order_id`, `food_id`, `quantity`, `unit_price`, `line_total`) VALUES
(4, 2, 9, 2, 289.00, 578.00),
(5, 2, 12, 2, 79.00, 158.00),
(6, 2, 21, 1, 89.00, 89.00);

-- Order 3 items
INSERT INTO `order_items` (`order_item_id`, `order_id`, `food_id`, `quantity`, `unit_price`, `line_total`) VALUES
(7, 3, 6, 1, 179.00, 179.00),
(8, 3, 17, 1, 149.00, 149.00),
(9, 3, 12, 1, 79.00, 79.00);

-- Payments
INSERT INTO `payments` (`payment_id`, `order_id`, `payment_method`, `amount`, `payment_status`, `transaction_reference`, `created_at`) VALUES
(1, 1, 'Demo Online Payment', 516.00, 'Completed', 'TXN-DEMO-20261011-8192-A9F2', '2026-10-11 19:15:30'),
(2, 2, 'Cash on Delivery', 874.00, 'Pending', 'TXN-COD-20261012-9014-C4B1', '2026-10-12 13:00:15'),
(3, 3, 'Demo Online Payment', 456.00, 'Completed', 'TXN-DEMO-20261013-1102-E88D', '2026-10-13 20:45:22');

-- Order Status History Records
INSERT INTO `order_status_history` (`history_id`, `order_id`, `old_status`, `new_status`, `changed_at`, `notes`) VALUES
(1, 1, NULL, 'Pending', '2026-10-11 19:15:00', 'Order placed successfully'),
(2, 1, 'Pending', 'Preparing', '2026-10-11 19:22:00', 'Kitchen accepted order'),
(3, 1, 'Preparing', 'Out for Delivery', '2026-10-11 19:45:00', 'Dispatched with courier'),
(4, 1, 'Out for Delivery', 'Delivered', '2026-10-11 20:10:00', 'Delivered to customer'),
(5, 2, NULL, 'Pending', '2026-10-12 13:00:00', 'Order placed via COD'),
(6, 2, 'Pending', 'Preparing', '2026-10-12 13:08:00', 'Kitchen preparing order'),
(7, 3, NULL, 'Pending', '2026-10-13 20:45:00', 'Order placed and paid');