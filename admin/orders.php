<?php
/**
 * admin/orders.php — All orders with status filter + search + pagination.
 */
require_once __DIR__ . '/../config/config.php';
require_admin();

$status = $_GET['status'] ?? '';
$q      = clean($_GET['q'] ?? '');
$page   = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($page - 1) * ITEMS_PER_PAGE;

$where = ['1=1']; $params = [];
$validStatuses = ['pending','confirmed','shipped','delivered','cancelled'];
if (in_array($status, $validStatuses, true)) { $where[] = 'o.status = ?'; $params[] = $status; }
if ($q !== '') {
    $where[] = '(o.order_number LIKE ? OR u.email LIKE ? OR CONCAT(u.first_name," ",u.last_name) LIKE ?)';
    $like = "%$q%"; array_push($params, $like, $like, $like);
}
$whereSql = implode(' AND ', $where);

$total = (int) db_scalar("SELECT COUNT(*) FROM orders o JOIN users u ON u.user_id=o.user_id WHERE $whereSql", $params);
$totalPages = max(1, (int) ceil($total / ITEMS_PER_PAGE));

$orders = db_all(
    "SELECT o.order_id, o.order_number, o.status, o.total_amount, o.placed_at,
            CONCAT(u.first_name,' ',u.last_name) AS customer, u.email,
            p.status AS pay_status
     FROM orders o
     JOIN users u ON u.user_id = o.user_id
     LEFT JOIN payments p ON p.payment_id = (SELECT MAX(payment_id) FROM payments WHERE order_id = o.order_id)
     WHERE $whereSql
     ORDER BY o.placed_at DESC
     LIMIT ? OFFSET ?", array_merge($params, [ITEMS_PER_PAGE, $offset]));

$page_title = 'Manage Orders';
$active = 'orders';
$dash_title = 'Manage Orders';
include INCLUDES_PATH . '/header.php';
include INCLUDES_PATH . '/dash_open.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div class="btn-group btn-group-sm flex-wrap">
    <a href="<?= url('admin/orders.php') ?>" class="btn <?= $status===''?'btn-dark':'btn-outline-dark' ?>">All</a>
    <?php foreach ($validStatuses as $s): ?>
      <a href="<?= url('admin/orders.php?status='.$s) ?>" class="btn text-capitalize <?= $status===$s?'btn-warning':'btn-outline-secondary' ?>"><?= $s ?></a>
    <?php endforeach; ?>
  </div>
  <form method="get" class="d-flex gap-2">
    <?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
    <input type="text" name="q" class="form-control form-control-sm" placeholder="Order # / customer" value="<?= e($q) ?>">
    <button class="btn btn-sm btn-outline-dark">Search</button>
  </form>
</div>

<div class="card shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light"><tr><th>Order #</th><th>Customer</th><th>Date</th><th>Status</th><th>Payment</th><th class="text-end">Total</th><th></th></tr></thead>
      <tbody>
      <?php if (!$orders): ?>
        <tr><td colspan="7"><div class="empty-state"><i class="bi bi-bag d-block mb-2"></i>No orders found.</div></td></tr>
      <?php else: foreach ($orders as $o): ?>
        <tr>
          <td class="fw-semibold"><?= e($o['order_number']) ?></td>
          <td><?= e($o['customer']) ?><div class="small text-muted"><?= e($o['email']) ?></div></td>
          <td class="small text-muted"><?= nice_date($o['placed_at']) ?></td>
          <td><?= status_badge($o['status']) ?></td>
          <td><?= $o['pay_status'] ? status_badge($o['pay_status']) : '—' ?></td>
          <td class="text-end fw-semibold"><?= money($o['total_amount']) ?></td>
          <td class="text-end"><a href="<?= url('admin/order_detail.php?id='.(int)$o['order_id']) ?>" class="btn btn-sm btn-outline-dark">Manage</a></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($totalPages > 1): ?>
  <nav class="mt-3"><ul class="pagination justify-content-center">
    <?php for ($p=1;$p<=$totalPages;$p++): $qs=http_build_query(array_filter(['status'=>$status,'q'=>$q,'page'=>$p])); ?>
      <li class="page-item <?= $p===$page?'active':'' ?>"><a class="page-link" href="<?= url('admin/orders.php?'.$qs) ?>"><?= $p ?></a></li>
    <?php endfor; ?>
  </ul></nav>
<?php endif; ?>

<?php include INCLUDES_PATH . '/dash_close.php'; include INCLUDES_PATH . '/footer.php'; ?>
