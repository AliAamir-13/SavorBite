<?php
/**
 * Checkout Page - SavorBite Online Food Ordering System
 * Demonstrates: ACID Database Transactions, Price Re-verification, Rollback Safety
 */
$pageTitle = 'Secure Checkout - SavorBite';
require_once __DIR__ . '/includes/header.php';

require_login('checkout.php');

$userId = $_SESSION['user_id'];
$cartItems = get_cart($pdo);
$totals = get_cart_totals($pdo);

if (empty($cartItems)) {
    set_flash_message('error', 'Your cart is empty.');
    header("Location: menu.php");
    exit();
}

// Fetch user addresses
$addresses = get_user_addresses($pdo, $userId);

$errorMessage = null;

// Handle order placement form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'place_order') {
    if (!verify_csrf_token()) {
        $errorMessage = 'Security token expired. Please try again.';
    } else {
        $addressOption = $_POST['address_option'] ?? 'saved';
        $finalAddressText = '';

        if ($addressOption === 'new' || empty($addresses)) {
            $label = trim($_POST['address_label'] ?? 'Home');
            $line = trim($_POST['new_address_line'] ?? '');
            $city = trim($_POST['new_city'] ?? '');
            $postalCode = trim($_POST['new_postal_code'] ?? '');
            $saveAsDefault = isset($_POST['save_as_default']);

            if (empty($line) || empty($city) || empty($postalCode)) {
                $errorMessage = 'Please complete all address fields.';
            } else {
                add_user_address($pdo, $userId, $label, $line, $city, $postalCode, $saveAsDefault);
                $finalAddressText = "{$label}: {$line}, {$city}, {$postalCode}";
            }
        } else {
            $selectedAddressId = (int)($_POST['saved_address_id'] ?? 0);
            $stmt = $pdo->prepare("SELECT * FROM addresses WHERE address_id = ? AND user_id = ?");
            $stmt->execute([$selectedAddressId, $userId]);
            $addr = $stmt->fetch();

            if (!$addr) {
                $errorMessage = 'Please select a valid delivery address.';
            } else {
                $finalAddressText = "{$addr['address_label']}: {$addr['address_line']}, {$addr['city']}, {$addr['postal_code']}";
            }
        }

        $paymentMethod = $_POST['payment_method'] ?? 'Demo Online Payment';
        $instructions = trim($_POST['special_instructions'] ?? '');

        if (!$errorMessage) {
            try {
                // Execute ACID Transaction
                $orderResult = execute_order_placement($pdo, $userId, $finalAddressText, $paymentMethod, $instructions);
                header("Location: order-success.php?id=" . $orderResult['order_id']);
                exit();
            } catch (Exception $e) {
                $errorMessage = $e->getMessage();
            }
        }
    }
}
?>

