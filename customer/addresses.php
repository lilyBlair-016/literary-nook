<?php
/**
 * customer/addresses.php — Address book CRUD (add / edit / delete / set default).
 */
require_once __DIR__ . '/../config/config.php';
require_login();
$uid = (int) $_SESSION['user_id'];

/* ---- POST actions ---------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id    = (int) ($_POST['address_id'] ?? 0);
        $label = clean($_POST['label'] ?? 'Home');
        $rec   = clean($_POST['recipient_name'] ?? '');
        $l1    = clean($_POST['line1'] ?? '');
        $l2    = clean($_POST['line2'] ?? '');
        $city  = clean($_POST['city'] ?? '');
        $state = clean($_POST['state'] ?? '');
        $zip   = clean($_POST['postal_code'] ?? '');
        $phone = clean($_POST['phone'] ?? '');
        $isDef = !empty($_POST['is_default']) ? 1 : 0;

        $errors = [];
        foreach (['recipient_name'=>$rec,'line1'=>$l1,'city'=>$city,'state'=>$state,'postal_code'=>$zip] as $k=>$v)
            if ($v === '') $errors[] = ucwords(str_replace('_',' ',$k)) . ' is required.';
        if (!valid_phone($phone)) $errors[] = 'Phone number format is invalid.';

        if ($errors) {
            foreach ($errors as $e) set_flash($e, 'danger');
            redirect('customer/addresses.php');
        }

        // If this one is default, unset the others first.
        if ($isDef) db_exec('UPDATE addresses SET is_default = 0 WHERE user_id = ?', [$uid]);

        if ($id > 0) {
            // Ownership check baked into the WHERE clause.
            db_exec('UPDATE addresses SET label=?,recipient_name=?,line1=?,line2=?,city=?,state=?,postal_code=?,phone=?,is_default=?
                     WHERE address_id=? AND user_id=?',
                    [$label,$rec,$l1,$l2,$city,$state,$zip,$phone,$isDef,$id,$uid]);
            set_flash('Address updated.', 'success');
        } else {
            db_insert('INSERT INTO addresses (user_id,label,recipient_name,line1,line2,city,state,postal_code,phone,is_default)
                       VALUES (?,?,?,?,?,?,?,?,?,?)',
                      [$uid,$label,$rec,$l1,$l2,$city,$state,$zip,$phone,$isDef]);
            set_flash('Address added.', 'success');
        }
        redirect('customer/addresses.php');
    }

    if ($action === 'delete') {
        db_exec('DELETE FROM addresses WHERE address_id = ? AND user_id = ?',
                [(int) $_POST['address_id'], $uid]);
        set_flash('Address removed.', 'info');
        redirect('customer/addresses.php');
    }

    if ($action === 'default') {
        db_exec('UPDATE addresses SET is_default = 0 WHERE user_id = ?', [$uid]);
        db_exec('UPDATE addresses SET is_default = 1 WHERE address_id = ? AND user_id = ?',
                [(int) $_POST['address_id'], $uid]);
        set_flash('Default address updated.', 'success');
        redirect('customer/addresses.php');
    }
}

$addresses = db_all('SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, address_id', [$uid]);

$page_title = 'My Addresses';
$active = 'addresses';
$dash_title = 'My Addresses';
include INCLUDES_PATH . '/header.php';
include INCLUDES_PATH . '/dash_open.php';
?>

<div class="d-flex justify-content-end mb-3">
  <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#addrModal"
          onclick="fillAddr({})"><i class="bi bi-plus-lg me-1"></i>Add address</button>
</div>

<?php if (!$addresses): ?>
  <div class="empty-state"><i class="bi bi-geo-alt d-block mb-2"></i>No saved addresses yet.</div>
<?php else: ?>
  <div class="row row-cols-1 row-cols-md-2 g-3">
    <?php foreach ($addresses as $a): ?>
      <div class="col">
        <div class="card shadow-sm h-100">
          <div class="card-body">
            <div class="d-flex justify-content-between">
              <h6 class="mb-1"><?= e($a['label']) ?>
                <?php if ($a['is_default']): ?><span class="badge text-bg-success ms-1">Default</span><?php endif; ?>
              </h6>
            </div>
            <p class="mb-1 fw-semibold"><?= e($a['recipient_name']) ?></p>
            <p class="mb-1 small text-muted">
              <?= e($a['line1']) ?><?= $a['line2'] ? ', ' . e($a['line2']) : '' ?><br>
              <?= e($a['city'] . ', ' . $a['state'] . ' ' . $a['postal_code']) ?><br>
              <?= e($a['country']) ?><?= $a['phone'] ? ' · ' . e($a['phone']) : '' ?>
            </p>
            <div class="mt-2 d-flex gap-2 flex-wrap">
              <button class="btn btn-sm btn-outline-dark" data-bs-toggle="modal" data-bs-target="#addrModal"
                      onclick='fillAddr(<?= json_encode($a, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                <i class="bi bi-pencil"></i> Edit</button>
              <?php if (!$a['is_default']): ?>
                <form method="post" class="d-inline">
                  <?= csrf_field() ?><input type="hidden" name="action" value="default">
                  <input type="hidden" name="address_id" value="<?= (int) $a['address_id'] ?>">
                  <button class="btn btn-sm btn-outline-success"><i class="bi bi-star"></i> Set default</button>
                </form>
              <?php endif; ?>
              <form method="post" class="d-inline">
                <?= csrf_field() ?><input type="hidden" name="action" value="delete">
                <input type="hidden" name="address_id" value="<?= (int) $a['address_id'] ?>">
                <button class="btn btn-sm btn-outline-danger" data-confirm="Delete this address?"><i class="bi bi-trash"></i></button>
              </form>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<!-- Add/Edit modal -->
<div class="modal fade" id="addrModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="post" class="modal-content needs-validation" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="address_id" id="a_id" value="0">
      <div class="modal-header">
        <h5 class="modal-title" id="addrModalTitle">Add address</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label">Label</label>
            <input class="form-control" name="label" id="a_label" value="Home"></div>
          <div class="col-md-6"><label class="form-label">Recipient name</label>
            <input class="form-control" name="recipient_name" id="a_rec" required></div>
          <div class="col-12"><label class="form-label">Address line 1</label>
            <input class="form-control" name="line1" id="a_l1" required></div>
          <div class="col-12"><label class="form-label">Address line 2 <span class="text-muted small">(optional)</span></label>
            <input class="form-control" name="line2" id="a_l2"></div>
          <div class="col-md-5"><label class="form-label">City</label>
            <input class="form-control" name="city" id="a_city" required></div>
          <div class="col-md-4"><label class="form-label">State/Province</label>
            <input class="form-control" name="state" id="a_state" required></div>
          <div class="col-md-3"><label class="form-label">Postal code</label>
            <input class="form-control" name="postal_code" id="a_zip" required></div>
          <div class="col-md-6"><label class="form-label">Phone</label>
            <input class="form-control" name="phone" id="a_phone"></div>
          <div class="col-md-6 d-flex align-items-end">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="is_default" id="a_def" value="1">
              <label class="form-check-label" for="a_def">Set as default</label>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-warning">Save address</button>
      </div>
    </form>
  </div>
</div>

<script>
/* Populate the modal for add (empty) or edit (existing row). */
function fillAddr(a) {
  document.getElementById('addrModalTitle').textContent = a.address_id ? 'Edit address' : 'Add address';
  document.getElementById('a_id').value    = a.address_id || 0;
  document.getElementById('a_label').value = a.label || 'Home';
  document.getElementById('a_rec').value   = a.recipient_name || '';
  document.getElementById('a_l1').value    = a.line1 || '';
  document.getElementById('a_l2').value    = a.line2 || '';
  document.getElementById('a_city').value  = a.city || '';
  document.getElementById('a_state').value = a.state || '';
  document.getElementById('a_zip').value   = a.postal_code || '';
  document.getElementById('a_phone').value = a.phone || '';
  document.getElementById('a_def').checked = a.is_default == 1;
}
</script>

<?php
include INCLUDES_PATH . '/dash_close.php';
include INCLUDES_PATH . '/footer.php';
