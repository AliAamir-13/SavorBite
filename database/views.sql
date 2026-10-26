-- =====================================================================
-- DATABASE: food_ordering_system
-- SQL Views Implementation
-- Demonstrates: Complex Joins, Aggregation, Abstraction, Read Security
-- =====================================================================

USE `food_ordering_system`;

-- ---------------------------------------------------------------------
-- 1. VIEW: food_menu_view
-- Joins Food_Items and Categories.
-- Provides a clean view for customer browsing without exposing raw IDs.
-- ---------------------------------------------------------------------
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

-- ---------------------------------------------------------------------
-- 2. VIEW: order_summary_view
-- Joins Orders, Users, Payments and computes item counts.
-- Ideal for Admin order management and Customer history lookup.
-- ---------------------------------------------------------------------
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

-- ---------------------------------------------------------------------
-- 3. VIEW: sales_summary_view
-- Computes aggregated sales grouped by date for business analytics.
-- Uses aggregate functions: COUNT(), SUM(), AVG().
-- ---------------------------------------------------------------------
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

-- ---------------------------------------------------------------------
-- 4. VIEW: top_selling_foods_view
-- Ranks items by order volume and revenue generated.
-- ---------------------------------------------------------------------
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
