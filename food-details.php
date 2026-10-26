<?php
/**
 * Food Details Page - SavorBite Online Food Ordering System
 */
require_once __DIR__ . '/includes/header.php';

$foodId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$food = get_food_by_id($pdo, $foodId);

if (!$food) {
    set_flash_message('error', 'Dish not found.');
    header("Location: menu.php");
    exit();
}

$pageTitle = htmlspecialchars($food['food_name']) . ' - SavorBite';

// Related items from same category
$relStmt = $pdo->prepare("
    SELECT * FROM food_menu_view 
    WHERE category_id = ? AND food_id != ? AND is_available = 1 
    LIMIT 3
");
$relStmt->execute([$food['category_id'], $foodId]);
$relatedFoods = $relStmt->fetchAll();

// Handle add to cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_to_cart') {
    if (!verify_csrf_token()) {
        set_flash_message('error', 'Security token expired. Please try again.');
    } else {
        $qty = max(1, (int)($_POST['quantity'] ?? 1));
        $result = add_to_cart($pdo, $foodId, $qty);
        if ($result['success']) {
            set_flash_message('success', "Added {$qty}x {$food['food_name']} to your cart!");
            header("Location: cart.php");
            exit();
        } else {
            set_flash_message('error', $result['message']);
        }
    }
}
?>

<div class="container details-container">
    <!-- Breadcrumb -->
    <nav class="breadcrumb-nav">
        <a href="index.php">Home</a> &gt; 
        <a href="menu.php">Menu</a> &gt; 
        <a href="menu.php?category=<?= $food['category_id'] ?>"><?= htmlspecialchars($food['category_name']) ?></a> &gt; 
        <span><?= htmlspecialchars($food['food_name']) ?></span>
    </nav>

    <div class="food-details-grid">
        <!-- Dish Imagery -->
        <div class="food-details-media">
            <img src="<?= htmlspecialchars($food['image_path']) ?>" alt="<?= htmlspecialchars($food['food_name']) ?>" class="details-main-img">
            <?php if ($food['is_popular']): ?>
                <span class="badge-featured">⭐ Chef's Signature</span>
            <?php endif; ?>
        </div>

        <!-- Dish Meta & Add to Cart -->
        <div class="food-details-info">
            <div class="details-header">
                <span class="category-badge-pill"><?= htmlspecialchars($food['category_name']) ?></span>
                <span class="stock-status <?= $food['is_available'] ? 'in-stock' : 'out-of-stock' ?>">
                    <?= $food['is_available'] ? '● In Kitchen & Ready' : '● Currently Sold Out' ?>
                </span>
            </div>

            <h1 class="details-title"><?= htmlspecialchars($food['food_name']) ?></h1>
            <p class="details-price"><?= format_price($food['price']) ?></p>

            <div class="details-description">
                <p><?= htmlspecialchars($food['description']) ?></p>
            </div>

            <div class="details-meta-highlights">
                <div class="meta-box">
                    <span class="meta-icon">⏱️</span>
                    <div>
                        <strong>Prep Time</strong>
                        <span>15-25 Mins</span>
                    </div>
                </div>
                <div class="meta-box">
                    <span class="meta-icon">🔥</span>
                    <div>
                        <strong>Serving</strong>
                        <span>Freshly Plated</span>
                    </div>
                </div>
                <div class="meta-box">
                    <span class="meta-icon">🌱</span>
                    <div>
                        <strong>Ingredients</strong>
                        <span>Pure & Sourced</span>
                    </div>
                </div>
            </div>

            <div class="details-action-box">
                <?php if ($food['is_available']): ?>
                    <form action="food-details.php?id=<?= $foodId ?>" method="POST" class="details-cart-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="add_to_cart">
                        
                        <div class="details-qty-wrapper">
                            <label>Quantity:</label>
                            <div class="qty-pill qty-pill-lg">
                                <button type="button" class="qty-minus" onclick="decrementDetailsQty()">-</button>
                                <input type="number" id="detail-qty" name="quantity" value="1" min="1" max="20" class="qty-input" readonly>
                                <button type="button" class="qty-plus" onclick="incrementDetailsQty()">+</button>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg btn-block">Add to Cart 🛒</button>
                    </form>
                <?php else: ?>
                    <button class="btn btn-disabled btn-lg btn-block" disabled>Currently Unavailable</button>
                    <p class="sold-out-note">Our kitchen is resting this item for today. Check back tomorrow!</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Related Dishes -->
    <?php if (!empty($relatedFoods)): ?>
        <section class="related-section">
            <h2 class="related-title">You Might Also Love</h2>
            <div class="food-grid">
                <?php foreach ($relatedFoods as $rel): ?>
                    <div class="food-card">
                        <div class="food-card-img-wrap">
                            <a href="food-details.php?id=<?= $rel['food_id'] ?>">
                                <img src="<?= htmlspecialchars($rel['image_path']) ?>" alt="<?= htmlspecialchars($rel['food_name']) ?>">
                            </a>
                            <span class="category-tag"><?= htmlspecialchars($rel['category_name']) ?></span>
                        </div>
                        <div class="food-card-body">
                            <h3 class="food-title">
                                <a href="food-details.php?id=<?= $rel['food_id'] ?>"><?= htmlspecialchars($rel['food_name']) ?></a>
                            </h3>
                            <span class="food-price"><?= format_price($rel['price']) ?></span>
                            <a href="food-details.php?id=<?= $rel['food_id'] ?>" class="btn btn-outline btn-sm btn-block">View Dish</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</div>

<script>
function incrementDetailsQty() {
    const input = document.getElementById('detail-qty');
    let val = parseInt(input.value) || 1;
    if (val < 20) input.value = val + 1;
}
function decrementDetailsQty() {
    const input = document.getElementById('detail-qty');
    let val = parseInt(input.value) || 1;
    if (val > 1) input.value = val - 1;
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
