<?php
/**
 * Home Page - SavorBite Online Food Ordering System
 */
$pageTitle = 'SavorBite - Delicious Food, Delivered to Your Door';
require_once __DIR__ . '/includes/header.php';

// Fetch categories and popular items from database
$categories = get_categories($pdo);

// Retrieve popular items using view
$stmt = $pdo->prepare("SELECT * FROM food_menu_view WHERE is_popular = 1 AND is_available = 1 LIMIT 8");
$stmt->execute();
$popularDishes = $stmt->fetchAll();

// Handle quick add to cart POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'quick_add') {
    if (!verify_csrf_token()) {
        set_flash_message('error', 'Security token mismatch. Please try again.');
    } else {
        $foodId = (int)($_POST['food_id'] ?? 0);
        $result = add_to_cart($pdo, $foodId, 1);
        if ($result['success']) {
            set_flash_message('success', 'Added to your cart!');
        } else {
            set_flash_message('error', $result['message']);
        }
    }
    header("Location: index.php");
    exit();
}
?>

<!-- Hero Section -->
<section class="hero-section">
    <div class="container hero-container">
        <div class="hero-content">
            <span class="hero-tagline">🔥 Handcrafted With Passion</span>
            <h1 class="hero-title">
                Delicious Food,<br>
                <span>Delivered To Your Door.</span>
            </h1>
            <p class="hero-subtitle">Satisfy your cravings with handcrafted artisan pizzas, flame-grilled burgers, authentic curries, and comforting ramen. Cooked fresh by top chefs.</p>
            
            <div class="hero-search-box">
                <form action="menu.php" method="GET" class="hero-form">
                    <span class="search-pin">📍</span>
                    <input type="text" name="search" placeholder="What are you craving today? (e.g., Pizza, Butter Chicken...)" required>
                    <button type="submit" class="btn btn-primary btn-lg">Search Food</button>
                </form>
            </div>

            <div class="hero-features">
                <div class="feature-item">
                    <span class="feat-icon">⚡</span>
                    <div>
                        <strong>Fast 30 Min Delivery</strong>
                        <span>Hot & fresh directly to you</span>
                    </div>
                </div>
                <div class="feature-item">
                    <span class="feat-icon">🌿</span>
                    <div>
                        <strong>100% Organic & Fresh</strong>
                        <span>Handpicked quality produce</span>
                    </div>
                </div>
                <div class="feature-item">
                    <span class="feat-icon">⭐</span>
                    <div>
                        <strong>4.9 Star Rating</strong>
                        <span>Over 15,000+ satisfied foodies</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="hero-media">
            <div class="hero-image-wrapper">
                <img src="https://images.unsplash.com/photo-1504674900247-0877df9cc836?w=800&auto=format&fit=crop&q=80" alt="Delicious Food Feast" class="hero-img">
                <div class="floating-order-card">
                    <span class="float-emoji">🍕</span>
                    <div>
                        <p class="float-title">Artisan Margherita</p>
                        <p class="float-sub">Freshly out of the oven</p>
                    </div>
                    <span class="float-price">₹199.00</span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Categories Section -->
<section class="section categories-section">
    <div class="container">
        <div class="section-header">
            <div>
                <span class="section-subtitle">Diverse Flavors</span>
                <h2 class="section-title">Explore by Category</h2>
            </div>
            <a href="menu.php" class="section-link">View All Categories &rarr;</a>
        </div>

        <div class="category-grid">
            <?php foreach ($categories as $cat): ?>
                <a href="menu.php?category=<?= $cat['category_id'] ?>" class="category-card">
                    <div class="category-img-wrap">
                        <img src="<?= htmlspecialchars($cat['image_url'] ?? 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=500') ?>" alt="<?= htmlspecialchars($cat['category_name']) ?>" loading="lazy">
                    </div>
                    <h3 class="category-name"><?= htmlspecialchars($cat['category_name']) ?></h3>
                    <p class="category-count">Explore &rarr;</p>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Popular Dishes Section -->
<section class="section popular-section">
    <div class="container">
        <div class="section-header">
            <div>
                <span class="section-subtitle">Customer Favorites</span>
                <h2 class="section-title">Chef's Recommended Dishes</h2>
            </div>
            <a href="menu.php?sort=popular" class="section-link">Browse Full Menu &rarr;</a>
        </div>

        <div class="food-grid">
            <?php foreach ($popularDishes as $dish): ?>
                <div class="food-card">
                    <div class="food-card-img-wrap">
                        <a href="food-details.php?id=<?= $dish['food_id'] ?>">
                            <img src="<?= htmlspecialchars($dish['image_path']) ?>" alt="<?= htmlspecialchars($dish['food_name']) ?>" loading="lazy">
                        </a>
                        <span class="category-tag"><?= htmlspecialchars($dish['category_name']) ?></span>
                        <?php if ($dish['is_popular']): ?>
                            <span class="popular-tag">🔥 Popular</span>
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
                            <form action="index.php" method="POST" class="quick-add-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="quick_add">
                                <input type="hidden" name="food_id" value="<?= $dish['food_id'] ?>">
                                <button type="submit" class="btn btn-primary btn-sm btn-block">Add to Cart 🛒</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Restaurant Experience Section -->
<section class="section experience-section">
    <div class="container experience-grid">
        <div class="exp-image">
            <img src="https://images.unsplash.com/photo-1555396273-367ea4eb4db5?w=800&auto=format&fit=crop&q=80" alt="Restaurant Interior" class="exp-img">
        </div>
        <div class="exp-content">
            <span class="section-subtitle">Why Food Lovers Choose Us</span>
            <h2 class="section-title">Crafted with Heart, Delivered with Care</h2>
            <p>Every dish at SavorBite begins with authentic recipes passed through generations, elevated with modern culinary techniques. We partner with local farmers to bring you pesticide-free, sustainably harvested ingredients.</p>
            
            <div class="perks-list">
                <div class="perk">
                    <span class="perk-check">✓</span>
                    <div>
                        <strong>Always Cooked to Order</strong>
                        <p>No pre-made heat-lamp food. We prepare your order the moment you click place.</p>
                    </div>
                </div>
                <div class="perk">
                    <span class="perk-check">✓</span>
                    <div>
                        <strong>Eco-Friendly Thermal Packaging</strong>
                        <p>100% compostable containers engineered to keep fried food crispy and soups boiling hot.</p>
                    </div>
                </div>
                <div class="perk">
                    <span class="perk-check">✓</span>
                    <div>
                        <strong>Live Order Tracking</strong>
                        <p>Real-time notifications from the kitchen stove to your front door.</p>
                    </div>
                </div>
            </div>

            <div class="exp-cta">
                <a href="menu.php" class="btn btn-primary btn-lg">Explore Menu & Order Now</a>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
