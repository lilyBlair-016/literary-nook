<?php
/**
 * admin/order_detail.php — Admin view of one order: items, payment, shipment,
 * status workflow (with stock restore on cancel), and shipment tracking edit.
 */
require_once __DIR__ . '/../config/config.php';
require_admin();
$oid = (int) ($_GET['id'] ?? 0);

$order = db_one(
    'SELECT o.*, CONCAT(u.first_name," ",u.last_name) AS customer, u.email, u.user_id AS cust_id,
            a.recipient_name, a.line1, a.line2, a.city, a.state, a.postal_code, a.country
     FROM orders o JOIN users u ON u.user_id = o.user_id
     LEFT JOIN addresses a ON a.address_id = o.shipping_address_id
     WHERE o.order_id = ?', [$oid]);
if (!$order) { set_flash('Order not found.', 'danger'); redirect('admin/orders.php'); }

/* ---- POST: update status or shipment --------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'status') {
        $new = $_POST['status'] ?? '';
        $valid = ['pending','confirmed','shipped','delivered','cancelled'];

        /* A physical order cannot go "shipped" without a carrier and a tracking
           number: the customer is told it is on its way and then has nothing to
           track. Digital-only orders have no shipment row, so they are exempt. */
        if ($new === 'shipped') {
            $ship = db_one('SELECT carrier, tracking_number FROM shipments
                            WHERE order_id = ? ORDER BY shipment_id DESC LIMIT 1', [$oid]);
            if ($ship && (trim((string) $ship['carrier']) === ''
                       || trim((string) $ship['tracking_number']) === '')) {
                set_flash('Enter the carrier and tracking number before marking this order as shipped.', 'danger');
                redirect('admin/order_detail.php?id=' . $oid);
            }
        }

        if (in_array($new, $valid, true) && $new !== $order['status']) {
            global $conn;
            try {
                mysqli_begin_transaction($conn);
                db_exec('UPDATE orders SET status = ? WHERE order_id = ?', [$new, $oid]);

                // Restore stock if cancelling (only once, from a non-cancelled state).
                if ($new === 'cancelled' && $order['status'] !== 'cancelled') {
                    foreach (db_all('SELECT format_id, quantity FROM order_items WHERE order_id = ? AND format_id IS NOT NULL', [$oid]) as $li) {
                        db_exec('UPDATE book_formats SET stock_qty = stock_qty + ?
                                 WHERE format_id = ? AND is_digital = 0', [(int)$li['quantity'], (int)$li['format_id']]);
                    }
                }
                // Sync shipment milestones.
                if ($new === 'shipped')   db_exec("UPDATE shipments SET status='in_transit', shipped_at=NOW() WHERE order_id=? AND shipped_at IS NULL", [$oid]);
                if ($new === 'delivered') db_exec("UPDATE shipments SET status='delivered', delivered_at=NOW() WHERE order_id=?", [$oid]);

                mysqli_commit($conn);

                // Notify the customer (shipping updates etc.).
                $type = in_array($new, ['shipped','delivered'], true) ? 'shipping' : 'order';

                // A "shipped" notice is useless without the tracking details.
                $track = '';
                if ($new === 'shipped') {
                    $s = db_one('SELECT carrier, tracking_number FROM shipments
                                 WHERE order_id = ? ORDER BY shipment_id DESC LIMIT 1', [$oid]);
                    if ($s && $s['carrier'] !== '' && $s['tracking_number'] !== '') {
                        $track = ' Carrier: ' . $s['carrier']
                               . ', tracking number: ' . $s['tracking_number'] . '.';
                    }
                }

                notify((int)$order['cust_id'], $type, 'Order ' . $order['order_number'] . ' ' . $new,
                       'Your order ' . $order['order_number'] . ' is now marked "' . $new . '".' . $track);

                $body = "<p>Your order <strong>{$order['order_number']}</strong> status is now <strong>{$new}</strong>.</p>";
                if ($track !== '') {
                    $body .= '<p>Carrier: <strong>' . e($s['carrier']) . '</strong><br>'
                           . 'Tracking number: <strong>' . e($s['tracking_number']) . '</strong></p>';
                }
                send_app_mail($order['email'], 'Order ' . $order['order_number'] . ' update', $body);
                set_flash('Order status updated to "' . e($new) . '".', 'success');
            } catch (Throwable $e) {
                mysqli_rollback($conn);
                set_flash('Could not update status.', 'danger');
            }
        }
        redirect('admin/order_detail.php?id=' . $oid);
    }

    if ($action === 'shipment') {
        $carrier  = clean($_POST['carrier'] ?? '');
        $tracking = clean($_POST['tracking_number'] ?? '');

        $errors = [];
        if (mb_strlen($carrier)  > 60) $errors[] = 'Carrier name is too long (max 60 characters).';
        if (mb_strlen($tracking) > 60) $errors[] = 'Tracking number is too long (max 60 characters).';
        // Both blank is fine (nothing dispatched yet); one blank is not.
        if (($carrier === '') !== ($tracking === '')) {
            $errors[] = 'Enter both the carrier and the tracking number, or leave both blank.';
        }
        if ($tracking !== '' && !preg_match('/^[A-Za-z0-9\- ]{4,60}$/', $tracking)) {
            $errors[] = 'Tracking number may only contain letters, numbers, spaces and hyphens.';
        }

        if ($errors) {
            foreach ($errors as $e) set_flash($e, 'danger');
        } else {
            db_exec('UPDATE shipments SET carrier = ?, tracking_number = ? WHERE order_id = ?',
                    [$carrier, $tracking, $oid]);
            set_flash('Shipment details saved.', 'success');
        }
        redirect('admin/order_detail.php?id=' . $oid);
    }
}

$items    = db_all('SELECT * FROM order_items WHERE order_id = ?', [$oid]);
$payment  = db_one('SELECT * FROM payments WHERE order_id = ? ORDER BY payment_id DESC LIMIT 1', [$oid]);
$shipment = db_one('SELECT * FROM shipments WHERE order_id = ? ORDER BY shipment_id DESC LIMIT 1', [$oid]);

$page_title = 'Order ' . $order['order_number'];
$active = 'orders';
$dash_title = 'Order ' . e($order['order_number']);
include INCLUDES_PATH . '/header.php';
include INCLUDES_PATH . '/dash_open.php';
?>
<a href="<?= url('admin/orders.php') ?>" class="btn btn-sm btn-outline-secondary mb-3">
  <i class="bi bi-arrow-left me-1"></i>Back to orders
</a>

<div class="row g-4">
  <div class="col-lg-8">
    <div class="card shadow-sm mb-4">
      <div class="card-header bg-white d-flex justify-content-between"><span class="fw-semibold">Items</span><?= status_badge($order['status']) ?></div>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead class="table-light"><tr><th>Title</th><th>Format</th><th class="text-end">Price</th><th class="text-center">Qty</th><th class="text-end">Total</th></tr></thead>
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
            <tr class="fw-bold border-top"><td colspan="4" class="text-end">Total</td><td class="text-end"><?= money($order['total_amount']) ?></td></tr>
          </tfoot>
        </table>
      </div>
    </div>

    <?php if ($shipment):
      $hasTracking = trim((string) $shipment['carrier']) !== ''
                  && trim((string) $shipment['tracking_number']) !== ''; ?>
    <div class="card shadow-sm">
      <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
        <span><i class="bi bi-truck me-1"></i>Shipment Tracking</span>
        <?php if (!$hasTracking): ?>
          <span class="badge text-bg-warning">Required before shipping</span>
        <?php endif; ?>
      </div>
      <div class="card-body">
        <?php if (!$hasTracking): ?>
          <div class="alert alert-warning small py-2">
            <i class="bi bi-exclamation-triangle me-1"></i>
            Add a carrier and tracking number first. The customer is emailed these
            details when the order is marked <strong>shipped</strong>, so the status
            cannot be changed until they are filled in.
          </div>
        <?php endif; ?>
        <form method="post" class="row g-2 align-items-end needs-validation" novalidate>
          <?= csrf_field() ?><input type="hidden" name="action" value="shipment">
          <div class="col-md-5"><label class="form-label small">Carrier</label>
            <input name="carrier" class="form-control form-control-sm" maxlength="60"
                   placeholder="e.g. LBC Express" value="<?= e($shipment['carrier']) ?>"></div>
          <div class="col-md-5"><label class="form-label small">Tracking #</label>
            <input name="tracking_number" class="form-control form-control-sm" maxlength="60"
                   pattern="[A-Za-z0-9\- ]{4,60}" placeholder="e.g. LBC123456789"
                   value="<?= e($shipment['tracking_number']) ?>">
            <div class="invalid-feedback">Letters, numbers, spaces and hyphens only (4–60).</div></div>
          <div class="col-md-2"><button class="btn btn-sm btn-outline-dark w-100">Save</button></div>
        </form>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <div class="col-lg-4">
    <!-- Status control -->
    <div class="card shadow-sm mb-3">
      <div class="card-header bg-white fw-semibold">Update Status</div>
      <div class="card-body">
        <form method="post">
          <?= csrf_field() ?><input type="hidden" name="action" value="status">
          <select name="status" class="form-select mb-2">
            <?php foreach (['pending','confirmed','shipped','delivered','cancelled'] as $s): ?>
              <option value="<?= $s ?>" <?= $order['status']===$s?'selected':'' ?> class="text-capitalize"><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
          </select>
          <button class="btn btn-warning w-100" data-confirm="Update order status?">Update</button>
        </form>
      </div>
    </div>
    <!-- Customer -->
    <div class="card shadow-sm mb-3">
      <div class="card-header bg-white fw-semibold">Customer</div>
      <div class="card-body small">
        <strong><?= e($order['customer']) ?></strong><br><?= e($order['email']) ?>
        <?php if ($order['recipient_name']): ?>
          <hr><strong>Ship to:</strong><br><?= e($order['recipient_name']) ?><br>
          <?= e($order['line1']) ?>, <?= e($order['city'].', '.$order['state'].' '.$order['postal_code']) ?>
        <?php endif; ?>
      </div>
    </div>
    <!-- Payment -->
    <div class="card shadow-sm">
      <div class="card-header bg-white fw-semibold">Payment</div>
      <div class="card-body small">
        <?php if ($payment): ?>
          Method: <strong class="text-uppercase"><?= e(str_replace('_',' ',$payment['payment_method'])) ?></strong><br>
          Status: <?= status_badge($payment['status']) ?><br>
          <?php if ($payment['transaction_ref']): ?>Ref: <code><?= e($payment['transaction_ref']) ?></code><?php endif; ?>
        <?php else: ?><span class="text-muted">No payment.</span><?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php include INCLUDES_PATH . '/dash_close.php'; include INCLUDES_PATH . '/footer.php'; ?>
