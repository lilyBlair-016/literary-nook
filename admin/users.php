<?php
/**
 * admin/users.php — User management: create staff/admins, change roles,
 * activate/deactivate, delete. Guards prevent an admin from locking themselves out.
 */
require_once __DIR__ . '/../config/config.php';
require_admin();
$me = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $first = clean($_POST['first_name'] ?? '');
        $last  = clean($_POST['last_name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $role  = in_array($_POST['role'] ?? '', ['customer','admin'], true) ? $_POST['role'] : 'customer';
        $pw    = $_POST['password'] ?? '';
        $errors = [];
        if (!valid_name($first) || !valid_name($last)) $errors[] = 'First and last name may only contain letters, spaces, hyphens and apostrophes.';
        if (!valid_email($email)) $errors[] = 'Valid email required.';
        if (!valid_password($pw)) $errors[] = password_rule_text();
        if (valid_email($email) && db_scalar('SELECT COUNT(*) FROM users WHERE email=?', [$email])) $errors[] = 'That email is already registered.';
        if ($errors) { foreach ($errors as $e) set_flash($e,'danger'); }
        else {
            db_insert('INSERT INTO users (first_name,last_name,email,password_hash,role) VALUES (?,?,?,?,?)',
                      [$first,$last,$email,password_hash($pw,PASSWORD_DEFAULT),$role]);
            set_flash('User created.', 'success');
        }
        redirect('admin/users.php');
    }

    $target = (int) ($_POST['user_id'] ?? 0);
    if ($action === 'toggle') {
        if ($target === $me) set_flash("You can't deactivate your own account.", 'danger');
        else { db_exec('UPDATE users SET is_active = 1 - is_active WHERE user_id=?', [$target]); set_flash('Status updated.','success'); }
    } elseif ($action === 'role') {
        $role = in_array($_POST['role'] ?? '', ['customer','admin'], true) ? $_POST['role'] : 'customer';
        if ($target === $me) set_flash("You can't change your own role.", 'danger');
        else { db_exec('UPDATE users SET role=? WHERE user_id=?', [$role, $target]); set_flash('Role updated.','success'); }
    } elseif ($action === 'delete') {
        if ($target === $me) set_flash("You can't delete your own account.", 'danger');
        else {
            try { db_exec('DELETE FROM users WHERE user_id=?', [$target]); set_flash('User deleted.','info'); }
            catch (mysqli_sql_exception $e) { set_flash('Cannot delete — user has orders on record. Deactivate instead.','danger'); }
        }
    }
    redirect('admin/users.php');
}

$users = db_all('SELECT * FROM users ORDER BY role, first_name');

$page_title = 'User Management';
$active = 'users';
$dash_title = 'User Management';
include INCLUDES_PATH . '/header.php';
include INCLUDES_PATH . '/dash_open.php';
?>
<div class="d-flex justify-content-end mb-3">
  <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#userModal"><i class="bi bi-person-plus me-1"></i>Add User</button>
</div>

<div class="card shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light"><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
      <?php foreach ($users as $u): $self = (int)$u['user_id'] === $me; ?>
        <tr>
          <td class="fw-semibold"><?= e($u['first_name'].' '.$u['last_name']) ?> <?= $self?'<span class="badge text-bg-info">you</span>':'' ?></td>
          <td class="small text-muted"><?= e($u['email']) ?></td>
          <td>
            <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="role"><input type="hidden" name="user_id" value="<?= (int)$u['user_id'] ?>">
              <select name="role" class="form-select form-select-sm d-inline-block" style="width:auto" onchange="this.form.submit()" <?= $self?'disabled':'' ?>>
                <option value="customer" <?= $u['role']==='customer'?'selected':'' ?>>Customer</option>
                <option value="admin" <?= $u['role']==='admin'?'selected':'' ?>>Admin</option>
              </select>
            </form>
          </td>
          <td><?= $u['is_active'] ? '<span class="badge text-bg-success">Active</span>' : '<span class="badge text-bg-secondary">Inactive</span>' ?></td>
          <td class="text-end text-nowrap">
            <?php if (!$self): ?>
              <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="user_id" value="<?= (int)$u['user_id'] ?>">
                <button class="btn btn-sm btn-outline-<?= $u['is_active']?'secondary':'success' ?>"><?= $u['is_active']?'Deactivate':'Activate' ?></button></form>
              <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="user_id" value="<?= (int)$u['user_id'] ?>">
                <button class="btn btn-sm btn-outline-danger" data-confirm="Delete this user permanently?"><i class="bi bi-trash"></i></button></form>
            <?php else: ?><span class="text-muted small">—</span><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Create-user modal -->
<div class="modal fade" id="userModal" tabindex="-1"><div class="modal-dialog">
  <form method="post" class="modal-content needs-validation" novalidate>
    <?= csrf_field() ?><input type="hidden" name="action" value="create">
    <div class="modal-header"><h5 class="modal-title">Add User</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <div class="row g-3">
        <div class="col-6"><label class="form-label">First name</label>
          <input name="first_name" class="form-control" required maxlength="50" pattern="<?= e(RE_NAME) ?>">
          <div class="invalid-feedback">Letters, spaces, hyphens and apostrophes only.</div></div>
        <div class="col-6"><label class="form-label">Last name</label>
          <input name="last_name" class="form-control" required maxlength="50" pattern="<?= e(RE_NAME) ?>">
          <div class="invalid-feedback">Letters, spaces, hyphens and apostrophes only.</div></div>
        <div class="col-12"><label class="form-label">Email</label>
          <input type="email" name="email" class="form-control" required maxlength="120">
          <div class="invalid-feedback">Enter a valid email address.</div></div>
        <div class="col-6"><label class="form-label">Password</label>
          <input type="password" name="password" class="form-control"
                 required minlength="8" pattern="<?= e(RE_PASSWORD) ?>" data-password>
          <div class="invalid-feedback"><?= e(password_rule_text()) ?></div>
          <div class="password-meter mt-1"></div></div>
        <div class="col-6"><label class="form-label">Role</label>
          <select name="role" class="form-select"><option value="customer">Customer</option><option value="admin">Admin</option></select></div>
      </div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-warning">Create</button></div>
  </form>
</div></div>
<?php include INCLUDES_PATH . '/dash_close.php'; include INCLUDES_PATH . '/footer.php'; ?>
