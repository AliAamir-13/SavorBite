<?php
/**
 * Core Application Functions and Business Logic
 * Interacts with MySQL through PDO prepared statements
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';

/**
 * Sanitize strings for HTML output to prevent XSS
 */
function sanitize($value) {
    return htmlspecialchars(trim((string)$value), ENT_QUOTES, 'UTF-8');
}

/**
 * Currency formatter
 */
function format_price($amount) {
    return '₹' . number_format((float)$amount, 2);
}

/**
 * Generate unique order number
 */
function generate_order_number() {
    return 'ORD-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

/**
 * Flash messaging system
 */
function set_flash_message($type, $message) {
    if (!isset($_SESSION['flash_messages'])) {
        $_SESSION['flash_messages'] = [];
    }
    $_SESSION['flash_messages'][] = ['type' => $type, 'message' => $message];
}

function get_flash_messages() {
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return $messages;
}

/**
 * Status badge styling
 */
function get_status_badge_html($status) {
    $colors = [
        'Pending'          => 'bg-amber-100 text-amber-800 border-amber-300',
        'Confirmed'        => 'bg-blue-100 text-blue-800 border-blue-300',
        'Preparing'        => 'bg-indigo-100 text-indigo-800 border-indigo-300',
        'Out for Delivery' => 'bg-purple-100 text-purple-800 border-purple-300',
        'Delivered'        => 'bg-emerald-100 text-emerald-800 border-emerald-300',
        'Cancelled'        => 'bg-rose-100 text-rose-800 border-rose-300',
        'Paid'             => 'bg-emerald-100 text-emerald-800 border-emerald-300',
        'Failed'           => 'bg-rose-100 text-rose-800 border-rose-300',
    ];
    $cls = $colors[$status] ?? 'bg-gray-100 text-gray-800 border-gray-300';
    return '<span class="status-badge ' . $cls . '">' . htmlspecialchars($status) . '</span>';
}

/**
 * Fetch all categories
 */
function get_categories($pdo) {
    $stmt = $pdo->query("SELECT * FROM categories ORDER BY category_name ASC");
    return $stmt->fetchAll();
}

/**
 * Fetch menu items using database view and prepared statement
 * Demonstrates SQL View usage and dynamic parameter binding
 */
function get_menu_items($pdo, $categoryId = null, $search = null, $sort = 'popular', $onlyAvailable = false) {
    $sql = "SELECT * FROM food_menu_view WHERE 1=1";
    $params = [];

    if ($categoryId !== null && $categoryId > 0) {
        $sql .= " AND category_id = ?";
        $params[] = $categoryId;
    }

    if (!empty($search)) {
        $sql .= " AND (food_name LIKE ? OR description LIKE ?)";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }

    if ($onlyAvailable) {
        $sql .= " AND is_available = 1";
    }

    switch ($sort) {
        case 'price_asc':
            $sql .= " ORDER BY price ASC";
            break;
        case 'price_desc':
            $sql .= " ORDER BY price DESC";
            break;
        case 'name':
            $sql .= " ORDER BY food_name ASC";
            break;
        case 'popular':
        default:
            $sql .= " ORDER BY is_popular DESC, food_id DESC";
            break;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Fetch a single food item by ID
 */
function get_food_by_id($pdo, $foodId) {
    $stmt = $pdo->prepare("SELECT * FROM food_menu_view WHERE food_id = ?");
    $stmt->execute([$foodId]);
    return $stmt->fetch();
}

/**
 * Retrieve shopping cart items
 * Seamlessly handles authenticated persistent database cart vs guest session cart
 */
function get_cart($pdo) {
    if (is_logged_in()) {
        $userId = $_SESSION['user_id'];
        $stmt = $pdo->prepare("
            SELECT 
                c.cart_item_id,
                c.food_id,
                c.quantity,
                f.food_name,
                f.price,
                f.image_path,
                f.is_available,
                cat.category_name,
                ROUND(c.quantity * f.price, 2) AS line_total
            FROM cart_items c
            INNER JOIN food_items f ON c.food_id = f.food_id
            INNER JOIN categories cat ON f.category_id = cat.category_id
            WHERE c.user_id = ?
            ORDER BY c.added_at DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    } else {
        // Guest session-based cart
        $cart = $_SESSION['guest_cart'] ?? [];
        if (empty($cart)) {
            return [];
        }

        $foodIds = array_keys($cart);
        $placeholders = implode(',', array_fill(0, count($foodIds), '?'));
        
        $stmt = $pdo->prepare("
            SELECT f.food_id, f.food_name, f.price, f.image_path, f.is_available, c.category_name
            FROM food_items f
            INNER JOIN categories c ON f.category_id = c.category_id
            WHERE f.food_id IN ($placeholders)
        ");
        $stmt->execute($foodIds);
        $items = $stmt->fetchAll();

        $result = [];
        foreach ($items as $item) {
            $qty = $cart[$item['food_id']];
            $result[] = [
                'cart_item_id' => 'guest_' . $item['food_id'],
                'food_id'      => $item['food_id'],
                'quantity'     => $qty,
                'food_name'    => $item['food_name'],
                'price'        => $item['price'],
                'image_path'   => $item['image_path'],
                'is_available' => $item['is_available'],
                'category_name'=> $item['category_name'],
                'line_total'   => round($qty * $item['price'], 2)
            ];
        }
        return $result;
    }
}

/**
 * Add item to cart
 */
function add_to_cart($pdo, $foodId, $quantity = 1) {
    $quantity = max(1, (int)$quantity);

    // Verify item exists and is available
    $stmt = $pdo->prepare("SELECT food_id, is_available FROM food_items WHERE food_id = ?");
    $stmt->execute([$foodId]);
    $food = $stmt->fetch();

    if (!$food || !$food['is_available']) {
        return ['success' => false, 'message' => 'Item is currently unavailable.'];
    }

    if (is_logged_in()) {
        $userId = $_SESSION['user_id'];
        // Use MySQL INSERT ... ON DUPLICATE KEY UPDATE to increment quantity cleanly
        $stmt = $pdo->prepare("
            INSERT INTO cart_items (user_id, food_id, quantity)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)
        ");
        $stmt->execute([$userId, $foodId, $quantity]);
    } else {
        if (!isset($_SESSION['guest_cart'])) {
            $_SESSION['guest_cart'] = [];
        }
        if (isset($_SESSION['guest_cart'][$foodId])) {
            $_SESSION['guest_cart'][$foodId] += $quantity;
        } else {
            $_SESSION['guest_cart'][$foodId] = $quantity;
        }
    }

    return ['success' => true, 'message' => 'Item added to cart!'];
}

/**
 * Update item quantity in cart
 */
function update_cart_quantity($pdo, $cartItemId, $quantity) {
    $quantity = (int)$quantity;
    if ($quantity <= 0) {
        return remove_cart_item($pdo, $cartItemId);
    }

    if (is_logged_in()) {
        $userId = $_SESSION['user_id'];
        $stmt = $pdo->prepare("UPDATE cart_items SET quantity = ? WHERE cart_item_id = ? AND user_id = ?");
        $stmt->execute([$quantity, $cartItemId, $userId]);
    } else {
        $foodId = (int)str_replace('guest_', '', $cartItemId);
        if (isset($_SESSION['guest_cart'][$foodId])) {
            $_SESSION['guest_cart'][$foodId] = $quantity;
        }
    }

    return true;
}

/**
 * Remove single item from cart
 */
function remove_cart_item($pdo, $cartItemId) {
    if (is_logged_in()) {
        $userId = $_SESSION['user_id'];
        $stmt = $pdo->prepare("DELETE FROM cart_items WHERE cart_item_id = ? AND user_id = ?");
        $stmt->execute([$cartItemId, $userId]);
    } else {
        $foodId = (int)str_replace('guest_', '', $cartItemId);
        unset($_SESSION['guest_cart'][$foodId]);
    }
    return true;
}

/**
 * Clear full cart
 */
function clear_cart($pdo) {
    if (is_logged_in()) {
        $userId = $_SESSION['user_id'];
        $stmt = $pdo->prepare("DELETE FROM cart_items WHERE user_id = ?");
        $stmt->execute([$userId]);
    } else {
        $_SESSION['guest_cart'] = [];
    }
}

/**
 * Sync guest session cart to database when logging in
 */
function sync_guest_cart_to_db($pdo, $userId) {
    if (isset($_SESSION['guest_cart']) && !empty($_SESSION['guest_cart'])) {
        foreach ($_SESSION['guest_cart'] as $foodId => $qty) {
            $stmt = $pdo->prepare("
                INSERT INTO cart_items (user_id, food_id, quantity)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)
            ");
            $stmt->execute([$userId, $foodId, $qty]);
        }
        unset($_SESSION['guest_cart']);
    }
}

/**
 * Get count of items in cart
 */
function get_cart_item_count($pdo) {
    if (is_logged_in()) {
        $userId = $_SESSION['user_id'];
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM cart_items WHERE user_id = ?");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    } else {
        $cart = $_SESSION['guest_cart'] ?? [];
        return array_sum($cart);
    }
}

/**
 * Get cart subtotal, delivery fee, and grand total
 */
function get_cart_totals($pdo) {
    $items = get_cart($pdo);
    $subtotal = 0;
    foreach ($items as $item) {
        $subtotal += ($item['price'] * $item['quantity']);
    }
    $deliveryFee = ($subtotal > 0) ? 49.00 : 0.00;
    $total = $subtotal + $deliveryFee;

    return [
        'subtotal'     => round($subtotal, 2),
        'delivery_fee' => round($deliveryFee, 2),
        'total'        => round($total, 2),
        'item_count'   => count($items)
    ];
}

/**
 * Fetch customer saved delivery addresses
 */
function get_user_addresses($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, address_id DESC");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/**
 * Add delivery address for customer
 */
function add_user_address($pdo, $userId, $label, $line, $city, $postalCode, $isDefault = false) {
    if ($isDefault) {
        // Clear previous default
        $stmt = $pdo->prepare("UPDATE addresses SET is_default = 0 WHERE user_id = ?");
        $stmt->execute([$userId]);
    }

    $stmt = $pdo->prepare("
        INSERT INTO addresses (user_id, address_label, address_line, city, postal_code, is_default)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$userId, $label, $line, $city, $postalCode, $isDefault ? 1 : 0]);
    return $pdo->lastInsertId();
}

/**
 * Place Order Transaction (ACID Guaranteed)
 * 1. Checks cart non-empty
 * 2. Begins PDO Transaction
 * 3. Re-validates current prices and availability from MySQL
 * 4. Inserts order header record with delivery address snapshot
 * 5. Inserts all order item records with locked historical unit_price
 * 6. Inserts simulated payment record
 * 7. Deletes items from cart_items
 * 8. Commits transaction or rolls back on any exception
 */
function execute_order_placement($pdo, $userId, $addressText, $paymentMethod, $instructions = '') {
    $cartItems = get_cart($pdo);

    if (empty($cartItems)) {
        throw new Exception("Your cart is empty. Please add items to proceed.");
    }

    // Begin Database Transaction
    $pdo->beginTransaction();

    try {
        $subtotal = 0.00;
        $validatedItems = [];

        // Step 1: Re-fetch latest price and availability directly from MySQL to prevent client tampering
        foreach ($cartItems as $item) {
            $stmt = $pdo->prepare("SELECT food_id, food_name, price, is_available FROM food_items WHERE food_id = ? FOR UPDATE");
            $stmt->execute([$item['food_id']]);
            $food = $stmt->fetch();

            if (!$food) {
                throw new Exception("Product ID #" . $item['food_id'] . " was not found in our menu.");
            }

            if (!$food['is_available']) {
                throw new Exception("'" . $food['food_name'] . "' is currently sold out. Please remove it from your cart.");
            }

            $lineTotal = round($food['price'] * $item['quantity'], 2);
            $subtotal += $lineTotal;

            $validatedItems[] = [
                'food_id'    => $food['food_id'],
                'food_name'  => $food['food_name'],
                'quantity'   => $item['quantity'],
                'unit_price' => $food['price'],
                'line_total' => $lineTotal
            ];
        }

        $deliveryFee = 3.99;
        $totalAmount = round($subtotal + $deliveryFee, 2);
        $orderNumber = generate_order_number();
        $paymentStatus = ($paymentMethod === 'Demo Online Payment') ? 'Paid' : 'Pending';

        // Step 2: Insert into orders table
        $orderStmt = $pdo->prepare("
            INSERT INTO orders (
                order_number, user_id, delivery_address_text, order_date,
                subtotal, delivery_fee, total_amount, payment_method,
                payment_status, order_status, special_instructions
            ) VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, ?, 'Pending', ?)
        ");
        $orderStmt->execute([
            $orderNumber,
            $userId,
            $addressText,
            $subtotal,
            $deliveryFee,
            $totalAmount,
            $paymentMethod,
            $paymentStatus,
            $instructions
        ]);

        $orderId = (int)$pdo->lastInsertId();

        // Step 3: Insert each order item record
        $itemStmt = $pdo->prepare("
            INSERT INTO order_items (order_id, food_id, quantity, unit_price, line_total)
            VALUES (?, ?, ?, ?, ?)
        ");

        foreach ($validatedItems as $vItem) {
            $itemStmt->execute([
                $orderId,
                $vItem['food_id'],
                $vItem['quantity'],
                $vItem['unit_price'],
                $vItem['line_total']
            ]);
        }

        // Step 4: Insert simulated payment record
        $txRef = 'TXN-' . strtoupper(substr($paymentMethod, 0, 3)) . '-' . date('Ymd') . '-' . bin2hex(random_bytes(4));
        $payStatus = ($paymentStatus === 'Paid') ? 'Completed' : 'Pending';

        $payStmt = $pdo->prepare("
            INSERT INTO payments (order_id, payment_method, amount, payment_status, transaction_reference, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $payStmt->execute([
            $orderId,
            $paymentMethod,
            $totalAmount,
            $payStatus,
            $txRef
        ]);

        // Step 5: Clear user's cart
        $clearStmt = $pdo->prepare("DELETE FROM cart_items WHERE user_id = ?");
        $clearStmt->execute([$userId]);

        // Commit transaction
        $pdo->commit();

        return [
            'success'      => true,
            'order_id'     => $orderId,
            'order_number' => $orderNumber
        ];

    } catch (Exception $e) {
        // Rollback on any failure to maintain atomicity and integrity
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Fetch orders for a specific user
 */
function get_user_orders($pdo, $userId) {
    $stmt = $pdo->prepare("
        SELECT 
            o.*,
            COUNT(oi.order_item_id) AS total_items,
            p.transaction_reference
        FROM orders o
        LEFT JOIN order_items oi ON o.order_id = oi.order_id
        LEFT JOIN payments p ON o.order_id = p.order_id
        WHERE o.user_id = ?
        GROUP BY o.order_id
        ORDER BY o.order_date DESC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/**
 * Fetch single order with its items (with ownership check)
 */
function get_order_with_items($pdo, $orderId, $userId = null) {
    $sql = "
        SELECT 
            o.*,
            u.full_name,
            u.email,
            u.phone,
            p.transaction_reference,
            p.payment_status AS pay_status
        FROM orders o
        INNER JOIN users u ON o.user_id = u.user_id
        LEFT JOIN payments p ON o.order_id = p.order_id
        WHERE o.order_id = ?
    ";
    $params = [$orderId];

    // If userId provided and not admin, restrict to owner
    if ($userId !== null) {
        $sql .= " AND o.user_id = ?";
        $params[] = $userId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $order = $stmt->fetch();

    if (!$order) {
        return null;
    }

    // Fetch items with food names and details
    $itemStmt = $pdo->prepare("
        SELECT 
            oi.*,
            f.food_name,
            f.image_path,
            c.category_name
        FROM order_items oi
        INNER JOIN food_items f ON oi.food_id = f.food_id
        INNER JOIN categories c ON f.category_id = c.category_id
        WHERE oi.order_id = ?
    ");
    $itemStmt->execute([$orderId]);
    $order['items'] = $itemStmt->fetchAll();

    // Fetch status history audit log
    $histStmt = $pdo->prepare("
        SELECT * FROM order_status_history WHERE order_id = ? ORDER BY changed_at ASC
    ");
    $histStmt->execute([$orderId]);
    $order['history'] = $histStmt->fetchAll();

    return $order;
}
