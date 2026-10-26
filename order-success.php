<?php
/**
 * Order Success / Confirmation Page - SavorBite Online Food Ordering System
 */
$pageTitle = 'Order Confirmed! - SavorBite';
require_once __DIR__ . '/includes/header.php';

require_login();

$userId = $_SESSION['user_id'];
$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$order = get_order_with_items($pdo, $orderId, $userId);

if (!$order) {
    set_flash_message('error', 'Order not found.');
    header("Location: my-orders.php");
    exit();
}
?>

<div class="container success-container">
    <div class="success-card">
        <div class="success-icon-badge">✓</div>
        <h1 class="success-title">Order Placed Successfully!</h1>
        <p class="success-subtitle">Thank you for ordering with SavorBite. Our kitchen is firing up the burners!</p>
        
        <div class="order-ref-banner">
            <span>Order Reference:</span>
            <strong><?= htmlspecialchars($order['order_number']) ?></strong>
        </div>

        <div class="order-details-summary-grid">
            <!-- Left: Order Items Breakdown -->
            <div class="receipt-section">
                <h3>Order Items</h3>
                <div class="receipt-items-list">
                    <?php foreach ($order['items'] as $item): ?>
                        <div class="receipt-item-row">
                            <div class="receipt-item-info">
                                <span class="receipt-qty"><?= $item['quantity'] ?>x</span>
                                <span class="receipt-name"><?= htmlspecialchars($item['food_name']) ?></span>
                            </div>
                            <span class="receipt-price"><?= format_price($item['line_total']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="receipt-totals-box">
                    <div class="receipt-total-row">
                        <span>Subtotal:</span>
                        <span><?= format_price($order['subtotal']) ?></span>
                    </div>
                    <div class="receipt-total-row">
                        <span>Delivery Fee:</span>
                        <span><?= format_price($order['delivery_fee']) ?></span>
                    </div>
                    <div class="receipt-total-row receipt-grand-total">
                        <span>Total Paid:</span>
                        <span><?= format_price($order['total_amount']) ?></span>
                    </div>
                </div>
            </div>

            <!-- Right: Delivery & Payment Details -->
            <div class="delivery-meta-section">
                <h3>Delivery & Payment Info</h3>
                
                <div class="meta-info-card">
                    <p class="meta-label">Delivering To:</p>
                    <p class="meta-val"><?= htmlspecialchars($order['delivery_address_text']) ?></p>
                </div>

                <div class="meta-info-card">
                    <p class="meta-label">Payment Method:</p>
                    <p class="meta-val"><?= htmlspecialchars($order['payment_method']) ?> (<?= htmlspecialchars($order['payment_status']) ?>)</p>
                    <?php if (!empty($order['transaction_reference'])): ?>
                        <small class="text-muted">Ref: <?= htmlspecialchars($order['transaction_reference']) ?></small>
                    <?php endif; ?>
                </div>

                <div class="meta-info-card">
                    <p class="meta-label">Current Status:</p>
                    <p class="meta-val"><?= get_status_badge_html($order['order_status']) ?></p>
                </div>

                <?php if (!empty($order['special_instructions'])): ?>
                    <div class="meta-info-card">
                        <p class="meta-label">Special Instructions:</p>
                        <p class="meta-val">"<?= htmlspecialchars($order['special_instructions']) ?>"</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="success-actions">
            <a href="order-details.php?id=<?= $order['order_id'] ?>" class="btn btn-primary btn-lg">Track Live Order Status &rarr;</a>
            <a href="menu.php" class="btn btn-outline btn-lg">Order More Food</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
