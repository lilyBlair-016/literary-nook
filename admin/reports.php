<?php
/**
 * admin/reports.php — Administrative analytics dashboard.
 *
 * Four areas, every figure read live from the database (Module 6/7: JOIN,
 * GROUP BY, aggregate functions, prepared statements):
 *   1. Inventory Status   2. Sales Statistics
 *   3. Customer Activity  4. Financial Reports
 *
 * Revenue convention used throughout:
 *   - Cancelled orders are excluded from every sales/revenue figure.
 *   - total_amount = subtotal − discount_amount + shipping_fee, so "net revenue"
 *     is SUM(total_amount); its components are broken out in the Financial section.
 */
require_once __DIR__ . '/../config/config.php';
require_admin();

/* Business assumptions that are NOT derivable from the schema. `books` has no
   cost price, so true profit cannot be computed — we estimate from a stated
   margin and label it as an estimate everywhere it is shown. */
const GROSS_MARGIN    = 0.40;   // assumed 40% gross margin on merchandise
const LOW_STOCK_LEVEL = 5;      // 1..this = "low stock"; 0 = out of stock
const TOP_N           = 8;      // rows in each "top …" table

/* Only these orders count as sales. */
const SOLD = "o.status <> 'cancelled'";

/* =============================================================================
   1. INVENTORY STATUS
   ========================================================================== */
