-- =====================================================================
-- DATABASE: food_ordering_system
-- Database Triggers Implementation
-- Demonstrates: Data Integrity Enforcement, Automated Auditing, Event Handlers
-- =====================================================================

USE `food_ordering_system`;

DROP TRIGGER IF EXISTS `trg_before_order_item_insert`;
DROP TRIGGER IF EXISTS `trg_after_order_insert`;
DROP TRIGGER IF EXISTS `trg_after_order_status_update`;

DELIMITER $$

-- ---------------------------------------------------------------------
-- 1. TRIGGER: trg_before_order_item_insert
-- Fires BEFORE INSERT on order_items.
-- Enforces data integrity: guarantees quantity >= 1, unit_price > 0,
-- and automatically calculates / validates line_total.
-- ---------------------------------------------------------------------
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

    -- Automatically ensure line_total matches quantity * unit_price
    SET NEW.line_total = ROUND(NEW.quantity * NEW.unit_price, 2);
END$$

-- ---------------------------------------------------------------------
-- 2. TRIGGER: trg_after_order_insert
-- Fires AFTER INSERT on orders.
-- Automatically logs the creation of the order into order_status_history.
-- ---------------------------------------------------------------------
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

-- ---------------------------------------------------------------------
-- 3. TRIGGER: trg_after_order_status_update
-- Fires AFTER UPDATE on orders.
-- When order_status changes, records the transition in order_status_history.
-- ---------------------------------------------------------------------
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

DELIMITER ;
