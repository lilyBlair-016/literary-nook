<?php
/**
 * admin/payments.php — Transaction history with status filter + refund action.
 */
require_once __DIR__ . '/../config/config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (($_POST['action'] ?? '') === 'refund') {
        $pid = (int) $_POST['payment_id'];
        $pay = db_one("SELECT * FROM payments WHERE payment_id = ? AND status = 'completed'", [$pid]);
        if ($pay) {
            db_exec("UPDATE payments SET status='refunded' WHERE payment_id=?", [$pid]);
            db_exec("UPDATE orders SET status='cancelled' WHERE order_id=?", [(int)$pay['order_id']]);
            // Restore stock for the cancelled order.
            foreach (db_all('SELECT format_id, quantity FROM order_items WHERE order_id=? AND format_id IS NOT NULL', [(int)$pay['order_id']]) as $li)
                db_exec('UPDATE book_formats SET stock_qty=stock_qty+? WHERE format_id=? AND is_digital=0', [(int)$li['quantity'], (int)$li['format_id']]);
            set_flash('Payment refunded and order cancelled.', 'success');
        }
    }
    redirect('admin/payments.php');
}

$status = $_GET['status'] ?? '';
$where = '1=1'; $params = [];
if (in_array($status, ['pending','completed','failed','refunded'], true)) { $where = 'p.status = ?'; $params = [$status]; }

$payments = db_all(
    "SELECT p.*, o.order_number, CONCAT(u.first_name,' ',u.last_name) AS customer
     FROM payments p
     JOIN orders o ON o.order_id = p.order_id
     JOIN users  u ON u.user_id  = o.user_id
     WHERE $where
     ORDER BY p.created_at DESC", $params);

/* Totals (Module 7: SUM aggregate). */
$collected = (float) db_scalar("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='completed'");

$page_title = 'Payments';
$active = 'payments';
$dash_title = 'Payments & Transactions';
include INCLUDES_PATH . '/header.php';
include INCLUDES_PATH . '/dash_open.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div class="btn-group btn-group-sm">
    <a href="<?= url('admin/payments.php') ?>" class="btn <?= $status===''?'btn-dark':'btn-outline-dark' ?>">All</a>
    <?php foreach (['completed','pending','refunded','failed'] as $s): ?>
      <a href="<?= url('admin/payments.php?status='.$s) ?>" class="btn text-capitalize <?= $status===$s?'btn-warning':'btn-outline-secondary' ?>"><?= $s ?></a>
    <?php endforeach; ?>
  </div>
  <span class="badge text-bg-success fs-6">Collected: <?= money($collected) ?></span>
</div>

<div class="card shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light"><tr><th>Ref</th><th>Order</th><th>Customer</th><th>Method</th><th class="text-end">Amount</th><th>Status</th><th>Date</th><th></th></tr></thead>
      <tbody>
      <?php if (!$payments): ?>
        <tr><td colspan="8"><div class="empty-state"><i class="bi bi-credit-card d-block mb-2"></i>No transactions.</div></td></tr>
      <?php else: foreach ($payments as $p): ?>
        <tr>
          <td class="small"><?= e($p['transaction_ref'] ?: '—') ?></td>
          <td><a href="<?= url('admin/order_detail.php?id='.(int)$p['order_id']) ?>"><?= e($p['order_number']) ?></a></td>
          <td><?= e($p['customer']) ?></td>
          <td class="text-capitalize small"><?= e(str_replace('_',' ',$p['payment_method'])) ?></td>
          <td class="text-end fw-semibold"><?= money($p['amount']) ?></td>
          <td><?= status_badge($p['status']) ?></td>
          <td class="small text-muted"><?= nice_date($p['created_at']) ?></td>
          <td class="text-end">
            <?php if ($p['status']==='completed'): ?>
              <form method="post" class="d-inline"><?= csrf_field() ?>
                <input type="hidden" name="action" value="refund"><input type="hidden" name="payment_id" value="<?= (int)$p['payment_id'] ?>">
                <button class="btn btn-sm btn-outline-danger" data-confirm="Refund this payment and cancel the order?">Refund</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include INCLUDES_PATH . '/dash_close.php'; include INCLUDES_PATH . '/footer.php'; ?>
