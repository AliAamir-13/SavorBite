<?php
/**
 * Order Details & Live Timeline - SavorBite Online Food Ordering System
 * Demonstrates: Historical Price Integrity, Trigger-Generated Audit Timeline
 */
$pageTitle = 'Order Details - SavorBite';
require_once __DIR__ . '/includes/header.php';

require_login();

$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$userId = is_admin() ? null : $_SESSION['user_id'];

// Retrieve order and ensure ownership if not admin
$order = get_order_with_items($pdo, $orderId, $userId);

if (!$order) {
    set_flash_message('error', 'Order not found or you do not have permission to view it.');
    header("Location: my-orders.php");
    exit();
}

$pageTitle = 'Order ' . htmlspecialchars($order['order_number']) . ' - SavorBite';
?>

<div class="container order-details-container">
    <div class="details-nav-header">
        <a href="my-orders.php" class="back-link">&larr; Back to My Orders</a>
        <div class="order-title-group">
            <h1 class="page-title">Order <?= htmlspecialchars($order['order_number']) ?></h1>
            <p class="page-subtitle">Placed on <?= date('F j, Y \a\t g:i A', strtotime($order['order_date'])) ?></p>
        </div>
        <div class="status-top-badge">
            <?= get_status_badge_html($order['order_status']) ?>
        </div>
    </div>

    <!-- Live Status Tracker Stepper -->
    <?php if ($order['order_status'] !== 'Cancelled'): ?>
        <div class="order-progress-stepper-card">
            <?php
            $statuses = ['Pending', 'Confirmed', 'Preparing', 'Out for Delivery', 'Delivered'];
            $currentIndex = array_search($order['order_status'], $statuses);
            if ($currentIndex === false) $currentIndex = 0;
            ?>
            <div class="stepper-track">
                <?php foreach ($statuses as $idx => $st): ?>
                    <div class="step-point <?= $idx <= $currentIndex ? 'completed' : '' ?> <?= $idx === $currentIndex ? 'current' : '' ?>">
                        <div class="step-circle">
                            <?php if ($idx < $currentIndex): ?>✓<?php else: ?><?= $idx + 1 ?><?php endif; ?>
                        </div>
                        <span class="step-label"><?= $st ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="order-cancelled-notice">
            <span>⚠️ This order was cancelled.</span>
        </div>
    <?php endif; ?>

    <div class="order-details-grid">
        <!-- Left: Items Ordered -->
        <div class="order-details-main">
            <div class="card-box">
                <h3 class="card-box-title">Ordered Items (<?= count($order['items']) ?>)</h3>
                
                <div class="itemized-table">
                    <div class="itemized-header">
                        <span>Item</span>
                        <span class="text-center">Qty</span>
                        <span class="text-right">Price</span>
                        <span class="text-right">Total</span>
                    </div>
                    <?php foreach ($order['items'] as $item): ?>
                        <div class="itemized-row">
                            <div class="item-info-cell">
                                <img src="<?= htmlspecialchars($item['image_path']) ?>" alt="<?= htmlspecialchars($item['food_name']) ?>" class="item-mini-thumb">
                                <div>
                                    <strong class="item-name"><?= htmlspecialchars($item['food_name']) ?></strong>
                                    <small class="item-cat-sub"><?= htmlspecialchars($item['category_name']) ?></small>
                                </div>
                            </div>
                            <div class="text-center"><?= $item['quantity'] ?></div>
                            <div class="text-right"><?= format_price($item['unit_price']) ?></div>
                            <div class="text-right font-bold"><?= format_price($item['line_total']) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="order-breakdown-footer">
                    <div class="breakdown-line">
                        <span>Subtotal:</span>
                        <span><?= format_price($order['subtotal']) ?></span>
                    </div>
                    <div class="breakdown-line">
                        <span>Delivery Fee:</span>
                        <span><?= format_price($order['delivery_fee']) ?></span>
                    </div>
                    <div class="breakdown-line grand-total-line">
                        <span>Grand Total:</span>
                        <span><?= format_price($order['total_amount']) ?></span>
                    </div>
                </div>
            </div>

            <!-- Status Timeline -->
            <?php if (!empty($order['history'])): ?>
                <div class="card-box timeline-box">
                    <h3 class="card-box-title">Order Status Updates</h3>
                    <div class="status-timeline">
                        <?php foreach ($order['history'] as $hist): ?>
                            <div class="timeline-event">
                                <div class="timeline-marker"></div>
                                <div class="timeline-body">
                                    <div class="timeline-head">
                                        <strong><?= htmlspecialchars($hist['new_status']) ?></strong>
                                        <span class="timeline-time"><?= date('g:i A, M d', strtotime($hist['changed_at'])) ?></span>
                                    </div>
                                    <p class="timeline-note"><?= htmlspecialchars($hist['notes'] ?? 'Status updated') ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Right: Delivery & Payment Details -->
        <div class="order-details-sidebar">
            <div class="card-box">
                <h3 class="card-box-title">Delivery Details</h3>
                <div class="meta-row">
                    <span class="meta-icon">👤</span>
                    <div>
                        <strong>Customer Name:</strong>
                        <p><?= htmlspecialchars($order['full_name']) ?></p>
                    </div>
                </div>
                <div class="meta-row">
                    <span class="meta-icon">📞</span>
                    <div>
                        <strong>Phone Number:</strong>
                        <p><?= htmlspecialchars($order['phone']) ?></p>
                    </div>
                </div>
                <div class="meta-row">
                    <span class="meta-icon">📍</span>
                    <div>
                        <strong>Delivery Address:</strong>
                        <p><?= htmlspecialchars($order['delivery_address_text']) ?></p>
                    </div>
                </div>
                <?php if (!empty($order['special_instructions'])): ?>
                    <div class="meta-row">
                        <span class="meta-icon">📝</span>
                        <div>
                            <strong>Special Instructions:</strong>
                            <p>"<?= htmlspecialchars($order['special_instructions']) ?>"</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="card-box">
                <h3 class="card-box-title">Payment Summary</h3>
                <div class="meta-row">
                    <span class="meta-icon">💳</span>
                    <div>
                        <strong>Method:</strong>
                        <p><?= htmlspecialchars($order['payment_method']) ?></p>
                    </div>
                </div>
                <div class="meta-row">
                    <span class="meta-icon">🏷️</span>
                    <div>
                        <strong>Payment Status:</strong>
                        <p><?= htmlspecialchars($order['payment_status']) ?></p>
                    </div>
                </div>
                <?php if (!empty($order['transaction_reference'])): ?>
                    <div class="meta-row">
                        <span class="meta-icon">🔢</span>
                        <div>
                            <strong>Transaction Ref:</strong>
                            <p class="text-mono"><?= htmlspecialchars($order['transaction_reference']) ?></p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
