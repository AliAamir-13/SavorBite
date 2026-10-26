<?php
/**
 * Global Header Component
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/csrf.php';

$pdo = getDbConnection();
$currentUser = current_user();
$cartCount = get_cart_item_count($pdo);
$flashMessages = get_flash_messages();
$pageTitle = $pageTitle ?? 'SavorBite - Fresh & Delicious Food Ordering';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <!-- Google Fonts: Inter (UI & Body) and Manrope (Headings & Titles) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
    <!-- Main Style -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <!-- Top announcement banner -->
    <aside aria-label="Announcement" class="announcement-bar">
        <p>✨ Free delivery on all orders over ₹499 | Use code <strong>FRESHBITES</strong></p>
    </aside>

    <!-- Main Navigation Bar -->
    <header class="site-header">
        <div class="container header-container">
            <!-- Brand Logo -->
            <a href="index.php" class="brand-logo">
                <span class="logo-icon">🍽️</span>
                <span class="brand-text">Savor<span>Bite</span></span>
            </a>

            <!-- Search Form -->
            <form action="menu.php" method="GET" class="nav-search-form">
                <input type="text" name="search" placeholder="Search dishes, pizzas, burgers..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                <button type="submit" aria-label="Search">🔍</button>
            </form>

            <!-- Navigation Links -->
            <nav class="nav-links">
                <a href="index.php" class="<?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>">Home</a>
                <a href="menu.php" class="<?= basename($_SERVER['PHP_SELF']) == 'menu.php' ? 'active' : '' ?>">Menu</a>
                <?php if (is_logged_in()): ?>
                    <a href="my-orders.php" class="<?= basename($_SERVER['PHP_SELF']) == 'my-orders.php' ? 'active' : '' ?>">My Orders</a>
                    <?php if (is_admin()): ?>
                        <a href="admin/dashboard.php" class="admin-badge-link">Admin Panel</a>
                    <?php endif; ?>
                <?php endif; ?>
            </nav>

            <!-- User & Cart Actions -->
            <div class="nav-actions">
                <!-- Cart Button -->
                <a href="cart.php" class="cart-btn" aria-label="Shopping Cart">
                    <span class="cart-icon">🛒</span>
                    <span class="cart-label">Cart</span>
                    <span class="cart-count" id="cart-badge-count"><?= $cartCount ?></span>
                </a>

                <!-- User Dropdown or Auth Links -->
                <?php if (is_logged_in()): ?>
                    <div class="user-menu-dropdown">
                        <button class="user-dropdown-btn">
                            <span class="user-avatar"><?= strtoupper(substr($currentUser['full_name'], 0, 1)) ?></span>
                            <span class="user-name"><?= htmlspecialchars($currentUser['full_name']) ?></span>
                            <span class="chevron">▾</span>
                        </button>
                        <div class="dropdown-menu">
                            <div class="dropdown-header">
                                <p class="signed-in-text">Signed in as</p>
                                <p class="user-email-text"><?= htmlspecialchars($currentUser['email']) ?></p>
                            </div>
                            <hr>
                            <a href="my-orders.php">📦 My Order History</a>
                            <?php if (is_admin()): ?>
                                <a href="admin/dashboard.php">⚙️ Admin Dashboard</a>
                            <?php endif; ?>
                            <hr>
                            <a href="logout.php" class="logout-link">🚪 Sign Out</a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="auth-buttons">
                        <a href="login.php" class="btn btn-outline btn-sm">Sign In</a>
                        <a href="register.php" class="btn btn-primary btn-sm">Register</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Global Toast / Flash Messages -->
    <?php if (!empty($flashMessages)): ?>
        <div class="flash-messages-container container">
            <?php foreach ($flashMessages as $flash): ?>
                <div class="flash-alert flash-<?= htmlspecialchars($flash['type']) ?>">
                    <span><?= htmlspecialchars($flash['message']) ?></span>
                    <button type="button" class="flash-close" onclick="this.parentElement.remove();">&times;</button>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Main Page Content Wrapper -->
    <main class="main-content">
