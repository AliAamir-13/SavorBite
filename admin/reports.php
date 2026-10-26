<?php
/**
 * Admin Sales Reports & Analytics - SavorBite Online Food Ordering System
 * Demonstrates: Date-based Filtering, SQL Views (`sales_summary_view`), Complex Subqueries, Aggregations, CSV Export
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';

require_admin();
$pdo = getDbConnection();

// --- 1. Date Range Handling with Defaults ---
$today = date('Y-m-d');
$defaultStartDate = date('Y-m-d', strtotime('-30 days'));

$preset = $_GET['preset'] ?? '';
$startDate = $_GET['start_date'] ?? $defaultStartDate;
$endDate = $_GET['end_date'] ?? $today;

if ($preset === 'today') {
    $startDate = $today;
    $endDate = $today;
} elseif ($preset === '7days') {
    $startDate = date('Y-m-d', strtotime('-6 days'));
    $endDate = $today;
} elseif ($preset === '30days') {
    $startDate = date('Y-m-d', strtotime('-29 days'));
    $endDate = $today;
} elseif ($preset === 'this_month') {
    $startDate = date('Y-m-01');
    $endDate = $today;
} elseif ($preset === 'all') {
    $startDate = '2020-01-01';
    $endDate = $today;
}

// Ensure valid dates
if (strtotime($startDate) === false) $startDate = $defaultStartDate;
if (strtotime($endDate) === false) $endDate = $today;
if ($startDate > $endDate) {
    $temp = $startDate;
    $startDate = $endDate;
    $endDate = $temp;
}

// --- 2. CSV Export Handling ---
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $csvFilename = "sales_report_" . $startDate . "_to_" . $endDate . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $csvFilename);

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Sales Date', 'Total Orders', 'Delivered Orders', 'Cancelled Orders', 'Net Revenue ($)', 'Average Order Value ($)']);

    $exportStmt = $pdo->prepare("
        SELECT 
            DATE(order_date) AS sales_date,
            COUNT(order_id) AS total_orders,
            SUM(CASE WHEN order_status = 'Delivered' THEN 1 ELSE 0 END) AS delivered_orders,
            SUM(CASE WHEN order_status = 'Cancelled' THEN 1 ELSE 0 END) AS cancelled_orders,
            ROUND(SUM(CASE WHEN order_status != 'Cancelled' THEN total_amount ELSE 0 END), 2) AS net_revenue,
            ROUND(AVG(CASE WHEN order_status != 'Cancelled' THEN total_amount ELSE NULL END), 2) AS avg_order_val
        FROM orders
        WHERE DATE(order_date) BETWEEN ? AND ?
        GROUP BY DATE(order_date)
        ORDER BY sales_date DESC
    ");
    $exportStmt->execute([$startDate, $endDate]);

    while ($row = $exportStmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['sales_date'],
            $row['total_orders'],
            $row['delivered_orders'],
            $row['cancelled_orders'],
            number_format($row['net_revenue'], 2, '.', ''),
            number_format($row['avg_order_val'] ?? 0, 2, '.', '')
        ]);
    }
    fclose($output);
    exit();
}

$adminPageTitle = 'Date-based Sales Reports & Analytics';
require_once __DIR__ . '/admin_header.php';

// --- 3. Filtered Date-Range Metrics ---
$rangeStatsStmt = $pdo->prepare("
    SELECT 
        COUNT(order_id) AS total_orders,
        COALESCE(SUM(CASE WHEN order_status != 'Cancelled' THEN total_amount ELSE 0 END), 0) AS total_revenue,
        COALESCE(AVG(CASE WHEN order_status != 'Cancelled' THEN total_amount ELSE NULL END), 0) AS avg_order_val,
        SUM(CASE WHEN order_status = 'Delivered' THEN 1 ELSE 0 END) AS delivered_count,
        SUM(CASE WHEN order_status = 'Cancelled' THEN 1 ELSE 0 END) AS cancelled_count
    FROM orders
    WHERE DATE(order_date) BETWEEN ? AND ?
");
$rangeStatsStmt->execute([$startDate, $endDate]);
$rangeStats = $rangeStatsStmt->fetch();

// --- 4. Daily Breakdown for Selected Date Range ---
$dailyBreakdownStmt = $pdo->prepare("
    SELECT 
        DATE(order_date) AS sales_date,
        COUNT(order_id) AS total_orders,
        SUM(CASE WHEN order_status = 'Delivered' THEN 1 ELSE 0 END) AS delivered_orders,
        SUM(CASE WHEN order_status = 'Cancelled' THEN 1 ELSE 0 END) AS cancelled_orders,
        ROUND(SUM(CASE WHEN order_status != 'Cancelled' THEN total_amount ELSE 0 END), 2) AS net_revenue,
        ROUND(AVG(CASE WHEN order_status != 'Cancelled' THEN total_amount ELSE NULL END), 2) AS average_order_value
    FROM orders
    WHERE DATE(order_date) BETWEEN ? AND ?
    GROUP BY DATE(order_date)
    ORDER BY sales_date DESC
");
$dailyBreakdownStmt->execute([$startDate, $endDate]);
$dailySales = $dailyBreakdownStmt->fetchAll();

// --- 5. Category Performance in Selected Date Range ---
$categorySalesStmt = $pdo->prepare("
    SELECT 
        c.category_name,
        COUNT(DISTINCT oi.order_id) AS orders_count,
        COALESCE(SUM(oi.quantity), 0) AS units_sold,
        COALESCE(SUM(oi.line_total), 0) AS category_revenue
    FROM categories c
    LEFT JOIN food_items f ON c.category_id = f.category_id
    LEFT JOIN order_items oi ON f.food_id = oi.food_id
    LEFT JOIN orders o ON oi.order_id = o.order_id 
        AND o.order_status != 'Cancelled'
        AND DATE(o.order_date) BETWEEN ? AND ?
    GROUP BY c.category_id, c.category_name
    ORDER BY category_revenue DESC
");
$categorySalesStmt->execute([$startDate, $endDate]);
$categorySales = $categorySalesStmt->fetchAll();

// --- 6. Top Selling Dishes in Selected Date Range ---
$topDishesStmt = $pdo->prepare("
    SELECT 
        f.food_name,
        c.category_name,
        f.price,
        f.image_path,
        COALESCE(SUM(oi.quantity), 0) AS units_sold,
        COALESCE(SUM(oi.line_total), 0) AS revenue_generated
    FROM food_items f
    INNER JOIN categories c ON f.category_id = c.category_id
    INNER JOIN order_items oi ON f.food_id = oi.food_id
    INNER JOIN orders o ON oi.order_id = o.order_id
    WHERE o.order_status != 'Cancelled' 
      AND DATE(o.order_date) BETWEEN ? AND ?
    GROUP BY f.food_id, f.food_name, c.category_name, f.price, f.image_path
    ORDER BY units_sold DESC
    LIMIT 5
");
$topDishesStmt->execute([$startDate, $endDate]);
$topDishes = $topDishesStmt->fetchAll();

// Catalog average price for reference subquery demo
$avgItemPrice = (float)$pdo->query("SELECT AVG(price) FROM food_items")->fetchColumn();
?>

<!-- Date Range Selection Toolbar -->
<div class="admin-card mb-6">
    <div class="admin-card-header">
        <div>
            <h3>📅 Date-Based Sales Reports</h3>
            <p class="text-sm text-muted">Select a custom date range or preset to inspect sales revenue, order volumes, and cuisine breakdown.</p>
        </div>
        <div class="header-actions">
            <a href="reports.php?export=csv&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>" class="btn btn-outline btn-sm">
                📥 Export CSV Report
            </a>
        </div>
    </div>

    <!-- Quick Presets -->
    <div class="filter-presets-bar mb-4">
        <span class="preset-label">Quick Presets:</span>
        <a href="reports.php?preset=today" class="preset-btn <?= ($preset === 'today' || ($startDate === $today && $endDate === $today)) ? 'active' : '' ?>">Today</a>
        <a href="reports.php?preset=7days" class="preset-btn <?= ($preset === '7days') ? 'active' : '' ?>">Last 7 Days</a>
        <a href="reports.php?preset=30days" class="preset-btn <?= ($preset === '30days' || ($startDate === $defaultStartDate && $endDate === $today && empty($preset))) ? 'active' : '' ?>">Last 30 Days</a>
        <a href="reports.php?preset=this_month" class="preset-btn <?= ($preset === 'this_month') ? 'active' : '' ?>">This Month</a>
        <a href="reports.php?preset=all" class="preset-btn <?= ($preset === 'all') ? 'active' : '' ?>">All Time</a>
    </div>

    <!-- Custom Date Filter Form -->
    <form action="reports.php" method="GET" class="date-filter-form">
        <div class="filter-inputs-group">
            <div class="filter-field">
                <label>From Date:</label>
                <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>" required>
            </div>
            <div class="filter-field">
                <label>To Date:</label>
                <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>" required>
            </div>
            <button type="submit" class="btn btn-primary">Apply Date Filter</button>
        </div>
        <span class="date-range-display">
            Showing records between <strong><?= date('M d, Y', strtotime($startDate)) ?></strong> and <strong><?= date('M d, Y', strtotime($endDate)) ?></strong>
        </span>
    </form>
</div>

<!-- Range KPI Cards -->
<div class="metrics-grid mb-6">
    <div class="metric-card">
        <div class="metric-icon bg-emerald-light">💵</div>
        <div class="metric-info">
            <span class="metric-label">Net Sales Revenue</span>
            <strong class="metric-value"><?= format_price($rangeStats['total_revenue']) ?></strong>
            <span class="metric-sub"><?= number_format($rangeStats['delivered_count']) ?> fulfilled orders</span>
        </div>
    </div>

    <div class="metric-card">
        <div class="metric-icon bg-blue-light">📦</div>
        <div class="metric-info">
            <span class="metric-label">Total Orders Placed</span>
            <strong class="metric-value"><?= number_format($rangeStats['total_orders']) ?></strong>
            <span class="metric-sub"><?= number_format($rangeStats['cancelled_count']) ?> cancelled</span>
        </div>
    </div>

    <div class="metric-card">
        <div class="metric-icon bg-purple-light">📊</div>
        <div class="metric-info">
            <span class="metric-label">Average Order Value (AOV)</span>
            <strong class="metric-value"><?= format_price($rangeStats['avg_order_val']) ?></strong>
            <span class="metric-sub">Across period</span>
        </div>
    </div>

    <div class="metric-card">
        <div class="metric-icon bg-amber-light">🎯</div>
        <div class="metric-info">
            <span class="metric-label">Fulfillment Rate</span>
            <strong class="metric-value">
                <?= $rangeStats['total_orders'] > 0 ? round(($rangeStats['delivered_count'] / $rangeStats['total_orders']) * 100, 1) : 0 ?>%
            </strong>
            <span class="metric-sub">Completion success</span>
        </div>
    </div>
</div>

<div class="admin-grid-2col mb-6">
    <!-- Daily Sales Performance Table -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h3>Daily Sales Breakdown (<?= count($dailySales) ?> active days)</h3>
        </div>
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Total Orders</th>
                        <th>Delivered</th>
                        <th>Cancelled</th>
                        <th>Net Revenue</th>
                        <th>Avg Value</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($dailySales)): ?>
                        <tr><td colspan="6" class="text-center py-4 text-muted">No orders recorded in this date range.</td></tr>
                    <?php else: ?>
                        <?php foreach ($dailySales as $day): ?>
                            <tr>
                                <td><strong><?= date('M d, Y', strtotime($day['sales_date'])) ?></strong></td>
                                <td><?= $day['total_orders'] ?></td>
                                <td><span class="text-emerald font-bold"><?= $day['delivered_orders'] ?></span></td>
                                <td><span class="text-danger"><?= $day['cancelled_orders'] ?></span></td>
                                <td><strong><?= format_price($day['net_revenue']) ?></strong></td>
                                <td><?= format_price($day['average_order_value'] ?? 0) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Category Revenue Contribution in Selected Range -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h3>Cuisine Revenue in Selected Range</h3>
        </div>
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Cuisine Category</th>
                        <th>Units Sold</th>
                        <th>Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categorySales as $cs): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($cs['category_name']) ?></strong></td>
                            <td><?= number_format($cs['units_sold']) ?> units</td>
                            <td><strong><?= format_price($cs['category_revenue']) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Top Dishes in Selected Range -->
<?php if (!empty($topDishes)): ?>
    <div class="admin-card mb-6">
        <div class="admin-card-header">
            <h3>Top Performing Dishes in Selected Period</h3>
        </div>
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Dish</th>
                        <th>Cuisine</th>
                        <th>Menu Price</th>
                        <th>Units Sold</th>
                        <th>Revenue Generated</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topDishes as $td): ?>
                        <tr>
                            <td>
                                <div class="user-table-cell">
                                    <img src="<?= htmlspecialchars($td['image_path']) ?>" alt="" style="width:36px;height:36px;border-radius:6px;object-fit:cover;">
                                    <strong><?= htmlspecialchars($td['food_name']) ?></strong>
                                </div>
                            </td>
                            <td><span class="category-pill"><?= htmlspecialchars($td['category_name']) ?></span></td>
                            <td><?= format_price($td['price']) ?></td>
                            <td><strong class="text-emerald"><?= $td['units_sold'] ?> units</strong></td>
                            <td><strong><?= format_price($td['revenue_generated']) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/admin_footer.php'; ?>
