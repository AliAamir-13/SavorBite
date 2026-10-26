<?php
/**
 * Admin Categories Management - SavorBite Online Food Ordering System
 * Demonstrates: GROUP BY with COUNT(), Foreign Key ON DELETE RESTRICT enforcement
 */
$adminPageTitle = 'Cuisine Categories';
require_once __DIR__ . '/admin_header.php';

// Handle Actions (Add, Edit, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        set_flash_message('error', 'Security token expired.');
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'add_category') {
            $name = trim($_POST['category_name'] ?? '');
            $desc = trim($_POST['description'] ?? '');
            $img = trim($_POST['image_url'] ?? '');

            if (empty($name)) {
                set_flash_message('error', 'Category name cannot be empty.');
            } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO categories (category_name, description, image_url) VALUES (?, ?, ?)");
                    $stmt->execute([$name, $desc, $img]);
                    set_flash_message('success', "Category '{$name}' created!");
                } catch (PDOException $e) {
                    set_flash_message('error', 'Category with this name already exists or database error: ' . $e->getMessage());
                }
            }
        } elseif ($action === 'edit_category') {
            $catId = (int)($_POST['category_id'] ?? 0);
            $name = trim($_POST['category_name'] ?? '');
            $desc = trim($_POST['description'] ?? '');
            $img = trim($_POST['image_url'] ?? '');

            if (empty($name)) {
                set_flash_message('error', 'Category name cannot be empty.');
            } else {
                try {
                    $stmt = $pdo->prepare("UPDATE categories SET category_name = ?, description = ?, image_url = ? WHERE category_id = ?");
                    $stmt->execute([$name, $desc, $img, $catId]);
                    set_flash_message('success', "Category '{$name}' updated!");
                } catch (PDOException $e) {
                    set_flash_message('error', 'Database error: ' . $e->getMessage());
                }
            }
        } elseif ($action === 'delete_category') {
            $catId = (int)($_POST['category_id'] ?? 0);
            try {
                $stmt = $pdo->prepare("DELETE FROM categories WHERE category_id = ?");
                $stmt->execute([$catId]);
                set_flash_message('success', 'Category deleted successfully.');
            } catch (PDOException $e) {
                // Catches Foreign Key constraint: ON DELETE RESTRICT
                set_flash_message('error', 'Cannot delete this category because dishes are actively assigned to it. Reassign or delete those dishes first.');
            }
        }
    }
    header("Location: categories.php");
    exit();
}

// Fetch categories with dish count using LEFT JOIN and GROUP BY
$categoriesStmt = $pdo->query("
    SELECT 
        c.*,
        COUNT(f.food_id) AS total_dishes
    FROM categories c
    LEFT JOIN food_items f ON c.category_id = f.category_id
    GROUP BY c.category_id, c.category_name, c.description, c.image_url, c.created_at
    ORDER BY c.category_name ASC
");
$categoriesList = $categoriesStmt->fetchAll();

// Edit check
$editingCat = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE category_id = ?");
    $stmt->execute([$editId]);
    $editingCat = $stmt->fetch();
}
?>

<div class="admin-grid-2col">
    <!-- Category Form -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h3><?= $editingCat ? 'Edit Category: ' . htmlspecialchars($editingCat['category_name']) : 'Create New Category' ?></h3>
            <?php if ($editingCat): ?>
                <a href="categories.php" class="btn btn-ghost btn-sm">Cancel</a>
            <?php endif; ?>
        </div>

        <form action="categories.php" method="POST" class="admin-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="<?= $editingCat ? 'edit_category' : 'add_category' ?>">
            <?php if ($editingCat): ?>
                <input type="hidden" name="category_id" value="<?= $editingCat['category_id'] ?>">
            <?php endif; ?>

            <div class="form-group">
                <label>Category Name *</label>
                <input type="text" name="category_name" value="<?= htmlspecialchars($editingCat['category_name'] ?? '') ?>" placeholder="e.g., Artisan Pizza" required>
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="2" placeholder="Brief summary of this cuisine..."><?= htmlspecialchars($editingCat['description'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label>Cover Image URL</label>
                <input type="url" name="image_url" value="<?= htmlspecialchars($editingCat['image_url'] ?? '') ?>" placeholder="https://images.unsplash.com/...">
            </div>

            <button type="submit" class="btn btn-primary"><?= $editingCat ? 'Save Changes' : 'Create Category' ?></button>
        </form>
    </div>

    <!-- Category Table -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h3>Active Categories (<?= count($categoriesList) ?>)</h3>
        </div>

        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Assigned Dishes</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categoriesList as $cat): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($cat['category_name']) ?></strong>
                                <br><small class="text-muted"><?= htmlspecialchars(substr($cat['description'] ?? '', 0, 45)) ?>...</small>
                            </td>
                            <td>
                                <span class="badge-count"><?= $cat['total_dishes'] ?> items</span>
                            </td>
                            <td>
                                <div class="action-buttons-group">
                                    <a href="categories.php?edit=<?= $cat['category_id'] ?>" class="btn-table-edit">Edit</a>
                                    <form action="categories.php" method="POST" onsubmit="return confirm('Delete this category?');" style="display:inline;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_category">
                                        <input type="hidden" name="category_id" value="<?= $cat['category_id'] ?>">
                                        <button type="submit" class="btn-table-del">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/admin_footer.php'; ?>
