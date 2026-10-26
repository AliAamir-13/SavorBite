<?php
/**
 * Admin Dashboard - SavorBite Online Food Ordering System
 * Demonstrates: SQL Aggregate Functions (COUNT, SUM, AVG), Multi-table JOINS,
 * and Date-filtered Quick Insights.
 */
$adminPageTitle = 'Dashboard Overview';
require_once __DIR__ . '/admin_header.php';

// Overall aggregate metrics queries
$totalSales = (float)$pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE order_status != 'Cancelled'")->fetchColumn();
$totalOrders = (int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$totalFoods = (int)$pdo->query("SELECT COUNT(*) FROM food_items")->fetchColumn();
$totalCustomers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn();
$pendingOrdersCount = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE order_status = 'Pending'")->fetchColumn();

// Today's snapshot
$todaySales = (float)$pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE order_status != 'Cancelled' AND DATE(order_date) = CURDATE()")->fetchColumn();
$todayOrders = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE DATE(order_date) = CURDATE()")->fetchColumn();

// Recent orders using View
$recentOrdersStmt = $pdo->query("
    SELECT * FROM order_summary_view 
    ORDER BY order_date DESC 
    LIMIT 6
");
$recentOrders = $recentOrdersStmt->fetchAll();

// Top selling dishes using JOIN and GROUP BY
$topDishesStmt = $pdo->query("
    SELECT 
        f.food_id,
        f.food_name,
        f.price,
        f.image_path,
        c.category_name,
        COALESCE(SUM(oi.quantity), 0) AS total_sold,
        COALESCE(SUM(oi.line_total), 0) AS revenue
    FROM food_items f
    INNER JOIN categories c ON f.category_id = c.category_id
    LEFT JOIN order_items oi ON f.food_id = oi.food_id
    LEFT JOIN orders o ON oi.order_id = o.order_id AND o.order_status != 'Cancelled'
    GROUP BY f.food_id, f.food_name, f.price, f.image_path, c.category_name
    ORDER BY total_sold DESC
    LIMIT 5
");
$topDishes = $topDishesStmt->fetchAll();
?>

<!-- Metric Summary Cards -->
<div class="metrics-grid">
    <div class="metric-card">
        <div class="metric-icon bg-emerald-light">💰</div>
        <div class="metric-info">
            <span class="metric-label">Net Sales Revenue</span>
            <strong class="metric-value"><?= format_price($totalSales) ?></strong>
            <span class="metric-sub"><?= format_price($todaySales) ?> recorded today</span>
        </div>
    </div>

    <div class="metric-card">
        <div class="metric-icon bg-blue-light">📦</div>
        <div class="metric-info">
            <span class="metric-label">Total Orders</span>
            <strong class="metric-value"><?= number_format($totalOrders) ?></strong>
            <span class="metric-sub"><?= $pendingOrdersCount ?> pending fulfillment</span>
        </div>
    </div>

    <div class="metric-card">
        <div class="metric-icon bg-amber-light">🍕</div>
        <div class="metric-info">
            <span class="metric-label">Active Menu Items</span>
            <strong class="metric-value"><?= number_format($totalFoods) ?> dishes</strong>
            <span class="metric-sub">Across 6 cuisine categories</span>
        </div>
    </div>

    <div class="metric-card">
        <div class="metric-icon bg-purple-light">👥</div>
        <div class="metric-info">
            <span class="metric-label">Registered Customers</span>
            <strong class="metric-value"><?= number_format($totalCustomers) ?> verified</strong>
            <span class="metric-sub">Active buyer accounts</span>
        </div>
    </div>
</div>

<!-- Quick Shortcuts Banner -->
<div class="admin-card mb-6 bg-white flex items-center justify-between flex-wrap gap-4 p-4">
    <div class="flex items-center gap-3">
        <span class="text-2xl">⚡</span>
        <div>
            <strong class="text-sm">Operations Quick Actions</strong>
            <p class="text-xs text-muted">Direct links to high-frequency management tools.</p>
        </div>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
        <a href="orders.php?status=Pending" class="btn btn-outline btn-sm">
            ⏳ View Pending Orders (<?= $pendingOrdersCount ?>)
        </a>
        <a href="foods.php" class="btn btn-outline btn-sm">
            ➕ Add Menu Item
        </a>
        <a href="reports.php?preset=today" class="btn btn-primary btn-sm">
            📊 Today's Sales Report
        </a>
    </div>
</div>

<div class="admin-grid-2col">
    <!-- Recent Orders Table -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h3>Recent Customer Orders</h3>
            <a href="orders.php" class="card-link">View All Orders &rarr;</a>
        </div>

        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Customer</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentOrders)): ?>
                        <tr><td colspan="5" class="text-center py-4">No orders placed yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentOrders as $ro): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($ro['order_number']) ?></strong>
                                    <br><small class="text-muted"><?= date('M d, g:i A', strtotime($ro['order_date'])) ?></small>
                                </td>
                                <td>
                                    <?= htmlspecialchars($ro['customer_name']) ?>
                                    <br><small class="text-muted"><?= htmlspecialchars($ro['customer_email']) ?></small>
                                </td>
                                <td><strong><?= format_price($ro['total_amount']) ?></strong></td>
                                <td><?= get_status_badge_html($ro['order_status']) ?></td>
                                <td>
                                    <a href="orders.php?order_id=<?= $ro['order_id'] ?>" class="btn-table-action">Manage &rarr;</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Top Selling Dishes -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h3>Top Performing Dishes</h3>
            <a href="foods.php" class="card-link">Manage Menu &rarr;</a>
        </div>

        <div class="top-dishes-list">
            <?php foreach ($topDishes as $td): ?>
                <div class="top-dish-item">
                    <img src="<?= htmlspecialchars($td['image_path']) ?>" alt="<?= htmlspecialchars($td['food_name']) ?>" class="top-dish-img">
                    <div class="top-dish-info">
                        <strong><?= htmlspecialchars($td['food_name']) ?></strong>
                        <span class="dish-cat"><?= htmlspecialchars($td['category_name']) ?> &bull; <?= format_price($td['price']) ?></span>
                    </div>
                    <div class="top-dish-stats">
                        <span class="units-sold"><?= $td['total_sold'] ?> sold</span>
                        <span class="rev-val"><?= format_price($td['revenue']) ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/admin_footer.php'; ?>