$inv = db_one("
    SELECT COUNT(DISTINCT b.book_id) AS titles,
           COUNT(f.format_id)        AS formats,
           COALESCE(SUM(CASE WHEN f.is_digital = 0 THEN f.stock_qty END),0)           AS units,
           COALESCE(SUM(CASE WHEN f.is_digital = 0 THEN f.stock_qty * f.price END),0) AS stock_value
    FROM books b JOIN book_formats f ON f.book_id = b.book_id
    WHERE b.is_active = 1");

$buckets = db_one("
    SELECT
      SUM(CASE WHEN f.is_digital = 1 THEN 1 ELSE 0 END)                        AS digital,
      SUM(CASE WHEN f.is_digital = 0 AND f.stock_qty = 0 THEN 1 ELSE 0 END)    AS out_of_stock,
      SUM(CASE WHEN f.is_digital = 0 AND f.stock_qty > 0
                AND f.stock_qty <= " . LOW_STOCK_LEVEL . " THEN 1 ELSE 0 END)  AS low_stock,
      SUM(CASE WHEN f.is_digital = 0
                AND f.stock_qty > " . LOW_STOCK_LEVEL . " THEN 1 ELSE 0 END)   AS healthy
    FROM books b JOIN book_formats f ON f.book_id = b.book_id
    WHERE b.is_active = 1");

$lowStock = db_all("
    SELECT b.book_id, b.title, a.name AS author, f.format_type, f.stock_qty
    FROM book_formats f
    JOIN books b   ON b.book_id   = f.book_id
    JOIN authors a ON a.author_id = b.author_id
    WHERE b.is_active = 1 AND f.is_digital = 0 AND f.stock_qty <= " . LOW_STOCK_LEVEL . "
    ORDER BY f.stock_qty ASC, b.title LIMIT 12");

$invByCategory = db_all("
    SELECT COALESCE(c.name,'Uncategorised') AS label,
           COUNT(DISTINCT b.book_id) AS titles,
           COALESCE(SUM(CASE WHEN f.is_digital = 0 THEN f.stock_qty END),0)            AS units,
           COALESCE(SUM(CASE WHEN f.is_digital = 0 THEN f.stock_qty * f.price END),0)  AS value
    FROM books b
    LEFT JOIN categories c ON c.category_id = b.category_id
    JOIN book_formats f    ON f.book_id     = b.book_id
    WHERE b.is_active = 1
    GROUP BY label ORDER BY value DESC");

$invByFormat = db_all("
    SELECT f.format_type AS label, COUNT(*) AS listings,
           COALESCE(SUM(CASE WHEN f.is_digital = 0 THEN f.stock_qty END),0)           AS units,
           COALESCE(SUM(CASE WHEN f.is_digital = 0 THEN f.stock_qty * f.price END),0) AS value
    FROM book_formats f JOIN books b ON b.book_id = f.book_id
    WHERE b.is_active = 1
    GROUP BY f.format_type ORDER BY units DESC");

$recentBooks = db_all("
    SELECT b.book_id, b.title, a.name AS author, b.created_at, COUNT(f.format_id) AS formats
    FROM books b
    JOIN authors a           ON a.author_id = b.author_id
    LEFT JOIN book_formats f ON f.book_id   = b.book_id
    GROUP BY b.book_id, b.title, a.name, b.created_at
    ORDER BY b.created_at DESC LIMIT 6");

/* =============================================================================
   2. SALES STATISTICS
   ========================================================================== */
$period = db_one("
    SELECT
      COALESCE(SUM(CASE WHEN DATE(o.placed_at) = CURDATE()                          THEN o.total_amount END),0) AS today,
      COALESCE(SUM(CASE WHEN o.placed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)         THEN o.total_amount END),0) AS week,
      COALESCE(SUM(CASE WHEN o.placed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)        THEN o.total_amount END),0) AS month,
      COALESCE(SUM(CASE WHEN YEAR(o.placed_at) = YEAR(CURDATE())                    THEN o.total_amount END),0) AS year
    FROM orders o WHERE " . SOLD);

$sales = db_one("
    SELECT COUNT(*) AS orders, COALESCE(SUM(o.total_amount),0) AS revenue,
           COALESCE(AVG(o.total_amount),0) AS aov
    FROM orders o WHERE " . SOLD);

$unitsSold = (int) db_scalar("
    SELECT COALESCE(SUM(oi.quantity),0)
    FROM order_items oi JOIN orders o ON o.order_id = oi.order_id
    WHERE " . SOLD);

$revenueSeries = db_all("
    SELECT DATE_FORMAT(o.placed_at,'%Y-%m') AS ym,
           DATE_FORMAT(o.placed_at,'%b %Y') AS label,
           SUM(o.total_amount) AS revenue, COUNT(*) AS orders
    FROM orders o
    WHERE " . SOLD . " AND o.placed_at >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)
    GROUP BY ym, label ORDER BY ym");

$bestSellers = db_all("
    SELECT oi.book_title AS label, SUM(oi.quantity) AS units, SUM(oi.line_total) AS revenue
    FROM order_items oi JOIN orders o ON o.order_id = oi.order_id
    WHERE " . SOLD . "
    GROUP BY oi.book_title ORDER BY units DESC, revenue DESC LIMIT " . TOP_N);

$bestCategories = db_all("
    SELECT COALESCE(c.name,'Uncategorised') AS label,
           SUM(oi.quantity) AS units, SUM(oi.line_total) AS revenue
    FROM order_items oi
    JOIN orders o          ON o.order_id    = oi.order_id
    JOIN book_formats f    ON f.format_id   = oi.format_id
    JOIN books b           ON b.book_id     = f.book_id
    LEFT JOIN categories c ON c.category_id = b.category_id
    WHERE " . SOLD . "
    GROUP BY label ORDER BY revenue DESC");

$topAuthors = db_all("
    SELECT a.name AS label, SUM(oi.quantity) AS units, SUM(oi.line_total) AS revenue
    FROM order_items oi
    JOIN orders o       ON o.order_id  = oi.order_id
    JOIN book_formats f ON f.format_id = oi.format_id
    JOIN books b        ON b.book_id   = f.book_id
    JOIN authors a      ON a.author_id = b.author_id
    WHERE " . SOLD . "
    GROUP BY a.name ORDER BY units DESC LIMIT " . TOP_N);

$ordersByStatus = db_all("
    SELECT status AS label, COUNT(*) AS n FROM orders GROUP BY status ORDER BY n DESC");

$byPaymentMethod = db_all("
    SELECT p.payment_method AS label, COUNT(*) AS n, COALESCE(SUM(p.amount),0) AS value
    FROM payments p WHERE p.status = 'completed'
    GROUP BY p.payment_method ORDER BY value DESC");

/* =============================================================================
   3. CUSTOMER ACTIVITY
   ========================================================================== */
$cust = db_one("
    SELECT COUNT(*) AS total,
           SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS new_30d,
           SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active
    FROM users WHERE role = 'customer'");

$buyers = db_one("
    SELECT COUNT(*) AS buyers,
           COALESCE(SUM(CASE WHEN n > 1 THEN 1 ELSE 0 END),0) AS repeat_buyers,
           COALESCE(AVG(n),0) AS avg_orders
    FROM (SELECT o.user_id, COUNT(*) AS n FROM orders o WHERE " . SOLD . " GROUP BY o.user_id) t");

$topSpenders = db_all("
    SELECT CONCAT(u.first_name,' ',u.last_name) AS label, u.email,
           COUNT(o.order_id) AS orders, SUM(o.total_amount) AS spent
    FROM orders o JOIN users u ON u.user_id = o.user_id
    WHERE " . SOLD . "
    GROUP BY u.user_id, label, u.email ORDER BY spent DESC LIMIT " . TOP_N);

$signupSeries = db_all("
    SELECT DATE_FORMAT(created_at,'%Y-%m') AS ym, DATE_FORMAT(created_at,'%b %Y') AS label,
           COUNT(*) AS n
    FROM users
    WHERE role = 'customer' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)
    GROUP BY ym, label ORDER BY ym");

$wish = db_one("
    SELECT COUNT(*) AS entries, COUNT(DISTINCT user_id) AS users, COUNT(DISTINCT book_id) AS books
    FROM wishlists");

$mostWishlisted = db_all("
    SELECT b.title AS label, a.name AS author, COUNT(*) AS n
    FROM wishlists w
    JOIN books b   ON b.book_id   = w.book_id
    JOIN authors a ON a.author_id = b.author_id
    GROUP BY b.book_id, b.title, a.name ORDER BY n DESC LIMIT " . TOP_N);

$recentCustomers = db_all("
    SELECT CONCAT(u.first_name,' ',u.last_name) AS label,
           MAX(o.placed_at) AS last_order, COUNT(o.order_id) AS orders
    FROM users u JOIN orders o ON o.user_id = u.user_id
    WHERE " . SOLD . "
    GROUP BY u.user_id, label ORDER BY last_order DESC LIMIT " . TOP_N);

/* =============================================================================
   4. FINANCIAL REPORTS
   ========================================================================== */
$fin = db_one("
    SELECT COALESCE(SUM(o.subtotal),0)        AS gross,
           COALESCE(SUM(o.discount_amount),0) AS discounts,
           COALESCE(SUM(o.shipping_fee),0)    AS shipping,
           COALESCE(SUM(o.total_amount),0)    AS net,
           COALESCE(AVG(o.total_amount),0)    AS avg_order
    FROM orders o WHERE " . SOLD);

/* Shipping is pass-through, so margin applies to merchandise only. */
$merchandise    = (float) $fin['gross'] - (float) $fin['discounts'];
$profitEstimate = $merchandise * GROSS_MARGIN;

$pay = db_one("
    SELECT COUNT(*) AS transactions,
      COALESCE(SUM(status='completed'),0) AS completed,
      COALESCE(SUM(status='pending'),0)   AS pending,
      COALESCE(SUM(status='refunded'),0)  AS refunded,
      COALESCE(SUM(status='failed'),0)    AS failed,
      COALESCE(SUM(CASE WHEN status='completed' THEN amount END),0) AS collected,
      COALESCE(SUM(CASE WHEN status='pending'   THEN amount END),0) AS outstanding,
      COALESCE(SUM(CASE WHEN status='refunded'  THEN amount END),0) AS refunded_value
    FROM payments");

/* ---- Chart payloads -------------------------------------------------------- */
$chart = [
    'revenue'  => ['labels' => array_column($revenueSeries, 'label'),
                   'data'   => array_map('floatval', array_column($revenueSeries, 'revenue')),
                   'orders' => array_map('intval',   array_column($revenueSeries, 'orders'))],
    'status'   => ['labels' => array_map('ucfirst', array_column($ordersByStatus, 'label')),
                   'data'   => array_map('intval',  array_column($ordersByStatus, 'n'))],
    'category' => ['labels' => array_column($bestCategories, 'label'),
                   'data'   => array_map('floatval', array_column($bestCategories, 'revenue'))],
    'format'   => ['labels' => array_map('ucfirst', array_column($invByFormat, 'label')),
                   'data'   => array_map('intval',  array_column($invByFormat, 'units'))],
    'signups'  => ['labels' => array_column($signupSeries, 'label'),
                   'data'   => array_map('intval', array_column($signupSeries, 'n'))],
];

$page_title = 'Reports';
$active     = 'reports';
$dash_title = 'Administrative Reports';
include INCLUDES_PATH . '/header.php';
include INCLUDES_PATH . '/dash_open.php';

/** One KPI tile. */
function kpi(string $label, string $value, string $icon, string $sub = ''): void { ?>
  <div class="col-6 col-lg-3">
    <div class="card shadow-sm h-100 kpi-card">
      <div class="card-body">
        <div class="d-flex align-items-start justify-content-between">
          <span class="text-muted small text-uppercase kpi-label"><?= e($label) ?></span>
          <i class="bi <?= e($icon) ?> kpi-icon"></i>
        </div>
        <div class="kpi-value"><?= e($value) ?></div>
        <?php if ($sub !== ''): ?><div class="small text-muted"><?= e($sub) ?></div><?php endif; ?>
      </div>
    </div>
  </div>
<?php } ?>

<!-- ========== HEADLINE KPIs ========== -->
<div class="row g-3 mb-4">
  <?php
    kpi('Net revenue',     money($fin['net']),                     'bi-cash-coin',       'cancelled orders excluded');
    kpi('Orders',          number_format((int) $sales['orders']),  'bi-receipt',         'lifetime');
    kpi('Avg order value', money($sales['aov']),                   'bi-graph-up-arrow');
    kpi('Books sold',      number_format($unitsSold),              'bi-book',            'units, all formats');
    kpi('Customers',       number_format((int) $cust['total']),    'bi-people',          (int) $cust['new_30d'] . ' new in 30 days');
    kpi('Inventory value', money($inv['stock_value']),             'bi-boxes',           'physical stock at list price');
    kpi('Units in stock',  number_format((int) $inv['units']),     'bi-box-seam',        (int) $inv['titles'] . ' active titles');
    kpi('Wishlisted',      number_format((int) $wish['entries']),  'bi-heart',           (int) $wish['books'] . ' distinct books');
  ?>
</div>

<!-- ========== CHARTS ========== -->
<div class="row g-4 mb-4">
  <div class="col-lg-8">
    <div class="card shadow-sm h-100">
      <div class="card-header bg-white fw-semibold">
        <i class="bi bi-graph-up me-1"></i>Revenue over time
        <span class="text-muted fw-normal small">· last 12 months</span>
      </div>
      <div class="card-body">
        <?php if ($revenueSeries): ?><canvas id="revenueChart" height="110"></canvas>
        <?php else: ?><p class="text-muted text-center py-5 mb-0">No sales yet.</p><?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card shadow-sm h-100">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-pie-chart me-1"></i>Orders by status</div>
      <div class="card-body">
        <?php if ($ordersByStatus): ?><canvas id="statusChart" height="200"></canvas>
        <?php else: ?><p class="text-muted text-center py-5 mb-0">No orders yet.</p><?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card shadow-sm h-100">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-tags me-1"></i>Revenue by category</div>
      <div class="card-body">
        <?php if ($bestCategories): ?><canvas id="categoryChart" height="200"></canvas>
        <?php else: ?><p class="text-muted text-center py-5 mb-0">No sales yet.</p><?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card shadow-sm h-100">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-bar-chart me-1"></i>Stock by format</div>
      <div class="card-body">
        <?php if ($invByFormat): ?><canvas id="formatChart" height="200"></canvas>
        <?php else: ?><p class="text-muted text-center py-5 mb-0">No inventory.</p><?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card shadow-sm h-100">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-person-plus me-1"></i>Customer registrations</div>
      <div class="card-body">
        <?php if ($signupSeries): ?><canvas id="signupChart" height="200"></canvas>
        <?php else: ?><p class="text-muted text-center py-5 mb-0">No registrations yet.</p><?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- ========== 1. INVENTORY STATUS ========== -->
<h2 class="h5 report-section"><i class="bi bi-boxes me-2"></i>Inventory Status</h2>
<div class="row g-4 mb-4">
  <div class="col-lg-3">
    <div class="card shadow-sm h-100">
      <div class="card-header bg-white fw-semibold">Stock health</div>
      <div class="card-body">
        <div class="stat-line"><span><span class="dot bg-success"></span>Healthy (&gt; <?= LOW_STOCK_LEVEL ?>)</span><strong><?= (int) $buckets['healthy'] ?></strong></div>
        <div class="stat-line"><span><span class="dot bg-warning"></span>Low (1–<?= LOW_STOCK_LEVEL ?>)</span><strong><?= (int) $buckets['low_stock'] ?></strong></div>
        <div class="stat-line"><span><span class="dot bg-danger"></span>Out of stock</span><strong><?= (int) $buckets['out_of_stock'] ?></strong></div>
        <div class="stat-line"><span><span class="dot bg-info"></span>Digital (unlimited)</span><strong><?= (int) $buckets['digital'] ?></strong></div>
        <hr>
        <div class="stat-line"><span>Units in stock</span><strong><?= number_format((int) $inv['units']) ?></strong></div>
        <div class="stat-line"><span>Inventory value</span><strong><?= money($inv['stock_value']) ?></strong></div>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card shadow-sm h-100">
      <div class="card-header bg-white fw-semibold text-danger"><i class="bi bi-exclamation-triangle me-1"></i>Needs restocking</div>
      <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle">
          <thead class="table-light"><tr><th>Book</th><th>Format</th><th class="text-center">Stock</th></tr></thead>
          <tbody>
          <?php foreach ($lowStock as $r): ?>
            <tr>
              <td><a href="<?= url('admin/book_form.php?id=' . (int) $r['book_id']) ?>" class="text-decoration-none"><?= e(excerpt($r['title'], 30)) ?></a>
                  <div class="text-muted small"><?= e($r['author']) ?></div></td>
              <td class="text-capitalize small"><?= e($r['format_type']) ?></td>
              <td class="text-center">
                <span class="badge <?= (int) $r['stock_qty'] === 0 ? 'text-bg-danger' : 'text-bg-warning' ?>"><?= (int) $r['stock_qty'] ?></span>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$lowStock): ?>
            <tr><td colspan="3" class="text-center text-success py-4"><i class="bi bi-check-circle me-1"></i>Everything is well stocked.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card shadow-sm h-100">
      <div class="card-header bg-white fw-semibold">Inventory by category</div>
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead class="table-light"><tr><th>Category</th><th class="text-center">Titles</th><th class="text-end">Value</th></tr></thead>
          <tbody>
          <?php foreach ($invByCategory as $r): ?>
            <tr><td><?= e($r['label']) ?></td><td class="text-center"><?= (int) $r['titles'] ?></td>
                <td class="text-end"><?= money($r['value']) ?></td></tr>
          <?php endforeach; ?>
          <?php if (!$invByCategory): ?><tr><td colspan="3" class="text-center text-muted py-3">No data.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card shadow-sm h-100">
      <div class="card-header bg-white fw-semibold">Inventory by format</div>
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead class="table-light"><tr><th>Format</th><th class="text-center">Listings</th><th class="text-center">Units</th><th class="text-end">Value</th></tr></thead>
          <tbody>
          <?php foreach ($invByFormat as $r): ?>
            <tr><td class="text-capitalize"><?= e($r['label']) ?></td>
                <td class="text-center"><?= (int) $r['listings'] ?></td>
                <td class="text-center"><?= (int) $r['units'] > 0 ? (int) $r['units'] : '<span class="text-muted">∞</span>' ?></td>
                <td class="text-end"><?= money($r['value']) ?></td></tr>
          <?php endforeach; ?>
          <?php if (!$invByFormat): ?><tr><td colspan="4" class="text-center text-muted py-3">No data.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card shadow-sm h-100">
      <div class="card-header bg-white fw-semibold">Recently added books</div>
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead class="table-light"><tr><th>Title</th><th class="text-center">Formats</th><th class="text-end">Added</th></tr></thead>
          <tbody>
          <?php foreach ($recentBooks as $r): ?>
            <tr><td><a href="<?= url('admin/book_form.php?id=' . (int) $r['book_id']) ?>" class="text-decoration-none"><?= e(excerpt($r['title'], 28)) ?></a>
                    <div class="text-muted small"><?= e($r['author']) ?></div></td>
                <td class="text-center"><?= (int) $r['formats'] ?></td>
                <td class="text-end small text-muted"><?= nice_date($r['created_at']) ?></td></tr>
          <?php endforeach; ?>
          <?php if (!$recentBooks): ?><tr><td colspan="3" class="text-center text-muted py-3">No books.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- ========== 2. SALES STATISTICS ========== -->
<h2 class="h5 report-section"><i class="bi bi-graph-up-arrow me-2"></i>Sales Statistics</h2>
<div class="row g-3 mb-4">
  <?php
    kpi('Today',      money($period['today']), 'bi-calendar-day');
    kpi('This week',  money($period['week']),  'bi-calendar-week',  'last 7 days');
    kpi('This month', money($period['month']), 'bi-calendar-month', 'last 30 days');
    kpi('This year',  money($period['year']),  'bi-calendar3',      date('Y'));
  ?>
</div>
<div class="row g-4 mb-4">
  <div class="col-lg-4">
    <div class="card shadow-sm h-100">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-trophy me-1"></i>Best-selling books</div>
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead class="table-light"><tr><th>Title</th><th class="text-center">Units</th><th class="text-end">Revenue</th></tr></thead>
          <tbody>
          <?php foreach ($bestSellers as $r): ?>
            <tr><td><?= e(excerpt($r['label'], 28)) ?></td>
                <td class="text-center"><strong><?= (int) $r['units'] ?></strong></td>
                <td class="text-end"><?= money($r['revenue']) ?></td></tr>
          <?php endforeach; ?>
          <?php if (!$bestSellers): ?><tr><td colspan="3" class="text-center text-muted py-3">No sales yet.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card shadow-sm h-100">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-person-badge me-1"></i>Most popular authors</div>
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead class="table-light"><tr><th>Author</th><th class="text-center">Units</th><th class="text-end">Revenue</th></tr></thead>
          <tbody>
          <?php foreach ($topAuthors as $r): ?>
            <tr><td><?= e($r['label']) ?></td>
                <td class="text-center"><strong><?= (int) $r['units'] ?></strong></td>
                <td class="text-end"><?= money($r['revenue']) ?></td></tr>
          <?php endforeach; ?>
          <?php if (!$topAuthors): ?><tr><td colspan="3" class="text-center text-muted py-3">No sales yet.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card shadow-sm h-100">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-wallet2 me-1"></i>Revenue by payment method</div>
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead class="table-light"><tr><th>Method</th><th class="text-center">Txns</th><th class="text-end">Collected</th></tr></thead>
          <tbody>
          <?php foreach ($byPaymentMethod as $r): ?>
            <tr><td class="text-uppercase small"><?= e(str_replace('_', ' ', $r['label'])) ?></td>
                <td class="text-center"><?= (int) $r['n'] ?></td>
                <td class="text-end"><?= money($r['value']) ?></td></tr>
          <?php endforeach; ?>
          <?php if (!$byPaymentMethod): ?><tr><td colspan="3" class="text-center text-muted py-3">No completed payments.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- ========== 3. CUSTOMER ACTIVITY ========== -->
<h2 class="h5 report-section"><i class="bi bi-people me-2"></i>Customer Activity</h2>
<div class="row g-3 mb-4">
  <?php
    $buyerCount = (int) $buyers['buyers'];
    $repeatRate = $buyerCount > 0 ? round(100 * (int) $buyers['repeat_buyers'] / $buyerCount) : 0;
    kpi('Customers who bought', number_format($buyerCount), 'bi-bag-check', (int) $cust['total'] . ' registered');
    kpi('Repeat customers', number_format((int) $buyers['repeat_buyers']), 'bi-arrow-repeat', $repeatRate . '% of buyers');
    kpi('Purchase frequency', number_format((float) $buyers['avg_orders'], 1), 'bi-cart-check', 'orders per buying customer');
    kpi('Active accounts', number_format((int) $cust['active']), 'bi-person-check', 'not deactivated');
  ?>
</div>
<div class="row g-4 mb-4">
  <div class="col-lg-4">
    <div class="card shadow-sm h-100">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-star me-1"></i>Top spending customers</div>
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead class="table-light"><tr><th>Customer</th><th class="text-center">Orders</th><th class="text-end">Spent</th></tr></thead>
          <tbody>
          <?php foreach ($topSpenders as $r): ?>
            <tr><td><?= e($r['label']) ?><div class="text-muted small"><?= e(excerpt($r['email'], 24)) ?></div></td>
                <td class="text-center"><?= (int) $r['orders'] ?></td>
                <td class="text-end"><strong><?= money($r['spent']) ?></strong></td></tr>
          <?php endforeach; ?>
          <?php if (!$topSpenders): ?><tr><td colspan="3" class="text-center text-muted py-3">No purchases yet.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card shadow-sm h-100">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-heart me-1"></i>Most wishlisted books</div>
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead class="table-light"><tr><th>Book</th><th class="text-end">Saves</th></tr></thead>
          <tbody>
          <?php foreach ($mostWishlisted as $r): ?>
            <tr><td><?= e(excerpt($r['label'], 30)) ?><div class="text-muted small"><?= e($r['author']) ?></div></td>
                <td class="text-end"><span class="badge text-bg-light border"><?= (int) $r['n'] ?></span></td></tr>
          <?php endforeach; ?>
          <?php if (!$mostWishlisted): ?><tr><td colspan="2" class="text-center text-muted py-3">No wishlist activity.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card shadow-sm h-100">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-clock-history me-1"></i>Recently active customers</div>
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead class="table-light"><tr><th>Customer</th><th class="text-center">Orders</th><th class="text-end">Last order</th></tr></thead>
          <tbody>
          <?php foreach ($recentCustomers as $r): ?>
            <tr><td><?= e($r['label']) ?></td>
                <td class="text-center"><?= (int) $r['orders'] ?></td>
                <td class="text-end small text-muted"><?= nice_date($r['last_order']) ?></td></tr>
          <?php endforeach; ?>
          <?php if (!$recentCustomers): ?><tr><td colspan="3" class="text-center text-muted py-3">No activity.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- ========== 4. FINANCIAL REPORTS ========== -->
<h2 class="h5 report-section"><i class="bi bi-calculator me-2"></i>Financial Reports</h2>
<div class="row g-4 mb-4">
  <div class="col-lg-5">
    <div class="card shadow-sm h-100">
      <div class="card-header bg-white fw-semibold">Revenue breakdown</div>
      <div class="card-body">
        <div class="stat-line"><span>Gross merchandise (subtotal)</span><strong><?= money($fin['gross']) ?></strong></div>
        <div class="stat-line"><span>Less: discounts given</span><strong class="text-success">−<?= money($fin['discounts']) ?></strong></div>
        <div class="stat-line"><span>Plus: shipping income</span><strong>+<?= money($fin['shipping']) ?></strong></div>
        <hr>
        <div class="stat-line fs-5"><span class="fw-semibold">Net revenue</span><strong><?= money($fin['net']) ?></strong></div>
        <div class="stat-line"><span>Average revenue per order</span><strong><?= money($fin['avg_order']) ?></strong></div>
        <div class="stat-line"><span>Total transactions</span><strong><?= (int) $pay['transactions'] ?></strong></div>
        <hr>
        <div class="stat-line">
          <span>Estimated gross profit</span>
          <strong class="text-success"><?= money($profitEstimate) ?></strong>
        </div>
        <div class="alert alert-warning small mt-2 mb-0 py-2">
          <i class="bi bi-info-circle me-1"></i>
          <strong>Estimate.</strong> Books carry no cost price in the database, so profit cannot be
          measured. This applies a <?= (int) (GROSS_MARGIN * 100) ?>% assumed margin to merchandise
          revenue (subtotal − discounts, excluding shipping). Add a cost column to report it exactly.
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-3">
    <div class="card shadow-sm h-100">
      <div class="card-header bg-white fw-semibold">Payments</div>
      <div class="card-body">
        <div class="stat-line"><span><span class="dot bg-success"></span>Completed</span><strong><?= (int) $pay['completed'] ?></strong></div>
        <div class="stat-line"><span><span class="dot bg-warning"></span>Pending</span><strong><?= (int) $pay['pending'] ?></strong></div>
        <div class="stat-line"><span><span class="dot bg-secondary"></span>Refunded</span><strong><?= (int) $pay['refunded'] ?></strong></div>
        <div class="stat-line"><span><span class="dot bg-danger"></span>Failed</span><strong><?= (int) $pay['failed'] ?></strong></div>
        <hr>
        <div class="stat-line"><span>Collected</span><strong class="text-success"><?= money($pay['collected']) ?></strong></div>
        <div class="stat-line"><span>Outstanding</span><strong class="text-warning"><?= money($pay['outstanding']) ?></strong></div>
        <div class="stat-line"><span>Refunded</span><strong><?= money($pay['refunded_value']) ?></strong></div>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card shadow-sm h-100">
      <div class="card-header bg-white fw-semibold">Revenue by month</div>
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead class="table-light"><tr><th>Month</th><th class="text-center">Orders</th><th class="text-end">Revenue</th></tr></thead>
          <tbody>
          <?php foreach (array_reverse($revenueSeries) as $r): ?>
            <tr><td><?= e($r['label']) ?></td>
                <td class="text-center"><?= (int) $r['orders'] ?></td>
                <td class="text-end"><?= money($r['revenue']) ?></td></tr>
          <?php endforeach; ?>
          <?php if (!$revenueSeries): ?><tr><td colspan="3" class="text-center text-muted py-3">No sales yet.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    if (typeof Chart === 'undefined') return;   // CDN unreachable — the tables still carry every figure

    var DATA = <?= json_encode($chart, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var CUR  = <?= json_encode(CURRENCY) ?>;

    /* Site palette (assets/css/style.css) so the charts match the theme. */
    var CARAMEL = '#C08552', BROWNIE = '#5E3023', COFFEE = '#895737', CREAM = '#F3E9DC';
    var SERIES  = [CARAMEL, BROWNIE, COFFEE, '#D9A470', '#7A4A32', '#E0C3A0', '#4A2419', '#B0704A'];
    var GRID    = 'rgba(94,48,35,.08)';

    Chart.defaults.color = '#6b5548';

    function money(v) {
        return CUR + Number(v).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function el(id) { return document.getElementById(id); }

    if (el('revenueChart')) {
        new Chart(el('revenueChart'), {
            type: 'line',
            data: { labels: DATA.revenue.labels, datasets: [{
                label: 'Revenue', data: DATA.revenue.data,
                borderColor: BROWNIE, backgroundColor: 'rgba(192,133,82,.22)',
                fill: true, tension: .35, borderWidth: 2,
                pointRadius: 4, pointBackgroundColor: CARAMEL, pointBorderColor: BROWNIE
            }]},
            options: {
                plugins: { legend: { display: false }, tooltip: { callbacks: {
                    label: function (c) {
                        return [' Revenue: ' + money(c.parsed.y),
                                ' Orders: '  + DATA.revenue.orders[c.dataIndex]];
                    }
                }}},
                scales: {
                    y: { beginAtZero: true, grid: { color: GRID },
                         ticks: { callback: function (v) { return money(v); } } },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    if (el('statusChart')) {
        new Chart(el('statusChart'), {
            type: 'doughnut',
            data: { labels: DATA.status.labels, datasets: [{
                data: DATA.status.data, backgroundColor: SERIES, borderColor: CREAM, borderWidth: 2 }]},
            options: { cutout: '58%',
                       plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, padding: 10 } } } }
        });
    }

    if (el('categoryChart')) {
        new Chart(el('categoryChart'), {
            type: 'pie',
            data: { labels: DATA.category.labels, datasets: [{
                data: DATA.category.data, backgroundColor: SERIES, borderColor: CREAM, borderWidth: 2 }]},
            options: { plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, padding: 10 } },
                tooltip: { callbacks: { label: function (c) { return ' ' + c.label + ': ' + money(c.parsed); } } }
            }}
        });
    }

    if (el('formatChart')) {
        new Chart(el('formatChart'), {
            type: 'bar',
            data: { labels: DATA.format.labels, datasets: [{
                label: 'Units in stock', data: DATA.format.data,
                backgroundColor: CARAMEL, borderColor: BROWNIE, borderWidth: 1, borderRadius: 5 }]},
            options: { plugins: { legend: { display: false } },
                       scales: { y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: GRID } },
                                 x: { grid: { display: false } } } }
        });
    }

    if (el('signupChart')) {
        new Chart(el('signupChart'), {
            type: 'bar',
            data: { labels: DATA.signups.labels, datasets: [{
                label: 'New customers', data: DATA.signups.data,
                backgroundColor: COFFEE, borderRadius: 5 }]},
            options: { plugins: { legend: { display: false } },
                       scales: { y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: GRID } },
                                 x: { grid: { display: false } } } }
        });
    }
})();
</script>

<?php
include INCLUDES_PATH . '/dash_close.php';
include INCLUDES_PATH . '/footer.php';
