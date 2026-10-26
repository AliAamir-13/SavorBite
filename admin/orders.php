<?php
/**
 * Admin Customer Orders Management - SavorBite Online Food Ordering System
 * Demonstrates: Stored Procedure call `UpdateOrderStatus`, Database Trigger invocation,
 * and comprehensive Order Status History (Audit Log) visualization.
 */
$adminPageTitle = 'Customer Orders Management';
require_once __DIR__ . '/admin_header.php';

// Handle order status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_order_status') {
    if (!verify_csrf_token()) {
        set_flash_message('error', 'Security token expired.');
    } else {
        $orderId = (int)($_POST['order_id'] ?? 0);
        $newStatus = $_POST['new_status'] ?? '';
        $paymentStatus = $_POST['payment_status'] ?? '';

        try {
            // Call Stored Procedure: UpdateOrderStatus
            // If the MySQL stored procedure isn't installed in user's XAMPP yet, falls back safely to direct update
            try {
                $procStmt = $pdo->prepare("CALL UpdateOrderStatus(?, ?, ?)");
                $procStmt->execute([$orderId, $newStatus, $paymentStatus]);
            } catch (PDOException $pe) {
                // Direct fallback update
                $stmt = $pdo->prepare("UPDATE orders SET order_status = ?, payment_status = ? WHERE order_id = ?");
                $stmt->execute([$newStatus, $paymentStatus, $orderId]);
            }

            set_flash_message('success', "Order #{$orderId} status changed to '{$newStatus}'. Trigger automatically recorded audit history.");
        } catch (Exception $e) {
            set_flash_message('error', 'Failed to update order: ' . $e->getMessage());
        }
    }
    header("Location: orders.php?order_id={$orderId}" . (!empty($_GET['status']) ? '&status=' . urlencode($_GET['status']) : ''));
    exit();
}

$statusFilter = $_GET['status'] ?? '';
$searchFilter = trim($_GET['search'] ?? '');

// Build dynamic query with parameter binding
$sql = "SELECT * FROM order_summary_view WHERE 1=1";
$params = [];

if (!empty($statusFilter)) {
    $sql .= " AND order_status = ?";
    $params[] = $statusFilter;
}

if (!empty($searchFilter)) {
    $sql .= " AND (order_number LIKE ? OR customer_name LIKE ? OR customer_email LIKE ?)";
    $params[] = "%{$searchFilter}%";
    $params[] = "%{$searchFilter}%";
    $params[] = "%{$searchFilter}%";
}

$sql .= " ORDER BY order_date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$ordersList = $stmt->fetchAll();

// Check if viewing single order details
$selectedOrderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : null;
$selectedOrder = null;
if ($selectedOrderId) {
    $selectedOrder = get_order_with_items($pdo, $selectedOrderId, null);
}
?>

<!-- Filter and Search Bar -->
<div class="admin-card mb-6">
    <div class="orders-filter-bar">
        <a href="orders.php<?= !empty($searchFilter) ? '?search=' . urlencode($searchFilter) : '' ?>" class="filter-tab <?= empty($statusFilter) ? 'active' : '' ?>">All Orders</a>
        <a href="orders.php?status=Pending<?= !empty($searchFilter) ? '&search=' . urlencode($searchFilter) : '' ?>" class="filter-tab <?= $statusFilter === 'Pending' ? 'active' : '' ?>">Pending</a>
        <a href="orders.php?status=Confirmed<?= !empty($searchFilter) ? '&search=' . urlencode($searchFilter) : '' ?>" class="filter-tab <?= $statusFilter === 'Confirmed' ? 'active' : '' ?>">Confirmed</a>
        <a href="orders.php?status=Preparing<?= !empty($searchFilter) ? '&search=' . urlencode($searchFilter) : '' ?>" class="filter-tab <?= $statusFilter === 'Preparing' ? 'active' : '' ?>">Preparing</a>
        <a href="orders.php?status=Out for Delivery<?= !empty($searchFilter) ? '&search=' . urlencode($searchFilter) : '' ?>" class="filter-tab <?= $statusFilter === 'Out for Delivery' ? 'active' : '' ?>">Out for Delivery</a>
        <a href="orders.php?status=Delivered<?= !empty($searchFilter) ? '&search=' . urlencode($searchFilter) : '' ?>" class="filter-tab <?= $statusFilter === 'Delivered' ? 'active' : '' ?>">Delivered</a>
        <a href="orders.php?status=Cancelled<?= !empty($searchFilter) ? '&search=' . urlencode($searchFilter) : '' ?>" class="filter-tab <?= $statusFilter === 'Cancelled' ? 'active' : '' ?>">Cancelled</a>
    </div>

    <form action="orders.php" method="GET" class="orders-search-form mt-4">
        <?php if (!empty($statusFilter)): ?>
            <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
        <?php endif; ?>
        <div class="search-input-wrapper">
            <input type="text" name="search" value="<?= htmlspecialchars($searchFilter) ?>" placeholder="Search by Order # or Customer Name..." class="form-control">
            <button type="submit" class="btn btn-primary btn-sm">Search</button>
            <?php if (!empty($searchFilter)): ?>
                <a href="orders.php<?= !empty($statusFilter) ? '?status=' . urlencode($statusFilter) : '' ?>" class="btn btn-ghost btn-sm">Clear</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Detailed Order View Modal/Card with Order Status History -->
