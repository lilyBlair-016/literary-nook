<?php
/**
 * admin/dashboard.php — Admin landing with key counts + quick links.
 * (A fuller reporting dashboard is built in Phase 11.)
 */
require_once __DIR__ . '/../config/config.php';
require_admin();

$stats = [
    'books'     => (int) db_scalar('SELECT COUNT(*) FROM books'),
    'customers' => (int) db_scalar("SELECT COUNT(*) FROM users WHERE role = 'customer'"),
    'orders'    => (int) db_scalar('SELECT COUNT(*) FROM orders'),
    'revenue'   => (float) db_scalar("SELECT COALESCE(SUM(total_amount),0) FROM orders
                                      WHERE status IN ('confirmed','shipped','delivered')"),
    'low_stock' => (int) db_scalar('SELECT COUNT(*) FROM book_formats WHERE is_digital = 0 AND stock_qty <= 10'),
    'pending'   => (int) db_scalar("SELECT COUNT(*) FROM orders WHERE status = 'pending'"),
];

$page_title = 'Admin Dashboard';
$active = 'dashboard';
$dash_title = 'Admin Dashboard';
include INCLUDES_PATH . '/header.php';
include INCLUDES_PATH . '/dash_open.php';
?>
<div class="row row-cols-2 row-cols-lg-4 g-3 mb-4">
  <?php
  $cards = [
      ['Books',     $stats['books'],              'bi-book',        url('admin/books.php')],
      ['Customers', $stats['customers'],          'bi-people',      '#'],
      ['Orders',    $stats['orders'],             'bi-bag-check',   '#'],
      ['Revenue',   money($stats['revenue']),     'bi-cash-stack',  '#'],
      ['Low stock', $stats['low_stock'] . ' items','bi-exclamation-triangle', url('admin/inventory.php')],
      ['Pending',   $stats['pending'] . ' orders','bi-hourglass',   '#'],
  ];
  foreach ($cards as [$label, $value, $icon, $link]): ?>
    <div class="col">
      <a href="<?= e($link) ?>" class="text-decoration-none">
        <div class="card stat-card shadow-sm h-100">
          <div class="card-body d-flex justify-content-between">
            <div><div class="text-muted small text-uppercase"><?= e($label) ?></div>
              <div class="stat-value text-dark"><?= $value ?></div></div>
            <i class="bi <?= $icon ?> fs-2 text-warning"></i>
          </div>
        </div>
      </a>
    </div>
  <?php endforeach; ?>
</div>

<div class="card shadow-sm">
  <div class="card-header bg-white fw-semibold">Quick Actions</div>
  <div class="card-body d-flex flex-wrap gap-2">
    <a href="<?= url('admin/book_form.php') ?>" class="btn btn-warning"><i class="bi bi-plus-lg me-1"></i>Add Book</a>
    <a href="<?= url('admin/books.php') ?>" class="btn btn-outline-dark"><i class="bi bi-book me-1"></i>Manage Books</a>
    <a href="<?= url('admin/inventory.php') ?>" class="btn btn-outline-dark"><i class="bi bi-boxes me-1"></i>Stock</a>
    <a href="<?= url('admin/taxonomy.php?type=authors') ?>" class="btn btn-outline-dark"><i class="bi bi-tags me-1"></i>Catalog Data</a>
  </div>
</div>

<?php
include INCLUDES_PATH . '/dash_close.php';
include INCLUDES_PATH . '/footer.php';
