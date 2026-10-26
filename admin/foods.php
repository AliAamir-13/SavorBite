<?php
/**
 * Admin Food Items Management - SavorBite Online Food Ordering System
 * Demonstrates: CRUD Operations, Check Constraints (price > 0), Foreign Keys,
 * and Filtered Search by Category and Dish Name.
 */
$adminPageTitle = 'Menu Items Management';
require_once __DIR__ . '/admin_header.php';

$categories = get_categories($pdo);

// Handle Actions (Add, Edit, Toggle Availability, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        set_flash_message('error', 'Security token expired.');
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'add_food') {
            $name = trim($_POST['food_name'] ?? '');
            $categoryId = (int)($_POST['category_id'] ?? 0);
            $price = (float)($_POST['price'] ?? 0);
            $description = trim($_POST['description'] ?? '');
            $imagePath = trim($_POST['image_path'] ?? '');
            $isAvailable = isset($_POST['is_available']) ? 1 : 0;
            $isPopular = isset($_POST['is_popular']) ? 1 : 0;

            if (empty($name) || empty($description) || $price <= 0 || empty($imagePath) || $categoryId <= 0) {
                set_flash_message('error', 'Please fill in all required fields and ensure price is greater than 0.');
            } else {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO food_items (category_id, food_name, description, price, image_path, is_available, is_popular)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$categoryId, $name, $description, $price, $imagePath, $isAvailable, $isPopular]);
                    set_flash_message('success', "Dish '{$name}' added to menu successfully!");
                } catch (PDOException $e) {
                    set_flash_message('error', 'Database error: ' . $e->getMessage());
                }
            }
        } elseif ($action === 'edit_food') {
            $foodId = (int)($_POST['food_id'] ?? 0);
            $name = trim($_POST['food_name'] ?? '');
            $categoryId = (int)($_POST['category_id'] ?? 0);
            $price = (float)($_POST['price'] ?? 0);
            $description = trim($_POST['description'] ?? '');
            $imagePath = trim($_POST['image_path'] ?? '');
            $isAvailable = isset($_POST['is_available']) ? 1 : 0;
            $isPopular = isset($_POST['is_popular']) ? 1 : 0;

            if (empty($name) || empty($description) || $price <= 0 || empty($imagePath) || $categoryId <= 0) {
                set_flash_message('error', 'Please fill in all required fields and ensure price is greater than 0.');
            } else {
                try {
                    $stmt = $pdo->prepare("
                        UPDATE food_items 
                        SET category_id = ?, food_name = ?, description = ?, price = ?, image_path = ?, is_available = ?, is_popular = ?
                        WHERE food_id = ?
                    ");
                    $stmt->execute([$categoryId, $name, $description, $price, $imagePath, $isAvailable, $isPopular, $foodId]);
                    set_flash_message('success', "Dish '{$name}' updated successfully!");
                } catch (PDOException $e) {
                    set_flash_message('error', 'Database error: ' . $e->getMessage());
                }
            }
        } elseif ($action === 'toggle_availability') {
            $foodId = (int)($_POST['food_id'] ?? 0);
            $stmt = $pdo->prepare("UPDATE food_items SET is_available = IF(is_available = 1, 0, 1) WHERE food_id = ?");
            $stmt->execute([$foodId]);
            set_flash_message('success', 'Availability status updated.');
        } elseif ($action === 'delete_food') {
            $foodId = (int)($_POST['food_id'] ?? 0);
            try {
                $stmt = $pdo->prepare("DELETE FROM food_items WHERE food_id = ?");
                $stmt->execute([$foodId]);
                set_flash_message('success', 'Food item deleted from menu.');
            } catch (PDOException $e) {
                // Catches Foreign Key constraint violation if item exists in order_items
                set_flash_message('error', 'Cannot delete this dish because it is referenced in past customer orders. You may mark it as "Unavailable" instead to preserve historical accounting.');
            }
        }
    }
    header("Location: foods.php");
    exit();
}

$filterCategory = isset($_GET['category_id']) && $_GET['category_id'] !== '' ? (int)$_GET['category_id'] : null;
$searchDish = trim($_GET['search'] ?? '');

// Fetch food items joined with categories with optional filters
$sql = "
    SELECT f.*, c.category_name 
    FROM food_items f
    INNER JOIN categories c ON f.category_id = c.category_id
    WHERE 1=1
";
$params = [];

if ($filterCategory !== null && $filterCategory > 0) {
    $sql .= " AND f.category_id = ?";
    $params[] = $filterCategory;
}

if (!empty($searchDish)) {
    $sql .= " AND (f.food_name LIKE ? OR f.description LIKE ?)";
    $params[] = "%{$searchDish}%";
    $params[] = "%{$searchDish}%";
}

$sql .= " ORDER BY f.category_id ASC, f.food_name ASC";

$foodsStmt = $pdo->prepare($sql);
$foodsStmt->execute($params);
$foods = $foodsStmt->fetchAll();

// Check if editing a specific food
$editingFood = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM food_items WHERE food_id = ?");
    $stmt->execute([$editId]);
    $editingFood = $stmt->fetch();
}
?>

<div class="admin-page-actions mb-4 flex items-center justify-between">
    <button type="button" class="btn btn-primary" onclick="document.getElementById('food-form-section').scrollIntoView({behavior: 'smooth'})">
        + Add New Food Item
    </button>
</div>