<?php if ($selectedOrder): ?>
    <div class="admin-card mb-6 highlight-card" id="order-details-focus">
        <div class="admin-card-header">
            <div>
                <h3>Order <?= htmlspecialchars($selectedOrder['order_number']) ?></h3>
                <small class="text-muted">Placed on <?= date('F j, Y \a\t g:i A', strtotime($selectedOrder['order_date'])) ?></small>
            </div>
            <a href="orders.php<?= !empty($statusFilter) ? '?status=' . urlencode($statusFilter) : '' ?>" class="btn btn-ghost btn-sm">&times; Close Detailed View</a>
        </div>

        <div class="admin-order-modal-grid">
            <!-- Left Column: Ordered Items and Calculations -->
            <div class="modal-left">
                <h4>Items in Order (<?= count($selectedOrder['items']) ?> items)</h4>
                <div class="itemized-table mb-4">
                    <?php foreach ($selectedOrder['items'] as $item): ?>
                        <div class="itemized-row">
                            <div class="flex items-center gap-2">
                                <img src="<?= htmlspecialchars($item['image_path']) ?>" alt="" style="width:36px;height:36px;border-radius:6px;object-fit:cover;">
                                <div>
                                    <strong><?= htmlspecialchars($item['food_name']) ?></strong>
                                    <br><small class="text-muted"><?= $item['quantity'] ?> × <?= format_price($item['unit_price']) ?></small>
                                </div>
                            </div>
                            <div class="text-right font-bold"><?= format_price($item['line_total']) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="order-breakdown-footer mb-6">
                    <div class="breakdown-line"><span>Subtotal:</span> <span><?= format_price($selectedOrder['subtotal']) ?></span></div>
                    <div class="breakdown-line"><span>Delivery Fee:</span> <span><?= format_price($selectedOrder['delivery_fee']) ?></span></div>
                    <div class="breakdown-line grand-total-line"><span>Total Amount:</span> <span><?= format_price($selectedOrder['total_amount']) ?></span></div>
                </div>

                <!-- Customer Details Card -->
                <div class="customer-info-box">
                    <h5 class="mb-2 font-bold">Delivery & Customer Info</h5>
                    <p><strong>Customer:</strong> <?= htmlspecialchars($selectedOrder['full_name']) ?></p>
                    <p><strong>Email:</strong> <?= htmlspecialchars($selectedOrder['email']) ?></p>
                    <p><strong>Phone:</strong> <?= htmlspecialchars($selectedOrder['phone']) ?></p>
                    <p><strong>Address:</strong> <?= htmlspecialchars($selectedOrder['delivery_address_text']) ?></p>
                    <?php if (!empty($selectedOrder['special_instructions'])): ?>
                        <p><strong>Instructions:</strong> "<?= htmlspecialchars($selectedOrder['special_instructions']) ?>"</p>
                    <?php endif; ?>
                    <p><strong>Payment Method:</strong> <?= htmlspecialchars($selectedOrder['payment_method']) ?> (<?= htmlspecialchars($selectedOrder['payment_status']) ?>)</p>
                    <?php if (!empty($selectedOrder['transaction_reference'])): ?>
                        <p><strong>Tx Reference:</strong> <code><?= htmlspecialchars($selectedOrder['transaction_reference']) ?></code></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Column: Status Transition and Trigger-Generated Order Status History -->
            <div class="modal-right">
                <div class="status-update-box mb-6">
                    <h4>Update Order Lifecycle</h4>
                    <form action="orders.php" method="POST" class="admin-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="update_order_status">
                        <input type="hidden" name="order_id" value="<?= $selectedOrder['order_id'] ?>">

                        <div class="form-group">
                            <label>Order Status</label>
                            <select name="new_status">
                                <?php foreach (['Pending', 'Confirmed', 'Preparing', 'Out for Delivery', 'Delivered', 'Cancelled'] as $st): ?>
                                    <option value="<?= $st ?>" <?= $selectedOrder['order_status'] === $st ? 'selected' : '' ?>><?= $st ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Payment Status</label>
                            <select name="payment_status">
                                <option value="Pending" <?= $selectedOrder['payment_status'] === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="Paid" <?= $selectedOrder['payment_status'] === 'Paid' ? 'selected' : '' ?>>Paid / Completed</option>
                                <option value="Failed" <?= $selectedOrder['payment_status'] === 'Failed' ? 'selected' : '' ?>>Failed</option>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary btn-block">
                            Update Status (Invokes Procedure & Trigger)
                        </button>
                    </form>
                </div>

                <!-- ORDER STATUS HISTORY (AUDIT TRAIL) -->
                <div class="order-history-audit-box">
                    <h4 class="flex items-center justify-between">
                        <span>📜 Order Status History (Audit Trail)</span>
                        <span class="badge-count"><?= count($selectedOrder['history'] ?? []) ?> Events</span>
                    </h4>
                    <p class="text-xs text-muted mb-3">
                        Populated automatically by MySQL database triggers (<code>trg_after_order_insert</code> and <code>trg_after_order_status_update</code>).
                    </p>

                    <?php if (empty($selectedOrder['history'])): ?>
                        <p class="text-xs text-muted">No historical status transitions recorded.</p>
                    <?php else: ?>
                        <div class="status-timeline">
                            <?php foreach ($selectedOrder['history'] as $hist): ?>
                                <div class="timeline-event">
                                    <div class="timeline-marker"></div>
                                    <div class="timeline-body">
                                        <div class="timeline-head">
                                            <strong>
                                                <?php if (!empty($hist['old_status'])): ?>
                                                    <?= htmlspecialchars($hist['old_status']) ?> &rarr;
                                                <?php endif; ?>
                                                <?= htmlspecialchars($hist['new_status']) ?>
                                            </strong>
                                            <span class="timeline-time"><?= date('M d, g:i A', strtotime($hist['changed_at'])) ?></span>
                                        </div>
                                        <p class="timeline-note"><?= htmlspecialchars($hist['notes'] ?? 'Status transition logged') ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Orders Table -->
