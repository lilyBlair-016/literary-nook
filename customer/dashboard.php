<?php
/**
 * customer/dashboard.php — Customer home: quick stats + recent activity.
 */
require_once __DIR__ . '/../config/config.php';
require_login();
if (is_admin()) redirect('admin/dashboard.php');   // admins have their own panel

$uid = (int) $_SESSION['user_id'];

/* Stat aggregates (Module 6/7: COUNT, SUM, prepared statements). */
$stats = [
    'orders'   => (int) db_scalar('SELECT COUNT(*) FROM orders WHERE user_id = ?', [$uid]),
    'wishlist' => (int) db_scalar('SELECT COUNT(*) FROM wishlists WHERE user_id = ?', [$uid]),
    'spent'    => (float) db_scalar(
        "SELECT COALESCE(SUM(total_amount),0) FROM orders
         WHERE user_id = ? AND status IN ('confirmed','shipped','delivered')", [$uid]),
    'unread'   => (int) db_scalar('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [$uid]),
];

$recentOrders = db_all(
    'SELECT order_id, order_number, status, total_amount, placed_at
     FROM orders WHERE user_id = ? ORDER BY placed_at DESC LIMIT 5', [$uid]);

$notifications = db_all(
    'SELECT type, subject, message, is_read, created_at
     FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5', [$uid]);

$me = db_one('SELECT * FROM users WHERE user_id = ?', [$uid]);

$page_title = 'My Dashboard';
$active = 'dashboard';
$dash_title = 'Welcome back, ' . e($me['first_name']) . '!';
include INCLUDES_PATH . '/header.php';
include INCLUDES_PATH . '/dash_open.php';
?>

<!-- Stat cards -->
<div class="row row-cols-2 row-cols-lg-4 g-3 mb-4">
  <?php
  $cards = [
      ['My Orders',   $stats['orders'],           'bi-bag',        url('customer/orders.php')],
      ['Wishlist',    $stats['wishlist'],          'bi-heart',      url('customer/wishlist.php')],
      ['Total Spent', money($stats['spent']),      'bi-cash-stack', '#'],
      ['Unread',      $stats['unread'] . ' alerts','bi-bell',       '#'],
  ];
  foreach ($cards as [$label, $value, $icon, $link]): ?>
    <div class="col">
      <a href="<?= e($link) ?>" class="text-decoration-none">
        <div class="card stat-card shadow-sm h-100">
          <div class="card-body">
            <div class="d-flex justify-content-between">
              <div>
                <div class="text-muted small text-uppercase"><?= e($label) ?></div>
                <div class="stat-value text-dark"><?= $value ?></div>
              </div>
              <i class="bi <?= $icon ?> fs-2 text-warning"></i>
            </div>
          </div>
        </div>
      </a>
    </div>
  <?php endforeach; ?>
</div>

<div class="row g-4">
  <!-- Recent orders -->
  <div class="col-lg-7">
    <div class="card shadow-sm h-100">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-clock-history me-1"></i>Recent Orders</div>
      <div class="card-body p-0">
        <?php if (!$recentOrders): ?>
          <div class="empty-state"><i class="bi bi-bag-x d-block mb-2"></i>No orders yet.
            <div><a href="<?= url('books/browse.php') ?>" class="btn btn-sm btn-warning mt-2">Start shopping</a></div>
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light"><tr><th>Order</th><th>Date</th><th>Status</th><th class="text-end">Total</th></tr></thead>
              <tbody>
              <?php foreach ($recentOrders as $o): ?>
                <tr>
                  <td><a href="<?= url('customer/order_detail.php?id=' . (int) $o['order_id']) ?>"><?= e($o['order_number']) ?></a></td>
                  <td class="small text-muted"><?= nice_date($o['placed_at']) ?></td>
                  <td><?= status_badge($o['status']) ?></td>
                  <td class="text-end fw-semibold"><?= money($o['total_amount']) ?></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Notifications -->
  <div class="col-lg-5">
    <div class="card shadow-sm h-100">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-bell me-1"></i>Notifications</div>
      <div class="list-group list-group-flush">
        <?php if (!$notifications): ?>
          <div class="empty-state"><i class="bi bi-bell-slash d-block mb-2"></i>No notifications.</div>
        <?php else: foreach ($notifications as $n): ?>
          <div class="list-group-item <?= $n['is_read'] ? '' : 'bg-warning-subtle' ?>">
            <div class="d-flex justify-content-between">
              <strong class="small"><?= e($n['subject']) ?></strong>
              <span class="text-muted" style="font-size:.72rem;"><?= nice_date($n['created_at']) ?></span>
            </div>
            <div class="small text-muted"><?= e(excerpt($n['message'], 90)) ?></div>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
  </div>
</div>

<?php
include INCLUDES_PATH . '/dash_close.php';
include INCLUDES_PATH . '/footer.php';
