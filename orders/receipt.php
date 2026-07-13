<?php
/**
 * orders/receipt.php — Printable receipt / invoice for an order.
 * Accessible to the order's owner or any admin.
 */
require_once __DIR__ . '/../config/config.php';
require_login();
$oid = (int) ($_GET['order'] ?? 0);

$order = db_one(
    'SELECT o.*, CONCAT(u.first_name," ",u.last_name) AS customer, u.email, u.user_id AS cust_id,
            a.recipient_name, a.line1, a.line2, a.city, a.state, a.postal_code, a.country
     FROM orders o JOIN users u ON u.user_id = o.user_id
     LEFT JOIN addresses a ON a.address_id = o.shipping_address_id
     WHERE o.order_id = ?', [$oid]);

if (!$order || (!is_admin() && (int) $order['cust_id'] !== (int) $_SESSION['user_id'])) {
    set_flash('Receipt not found.', 'danger'); redirect('customer/orders.php');
}

$items   = db_all('SELECT * FROM order_items WHERE order_id = ?', [$oid]);
$payment = db_one('SELECT * FROM payments WHERE order_id = ? ORDER BY payment_id DESC LIMIT 1', [$oid]);

$page_title = 'Receipt ' . $order['order_number'];
include INCLUDES_PATH . '/header.php';
?>
<div class="container">
  <div class="d-flex justify-content-between align-items-center mb-3 d-print-none">
    <a href="<?= url('customer/order_detail.php?id='.$oid) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to order</a>
    <button onclick="window.print()" class="btn btn-warning btn-sm"><i class="bi bi-printer me-1"></i>Print / Save PDF</button>
  </div>

  <div class="card shadow-sm">
    <div class="card-body p-4 p-md-5">
      <div class="d-flex justify-content-between mb-4">
        <div>
          <h2 class="h4 mb-0 d-flex align-items-center gap-2">
            <?php if ($receiptLogo = site_logo_url()): ?>
              <img src="<?= e($receiptLogo) ?>" alt="" class="site-logo site-logo-print">
            <?php else: ?>
              <i class="bi bi-book-half text-warning"></i>
            <?php endif; ?>
            <span><?= e(SITE_NAME) ?></span>
          </h2>
          <small class="text-muted"><?= e(SITE_TAGLINE) ?></small>
        </div>
        <div class="text-end">
          <h3 class="h5 mb-0">RECEIPT</h3>
          <div class="small text-muted"><?= e($order['order_number']) ?></div>
          <div class="small text-muted"><?= nice_datetime($order['placed_at']) ?></div>
        </div>
      </div>

      <div class="row mb-4">
        <div class="col-6">
          <div class="text-muted small text-uppercase">Billed to</div>
          <strong><?= e($order['customer']) ?></strong><br>
          <span class="small"><?= e($order['email']) ?></span>
          <?php if ($order['recipient_name']): ?>
            <div class="text-muted small text-uppercase mt-2">Ship to</div>
            <?= e($order['recipient_name']) ?><br>
            <span class="small"><?= e($order['line1']) ?>, <?= e($order['city'].', '.$order['state'].' '.$order['postal_code']) ?></span>
          <?php endif; ?>
        </div>
        <div class="col-6 text-end">
          <div class="text-muted small text-uppercase">Payment</div>
          <span class="text-capitalize"><?= $payment ? e(str_replace('_',' ',$payment['payment_method'])) : '—' ?></span><br>
          <?= $payment ? status_badge($payment['status']) : '' ?>
          <?php if ($payment && $payment['transaction_ref']): ?><div class="small text-muted mt-1"><?= e($payment['transaction_ref']) ?></div><?php endif; ?>
        </div>
      </div>

      <table class="table">
        <thead class="table-light"><tr><th>Description</th><th>Format</th><th class="text-end">Unit</th><th class="text-center">Qty</th><th class="text-end">Amount</th></tr></thead>
        <tbody>
        <?php foreach ($items as $it): ?>
          <tr><td><?= e($it['book_title']) ?></td><td class="text-capitalize"><?= e($it['format_type']) ?></td>
              <td class="text-end"><?= money($it['unit_price']) ?></td><td class="text-center"><?= (int)$it['quantity'] ?></td>
              <td class="text-end"><?= money($it['line_total']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr><td colspan="4" class="text-end">Subtotal</td><td class="text-end"><?= money($order['subtotal']) ?></td></tr>
          <?php if ($order['discount_amount']>0): ?><tr class="text-success"><td colspan="4" class="text-end">Discount</td><td class="text-end">−<?= money($order['discount_amount']) ?></td></tr><?php endif; ?>
          <tr><td colspan="4" class="text-end">Shipping</td><td class="text-end"><?= money($order['shipping_fee']) ?></td></tr>
          <tr class="fw-bold fs-5 border-top"><td colspan="4" class="text-end">TOTAL</td><td class="text-end"><?= money($order['total_amount']) ?></td></tr>
        </tfoot>
      </table>

      <p class="text-center text-muted small mt-4 mb-0">Thank you for shopping at <?= e(SITE_NAME) ?>!</p>
    </div>
  </div>
</div>
<?php include INCLUDES_PATH . '/footer.php'; ?>
