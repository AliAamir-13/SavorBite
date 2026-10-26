<?php
/**
 * Admin Panel Header Component
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';

require_admin();

$pdo = getDbConnection();
$adminUser = current_user();
$flashMessages = get_flash_messages();
$adminPageTitle = $adminPageTitle ?? 'Admin Control Center - SavorBite';
$activePage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($adminPageTitle) ?></title>
    <!-- Google Fonts: Inter (UI & Body) and Manrope (Headings & Metrics) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin-body">
    <div class="admin-layout">
        <!-- Sidebar Navigation -->
        <aside class="admin-sidebar">
            <div class="admin-sidebar-header">
                <a href="../index.php" class="admin-brand">
                    <span class="brand-emoji">🍽️</span>
                    <span class="brand-name">Savor<span>Bite</span> <small class="admin-tag">Admin</small></span>
                </a>
            </div>

            <nav class="admin-nav">
                <a href="dashboard.php" class="admin-nav-item <?= $activePage === 'dashboard.php' ? 'active' : '' ?>">
                    <span class="nav-icon">📊</span>
                    <span>Dashboard</span>
                </a>
                <a href="orders.php" class="admin-nav-item <?= $activePage === 'orders.php' ? 'active' : '' ?>">
                    <span class="nav-icon">📦</span>
                    <span>Customer Orders</span>
                </a>
                <a href="foods.php" class="admin-nav-item <?= $activePage === 'foods.php' ? 'active' : '' ?>">
                    <span class="nav-icon">🍕</span>
                    <span>Menu Items</span>
                </a>
                <a href="categories.php" class="admin-nav-item <?= $activePage === 'categories.php' ? 'active' : '' ?>">
                    <span class="nav-icon">📂</span>
                    <span>Categories</span>
                </a>
                <a href="users.php" class="admin-nav-item <?= $activePage === 'users.php' ? 'active' : '' ?>">
                    <span class="nav-icon">👥</span>
                    <span>Customers</span>
                </a>
                <a href="reports.php" class="admin-nav-item <?= $activePage === 'reports.php' ? 'active' : '' ?>">
                    <span class="nav-icon">📈</span>
                    <span>Sales Analytics</span>
                </a>
            </nav>

            <div class="admin-sidebar-footer">
                <a href="../index.php" class="btn-back-store" target="_blank">🌐 View Storefront &nearr;</a>
                <div class="admin-user-pill">
                    <span class="admin-avatar">A</span>
                    <div class="admin-user-text">
                        <strong><?= htmlspecialchars($adminUser['full_name']) ?></strong>
                        <small><?= htmlspecialchars($adminUser['email']) ?></small>
                    </div>
                </div>
                <a href="../logout.php" class="btn-admin-logout">🚪 Sign Out</a>
            </div>
        </aside>

        <!-- Main Admin Canvas -->
        <div class="admin-main-canvas">
            <header class="admin-topbar">
                <div class="topbar-title">
                    <h2><?= htmlspecialchars($adminPageTitle) ?></h2>
                </div>
                <div class="topbar-actions">
                    <span class="current-time-badge"><?= date('l, F j, Y') ?></span>
                </div>
            </header>

            <div class="admin-content-area">
                <?php if (!empty($flashMessages)): ?>
                    <div class="admin-flashes">
                        <?php foreach ($flashMessages as $flash): ?>
                            <div class="flash-alert flash-<?= htmlspecialchars($flash['type']) ?>">
                                <span><?= htmlspecialchars($flash['message']) ?></span>
                                <button type="button" class="flash-close" onclick="this.parentElement.remove();">&times;</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
