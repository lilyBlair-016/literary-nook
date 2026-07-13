<?php
/**
 * admin/customer_detail.php — A single customer's profile + activity (orders,
 * wishlist, addresses) for the "Customer Activity" requirement.
 */
require_once __DIR__ . '/../config/config.php';
require_admin();
$cid = (int) ($_GET['id'] ?? 0);

$c = db_one("SELECT * FROM users WHERE user_id = ? AND role = 'customer'", [$cid]);
if (!$c) { set_flash('Customer not found.', 'danger'); redirect('admin/customers.php'); }

$orders = db_all('SELECT order_id, order_number, status, total_amount, placed_at FROM orders WHERE user_id=? ORDER BY placed_at DESC', [$cid]);
$wishlist = db_all('SELECT b.title FROM wishlists w JOIN books b ON b.book_id=w.book_id WHERE w.user_id=?', [$cid]);
$addresses = db_all('SELECT * FROM addresses WHERE user_id=?', [$cid]);
$genres = db_all('SELECT g.name FROM user_preferred_genres upg JOIN genres g ON g.genre_id=upg.genre_id WHERE upg.user_id=?', [$cid]);
$spent = (float) db_scalar("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE user_id=? AND status IN('confirmed','shipped','delivered')", [$cid]);

$page_title = 'Customer · ' . $c['first_name'];
$active = 'customers';
$dash_title = 'Customer Profile';
include INCLUDES_PATH . '/header.php';
include INCLUDES_PATH . '/dash_open.php';
?>
<a href="<?= url('admin/customers.php') ?>" class="btn btn-sm btn-outline-secondary mb-3"><i class="bi bi-arrow-left me-1"></i>Back to customers</a>
<div class="row g-4">
  <div class="col-lg-4">
    <div class="card shadow-sm text-center">
      <div class="card-body">
        <i class="bi bi-person-circle text-warning" style="font-size:3.5rem;"></i>
        <h5 class="mt-2 mb-0"><?= e($c['first_name'].' '.$c['last_name']) ?></h5>
        <p class="text-muted small mb-1"><?= e($c['email']) ?></p>
        <span class="badge text-bg-dark text-uppercase"><?= e($c['membership_status']) ?></span>
        <?= $c['is_active'] ? '<span class="badge text-bg-success">Active</span>' : '<span class="badge text-bg-secondary">Inactive</span>' ?>
        <hr>
        <div class="d-flex justify-content-around">
          <div><div class="fw-bold"><?= count($orders) ?></div><div class="small text-muted">Orders</div></div>
          <div><div class="fw-bold"><?= money($spent) ?></div><div class="small text-muted">Spent</div></div>
          <div><div class="fw-bold"><?= count($wishlist) ?></div><div class="small text-muted">Wishlist</div></div>
        </div>
        <hr>
        <div class="text-start small">
          <strong>Phone:</strong> <?= e($c['phone'] ?: '—') ?><br>
          <strong>Joined:</strong> <?= nice_date($c['created_at']) ?><br>
          <strong>Genres:</strong> <?= $genres ? e(implode(', ', array_column($genres,'name'))) : '—' ?>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-8">
    <div class="card shadow-sm mb-3">
      <div class="card-header bg-white fw-semibold">Orders</div>
      <div class="table-responsive"><table class="table mb-0">
        <thead class="table-light"><tr><th>Order</th><th>Date</th><th>Status</th><th class="text-end">Total</th></tr></thead>
        <tbody>
        <?php foreach ($orders as $o): ?>
          <tr><td><a href="<?= url('admin/order_detail.php?id='.(int)$o['order_id']) ?>"><?= e($o['order_number']) ?></a></td>
              <td class="small text-muted"><?= nice_date($o['placed_at']) ?></td><td><?= status_badge($o['status']) ?></td>
              <td class="text-end"><?= money($o['total_amount']) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$orders): ?><tr><td colspan="4" class="text-center text-muted py-3">No orders.</td></tr><?php endif; ?>
        </tbody>
      </table></div>
    </div>
    <div class="card shadow-sm">
      <div class="card-header bg-white fw-semibold">Wishlist</div>
      <div class="card-body">
        <?php if ($wishlist): foreach ($wishlist as $w): ?>
          <span class="badge text-bg-light border mb-1"><i class="bi bi-heart text-danger"></i> <?= e($w['title']) ?></span>
        <?php endforeach; else: ?><span class="text-muted small">Empty.</span><?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php include INCLUDES_PATH . '/dash_close.php'; include INCLUDES_PATH . '/footer.php'; ?>