<div class="container checkout-container">
    <div class="checkout-header">
        <h1 class="page-title">Complete Your Order</h1>
        <p class="page-subtitle">Confirm your delivery details and choose your payment option</p>
    </div>

    <?php if ($errorMessage): ?>
        <div class="flash-alert flash-error">
            <span><?= htmlspecialchars($errorMessage) ?></span>
        </div>
    <?php endif; ?>

    <form action="checkout.php" method="POST" id="checkout-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="place_order">

        <div class="checkout-grid">
            <!-- Left Column: Delivery & Payment Details -->
            <div class="checkout-main">
                <!-- 1. Delivery Address Card -->
                <div class="checkout-card">
                    <div class="card-header-icon">
                        <span class="icon-circle">1</span>
                        <h2>Delivery Address</h2>
                    </div>

                    <?php if (!empty($addresses)): ?>
                        <div class="saved-addresses-list">
                            <?php foreach ($addresses as $index => $addr): ?>
                                <label class="address-radio-card <?= $addr['is_default'] ? 'selected' : '' ?>">
                                    <input type="radio" name="address_option" value="saved" 
                                           data-address-id="<?= $addr['address_id'] ?>" 
                                           <?= ($index === 0 || $addr['is_default']) ? 'checked' : '' ?>
                                           onchange="toggleAddressForms()">
                                    <input type="radio" name="saved_address_id" value="<?= $addr['address_id'] ?>" 
                                           style="display:none;" 
                                           <?= ($index === 0 || $addr['is_default']) ? 'checked' : '' ?>>
                                    <div class="addr-content">
                                        <div class="addr-header">
                                            <span class="addr-label-tag"><?= htmlspecialchars($addr['address_label']) ?></span>
                                            <?php if ($addr['is_default']): ?>
                                                <span class="default-pill">Default</span>
                                            <?php endif; ?>
                                        </div>
                                        <p class="addr-text"><?= htmlspecialchars($addr['address_line']) ?>, <?= htmlspecialchars($addr['city']) ?> <?= htmlspecialchars($addr['postal_code']) ?></p>
                                    </div>
                                </label>
                            <?php endforeach; ?>

                            <label class="address-radio-card add-new-card">
                                <input type="radio" name="address_option" value="new" onchange="toggleAddressForms()">
                                <div class="addr-content">
                                    <strong>+ Deliver to a different address</strong>
                                </div>
                            </label>
                        </div>
                    <?php endif; ?>

                    <!-- Inline New Address Form -->
                    <div id="new-address-section" style="<?= empty($addresses) ? 'display:block;' : 'display:none;' ?>">
                        <h4 class="form-subheading">Enter New Delivery Address:</h4>
                        <div class="form-group-row">
                            <div class="form-group">
                                <label>Address Label</label>
                                <input type="text" name="address_label" placeholder="e.g. Home, Office, Friend's Place" value="Home">
                            </div>
                            <div class="form-group">
                                <label>Postal / ZIP Code *</label>
                                <input type="text" name="new_postal_code" placeholder="97477">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Street Address *</label>
                            <input type="text" name="new_address_line" placeholder="Apartment, suite, street name">
                        </div>
                        <div class="form-group">
                            <label>City *</label>
                            <input type="text" name="new_city" placeholder="Springfield">
                        </div>
                        <div class="form-group-checkbox">
                            <label>
                                <input type="checkbox" name="save_as_default" value="1" checked>
                                Save this address to my profile
                            </label>
                        </div>
                    </div>
                </div>

                <!-- 2. Special Instructions -->
                <div class="checkout-card">
                    <div class="card-header-icon">
                        <span class="icon-circle">2</span>
                        <h2>Delivery Instructions (Optional)</h2>
                    </div>
                    <div class="form-group">
                        <textarea name="special_instructions" rows="2" placeholder="e.g., Leave on porch, gate code #4102, ring doorbell..."></textarea>
                    </div>
                </div>

                <!-- 3. Payment Method -->
                <div class="checkout-card">
                    <div class="card-header-icon">
                        <span class="icon-circle">3</span>
                        <h2>Payment Method</h2>
                    </div>

                    <div class="payment-methods-grid">
                        <label class="payment-option selected">
                            <input type="radio" name="payment_method" value="Demo Online Payment" checked>
                            <div class="pay-option-content">
                                <span class="pay-icon">💳</span>
                                <div>
                                    <strong>Demo Online Payment (Instant)</strong>
                                    <p>Simulated instant card/net-banking payment. No actual money charged.</p>
                                </div>
                            </div>
                        </label>

                        <label class="payment-option">
                            <input type="radio" name="payment_method" value="Cash on Delivery">
                            <div class="pay-option-content">
                                <span class="pay-icon">💵</span>
                                <div>
                                    <strong>Cash on Delivery (COD)</strong>
                                    <p>Pay with exact cash or mobile transfer when your food arrives.</p>
                                </div>
                            </div>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Right Column: Order Review Sidebar -->
            <div class="checkout-sidebar">
                <div class="summary-card">
                    <h3 class="summary-title">Order Review</h3>

                    <!-- Mini Cart Items List -->
                    <div class="checkout-items-list">
                        <?php foreach ($cartItems as $item): ?>
                            <div class="checkout-item-line">
                                <div class="item-meta">
                                    <span class="item-qty-badge"><?= $item['quantity'] ?>x</span>
                                    <span class="item-name-text"><?= htmlspecialchars($item['food_name']) ?></span>
                                </div>
                                <span class="item-price-text"><?= format_price($item['line_total']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <hr class="summary-divider">

                    <div class="summary-row">
                        <span>Items Subtotal:</span>
                        <span><?= format_price($totals['subtotal']) ?></span>
                    </div>

                    <div class="summary-row">
                        <span>Delivery Fee:</span>
                        <span><?= format_price($totals['delivery_fee']) ?></span>
                    </div>

                    <hr class="summary-divider">

                    <div class="summary-row total-row">
                        <span>Total Due:</span>
                        <span class="summary-grand-total"><?= format_price($totals['total']) ?></span>
                    </div>

                    <div class="checkout-btn-wrap">
                        <button type="submit" class="btn btn-primary btn-lg btn-block">Place Order Now &rarr;</button>
                        <p class="terms-notice">By placing your order, you agree to our kitchen preparation and delivery policies.</p>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
function toggleAddressForms() {
    const newSection = document.getElementById('new-address-section');
    const selected = document.querySelector('input[name="address_option"]:checked');
    if (selected && selected.value === 'new') {
        newSection.style.display = 'block';
    } else {
        newSection.style.display = 'none';
        // Sync saved_address_id
        if (selected) {
            const addrId = selected.getAttribute('data-address-id');
            const hiddenRadio = document.querySelector('input[name="saved_address_id"][value="' + addrId + '"]');
            if (hiddenRadio) hiddenRadio.checked = true;
        }
    }
}

// Payment option click visual
document.querySelectorAll('.payment-option').forEach(el => {
    el.addEventListener('click', () => {
        document.querySelectorAll('.payment-option').forEach(o => o.classList.remove('selected'));
        el.classList.add('selected');
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
