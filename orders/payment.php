<?php
/**
 * orders/payment.php — Simulated payment gateway. Marks the pending payment as
 * completed, confirms the order, and emails a receipt. (No real card data is
 * ever stored — the fields below are for demonstration only.)
 */
require_once __DIR__ . '/../config/config.php';
require_login();
$uid = (int) $_SESSION['user_id'];
$oid = (int) ($_GET['order'] ?? 0);

$order = db_one('SELECT * FROM orders WHERE order_id = ? AND user_id = ?', [$oid, $uid]);
if (!$order) { set_flash('Order not found.', 'danger'); redirect('customer/orders.php'); }

$payment = db_one('SELECT * FROM payments WHERE order_id = ? ORDER BY payment_id DESC LIMIT 1', [$oid]);
if (!$payment) { set_flash('No payment is due for this order.', 'info'); redirect('customer/order_detail.php?id=' . $oid); }
if ($payment['status'] === 'completed') { set_flash('This order is already paid.', 'info'); redirect('orders/receipt.php?order=' . $oid); }

/* ---- Process the (simulated) payment --------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $ref = 'TXN-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

    global $conn;
    try {
        mysqli_begin_transaction($conn);
        db_exec("UPDATE payments SET status='completed', transaction_ref=?, paid_at=NOW() WHERE payment_id=?",
                [$ref, (int) $payment['payment_id']]);
        // Move the order from pending to confirmed on successful payment.
        if ($order['status'] === 'pending')
            db_exec("UPDATE orders SET status='confirmed' WHERE order_id=?", [$oid]);
        mysqli_commit($conn);
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        set_flash('Payment could not be processed. Please try again.', 'danger');
        redirect('orders/payment.php?order=' . $oid);
    }

    notify($uid, 'order', 'Payment received for ' . $order['order_number'],
           'We received your payment of ' . money($payment['amount']) . '. Ref: ' . $ref);

    // Full itemised receipt (the same content as the printable one).
    send_app_mail($_SESSION['user_email'],
        'Receipt for ' . $order['order_number'] . ' — ' . SITE_NAME,
        order_receipt_html($oid));

    set_flash('Payment successful! Thank you.', 'success');
    redirect('orders/receipt.php?order=' . $oid);
}

$method = $payment['payment_method'];
$page_title = 'Payment';
include INCLUDES_PATH . '/header.php';
?>
<div class="container">
  <div class="row justify-content-center">
    <div class="col-lg-6">
      <div class="card shadow-sm">
        <div class="card-header bg-dark text-white d-flex justify-content-between">
          <span><i class="bi bi-lock me-1"></i>Secure Payment</span>
          <span class="text-warning fw-bold"><?= money($payment['amount']) ?></span>
        </div>
        <div class="card-body p-4">
          <p class="text-muted">Order <strong><?= e($order['order_number']) ?></strong> ·
            <span class="text-capitalize"><?= e(str_replace('_',' ',$method)) ?></span></p>

          <form method="post" class="needs-validation" data-loading novalidate>
            <?= csrf_field() ?>
            <?php if (in_array($method, ['credit_card','debit_card'], true)): ?>
              <div class="mb-3"><label class="form-label">Card number</label>
                <input class="form-control" placeholder="4242 4242 4242 4242" required pattern="[0-9 ]{12,19}"></div>
              <div class="row g-2">
                <div class="col-6"><label class="form-label">Expiry</label>
                  <input class="form-control" placeholder="MM/YY" required pattern="[0-9/]{4,5}"></div>
                <div class="col-6"><label class="form-label">CVV</label>
                  <input class="form-control" placeholder="123" required pattern="[0-9]{3,4}"></div>
              </div>
            <?php elseif ($method === 'paypal'): ?>
              <p>You'll be redirected to PayPal to authorise <?= money($payment['amount']) ?> (simulated).</p>
            <?php elseif ($method === 'gcash'): ?>
              <div class="mb-3"><label class="form-label">GCash mobile number</label>
                <input class="form-control" placeholder="09XX XXX XXXX" required></div>
            <?php endif; ?>

            <div class="alert alert-info small mt-3"><i class="bi bi-info-circle"></i> Demonstration gateway — no real charge is made and no card details are stored.</div>
            <button class="btn btn-warning btn-lg w-100"><i class="bi bi-check2-circle me-1"></i>Pay <?= money($payment['amount']) ?></button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
<div id="page-loader"><div class="spinner-border text-warning" role="status"></div></div>
<?php include INCLUDES_PATH . '/footer.php'; ?>