<!-- Add / Edit Food Form -->
<div class="admin-card mb-6" id="food-form-section">
    <div class="admin-card-header">
        <h3><?= $editingFood ? 'Edit Dish: ' . htmlspecialchars($editingFood['food_name']) : 'Add New Dish to Menu' ?></h3>
        <?php if ($editingFood): ?>
            <a href="foods.php" class="btn btn-ghost btn-sm">Cancel Edit</a>
        <?php endif; ?>
    </div>

    <form action="foods.php" method="POST" class="admin-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="<?= $editingFood ? 'edit_food' : 'add_food' ?>">
        <?php if ($editingFood): ?>
            <input type="hidden" name="food_id" value="<?= $editingFood['food_id'] ?>">
        <?php endif; ?>

        <div class="form-row-3">
            <div class="form-group">
                <label>Dish Name *</label>
                <input type="text" name="food_name" value="<?= htmlspecialchars($editingFood['food_name'] ?? '') ?>" placeholder="e.g., Truffle Burger" required>
            </div>
            <div class="form-group">
                <label>Category *</label>
                <select name="category_id" required>
                    <option value="">Select Category</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['category_id'] ?>" <?= ($editingFood && $editingFood['category_id'] == $cat['category_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['category_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Price (₹ INR) *</label>
                <input type="number" step="0.01" min="0.01" name="price" value="<?= htmlspecialchars($editingFood['price'] ?? '') ?>" placeholder="199.00" required>
            </div>
        </div>

        <div class="form-group">
            <label>Image URL *</label>
            <input type="url" name="image_path" value="<?= htmlspecialchars($editingFood['image_path'] ?? '') ?>" placeholder="https://images.unsplash.com/..." required>
        </div>

        <div class="form-group">
            <label>Description *</label>
            <textarea name="description" rows="2" placeholder="Describe the ingredients, preparation method, and flavors..." required><?= htmlspecialchars($editingFood['description'] ?? '') ?></textarea>
        </div>

        <div class="form-row-checkboxes">
            <label class="checkbox-label">
                <input type="checkbox" name="is_available" value="1" <?= (!$editingFood || $editingFood['is_available']) ? 'checked' : '' ?>>
                Available for Ordering (Kitchen Stock)
            </label>
            <label class="checkbox-label">
                <input type="checkbox" name="is_popular" value="1" <?= ($editingFood && $editingFood['is_popular']) ? 'checked' : '' ?>>
                Feature as "Popular Dish" on Home Page
            </label>
        </div>

        <div class="form-submit-row">
            <button type="submit" class="btn btn-primary"><?= $editingFood ? 'Save Changes' : 'Create Food Item' ?></button>
        </div>
    </form>
</div>

<!-- Food Items List Filter & Table -->
<div class="admin-card">
    <div class="admin-card-header flex items-center justify-between flex-wrap gap-2">
        <h3>Menu Catalog (<?= count($foods) ?> dishes)</h3>
        
        <!-- Category & Search Filters -->
        <form action="foods.php" method="GET" class="flex items-center gap-2 text-xs">
            <select name="category_id" class="form-control text-xs" onchange="this.form.submit()">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['category_id'] ?>" <?= ($filterCategory === (int)$cat['category_id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['category_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="search" value="<?= htmlspecialchars($searchDish) ?>" placeholder="Search dishes..." class="form-control text-xs">
            <button type="submit" class="btn btn-primary btn-sm">Filter</button>
            <?php if ($filterCategory || !empty($searchDish)): ?>
                <a href="foods.php" class="btn btn-ghost btn-sm">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Dish</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Availability</th>
                    <th>Popular</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($foods)): ?>
                    <tr><td colspan="6" class="text-center py-4 text-muted">No dishes found matching the filter.</td></tr>
                <?php else: ?>
                    <?php foreach ($foods as $f): ?>
                        <tr>
                            <td>
                                <div class="table-food-item">
                                    <img src="<?= htmlspecialchars($f['image_path']) ?>" alt="<?= htmlspecialchars($f['food_name']) ?>" class="table-food-thumb">
                                    <div>
                                        <strong><?= htmlspecialchars($f['food_name']) ?></strong>
                                        <p class="text-muted text-xs"><?= htmlspecialchars(substr($f['description'], 0, 60)) ?>...</p>
                                    </div>
                                </div>
                            </td>
                            <td><span class="category-pill"><?= htmlspecialchars($f['category_name']) ?></span></td>
                            <td><strong><?= format_price($f['price']) ?></strong></td>
                            <td>
                                <form action="foods.php" method="POST" style="display:inline;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle_availability">
                                    <input type="hidden" name="food_id" value="<?= $f['food_id'] ?>">
                                    <button type="submit" class="status-toggle-btn <?= $f['is_available'] ? 'toggle-on' : 'toggle-off' ?>" title="Click to toggle availability">
                                        <?= $f['is_available'] ? '✓ In Stock' : '✗ Sold Out' ?>
                                    </button>
                                </form>
                            </td>
                            <td>
                                <?= $f['is_popular'] ? '<span class="text-amber">★ Yes</span>' : '<span class="text-muted">No</span>' ?>
                            </td>
                            <td>
                                <div class="action-buttons-group">
                                    <a href="foods.php?edit=<?= $f['food_id'] ?>" class="btn-table-edit">Edit</a>
                                    <form action="foods.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this dish?');" style="display:inline;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_food">
                                        <input type="hidden" name="food_id" value="<?= $f['food_id'] ?>">
                                        <button type="submit" class="btn-table-del">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/admin_footer.php'; ?>
