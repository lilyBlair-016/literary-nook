<?php
/**
 * admin/taxonomy.php — One data-driven CRUD screen for the four catalog lookup
 * tables (authors, publishers, genres, categories). The ?type= parameter is
 * validated against a whitelist config, so table/column identifiers are never
 * taken from raw user input (values are still bound via prepared statements).
 */
require_once __DIR__ . '/../config/config.php';
require_admin();

/* Whitelist of manageable taxonomies (identifiers are trusted, from here only). */
$TYPES = [
    'authors' => [
        'table' => 'authors', 'pk' => 'author_id', 'label' => 'Author', 'plural' => 'Authors',
        'fields' => ['name' => 'Name', 'bio' => 'Bio'],
    ],
    'publishers' => [
        'table' => 'publishers', 'pk' => 'publisher_id', 'label' => 'Publisher', 'plural' => 'Publishers',
        'fields' => ['name' => 'Name'],
    ],
    'genres' => [
        'table' => 'genres', 'pk' => 'genre_id', 'label' => 'Genre', 'plural' => 'Genres',
        'fields' => ['name' => 'Name'],
    ],
    'categories' => [
        'table' => 'categories', 'pk' => 'category_id', 'label' => 'Category', 'plural' => 'Categories',
        'fields' => ['name' => 'Name', 'description' => 'Description'],
    ],
];

$type = $_GET['type'] ?? 'authors';
if (!isset($TYPES[$type])) $type = 'authors';
$cfg   = $TYPES[$type];
$table = $cfg['table'];
$pk    = $cfg['pk'];

/* ---- Handle POST ----------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    // Re-validate the type from the POST body too.
    $ptype = $_POST['type'] ?? '';
    if (!isset($TYPES[$ptype])) { set_flash('Unknown catalog type.', 'danger'); redirect('admin/taxonomy.php'); }
    $cfg = $TYPES[$ptype]; $table = $cfg['table']; $pk = $cfg['pk'];
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $cols = array_keys($cfg['fields']);
        $vals = [];
        foreach ($cols as $c) $vals[$c] = clean($_POST[$c] ?? '');

        if ($vals['name'] === '') {
            set_flash($cfg['label'] . ' name is required.', 'danger');
            redirect('admin/taxonomy.php?type=' . $ptype);
        }

        if ($id > 0) {
            $set = implode(', ', array_map(fn($c) => "$c = ?", $cols));
            db_exec("UPDATE $table SET $set WHERE $pk = ?", array_merge(array_values($vals), [$id]));
            set_flash($cfg['label'] . ' updated.', 'success');
        } else {
            $ph = implode(', ', array_fill(0, count($cols), '?'));
            try {
                db_insert("INSERT INTO $table (" . implode(', ', $cols) . ") VALUES ($ph)", array_values($vals));
                set_flash($cfg['label'] . ' added.', 'success');
            } catch (mysqli_sql_exception $e) {
                set_flash('Could not add — a record with that name may already exist.', 'danger');
            }
        }
        redirect('admin/taxonomy.php?type=' . $ptype);
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        try {
            db_exec("DELETE FROM $table WHERE $pk = ?", [$id]);
            set_flash($cfg['label'] . ' deleted.', 'info');
        } catch (mysqli_sql_exception $e) {
            // e.g. authors referenced by books (ON DELETE RESTRICT).
            set_flash('Cannot delete — this ' . strtolower($cfg['label']) . ' is still used by one or more books.', 'danger');
        }
        redirect('admin/taxonomy.php?type=' . $ptype);
    }
}

/* ---- Load list ------------------------------------------------------------- */
$rows = db_all("SELECT * FROM $table ORDER BY name");

$page_title = 'Catalog · ' . $cfg['plural'];
$active = 'catalog';
$dash_title = 'Catalog Data';
include INCLUDES_PATH . '/header.php';
include INCLUDES_PATH . '/dash_open.php';
?>
<!-- Type tabs -->
<ul class="nav nav-pills mb-3">
  <?php foreach ($TYPES as $k => $t): ?>
    <li class="nav-item">
      <a class="nav-link <?= $k === $type ? 'active' : '' ?>" href="<?= url('admin/taxonomy.php?type=' . $k) ?>"><?= e($t['plural']) ?></a>
    </li>
  <?php endforeach; ?>
</ul>

<div class="d-flex justify-content-end mb-3">
  <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#taxModal" onclick="fillTax({})">
    <i class="bi bi-plus-lg me-1"></i>Add <?= e($cfg['label']) ?></button>
</div>

<div class="card shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr><?php foreach ($cfg['fields'] as $lbl): ?><th><?= e($lbl) ?></th><?php endforeach; ?><th></th></tr>
      </thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="9"><div class="empty-state"><i class="bi bi-tags d-block mb-2"></i>No <?= e(strtolower($cfg['plural'])) ?> yet.</div></td></tr>
      <?php else: foreach ($rows as $r): ?>
        <tr>
          <?php foreach (array_keys($cfg['fields']) as $c): ?>
            <td><?= e(excerpt($r[$c] ?? '', 120)) ?: '<span class="text-muted">—</span>' ?></td>
          <?php endforeach; ?>
          <td class="text-end text-nowrap">
            <button class="btn btn-sm btn-outline-dark" data-bs-toggle="modal" data-bs-target="#taxModal"
                    onclick='fillTax(<?= json_encode($r, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><i class="bi bi-pencil"></i></button>
            <form method="post" class="d-inline">
              <?= csrf_field() ?>
              <input type="hidden" name="type" value="<?= e($type) ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int) $r[$pk] ?>">
              <button class="btn btn-sm btn-outline-danger" data-confirm="Delete this <?= e(strtolower($cfg['label'])) ?>?"><i class="bi bi-trash"></i></button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add/Edit modal -->
<div class="modal fade" id="taxModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="post" class="modal-content">
      <?= csrf_field() ?>
      <input type="hidden" name="type" value="<?= e($type) ?>">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" id="tax_id" value="0">
      <div class="modal-header">
        <h5 class="modal-title" id="taxModalTitle">Add <?= e($cfg['label']) ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <?php foreach ($cfg['fields'] as $col => $lbl): ?>
          <div class="mb-3">
            <label class="form-label"><?= e($lbl) ?><?= $col === 'name' ? ' *' : '' ?></label>
            <?php if (in_array($col, ['bio', 'description'], true)): ?>
              <textarea name="<?= e($col) ?>" id="tax_<?= e($col) ?>" rows="3" class="form-control"></textarea>
            <?php else: ?>
              <input name="<?= e($col) ?>" id="tax_<?= e($col) ?>" class="form-control" <?= $col === 'name' ? 'required' : '' ?>>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-warning">Save</button>
      </div>
    </form>
  </div>
</div>

<script>
var TAX_PK = <?= json_encode($pk) ?>;
var TAX_FIELDS = <?= json_encode(array_keys($cfg['fields'])) ?>;
function fillTax(row) {
  document.getElementById('taxModalTitle').textContent = (row[TAX_PK] ? 'Edit' : 'Add') + ' <?= e($cfg['label']) ?>';
  document.getElementById('tax_id').value = row[TAX_PK] || 0;
  TAX_FIELDS.forEach(function (f) {
    var el = document.getElementById('tax_' + f);
    if (el) el.value = row[f] || '';
  });
}
</script>

<?php
include INCLUDES_PATH . '/dash_close.php';
include INCLUDES_PATH . '/footer.php';
