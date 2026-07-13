<?php
/**
 * customer/order_detail.php — Single order: items, totals, payment, shipment.
 * Ownership enforced: a customer can only view their own order.
 */
require_once __DIR__ . '/../config/config.php';
require_login();
$uid = (int) $_SESSION['user_id'];
$oid = (int) ($_GET['id'] ?? 0);

$order = db_one(
    'SELECT o.*, a.recipient_name, a.line1, a.line2, a.city, a.state, a.postal_code, a.country, a.phone AS addr_phone
     FROM orders o
     LEFT JOIN addresses a ON a.address_id = o.shipping_address_id
     WHERE o.order_id = ? AND o.user_id = ?', [$oid, $uid]);

if (!$order) {
    set_flash('Order not found.', 'danger');
    redirect('customer/orders.php');
}

$items    = db_all('SELECT * FROM order_items WHERE order_id = ?', [$oid]);
$payment  = db_one('SELECT * FROM payments  WHERE order_id = ? ORDER BY payment_id DESC LIMIT 1', [$oid]);
$shipment = db_one('SELECT * FROM shipments WHERE order_id = ? ORDER BY shipment_id DESC LIMIT 1', [$oid]);

$page_title = 'Order ' . $order['order_number'];
$active = 'orders';
$dash_title = 'Order ' . e($order['order_number']);
include INCLUDES_PATH . '/header.php';
include INCLUDES_PATH . '/dash_open.php';
?>

<a href="<?= url('customer/orders.php') ?>" class="btn btn-sm btn-outline-secondary mb-3">
  <i class="bi bi-arrow-left me-1"></i>Back to orders
</a>

<div class="row g-4">
  <div class="col-lg-8">
    <div class="card shadow-sm mb-4">
      <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-semibold">Items</span><?= status_badge($order['status']) ?>
      </div>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead class="table-light"><tr><th>Title</th><th>Format</th><th class="text-end">Price</th>
            <th class="text-center">Qty</th><th class="text-end">Total</th></tr></thead>
          <tbody>
          <?php foreach ($items as $it): ?>
            <tr>
              <td><?= e($it['book_title']) ?></td>
              <td><span class="badge text-bg-light text-capitalize"><?= e($it['format_type']) ?></span></td>
              <td class="text-end"><?= money($it['unit_price']) ?></td>
              <td class="text-center"><?= (int) $it['quantity'] ?></td>
              <td class="text-end fw-semibold"><?= money($it['line_total']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr><td colspan="4" class="text-end">Subtotal</td><td class="text-end"><?= money($order['subtotal']) ?></td></tr>
            <?php if ($order['discount_amount'] > 0): ?>
              <tr class="text-success"><td colspan="4" class="text-end">Discount</td><td class="text-end">−<?= money($order['discount_amount']) ?></td></tr>
            <?php endif; ?>
            <tr><td colspan="4" class="text-end">Shipping</td><td class="text-end"><?= money($order['shipping_fee']) ?></td></tr>
            <tr class="fw-bold border-top"><td colspan="4" class="text-end">Total</td><td class="text-end"><?= money($order['total_amount']) ?></td></tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <!-- Shipping address -->
    <div class="card shadow-sm mb-3">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-geo-alt me-1"></i>Shipping Address</div>
      <div class="card-body small">
        <?php if ($order['recipient_name']): ?>
          <strong><?= e($order['recipient_name']) ?></strong><br>
          <?= e($order['line1']) ?><?= $order['line2'] ? ', ' . e($order['line2']) : '' ?><br>
          <?= e($order['city'] . ', ' . $order['state'] . ' ' . $order['postal_code']) ?><br>
          <?= e($order['country']) ?>
        <?php else: ?><span class="text-muted">Digital order — no shipping.</span><?php endif; ?>
      </div>
    </div>
    <!-- Payment -->
    <div class="card shadow-sm mb-3">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-credit-card me-1"></i>Payment</div>
      <div class="card-body small">
        <?php if ($payment): ?>
          Method: <strong class="text-uppercase"><?= e(str_replace('_',' ',$payment['payment_method'])) ?></strong><br>
          Status: <?= status_badge($payment['status']) ?><br>
          <?php if ($payment['transaction_ref']): ?>Ref: <code><?= e($payment['transaction_ref']) ?></code><br><?php endif; ?>
          Paid: <?= nice_datetime($payment['paid_at']) ?>
        <?php else: ?><span class="text-muted">No payment recorded.</span><?php endif; ?>
      </div>
    </div>
    <!-- Shipment -->
    <?php if ($shipment): ?>
    <div class="card shadow-sm">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-truck me-1"></i>Shipment</div>
      <div class="card-body small">
        Carrier: <strong><?= e($shipment['carrier'] ?: '—') ?></strong><br>
        Tracking: <code><?= e($shipment['tracking_number'] ?: '—') ?></code><br>
        Status: <?= status_badge($shipment['status']) ?><br>
        Shipped: <?= nice_date($shipment['shipped_at']) ?> · Delivered: <?= nice_date($shipment['delivered_at']) ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php
include INCLUDES_PATH . '/dash_close.php';
include INCLUDES_PATH . '/footer.php';
