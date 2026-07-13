<?php
/**
 * admin/inventory.php — Stock management for physical formats.
 * Set an exact quantity, or quick-adjust by a delta. Digital formats are
 * unlimited and shown read-only.
 */
require_once __DIR__ . '/../config/config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $fid    = (int) ($_POST['format_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    // Only physical formats can have their stock changed.
    $fmt = db_one('SELECT * FROM book_formats WHERE format_id = ? AND is_digital = 0', [$fid]);
    if ($fmt) {
        if ($action === 'set') {
            $qty = max(0, (int) ($_POST['stock'] ?? 0));
            db_exec('UPDATE book_formats SET stock_qty = ? WHERE format_id = ?', [$qty, $fid]);
            set_flash('Stock updated.', 'success');
        } elseif ($action === 'adjust') {
            $delta = (int) ($_POST['delta'] ?? 0);
            $new   = max(0, (int) $fmt['stock_qty'] + $delta);
            db_exec('UPDATE book_formats SET stock_qty = ? WHERE format_id = ?', [$new, $fid]);
            set_flash('Stock adjusted by ' . ($delta > 0 ? '+' : '') . $delta . '.', 'success');
        }
    }
    redirect('admin/inventory.php' . (!empty($_GET['low']) ? '?low=1' : ''));
}

$lowOnly = !empty($_GET['low']);
$whereSql = $lowOnly ? 'WHERE f.is_digital = 0 AND f.stock_qty <= 10' : '';

$rows = db_all(
    "SELECT f.format_id, f.format_type, f.is_digital, f.price, f.stock_qty,
            b.title, b.book_id
     FROM book_formats f
     JOIN books b ON b.book_id = f.book_id
     $whereSql
     ORDER BY (f.is_digital = 0 AND f.stock_qty <= 10) DESC, b.title, f.format_type");

$lowCount = (int) db_scalar('SELECT COUNT(*) FROM book_formats WHERE is_digital = 0 AND stock_qty <= 10');

$page_title = 'Inventory';
$active = 'inventory';
$dash_title = 'Stock Management';
include INCLUDES_PATH . '/header.php';
include INCLUDES_PATH . '/dash_open.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div class="btn-group">
    <a href="<?= url('admin/inventory.php') ?>" class="btn btn-sm <?= $lowOnly?'btn-outline-dark':'btn-dark' ?>">All</a>
    <a href="<?= url('admin/inventory.php?low=1') ?>" class="btn btn-sm <?= $lowOnly?'btn-warning':'btn-outline-warning' ?>">
      Low stock <span class="badge text-bg-danger"><?= $lowCount ?></span></a>
  </div>
</div>

<div class="card shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr><th>Book</th><th>Format</th><th class="text-end">Price</th><th class="text-center">Stock</th>
            <th style="width:260px;">Update</th></tr>
      </thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="5"><div class="empty-state"><i class="bi bi-boxes d-block mb-2"></i>Nothing to show.</div></td></tr>
      <?php else: foreach ($rows as $r):
          $digital = (int) $r['is_digital'] === 1;
          $low = !$digital && (int) $r['stock_qty'] <= 10; ?>
        <tr class="<?= $low ? 'table-warning' : '' ?>">
          <td><a href="<?= url('books/view.php?id='.(int)$r['book_id']) ?>" target="_blank"><?= e($r['title']) ?></a></td>
          <td><span class="badge text-bg-dark text-capitalize"><?= e($r['format_type']) ?></span></td>
          <td class="text-end"><?= money($r['price']) ?></td>
          <td class="text-center">
            <?php if ($digital): ?><span class="text-muted">∞</span>
            <?php else: ?><span class="fw-semibold <?= $low?'text-danger':'' ?>"><?= (int) $r['stock_qty'] ?></span><?php endif; ?>
          </td>
          <td>
            <?php if ($digital): ?>
              <span class="text-muted small">Digital — unlimited</span>
            <?php else: ?>
              <div class="d-flex gap-1">
                <form method="post" class="d-flex gap-1">
                  <?= csrf_field() ?><input type="hidden" name="action" value="set">
                  <input type="hidden" name="format_id" value="<?= (int) $r['format_id'] ?>">
                  <input type="number" name="stock" min="0" value="<?= (int) $r['stock_qty'] ?>"
                         class="form-control form-control-sm" style="width:80px;">
                  <button class="btn btn-sm btn-outline-dark">Set</button>
                </form>
                <form method="post">
                  <?= csrf_field() ?><input type="hidden" name="action" value="adjust">
                  <input type="hidden" name="format_id" value="<?= (int) $r['format_id'] ?>">
                  <input type="hidden" name="delta" value="10">
                  <button class="btn btn-sm btn-outline-success" title="+10">+10</button>
                </form>
              </div>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php
include INCLUDES_PATH . '/dash_close.php';
include INCLUDES_PATH . '/footer.php';