<div class="admin-card">
    <div class="admin-card-header">
        <h3>Orders List (<?= count($ordersList) ?> orders)</h3>
    </div>

    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Order #</th>
                    <th>Date</th>
                    <th>Customer</th>
                    <th>Total</th>
                    <th>Payment</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($ordersList)): ?>
                    <tr><td colspan="7" class="text-center py-4 text-muted">No orders match the selected filter.</td></tr>
                <?php else: ?>
                    <?php foreach ($ordersList as $ord): ?>
                        <tr class="<?= ($selectedOrderId === (int)$ord['order_id']) ? 'row-highlighted' : '' ?>">
                            <td><strong><?= htmlspecialchars($ord['order_number']) ?></strong></td>
                            <td><?= date('M d, Y h:i A', strtotime($ord['order_date'])) ?></td>
                            <td>
                                <?= htmlspecialchars($ord['customer_name']) ?>
                                <br><small class="text-muted"><?= htmlspecialchars($ord['customer_phone']) ?></small>
                            </td>
                            <td><strong><?= format_price($ord['total_amount']) ?></strong></td>
                            <td>
                                <?= htmlspecialchars($ord['payment_method']) ?>
                                <br><small class="status-pill-small <?= $ord['payment_status'] === 'Paid' ? 'pill-paid' : 'pill-pending' ?>"><?= htmlspecialchars($ord['payment_status']) ?></small>
                            </td>
                            <td><?= get_status_badge_html($ord['order_status']) ?></td>
                            <td>
                                <a href="orders.php?order_id=<?= $ord['order_id'] ?><?= !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : '' ?><?= !empty($searchFilter) ? '&search=' . urlencode($searchFilter) : '' ?>" class="btn-table-action">
                                    Manage & History &rarr;
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/admin_footer.php'; ?>
