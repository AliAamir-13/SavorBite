# SavorBite - DBMS College Viva Questions & Demonstration SQL Queries

This guide provides the exact SQL queries to run during your college DBMS practical evaluation or viva demonstration to prove each database management concept.

---

## 1. SQL Joins Demonstrations

### Q1: "Show me a query displaying all ordered items along with customer details and food names."
```sql
SELECT 
    o.order_number,
    u.full_name AS customer_name,
    f.food_name,
    c.category_name,
    oi.quantity,
    oi.unit_price,
    oi.line_total,
    o.order_status
FROM orders o
INNER JOIN users u ON o.user_id = u.user_id
INNER JOIN order_items oi ON o.order_id = oi.order_id
INNER JOIN food_items f ON oi.food_id = f.food_id
INNER JOIN categories c ON f.category_id = c.category_id
ORDER BY o.order_date DESC;
```

### Q2: "Show all food items, even those that have never been ordered (LEFT JOIN demonstration)."
```sql
SELECT 
    f.food_id,
    f.food_name,
    c.category_name,
    COALESCE(SUM(oi.quantity), 0) AS times_ordered
FROM food_items f
INNER JOIN categories c ON f.category_id = c.category_id
LEFT JOIN order_items oi ON f.food_id = oi.food_id
GROUP BY f.food_id, f.food_name, c.category_name
ORDER BY times_ordered ASC;
```

---

## 2. Aggregate Functions & GROUP BY / HAVING

### Q3: "Calculate the total revenue and order count for each payment method."
```sql
SELECT 
    payment_method,
    COUNT(order_id) AS total_orders,
    ROUND(SUM(total_amount), 2) AS total_collected,
    ROUND(AVG(total_amount), 2) AS avg_order_value
FROM orders
WHERE order_status != 'Cancelled'
GROUP BY payment_method;
```

### Q4: "Find categories with an average dish price greater than ₹150 (Demonstrates GROUP BY with HAVING)."
```sql
SELECT 
    c.category_name,
    COUNT(f.food_id) AS total_dishes,
    ROUND(AVG(f.price), 2) AS average_price,
    MIN(f.price) AS lowest_price,
    MAX(f.price) AS highest_price
FROM categories c
INNER JOIN food_items f ON c.category_id = f.category_id
GROUP BY c.category_id, c.category_name
HAVING average_price > 150.00
ORDER BY average_price DESC;
```

---

## 3. Subqueries Demonstrations

### Q5: "Find dishes priced higher than the average dish price in the entire restaurant (Scalar Subquery)."
```sql
SELECT food_id, food_name, price
FROM food_items
WHERE price > (SELECT AVG(price) FROM food_items)
ORDER BY price DESC;
```

### Q6: "Find customers who have placed at least one order using Cash on Delivery (Correlated / IN Subquery)."
```sql
SELECT user_id, full_name, email, phone
FROM users
WHERE user_id IN (
    SELECT DISTINCT user_id 
    FROM orders 
    WHERE payment_method = 'Cash on Delivery'
);
```

### Q7: "Find the top selling dish using a nested subquery."
```sql
SELECT food_name, price 
FROM food_items
WHERE food_id = (
    SELECT food_id 
    FROM order_items 
    GROUP BY food_id 
    ORDER BY SUM(quantity) DESC 
    LIMIT 1
);
```

---

## 4. SQL Views Demonstrations

### Q8: "How does your system abstract complex joins for the menu?"
```sql
-- Query the food_menu_view created in views.sql
SELECT food_name, category_name, price, is_available 
FROM food_menu_view 
WHERE category_name = 'Artisan Pizza';
```

### Q9: "Show the daily revenue summary using the view."
```sql
-- Query the sales_summary_view
SELECT * FROM sales_summary_view;
```

---

## 5. Stored Procedures Demonstrations

### Q10: "Execute your stored procedure to update an order status and show me what happens."
```sql
-- Call the stored procedure:
CALL UpdateOrderStatus(2, 'Delivered', 'Paid');

-- Verify the order record updated:
SELECT order_id, order_status, payment_status 
FROM orders 
WHERE order_id = 2;
```

### Q11: "Demonstrate a stored procedure with OUT parameters."
```sql
-- Call GetCustomerOrderStats for customer #2
CALL GetCustomerOrderStats(2, @orders, @spent, @last_date);

-- Display the OUT variables:
SELECT @orders AS total_orders, @spent AS total_spent, @last_date AS last_ordered;
```

---

## 6. Database Triggers Demonstrations

### Q12: "Prove that your database automatically logs order status changes (Trigger audit trail)."
```sql
-- Step 1: Update an order status
UPDATE orders 
SET order_status = 'Out for Delivery' 
WHERE order_id = 2;

-- Step 2: Query the order_status_history table populated by the AFTER UPDATE trigger
SELECT * FROM order_status_history 
WHERE order_id = 2 
ORDER BY changed_at DESC;
```

### Q13: "Prove that your trigger enforces positive order item pricing."
```sql
-- Try inserting an invalid negative unit_price into order_items:
INSERT INTO order_items (order_id, food_id, quantity, unit_price, line_total)
VALUES (1, 1, 2, -10.00, -20.00);

-- Expected Result:
-- ERROR 1644 (45000): Order item unit price must be greater than zero
```

---

## 7. ACID Transactions Demonstration

### Q14: "Demonstrate how the database handles transaction rollback if something fails mid-checkout."
```sql
-- Start transaction
START TRANSACTION;

-- Step 1: Create an order
INSERT INTO orders (order_number, user_id, delivery_address_text, subtotal, delivery_fee, total_amount, payment_method, payment_status, order_status)
VALUES ('ORD-TEST-FAIL-01', 2, 'Test Address', 25.00, 3.99, 28.99, 'Cash on Delivery', 'Pending', 'Pending');

-- Step 2: Attempt to insert an invalid line item (simulating failure)
-- Intentionally trigger check constraint violation or trigger error
INSERT INTO order_items (order_id, food_id, quantity, unit_price, line_total)
VALUES (LAST_INSERT_ID(), 1, -5, 14.50, -72.50);

-- Since an error occurs, execute ROLLBACK:
ROLLBACK;

-- Verify that the test order was NOT committed:
SELECT * FROM orders WHERE order_number = 'ORD-TEST-FAIL-01';
-- Returns 0 rows (Atomic rollback verified!)
```

---

## 8. Common Viva Theoretical Questions & High-Scoring Answers

### Q: Why did you snapshot `delivery_address_text` in the `orders` table instead of just referencing `address_id`?
> **Answer:** "In real-world e-commerce and food delivery systems, customers frequently edit or delete their saved addresses. If `orders` only held a foreign key `address_id`, any future edit to that address would retroactively corrupt previous historical orders. Snapshotting the physical text at the exact moment of order placement preserves legal and financial historical auditability without violating normalization principles for active address management."

### Q: Why did you freeze `unit_price` in `order_items`?
> **Answer:** "Restaurant menu prices change dynamically over time. If `order_items` only joined with `food_items.price`, any price hike would alter the financial totals of orders placed weeks or months ago. Freezing `unit_price` in `order_items` ensures accounting immutability."

### Q: Why did you use PDO over mysqli?
> **Answer:** "PDO provides an object-oriented interface, supports prepared statements with native parameter binding to guard against SQL Injection, provides fine-grained transaction management (`beginTransaction`, `commit`, `rollBack`), and uses consistent exception handling (`PDOException`)."
