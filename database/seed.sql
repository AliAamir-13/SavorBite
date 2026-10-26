-- =====================================================================
-- DATABASE: food_ordering_system
-- Seed Data Script with Sample Users, Categories, Foods, Addresses & Orders
-- =====================================================================

USE `food_ordering_system`;

-- Disable foreign key checks for clean seeding
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE `payments`;
TRUNCATE TABLE `order_status_history`;
TRUNCATE TABLE `order_items`;
TRUNCATE TABLE `orders`;
TRUNCATE TABLE `cart_items`;
TRUNCATE TABLE `food_items`;
TRUNCATE TABLE `categories`;
TRUNCATE TABLE `addresses`;
TRUNCATE TABLE `users`;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- 1. SEED USERS
-- Password for admin: "Admin@123"
-- Password for customer: "Customer@123"
-- Hashes generated via standard password_hash(..., PASSWORD_DEFAULT)
-- ---------------------------------------------------------------------
INSERT INTO `users` (`user_id`, `full_name`, `email`, `phone`, `password_hash`, `role`, `created_at`) VALUES
(1, 'Administrator', 'admin@savorbite.com', '+1 (555) 019-2834', '$2y$10$U8yI86u8o.7UuH4eH4eFRe5L0w1Z5mXqBqG1I.iQ3f6T6tW1Y7xme', 'admin', '2026-09-01 10:00:00'),
(2, 'John Doe', 'john@example.com', '+1 (555) 432-8765', '$2y$10$zS39GZgO1u6qE5vVqY6jAeG7EaW0oK.Q5h2X8oN4mP1jK9lU8y1uO', 'customer', '2026-09-15 12:30:00'),
(3, 'Sarah Jenkins', 'sarah.j@example.com', '+1 (555) 789-1234', '$2y$10$zS39GZgO1u6qE5vVqY6jAeG7EaW0oK.Q5h2X8oN4mP1jK9lU8y1uO', 'customer', '2026-09-20 14:15:00');

-- ---------------------------------------------------------------------
-- 2. SEED ADDRESSES
-- ---------------------------------------------------------------------
INSERT INTO `addresses` (`address_id`, `user_id`, `address_label`, `address_line`, `city`, `postal_code`, `is_default`, `created_at`) VALUES
(1, 2, 'Home', '742 Evergreen Terrace, Apt 4B', 'Springfield', '97477', 1, '2026-09-15 12:35:00'),
(2, 2, 'Work', '404 Silicon Parkway, Suite 300', 'Springfield', '97401', 0, '2026-09-18 09:10:00'),
(3, 3, 'Apartment', '120 Ocean View Drive', 'Springfield', '97402', 1, '2026-09-20 14:20:00');

-- ---------------------------------------------------------------------
-- 3. SEED CATEGORIES (6 Categories)
-- ---------------------------------------------------------------------
INSERT INTO `categories` (`category_id`, `category_name`, `description`, `image_url`, `created_at`) VALUES
(1, 'Artisan Pizza', 'Stone-baked thin crust and rustic Neapolitan style pizzas topped with fresh mozzarella and basil.', 'https://images.unsplash.com/photo-1513104890138-7c749659a591?w=600&auto=format&fit=crop&q=80', '2026-09-01 10:00:00'),
(2, 'Gourmet Burgers', 'Flame-grilled prime angus beef and crispy chicken patties served in toasted brioche buns.', 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=600&auto=format&fit=crop&q=80', '2026-09-01 10:00:00'),
(3, 'Indian Specialties', 'Fragrant curries, rich slow-simmered biryanis, and aromatic spiced clay-oven delicacies.', 'https://images.unsplash.com/photo-1585937421612-70a008356fbe?w=600&auto=format&fit=crop&q=80', '2026-09-01 10:00:00'),
(4, 'Pan-Asian & Bowls', 'Wok-tossed noodles, steaming noodle soups, crisp dumplings, and vibrant poke bowls.', 'https://images.unsplash.com/photo-1569718212165-3a8278d5f624?w=600&auto=format&fit=crop&q=80', '2026-09-01 10:00:00'),
(5, 'Desserts & Sweets', 'Decadent molten chocolate cakes, artisanal cheesecakes, and chilled creamy desserts.', 'https://images.unsplash.com/photo-1551024709-8f23befc6f87?w=600&auto=format&fit=crop&q=80', '2026-09-01 10:00:00'),
(6, 'Craft Beverages', 'Hand-crafted lemonades, chilled iced teas, botanical sodas, and tropical smoothies.', 'https://images.unsplash.com/photo-1513558161293-cdaf765ed2fd?w=600&auto=format&fit=crop&q=80', '2026-09-01 10:00:00');

-- ---------------------------------------------------------------------
-- 4. SEED FOOD ITEMS (24 items across 6 categories)
-- ---------------------------------------------------------------------
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

-- ---------------------------------------------------------------------
-- 5. SEED CART ITEMS (Sample persisted cart for customer 2)
-- ---------------------------------------------------------------------
INSERT INTO `cart_items` (`cart_item_id`, `user_id`, `food_id`, `quantity`, `added_at`) VALUES
(1, 2, 1, 2, '2026-10-10 18:20:00'),
(2, 2, 5, 1, '2026-10-10 18:22:00'),
(3, 2, 21, 2, '2026-10-10 18:25:00');

-- ---------------------------------------------------------------------
-- 6. SEED ORDERS & ORDER ITEMS & PAYMENTS
-- Demonstrates previous orders across different statuses for testing
-- ---------------------------------------------------------------------
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

-- Seed Payments
INSERT INTO `payments` (`payment_id`, `order_id`, `payment_method`, `amount`, `payment_status`, `transaction_reference`, `created_at`) VALUES
(1, 1, 'Demo Online Payment', 516.00, 'Completed', 'TXN-DEMO-20261011-8192-A9F2', '2026-10-11 19:15:30'),
(2, 2, 'Cash on Delivery', 874.00, 'Pending', 'TXN-COD-20261012-9014-C4B1', '2026-10-12 13:00:15'),
(3, 3, 'Demo Online Payment', 456.00, 'Completed', 'TXN-DEMO-20261013-1102-E88D', '2026-10-13 20:45:22');

-- Seed initial status history records
INSERT INTO `order_status_history` (`history_id`, `order_id`, `old_status`, `new_status`, `changed_at`, `notes`) VALUES
(1, 1, NULL, 'Pending', '2026-10-11 19:15:00', 'Order placed successfully'),
(2, 1, 'Pending', 'Preparing', '2026-10-11 19:22:00', 'Kitchen accepted order'),
(3, 1, 'Preparing', 'Out for Delivery', '2026-10-11 19:45:00', 'Dispatched with courier'),
(4, 1, 'Out for Delivery', 'Delivered', '2026-10-11 20:10:00', 'Delivered to customer'),
(5, 2, NULL, 'Pending', '2026-10-12 13:00:00', 'Order placed via COD'),
(6, 2, 'Pending', 'Preparing', '2026-10-12 13:08:00', 'Kitchen preparing order'),
(7, 3, NULL, 'Pending', '2026-10-13 20:45:00', 'Order placed and paid');
