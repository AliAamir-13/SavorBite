# SavorBite - Online Food Ordering System

A comprehensive, production-grade **Online Food Ordering System** developed using **HTML5, CSS3, JavaScript, PHP, and MySQL (PDO)**. This project is specifically designed for college Database Management Systems (DBMS) coursework and viva demonstrations, featuring rigorous relational modeling, 3NF normalization, ACID database transactions, views, triggers, and stored procedures while offering a modern commercial user experience.

---

## 🚀 Quick Setup Guide for XAMPP

Follow these step-by-step instructions to run the project on your local machine using XAMPP (Windows / macOS / Linux):

### Step 1: Install & Launch XAMPP
1. Download and install **XAMPP** from [apachefriends.org](https://www.apachefriends.org/) (PHP 8.0+ recommended).
2. Open the **XAMPP Control Panel**.
3. Start both the **Apache** and **MySQL** modules.

### Step 2: Copy Project Files to `htdocs`
1. Navigate to your XAMPP installation directory:
   - **Windows:** `C:\xampp\htdocs\`
   - **macOS:** `/Applications/XAMPP/xamppfiles/htdocs/`
   - **Linux:** `/opt/lampp/htdocs/`
2. Place the `food-ordering-system` folder directly into `htdocs`:
   ```
   htdocs/food-ordering-system/
   ```

### Step 3: Import the MySQL Database
1. Open your web browser and go to `http://localhost/phpmyadmin/`.
2. Click the **"New"** button in the left sidebar to create a new database.
3. Name the database **`food_ordering_system`** and set collation to `utf8mb4_unicode_ci`, then click **Create**.
4. Select the newly created database, go to the **Import** tab at the top, and import the following SQL files from the `database/` folder in order:
   - `database/schema.sql` (Creates all tables, constraints, foreign keys, and indexes)
   - `database/seed.sql` (Seeds 24 dishes, 6 categories, demo users, sample orders)
   - `database/views.sql` (Creates `food_menu_view`, `order_summary_view`, `sales_summary_view`)
   - `database/procedures.sql` (Creates `PlaceOrder`, `UpdateOrderStatus`, `GetCustomerOrderStats`)
   - `database/triggers.sql` (Creates order integrity and audit triggers)

### Step 4: Verify Database Configuration
Open `config/database.php` and ensure the database credentials match your local setup:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'food_ordering_system');
define('DB_USER', 'root');
define('DB_PASS', ''); // Default password in XAMPP is empty
```

### Step 5: Launch the Website
Open your browser and navigate to:
```
http://localhost/food-ordering-system/
```

---

## 🔑 Demo Login Credentials

For quick evaluation and testing during demonstrations:

| Account Type | Email Address | Password | Role / Access |
| :--- | :--- | :--- | :--- |
| **Administrator** | `admin@savorbite.com` | `Admin@123` | Full Admin Dashboard, Menu/Category CRUD, Order Status Updates, Sales Reports |
| **Customer** | `john@example.com` | `Customer@123` | Browsing, Persistent Cart, Checkout, Order Tracking, Cancellation |
| **Customer 2** | `sarah.j@example.com` | `Customer@123` | Multi-address support, Order history |

---

## 📂 Project Directory Structure

```
food-ordering-system/
├── index.php                 # Home page (Hero, Categories, Chef's specials)
├── menu.php                  # Dynamic menu with category filter, search, sort
├── food-details.php          # Detailed dish view, quantity selector, add to cart
├── cart.php                  # Shopping cart (DB-persisted & session fallback)
├── checkout.php              # Multi-address selector, payment methods, ACID transaction
├── order-success.php         # Order confirmation & receipt breakdown
├── my-orders.php             # Customer order history & cancellation
├── order-details.php         # Itemized order receipt & trigger status timeline
├── login.php                 # Customer & Admin sign-in (password_verify)
├── register.php              # Customer registration with address creation
├── logout.php                # Session destruction & sign out
├── config/
│   └── database.php          # PDO connection, UTF-8, Native prepared statements
├── includes/
│   ├── header.php            # Storefront header & navigation
│   ├── footer.php            # Storefront footer & operating hours
│   ├── auth.php              # Session auth, role checks, access guards
│   ├── functions.php         # Business logic, cart operations, queries
│   └── csrf.php              # CSRF token generation & validation
├── admin/
│   ├── dashboard.php         # Operational KPIs, recent orders, top dishes
│   ├── foods.php             # Menu items CRUD & instant stock toggle
│   ├── categories.php        # Cuisine categories management & dish counts
│   ├── orders.php            # Order fulfillment lifecycle & stored procedure calls
│   ├── users.php             # Customer directory with aggregated spend metrics
│   ├── reports.php           # Daily sales analytics & subquery demonstrations
│   ├── admin_header.php      # Admin sidebar navigation & layout
│   └── admin_footer.php      # Admin layout closure
├── assets/
│   ├── css/
│   │   ├── style.css         # Customer storefront styling (Warm orange/charcoal)
│   │   └── admin.css         # Admin operational dashboard styling
│   └── js/
│       └── script.js         # Client-side UI interactions
├── database/
│   ├── schema.sql            # Table DDL, constraints, foreign keys, indexes
│   ├── seed.sql              # Realistic sample dataset
│   ├── views.sql             # SQL Views implementation
│   ├── procedures.sql        # Stored procedures implementation
│   └── triggers.sql          # Triggers implementation
├── DBMS_DOCUMENTATION.md     # Full academic DBMS concept mapping
├── VIVA_QUESTIONS_SQL.md     # Viva demo queries and conceptual Q&A
└── README.md                 # Setup and execution guide
```
