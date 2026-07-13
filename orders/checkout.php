<?php
/**
 * orders/checkout.php — Review order, pick shipping address + payment method,
 * then hand off to place_order.php (Phase 7) which creates the order.
 */
require_once __DIR__ . '/../config/config.php';
require_login();
$uid = (int) $_SESSION['user_id'];

$items = get_cart_items($uid);
if (!$items) { set_flash('Your cart is empty.', 'warning'); redirect('orders/cart.php'); }

$problems = cart_stock_problems($items);
if ($problems) { set_flash('Please resolve stock issues before checkout.', 'danger'); redirect('orders/cart.php'); }

$subtotal = cart_subtotal($items);
$shipping = cart_shipping($items);
$needsShipping = $shipping > 0;                    // has at least one physical item

$promo = !empty($_SESSION['promo_code']) ? validate_promo($_SESSION['promo_code'], $subtotal)['promo'] : null;
$discount = promo_discount($promo, $subtotal);
$total    = max(0, $subtotal - $discount) + $shipping;

$addresses = db_all('SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, address_id', [$uid]);

$methods = [
    'credit_card' => ['Credit Card', 'bi-credit-card'],
    'debit_card'  => ['Debit Card',  'bi-credit-card-2-front'],
    'paypal'      => ['PayPal',      'bi-paypal'],
    'gcash'       => ['GCash',       'bi-wallet2'],
];
if ($needsShipping) $methods['cod'] = ['Cash on Delivery', 'bi-cash'];

$page_title = 'Checkout';
include INCLUDES_PATH . '/header.php';
?>
<div class="container">
  <h1 class="h3 mb-4"><i class="bi bi-bag-check me-2"></i>Checkout</h1>

  <form method="post" action="<?= url('orders/place_order.php') ?>" class="needs-validation" data-loading novalidate>
    <?= csrf_field() ?>
    <div class="row g-4">
      <div class="col-lg-8">

        <!-- Shipping address -->
        <?php if ($needsShipping): ?>
        <div class="card shadow-sm mb-4">
          <div class="card-header bg-white fw-semibold"><i class="bi bi-geo-alt me-1"></i>Shipping Address</div>
          <div class="card-body">
            <?php if (!$addresses): ?>
              <p class="text-muted mb-2">You have no saved addresses.</p>
              <a href="<?= url('customer/addresses.php') ?>" class="btn btn-sm btn-warning">Add an address</a>
            <?php else:
              // Pre-select the default address, or simply the first one when the
              // customer has never marked a default — otherwise nothing is checked
              // and this required radio silently blocks the whole form.
              $hasDefault = false;
              foreach ($addresses as $a) { if ($a['is_default']) { $hasDefault = true; break; } }
              $firstAddr = true;
              foreach ($addresses as $a):
                $checked = $hasDefault ? (bool) $a['is_default'] : $firstAddr; ?>
              <label class="d-block border rounded p-2 mb-2">
                <input type="radio" name="address_id" value="<?= (int)$a['address_id'] ?>" class="form-check-input me-2"
                       <?= $checked ? 'checked' : '' ?> required>
                <strong><?= e($a['label']) ?></strong> — <?= e($a['recipient_name']) ?>,
                <?= e($a['line1']) ?>, <?= e($a['city'].', '.$a['state'].' '.$a['postal_code']) ?>
              </label>
            <?php $firstAddr = false; endforeach; endif; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- Payment method -->
        <div class="card shadow-sm mb-4">
          <div class="card-header bg-white fw-semibold"><i class="bi bi-credit-card me-1"></i>Payment Method</div>
          <div class="card-body">
            <div class="row row-cols-2 row-cols-md-3 g-2">
              <?php $first = true; foreach ($methods as $key => [$label, $icon]): ?>
                <div class="col">
                  <label class="d-block border rounded text-center p-3 h-100">
                    <input type="radio" name="payment_method" value="<?= $key ?>" class="form-check-input d-block mx-auto mb-1"
                           <?= $first ? 'checked' : '' ?> required>
                    <i class="bi <?= $icon ?> fs-4 d-block"></i><span class="small"><?= e($label) ?></span>
                  </label>
                </div>
              <?php $first = false; endforeach; ?>
            </div>
            <p class="text-muted small mt-2 mb-0"><i class="bi bi-info-circle"></i> This is a simulated payment gateway for the project.</p>
          </div>
        </div>
      </div>

      <!-- Summary -->
      <div class="col-lg-4">
        <div class="card shadow-sm">
          <div class="card-header bg-white fw-semibold">Your Order</div>
          <ul class="list-group list-group-flush">
            <?php foreach ($items as $it): ?>
              <li class="list-group-item d-flex justify-content-between small">
                <span><?= e(excerpt($it['title'],22)) ?> <span class="text-muted">×<?= (int)$it['quantity'] ?></span>
                  <span class="badge text-bg-light text-capitalize"><?= e($it['format_type']) ?></span></span>
                <span><?= money($it['line_total']) ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
          <div class="card-body">
            <div class="d-flex justify-content-between"><span>Subtotal</span><span><?= money($subtotal) ?></span></div>
            <?php if ($discount > 0): ?>
              <div class="d-flex justify-content-between text-success"><span>Discount (<?= e($promo['code']) ?>)</span><span>−<?= money($discount) ?></span></div>
            <?php endif; ?>
            <div class="d-flex justify-content-between"><span>Shipping</span><span><?= $shipping > 0 ? money($shipping) : 'Free' ?></span></div>
            <hr>
            <div class="d-flex justify-content-between fw-bold fs-5"><span>Total</span><span><?= money($total) ?></span></div>
            <div class="d-grid mt-3">
              <button class="btn btn-warning btn-lg"><i class="bi bi-lock me-1"></i>Place Order</button>
            </div>
            <a href="<?= url('orders/cart.php') ?>" class="btn btn-outline-secondary btn-sm w-100 mt-2"><i class="bi bi-arrow-left me-1"></i>Back to cart</a>
          </div>
        </div>
      </div>
    </div>
  </form>
</div>
<div id="page-loader"><div class="spinner-border text-warning" role="status"></div></div>
<?php include INCLUDES_PATH . '/footer.php'; ?>
