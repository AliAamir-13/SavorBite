<?php
/**
 * Customer Order History - SavorBite Online Food Ordering System
 * Demonstrates: Customer Isolation (ownership check), Allowed State Transitions
 */
$pageTitle = 'My Order History - SavorBite';
require_once __DIR__ . '/includes/header.php';

require_login('my-orders.php');

$userId = $_SESSION['user_id'];

// Handle order cancellation (only permitted when status is 'Pending')
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_order') {
    if (!verify_csrf_token()) {
        set_flash_message('error', 'Security token expired.');
    } else {
        $cancelOrderId = (int)($_POST['order_id'] ?? 0);
        
        // Verify user owns the order and it is Pending
        $checkStmt = $pdo->prepare("SELECT order_id, order_status FROM orders WHERE order_id = ? AND user_id = ?");
        $checkStmt->execute([$cancelOrderId, $userId]);
        $orderToCancel = $checkStmt->fetch();

        if ($orderToCancel && $orderToCancel['order_status'] === 'Pending') {
            // Update order status to Cancelled (triggers order_status_history automatically)
            $updateStmt = $pdo->prepare("UPDATE orders SET order_status = 'Cancelled' WHERE order_id = ?");
            $updateStmt->execute([$cancelOrderId]);
            set_flash_message('success', 'Your order has been cancelled.');
        } else {
            set_flash_message('error', 'Only pending orders can be cancelled. This order has already been confirmed or completed by the kitchen.');
        }
    }
    header("Location: my-orders.php");
    exit();
}

$orders = get_user_orders($pdo, $userId);
?>

<div class="container orders-container">
    <div class="orders-header">
        <h1 class="page-title">My Orders</h1>
        <p class="page-subtitle">Track your active meals and review past culinary adventures</p>
    </div>

    <?php if (empty($orders)): ?>
        <div class="empty-orders-view">
            <span class="empty-icon">📦</span>
            <h2>No orders yet!</h2>
            <p>You haven't placed any food orders yet. Ready to experience extraordinary flavors?</p>
            <a href="menu.php" class="btn btn-primary btn-lg">Explore Menu</a>
        </div>
    <?php else: ?>
        <div class="orders-list">
            <?php foreach ($orders as $ord): ?>
                <div class="order-card-row">
                    <div class="order-card-header">
                        <div class="order-ref-group">
                            <span class="order-number-title"><?= htmlspecialchars($ord['order_number']) ?></span>
                            <span class="order-date-text">Placed on <?= date('M d, Y \a\t g:i A', strtotime($ord['order_date'])) ?></span>
                        </div>
                        <div class="order-status-group">
                            <?= get_status_badge_html($ord['order_status']) ?>
                        </div>
                    </div>

                    <div class="order-card-content">
                        <div class="order-meta-info">
                            <p><strong>Total Amount:</strong> <span class="order-price-highlight"><?= format_price($ord['total_amount']) ?></span></p>
                            <p><strong>Payment:</strong> <?= htmlspecialchars($ord['payment_method']) ?> (<?= htmlspecialchars($ord['payment_status']) ?>)</p>
                            <p><strong>Delivery Address:</strong> <?= htmlspecialchars($ord['delivery_address_text']) ?></p>
                        </div>
                        
                        <div class="order-card-actions">
                            <a href="order-details.php?id=<?= $ord['order_id'] ?>" class="btn btn-outline btn-sm">View Full Details &rarr;</a>

                            <?php if ($ord['order_status'] === 'Pending'): ?>
                                <form action="my-orders.php" method="POST" onsubmit="return confirm('Are you sure you want to cancel this pending order?');" class="inline-cancel-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="cancel_order">
                                    <input type="hidden" name="order_id" value="<?= $ord['order_id'] ?>">
                                    <button type="submit" class="btn btn-ghost btn-sm text-danger">Cancel Order</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
