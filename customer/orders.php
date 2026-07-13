<?php
/**
 * customer/orders.php — Order history list.
 */
require_once __DIR__ . '/../config/config.php';
require_login();
$uid = (int) $_SESSION['user_id'];

/* Orders + item count per order (Module 6: LEFT JOIN, GROUP BY, COUNT). */
$orders = db_all(
    "SELECT o.order_id, o.order_number, o.status, o.total_amount, o.placed_at,
            COUNT(oi.order_item_id) AS item_count
     FROM orders o
     LEFT JOIN order_items oi ON oi.order_id = o.order_id
     WHERE o.user_id = ?
     GROUP BY o.order_id, o.order_number, o.status, o.total_amount, o.placed_at
     ORDER BY o.placed_at DESC", [$uid]);

$page_title = 'My Orders';
$active = 'orders';
$dash_title = 'My Orders';
include INCLUDES_PATH . '/header.php';
include INCLUDES_PATH . '/dash_open.php';
?>

<?php if (!$orders): ?>
  <div class="empty-state">
    <i class="bi bi-bag-x d-block mb-2"></i>You haven't placed any orders yet.
    <div><a href="<?= url('books/browse.php') ?>" class="btn btn-sm btn-warning mt-2">Start shopping</a></div>
  </div>
<?php else: ?>
  <div class="card shadow-sm">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr><th>Order #</th><th>Date</th><th class="text-center">Items</th><th>Status</th>
              <th class="text-end">Total</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($orders as $o): ?>
          <tr>
            <td class="fw-semibold"><?= e($o['order_number']) ?></td>
            <td class="small text-muted"><?= nice_datetime($o['placed_at']) ?></td>
            <td class="text-center"><?= (int) $o['item_count'] ?></td>
            <td><?= status_badge($o['status']) ?></td>
            <td class="text-end fw-semibold"><?= money($o['total_amount']) ?></td>
            <td class="text-end">
              <a href="<?= url('customer/order_detail.php?id=' . (int) $o['order_id']) ?>"
                 class="btn btn-sm btn-outline-dark">View</a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<?php
include INCLUDES_PATH . '/dash_close.php';
include INCLUDES_PATH . '/footer.php';
