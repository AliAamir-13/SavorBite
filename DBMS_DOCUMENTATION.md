# SavorBite - DBMS Concepts Implementation Document

This technical document outlines the database architecture, schema normalization decisions, and implementation of core Database Management System (DBMS) concepts in the **SavorBite Online Food Ordering System**.

---

## 1. Database Normalization (3NF Implementation)

The relational schema is strictly normalized up to **Third Normal Form (3NF)** to eliminate anomalies and redundant duplication.

| Normal Form | Rule Enforced | Implementation in SavorBite |
| :--- | :--- | :--- |
| **First Normal Form (1NF)** | Each column contains atomic (indivisible) values; each record is identified by a primary key. | Multi-valued delivery addresses are separated from `users` into the `addresses` table. Multiple ordered items are stored in individual rows in `order_items` rather than comma-separated lists. |
| **Second Normal Form (2NF)** | In 1NF and every non-key attribute is fully functionally dependent on the primary key. | Composite table `cart_items` depends on both `(user_id, food_id)`. Category descriptions are separated from `food_items` so menu items do not repeat category metadata. |
| **Third Normal Form (3NF)** | In 2NF and no transitive functional dependencies exist ($X \to Y$ where $Y \to Z$). | Customer contact details exist only in `users`. Historical delivery addresses are snapshotted into `orders.delivery_address_text` to prevent external dependency on mutable address records. |

---

## 2. Entity Relationships & Foreign Key Strategy

```
  [users] 1 ────< N [addresses]
     │
     │ 1
     ▼ N
  [orders] 1 ────< N [order_items] >──── N [food_items] >──── 1 [categories]
     │                       ▲
     │ 1                     │ 1
     ▼ 1                     │
  [payments]              [cart_items] >──── N [users]
     │
     ▼ 1:N
  [order_status_history]
```

### Foreign Key Constraint Rules:
- **`addresses.user_id` -> `users.user_id`**: `ON DELETE CASCADE`. If a user deletes their account, secondary address entries are safely purged.
- **`food_items.category_id` -> `categories.category_id`**: `ON DELETE RESTRICT`. Prevents accidental deletion of categories that have active menu items.
- **`order_items.order_id` -> `orders.order_id`**: `ON DELETE CASCADE`. Order line items belong strictly to parent order records.
- **`order_items.food_id` -> `food_items.food_id`**: `ON DELETE RESTRICT`. Historical food items cannot be deleted if referenced in previous orders; instead, `food_items.is_available = 0` is used.
- **`payments.order_id` -> `orders.order_id`**: `ON DELETE CASCADE`. 1:1 financial record tied directly to order.

---

## 3. SQL Joins Implemented

1. **`INNER JOIN` (Menu Catalog & Order Details)**
   - Used in `food_menu_view`: joins `food_items` with `categories` on `category_id`.
   - Used in `order-details.php`: joins `order_items` with `food_items` and `categories` to generate itemized receipts.
2. **`LEFT JOIN` (Aggregated Sales & Cart)**
   - Used in `sales_summary_view` and `admin/dashboard.php`: joins `food_items` with `order_items` and `orders` to compute total volume sold even for items that have not yet been purchased (`COALESCE(SUM(oi.quantity), 0)`).
   - Used in `admin/users.php`: retrieves all registered customers alongside lifetime order count and total spend.

---

## 4. SQL Aggregate Functions & Grouping

- **`COUNT()`**: Counts active orders by status, registered customers, and distinct cart items.
- **`SUM()`**: Computes daily gross revenue in `sales_summary_view` and total sales per food category.
- **`AVG()`**: Computes Average Order Value (AOV) and average menu price.
- **`MIN()` / `MAX()`**: Identifies latest order date for customers and pricing ranges.
- **`GROUP BY` & `HAVING`**:
  ```sql
  SELECT category_id, COUNT(food_id) as total_items, AVG(price) as avg_price
  FROM food_items
  GROUP BY category_id
  HAVING total_items >= 3;
  ```

---

## 5. Subqueries

1. **Scalar Subquery (Comparative Analytics in `admin/reports.php`)**:
   Retrieves dishes priced above the catalog-wide average:
   ```sql
   SELECT food_name, price
   FROM food_items
   WHERE price > (SELECT AVG(price) FROM food_items);
   ```
2. **Correlated Subquery (Category Name Lookup)**:
   ```sql
   SELECT food_name, price, 
          (SELECT category_name FROM categories c WHERE c.category_id = f.category_id) as category
   FROM food_items f;
   ```
