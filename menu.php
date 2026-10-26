<?php
/**
 * Menu Page - SavorBite Online Food Ordering System
 * Demonstrates: Dynamic SQL Filtering, Search, Sorting, and SQL Views
 */
$pageTitle = 'Our Menu - SavorBite Restaurant';
require_once __DIR__ . '/includes/header.php';

// Retrieve filter query parameters
$categoryId = isset($_GET['category']) ? (int)$_GET['category'] : null;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'popular';
$onlyAvailable = isset($_GET['available_only']) && $_GET['available_only'] === '1';

// Fetch categories for category pills
$categories = get_categories($pdo);

// Fetch food items based on filters using View & Prepared Statement
$menuItems = get_menu_items($pdo, $categoryId, $search, $sort, $onlyAvailable);

// Handle add to cart form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_to_cart') {
    if (!verify_csrf_token()) {
        set_flash_message('error', 'Invalid form submission. Please try again.');
    } else {
        $foodId = (int)($_POST['food_id'] ?? 0);
        $qty = (int)($_POST['quantity'] ?? 1);
        $result = add_to_cart($pdo, $foodId, $qty);
        if ($result['success']) {
            set_flash_message('success', 'Added to cart successfully!');
        } else {
            set_flash_message('error', $result['message']);
        }
    }
    // Retain current filter query string on redirect
    $queryString = $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '';
    header("Location: menu.php" . $queryString);
    exit();
}
?>

<div class="menu-page-header">
    <div class="container text-center">
        <h1 class="page-title">Explore Our Full Menu</h1>
        <p class="page-subtitle">From stone-baked pizzas to fragrant curries and handcrafted beverages. Freshly prepared to order.</p>
    </div>
</div>

<div class="container menu-container">
    <!-- Category Filter Tabs -->
    <div class="category-filter-nav">
        <a href="menu.php<?= !empty($search) ? '?search=' . urlencode($search) : '' ?>" 
           class="cat-filter-btn <?= empty($categoryId) ? 'active' : '' ?>">
           🍽️ All Items
        </a>
        <?php foreach ($categories as $cat): ?>
            <a href="menu.php?category=<?= $cat['category_id'] ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($sort) ? '&sort=' . urlencode($sort) : '' ?>" 
               class="cat-filter-btn <?= ($categoryId == $cat['category_id']) ? 'active' : '' ?>">
               <?= htmlspecialchars($cat['category_name']) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Search & Sort Controls Toolbar -->
    <div class="menu-toolbar">
        <form action="menu.php" method="GET" class="toolbar-search-form">
            <?php if ($categoryId): ?>
                <input type="hidden" name="category" value="<?= $categoryId ?>">
            <?php endif; ?>
            
            <div class="search-input-wrap">
                <input type="text" name="search" placeholder="Search dishes by name or ingredients..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="search-btn">Search</button>
            </div>
            
            <?php if (!empty($search)): ?>
                <a href="menu.php<?= $categoryId ? '?category=' . $categoryId : '' ?>" class="clear-search-btn">Clear</a>
            <?php endif; ?>
        </form>

        <form action="menu.php" method="GET" class="toolbar-sort-form">
            <?php if ($categoryId): ?>
                <input type="hidden" name="category" value="<?= $categoryId ?>">
            <?php endif; ?>
            <?php if (!empty($search)): ?>
                <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
            <?php endif; ?>

            <div class="sort-select-wrap">
                <label for="sort-select">Sort by:</label>
                <select name="sort" id="sort-select" onchange="this.form.submit()">
                    <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>Most Popular</option>
                    <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: Low to High</option>
                    <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: High to Low</option>
                    <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Alphabetical (A-Z)</option>
                </select>
            </div>
        </form>
    </div>

    <!-- Results Status Count -->
    <div class="menu-results-bar">
        <p>Showing <strong><?= count($menuItems) ?></strong> <?= count($menuItems) === 1 ? 'dish' : 'dishes' ?>
           <?php if (!empty($search)): ?> matching "<em><?= htmlspecialchars($search) ?></em>"<?php endif; ?>
        </p>
    </div>

    <!-- Food Items Grid -->
    <?php if (empty($menuItems)): ?>
        <div class="empty-state">
            <span class="empty-icon">🔍</span>
            <h3>No delicious dishes found</h3>
            <p>Try clearing your search query or choosing another cuisine category.</p>
            <a href="menu.php" class="btn btn-outline">Reset All Filters</a>
        </div>
    <?php else: ?>
        <div class="food-grid">
            <?php foreach ($menuItems as $dish): ?>
                <div class="food-card <?= !$dish['is_available'] ? 'item-unavailable' : '' ?>">
                    <div class="food-card-img-wrap">
                        <a href="food-details.php?id=<?= $dish['food_id'] ?>">
                            <img src="<?= htmlspecialchars($dish['image_path']) ?>" alt="<?= htmlspecialchars($dish['food_name']) ?>" loading="lazy">
                        </a>
                        <span class="category-tag"><?= htmlspecialchars($dish['category_name']) ?></span>
                        <?php if ($dish['is_popular']): ?>
                            <span class="popular-tag">🔥 Popular</span>
                        <?php endif; ?>
                        <?php if (!$dish['is_available']): ?>
                            <span class="sold-out-overlay">Sold Out</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="food-card-body">
                        <div class="food-card-header">
                            <h3 class="food-title">
                                <a href="food-details.php?id=<?= $dish['food_id'] ?>"><?= htmlspecialchars($dish['food_name']) ?></a>
                            </h3>
                            <span class="food-price"><?= format_price($dish['price']) ?></span>
                        </div>
                        <p class="food-desc"><?= htmlspecialchars($dish['description']) ?></p>
                        
                        <div class="food-card-footer">
                            <?php if ($dish['is_available']): ?>
                                <form action="menu.php<?= !empty($_SERVER['QUERY_STRING']) ? '?' . htmlspecialchars($_SERVER['QUERY_STRING']) : '' ?>" method="POST" class="menu-add-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="add_to_cart">
                                    <input type="hidden" name="food_id" value="<?= $dish['food_id'] ?>">
                                    <div class="qty-pill">
                                        <button type="button" class="qty-minus" onclick="decrementQty(this)">-</button>
                                        <input type="number" name="quantity" value="1" min="1" max="20" class="qty-input" readonly>
                                        <button type="button" class="qty-plus" onclick="incrementQty(this)">+</button>
                                    </div>
                                    <button type="submit" class="btn btn-primary btn-sm">Add 🛒</button>
                                </form>
                            <?php else: ?>
                                <button type="button" class="btn btn-disabled btn-sm btn-block" disabled>Temporarily Unavailable</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
function incrementQty(btn) {
    const input = btn.parentElement.querySelector('.qty-input');
    let val = parseInt(input.value) || 1;
    if (val < 20) input.value = val + 1;
}
function decrementQty(btn) {
    const input = btn.parentElement.querySelector('.qty-input');
    let val = parseInt(input.value) || 1;
    if (val > 1) input.value = val - 1;
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
