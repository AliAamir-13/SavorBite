<?php
/**
 * Admin Customers Overview - SavorBite Online Food Ordering System
 * Demonstrates: SQL Aggregates (COUNT, SUM, MAX) with GROUP BY over JOINed tables
 */
$adminPageTitle = 'Customer Accounts';
require_once __DIR__ . '/admin_header.php';

// Retrieve customers with their order history stats
$usersStmt = $pdo->query("
    SELECT 
        u.user_id,
        u.full_name,
        u.email,
        u.phone,
        u.created_at AS registered_at,
        COUNT(o.order_id) AS total_orders,
        COALESCE(SUM(CASE WHEN o.order_status != 'Cancelled' THEN o.total_amount ELSE 0 END), 0) AS total_spent,
        MAX(o.order_date) AS latest_order_date
    FROM users u
    LEFT JOIN orders o ON u.user_id = o.user_id
    WHERE u.role = 'customer'
    GROUP BY u.user_id, u.full_name, u.email, u.phone, u.created_at
    ORDER BY total_spent DESC, u.created_at DESC
");
$customers = $usersStmt->fetchAll();
?>

<div class="admin-card">
    <div class="admin-card-header">
        <h3>Customer Directory (<?= count($customers) ?> registered)</h3>
    </div>

    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Customer Name</th>
                    <th>Contact Info</th>
                    <th>Total Orders</th>
                    <th>Lifetime Spend</th>
                    <th>Last Order Date</th>
                    <th>Member Since</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($customers as $c): ?>
                    <tr>
                        <td>
                            <div class="user-table-cell">
                                <span class="user-cell-avatar"><?= strtoupper(substr($c['full_name'], 0, 1)) ?></span>
                                <div>
                                    <strong><?= htmlspecialchars($c['full_name']) ?></strong>
                                    <br><small class="text-muted">ID: #<?= $c['user_id'] ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <a href="mailto:<?= htmlspecialchars($c['email']) ?>"><?= htmlspecialchars($c['email']) ?></a>
                            <br><small class="text-muted"><?= htmlspecialchars($c['phone']) ?></small>
                        </td>
                        <td>
                            <span class="badge-count"><?= $c['total_orders'] ?> orders</span>
                        </td>
                        <td>
                            <strong class="text-emerald"><?= format_price($c['total_spent']) ?></strong>
                        </td>
                        <td>
                            <?= $c['latest_order_date'] ? date('M d, Y', strtotime($c['latest_order_date'])) : '<span class="text-muted">None</span>' ?>
                        </td>
                        <td>
                            <?= date('M d, Y', strtotime($c['registered_at'])) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/admin_footer.php'; ?>