3. **Subquery with `NOT IN` (Unordered Food Identification)**:
   ```sql
   SELECT food_name FROM food_items 
   WHERE food_id NOT IN (SELECT DISTINCT food_id FROM order_items);
   ```

---

## 6. SQL Views

| View Name | Primary Source Tables | Purpose |
| :--- | :--- | :--- |
| **`food_menu_view`** | `food_items`, `categories` | Abstracts category IDs and joins for clean menu browsing and card display. |
| **`order_summary_view`** | `orders`, `users`, `order_items`, `payments` | Pre-computes order customer names, item counts, and payment references for Admin and customer lookup. |
| **`sales_summary_view`** | `orders` | Daily grouped financial metrics: net revenue, delivered order count, average ticket size. |
| **`top_selling_foods_view`**| `food_items`, `categories`, `order_items`, `orders` | Ranks dishes by volume and revenue generated. |

---

## 7. Stored Procedures

1. **`PlaceOrder` (`database/procedures.sql`)**:
   - Parameters: `p_user_id`, `p_order_number`, `p_address_text`, `p_subtotal`, `p_delivery_fee`, `p_total_amount`, `p_payment_method`, `p_payment_status`, `p_instructions`, `p_tx_ref`, `OUT p_new_order_id`.
   - Handles the atomic creation of the order header and initial payment log record.
2. **`UpdateOrderStatus` (`database/procedures.sql`)**:
   - Parameters: `p_order_id`, `p_new_status`, `p_payment_status`.
   - Validates that finalized orders (`Delivered` or `Cancelled`) cannot be illegally re-opened, updates payment records when COD orders are delivered, and triggers status change logging.
3. **`GetCustomerOrderStats` (`database/procedures.sql`)**:
   - Parameters: `IN p_user_id`, `OUT p_total_orders`, `OUT p_total_spent`, `OUT p_last_order_date`.
   - Demonstrates OUT parameters and procedural aggregation.

---

## 8. Database Triggers

1. **`trg_before_order_item_insert` (BEFORE INSERT on `order_items`)**:
   - Validates `NEW.quantity > 0` and `NEW.unit_price > 0`.
   - Automatically sets and enforces `NEW.line_total = ROUND(NEW.quantity * NEW.unit_price, 2)`.
2. **`trg_after_order_insert` (AFTER INSERT on `orders`)**:
   - Fires automatically whenever a new order is inserted, logging the initial state into `order_status_history`.
3. **`trg_after_order_status_update` (AFTER UPDATE on `orders`)**:
   - Detects `IF OLD.order_status <> NEW.order_status` and writes an audit record into `order_status_history` capturing `old_status`, `new_status`, and `changed_at`.

---

## 9. Transactions and ACID Properties

The checkout workflow in `includes/functions.php` (`execute_order_placement()`) guarantees complete ACID compliance:

```
[Start PDO Transaction]
  │
  ├─ 1. Re-query food_items table FOR UPDATE to lock rows and verify real-time price & availability
  ├─ 2. Insert into orders table (captures order header and address snapshot)
  ├─ 3. Loop and insert each order_items record (freezes unit_price at time of purchase)
  ├─ 4. Insert simulated payment record into payments table
  ├─ 5. Delete corresponding records from cart_items
  │
[Commit Transaction] (or Rollback on any Exception / Constraint Failure)
```

- **Atomicity:** All operations succeed together, or the entire order is rolled back if an item is out of stock.
- **Consistency:** Database check constraints (`price > 0`, `quantity > 0`) and foreign keys prevent invalid states.
- **Isolation:** Uses InnoDB row-level locking during checkout to prevent concurrency races.
- **Durability:** Writes committed to InnoDB write-ahead redo logs and persistent storage.

---

## 10. Indexing Strategy

Indexes created in `schema.sql`:
- `idx_users_email` (UNIQUE): Accelerates login queries.
- `idx_food_category_id`: Speeds up category menu filtering.
- `idx_food_name`: Accelerates customer search queries.
- `idx_food_available`: Quick filtering of in-stock dishes.
- `idx_orders_user_id`: Optimizes `my-orders.php` customer history queries.
- `idx_orders_order_date`: Speeds up daily revenue aggregation in `sales_summary_view`.
- `idx_orders_status`: Fast order filtering in the admin panel.
- `unique_user_food` on `cart_items`: Enforces one cart row per dish per user and allows `ON DUPLICATE KEY UPDATE`.
