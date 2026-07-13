<?php
/**
 * orders/confirmation.php — Post-order thank-you page.
 */
require_once __DIR__ . '/../config/config.php';
require_login();
$uid = (int) $_SESSION['user_id'];
$oid = (int) ($_GET['order'] ?? 0);

$order = db_one('SELECT * FROM orders WHERE order_id = ? AND user_id = ?', [$oid, $uid]);
if (!$order) { set_flash('Order not found.', 'danger'); redirect('customer/orders.php'); }

$payment = db_one('SELECT * FROM payments WHERE order_id = ? ORDER BY payment_id DESC LIMIT 1', [$oid]);

$page_title = 'Order Confirmed';
include INCLUDES_PATH . '/header.php';
?>
<div class="container">
  <div class="row justify-content-center">
    <div class="col-lg-7 text-center">
      <div class="card shadow-sm border-0 p-4 p-md-5">
        <i class="bi bi-check-circle-fill text-success" style="font-size:4rem;"></i>
        <h1 class="h3 mt-3">Thank you for your order!</h1>
        <p class="text-muted">Your order number is</p>
        <p class="fs-4 fw-bold"><?= e($order['order_number']) ?></p>

        <div class="d-flex justify-content-center gap-4 my-3">
          <div><div class="text-muted small">Total</div><div class="fw-semibold"><?= money($order['total_amount']) ?></div></div>
          <div><div class="text-muted small">Status</div><div><?= status_badge($order['status']) ?></div></div>
          <div><div class="text-muted small">Payment</div><div><?= $payment ? status_badge($payment['status']) : '—' ?></div></div>
        </div>

        <?php if ($payment && $payment['status'] === 'pending' && $payment['payment_method'] !== 'cod'): ?>
          <a href="<?= url('orders/payment.php?order=' . (int)$order['order_id']) ?>" class="btn btn-warning btn-lg">Complete Payment</a>
        <?php endif; ?>
        <a href="<?= url('customer/order_detail.php?id=' . (int)$order['order_id']) ?>" class="btn btn-outline-dark mt-2">View Order Details</a>
        <a href="<?= url('books/browse.php') ?>" class="btn btn-outline-secondary mt-2"><i class="bi bi-arrow-left me-1"></i>Continue shopping</a>
      </div>
    </div>
  </div>
</div>
<?php include INCLUDES_PATH . '/footer.php'; ?>
