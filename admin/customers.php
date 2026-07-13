<?php
/**
 * admin/customers.php — Manage customers: search, membership, activate/deactivate.
 */
require_once __DIR__ . '/../config/config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $cid = (int) ($_POST['user_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    // Guard: only affect customers, never other admins.
    $cust = db_one("SELECT * FROM users WHERE user_id = ? AND role = 'customer'", [$cid]);
    if ($cust) {
        if ($action === 'toggle') {
            db_exec('UPDATE users SET is_active = 1 - is_active WHERE user_id = ?', [$cid]);
            set_flash('Customer status updated.', 'success');
        } elseif ($action === 'membership' && in_array($_POST['membership'] ?? '', ['regular','silver','gold','vip'], true)) {
            db_exec('UPDATE users SET membership_status = ? WHERE user_id = ?', [$_POST['membership'], $cid]);
            set_flash('Membership updated.', 'success');
        }
    }
    redirect('admin/customers.php' . (!empty($_GET['q']) ? '?q=' . urlencode($_GET['q']) : ''));
}

$q = clean($_GET['q'] ?? '');
$where = "u.role = 'customer'"; $params = [];
if ($q !== '') {
    $where .= ' AND (u.email LIKE ? OR CONCAT(u.first_name," ",u.last_name) LIKE ?)';
    $like = "%$q%"; $params = [$like, $like];
}

$customers = db_all(
    "SELECT u.*, COUNT(DISTINCT o.order_id) AS orders,
            COALESCE(SUM(CASE WHEN o.status IN('confirmed','shipped','delivered') THEN o.total_amount ELSE 0 END),0) AS spent
     FROM users u
     LEFT JOIN orders o ON o.user_id = u.user_id
     WHERE $where
     GROUP BY u.user_id
     ORDER BY spent DESC", $params);

$page_title = 'Customers';
$active = 'customers';
$dash_title = 'Manage Customers';
include INCLUDES_PATH . '/header.php';
include INCLUDES_PATH . '/dash_open.php';
?>
<form method="get" class="d-flex gap-2 mb-3" style="max-width:360px;">
  <input type="text" name="q" class="form-control form-control-sm" placeholder="Search name/email" value="<?= e($q) ?>">
  <button class="btn btn-sm btn-outline-dark">Search</button>
</form>

<div class="card shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light"><tr><th>Customer</th><th>Membership</th><th class="text-center">Orders</th><th class="text-end">Spent</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php if (!$customers): ?>
        <tr><td colspan="6"><div class="empty-state"><i class="bi bi-people d-block mb-2"></i>No customers found.</div></td></tr>
      <?php else: foreach ($customers as $c): ?>
        <tr class="<?= $c['is_active'] ? '' : 'table-secondary' ?>">
          <td>
            <a href="<?= url('admin/customer_detail.php?id='.(int)$c['user_id']) ?>" class="fw-semibold text-decoration-none text-dark"><?= e($c['first_name'].' '.$c['last_name']) ?></a>
            <div class="small text-muted"><?= e($c['email']) ?></div>
          </td>
          <td style="width:130px;">
            <form method="post" class="d-flex gap-1">
              <?= csrf_field() ?><input type="hidden" name="action" value="membership"><input type="hidden" name="user_id" value="<?= (int)$c['user_id'] ?>">
              <select name="membership" class="form-select form-select-sm" onchange="this.form.submit()">
                <?php foreach (['regular','silver','gold','vip'] as $m): ?>
                  <option value="<?= $m ?>" <?= $c['membership_status']===$m?'selected':'' ?>><?= ucfirst($m) ?></option>
                <?php endforeach; ?>
              </select>
            </form>
          </td>
          <td class="text-center"><?= (int)$c['orders'] ?></td>
          <td class="text-end fw-semibold"><?= money($c['spent']) ?></td>
          <td><?= $c['is_active'] ? '<span class="badge text-bg-success">Active</span>' : '<span class="badge text-bg-secondary">Inactive</span>' ?></td>
          <td class="text-end">
            <form method="post" class="d-inline"><?= csrf_field() ?>
              <input type="hidden" name="action" value="toggle"><input type="hidden" name="user_id" value="<?= (int)$c['user_id'] ?>">
              <button class="btn btn-sm btn-outline-<?= $c['is_active']?'danger':'success' ?>" data-confirm="<?= $c['is_active']?'Deactivate':'Activate' ?> this customer?">
                <?= $c['is_active'] ? 'Deactivate' : 'Activate' ?></button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include INCLUDES_PATH . '/dash_close.php'; include INCLUDES_PATH . '/footer.php'; ?>
