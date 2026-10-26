-- =====================================================================
-- DATABASE: food_ordering_system
-- Stored Procedures Implementation
-- Demonstrates: Procedural SQL, IN/OUT Parameters, Control Flow, Transactions
-- =====================================================================

USE `food_ordering_system`;

DROP PROCEDURE IF EXISTS `PlaceOrder`;
DROP PROCEDURE IF EXISTS `UpdateOrderStatus`;
DROP PROCEDURE IF EXISTS `GetCustomerOrderStats`;

DELIMITER $$

-- ---------------------------------------------------------------------
-- 1. PROCEDURE: PlaceOrder
-- Creates an order and its associated initial payment record atomically.
-- Parameters:
--   IN p_user_id INT
--   IN p_order_number VARCHAR(30)
--   IN p_address_text TEXT
--   IN p_subtotal DECIMAL(10,2)
--   IN p_delivery_fee DECIMAL(10,2)
--   IN p_total_amount DECIMAL(10,2)
--   IN p_payment_method VARCHAR(50)
--   IN p_payment_status VARCHAR(20)
--   IN p_instructions TEXT
--   IN p_tx_ref VARCHAR(100)
--   OUT p_new_order_id INT
-- ---------------------------------------------------------------------
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
        -- Rollback if any SQL exception occurs
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    -- Insert into Orders table
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

    -- Insert associated simulated payment record
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

-- ---------------------------------------------------------------------
-- 2. PROCEDURE: UpdateOrderStatus
-- Validates permitted order status transitions and updates order.
-- Triggers will automatically capture this update into order_status_history.
-- ---------------------------------------------------------------------
CREATE PROCEDURE `UpdateOrderStatus`(
    IN p_order_id INT,
    IN p_new_status VARCHAR(50),
    IN p_payment_status VARCHAR(20)
)
BEGIN
    DECLARE v_current_status VARCHAR(50);
    
    -- Retrieve current status
    SELECT `order_status` INTO v_current_status 
    FROM `orders` 
    WHERE `order_id` = p_order_id;

    IF v_current_status IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Order not found';
    END IF;

    -- Disallow updating delivered or cancelled orders
    IF v_current_status IN ('Delivered', 'Cancelled') AND p_new_status != v_current_status THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot modify finalized or cancelled orders';
    END IF;

    -- Perform status update
    UPDATE `orders`
    SET 
        `order_status` = p_new_status,
        `payment_status` = CASE 
            WHEN p_payment_status IS NOT NULL AND p_payment_status != '' THEN p_payment_status 
            WHEN p_new_status = 'Delivered' AND `payment_method` = 'Cash on Delivery' THEN 'Paid'
            ELSE `payment_status` 
        END
    WHERE `order_id` = p_order_id;

    -- Sync payment record if order marked delivered with COD
    IF p_new_status = 'Delivered' THEN
        UPDATE `payments`
        SET `payment_status` = 'Completed'
        WHERE `order_id` = p_order_id;
    END IF;
END$$

-- ---------------------------------------------------------------------
-- 3. PROCEDURE: GetCustomerOrderStats
-- Demonstrates OUT parameters and aggregate functions in a procedure.
-- ---------------------------------------------------------------------
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
