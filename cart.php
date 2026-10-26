<?php
/**
 * Shopping Cart Page - SavorBite Online Food Ordering System
 * Supports database persistence for logged-in users and session storage for guests
 */
$pageTitle = 'Shopping Cart - SavorBite';
require_once __DIR__ . '/includes/header.php';

// Handle cart actions (update quantity, remove, clear)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        set_flash_message('error', 'Session expired. Please try again.');
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'update_qty') {
            $itemId = $_POST['cart_item_id'] ?? '';
            $qty = (int)($_POST['quantity'] ?? 1);
            update_cart_quantity($pdo, $itemId, $qty);
            set_flash_message('success', 'Cart updated.');
        } elseif ($action === 'remove_item') {
            $itemId = $_POST['cart_item_id'] ?? '';
            remove_cart_item($pdo, $itemId);
            set_flash_message('success', 'Item removed from cart.');
        } elseif ($action === 'clear_cart') {
            clear_cart($pdo);
            set_flash_message('success', 'Your cart has been cleared.');
        }
    }
    header("Location: cart.php");
    exit();
}

$cartItems = get_cart($pdo);
$totals = get_cart_totals($pdo);
?>

<div class="container cart-page-container">
    <div class="cart-header-section">
        <h1 class="page-title">Your Order Cart</h1>
        <p class="page-subtitle">Review your items before proceeding to secure checkout</p>
    </div>

    <?php if (empty($cartItems)): ?>
        <div class="empty-cart-view">
            <div class="empty-cart-illustration">🛒</div>
            <h2>Your cart is hungry & empty!</h2>
            <p>You haven't added any appetizing dishes to your cart yet. Explore our handcrafted menu.</p>
            <a href="menu.php" class="btn btn-primary btn-lg">Browse Delicious Menu</a>
        </div>
    <?php else: ?>
        <div class="cart-layout-grid">
            <!-- Cart Items List -->
            <div class="cart-items-wrapper">
                <div class="cart-table-card">
                    <div class="cart-table-header">
                        <span>Dish</span>
                        <span>Price</span>
                        <span>Quantity</span>
                        <span>Subtotal</span>
                        <span></span>
                    </div>

                    <div class="cart-items-body">
                        <?php foreach ($cartItems as $item): ?>
                            <div class="cart-item-row <?= !$item['is_available'] ? 'item-out-of-stock-row' : '' ?>">
                                <!-- Dish Info -->
                                <div class="cart-item-col dish-col">
                                    <img src="<?= htmlspecialchars($item['image_path']) ?>" alt="<?= htmlspecialchars($item['food_name']) ?>" class="cart-thumb">
                                    <div>
                                        <h4 class="cart-dish-name">
                                            <a href="food-details.php?id=<?= $item['food_id'] ?>"><?= htmlspecialchars($item['food_name']) ?></a>
                                        </h4>
                                        <span class="cart-dish-cat"><?= htmlspecialchars($item['category_name']) ?></span>
                                        <?php if (!$item['is_available']): ?>
                                            <span class="warning-tag">⚠️ Currently Unavailable</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Unit Price -->
                                <div class="cart-item-col price-col">
                                    <?= format_price($item['price']) ?>
                                </div>

                                <!-- Quantity Controls -->
                                <div class="cart-item-col qty-col">
                                    <form action="cart.php" method="POST" class="inline-qty-form">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="update_qty">
                                        <input type="hidden" name="cart_item_id" value="<?= htmlspecialchars($item['cart_item_id']) ?>">
                                        
                                        <div class="qty-pill">
                                            <button type="submit" name="quantity" value="<?= $item['quantity'] - 1 ?>" class="qty-minus" <?= $item['quantity'] <= 1 ? 'disabled' : '' ?>>-</button>
                                            <span class="qty-display"><?= $item['quantity'] ?></span>
                                            <button type="submit" name="quantity" value="<?= $item['quantity'] + 1 ?>" class="qty-plus" <?= $item['quantity'] >= 20 ? 'disabled' : '' ?>>+</button>
                                        </div>
                                    </form>
                                </div>

                                <!-- Line Total -->
                                <div class="cart-item-col total-col font-bold">
                                    <?= format_price($item['line_total']) ?>
                                </div>

                                <!-- Remove Button -->
                                <div class="cart-item-col remove-col">
                                    <form action="cart.php" method="POST" onsubmit="return confirm('Remove this dish from your cart?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="remove_item">
                                        <input type="hidden" name="cart_item_id" value="<?= htmlspecialchars($item['cart_item_id']) ?>">
                                        <button type="submit" class="btn-remove-item" title="Remove Item">&times;</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="cart-actions-bar">
                        <a href="menu.php" class="btn btn-outline btn-sm">&larr; Continue Ordering</a>
                        <form action="cart.php" method="POST" onsubmit="return confirm('Are you sure you want to clear your cart?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="clear_cart">
                            <button type="submit" class="btn btn-ghost btn-sm text-danger">Clear Cart</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Order Summary Sidebar -->
            <div class="cart-summary-wrapper">
                <div class="summary-card">
                    <h3 class="summary-title">Order Summary</h3>

                    <div class="summary-row">
                        <span>Items Subtotal:</span>
                        <span class="font-bold"><?= format_price($totals['subtotal']) ?></span>
                    </div>

                    <div class="summary-row">
                        <span>Standard Delivery:</span>
                        <span><?= format_price($totals['delivery_fee']) ?></span>
                    </div>

                    <div class="summary-row promo-hint">
                        <small>✨ Free delivery on orders over ₹499.00</small>
                    </div>

                    <hr class="summary-divider">

                    <div class="summary-row total-row">
                        <span>Total to Pay:</span>
                        <span class="summary-grand-total"><?= format_price($totals['total']) ?></span>
                    </div>

                    <div class="summary-cta">
                        <?php if (is_logged_in()): ?>
                            <a href="checkout.php" class="btn btn-primary btn-lg btn-block">Proceed to Checkout &rarr;</a>
                        <?php else: ?>
                            <a href="login.php?redirect=checkout.php" class="btn btn-primary btn-lg btn-block">Sign In to Checkout &rarr;</a>
                            <p class="guest-checkout-note">Sign in or create a quick account so we can deliver to your doorstep.</p>
                        <?php endif; ?>
                    </div>

                    <div class="trust-icons-row">
                        <span>🔒 256-Bit SSL Checkout</span>
                        <span>⚡ Freshly Cooked</span>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
