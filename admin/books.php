<?php
/**
 * admin/books.php — Book list with search + pagination; delete action.
 */
require_once __DIR__ . '/../config/config.php';
require_admin();

/* ---- Delete (soft check → hard delete cascades formats/genres) ------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (($_POST['action'] ?? '') === 'delete') {
        $bid = (int) $_POST['book_id'];
        $cover = db_scalar('SELECT cover_image FROM books WHERE book_id = ?', [$bid]);
        db_exec('DELETE FROM books WHERE book_id = ?', [$bid]);   // FK cascade
        delete_upload($cover);
        set_flash('Book deleted.', 'info');
    }
    redirect('admin/books.php');
}

$q      = clean($_GET['q'] ?? '');
$page   = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($page - 1) * ITEMS_PER_PAGE;

$where = '1=1'; $params = [];
if ($q !== '') {
    $where = '(b.title LIKE ? OR a.name LIKE ? OR b.isbn LIKE ?)';
    $like = "%$q%"; $params = [$like, $like, $like];
}

$total = (int) db_scalar(
    "SELECT COUNT(*) FROM books b JOIN authors a ON a.author_id=b.author_id WHERE $where", $params);
$totalPages = max(1, (int) ceil($total / ITEMS_PER_PAGE));

$books = db_all(
    "SELECT b.book_id, b.title, b.isbn, b.cover_image, b.is_active, a.name AS author,
            COUNT(DISTINCT f.format_id) AS formats,
            COALESCE(SUM(CASE WHEN f.is_digital=0 THEN f.stock_qty ELSE 0 END),0) AS total_stock
     FROM books b
     JOIN authors a ON a.author_id = b.author_id
     LEFT JOIN book_formats f ON f.book_id = b.book_id
     WHERE $where
     GROUP BY b.book_id, b.title, b.isbn, b.cover_image, b.is_active, a.name
     ORDER BY b.title
     LIMIT ? OFFSET ?", array_merge($params, [ITEMS_PER_PAGE, $offset]));

$page_title = 'Manage Books';
$active = 'books';
$dash_title = 'Manage Books';
include INCLUDES_PATH . '/header.php';
include INCLUDES_PATH . '/dash_open.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <form method="get" class="d-flex gap-2">
    <input type="text" name="q" class="form-control form-control-sm" placeholder="Search title/author/ISBN" value="<?= e($q) ?>">
    <button class="btn btn-sm btn-outline-dark">Search</button>
  </form>
  <a href="<?= url('admin/book_form.php') ?>" class="btn btn-warning"><i class="bi bi-plus-lg me-1"></i>Add Book</a>
</div>

<div class="card shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr><th></th><th>Title</th><th>Author</th><th>ISBN</th><th class="text-center">Formats</th>
            <th class="text-center">Stock</th><th></th></tr>
      </thead>
      <tbody>
      <?php if (!$books): ?>
        <tr><td colspan="7"><div class="empty-state"><i class="bi bi-book d-block mb-2"></i>No books found.</div></td></tr>
      <?php else: foreach ($books as $b): ?>
        <tr>
          <td style="width:44px;">
            <?php if (!empty($b['cover_image']) && file_exists(UPLOAD_PATH.'/'.$b['cover_image'])): ?>
              <img src="<?= e(UPLOAD_URL.$b['cover_image']) ?>" style="width:36px;height:52px;object-fit:cover;" class="rounded">
            <?php else: ?><i class="bi bi-book text-muted fs-4"></i><?php endif; ?>
          </td>
          <td class="fw-semibold"><?= e($b['title']) ?>
            <?php if (!$b['is_active']): ?><span class="badge text-bg-secondary">hidden</span><?php endif; ?></td>
          <td><?= e($b['author']) ?></td>
          <td class="small text-muted"><?= e($b['isbn']) ?></td>
          <td class="text-center"><?= (int) $b['formats'] ?></td>
          <td class="text-center"><?= (int) $b['total_stock'] ?></td>
          <td class="text-end text-nowrap">
            <a href="<?= url('admin/book_form.php?id=' . (int) $b['book_id']) ?>" class="btn btn-sm btn-outline-dark"><i class="bi bi-pencil"></i></a>
            <form method="post" class="d-inline">
              <?= csrf_field() ?><input type="hidden" name="action" value="delete">
              <input type="hidden" name="book_id" value="<?= (int) $b['book_id'] ?>">
              <button class="btn btn-sm btn-outline-danger" data-confirm="Delete “<?= e($b['title']) ?>” and all its formats?"><i class="bi bi-trash"></i></button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($totalPages > 1): ?>
  <nav class="mt-3"><ul class="pagination justify-content-center">
    <?php for ($p=1;$p<=$totalPages;$p++): ?>
      <li class="page-item <?= $p===$page?'active':'' ?>">
        <a class="page-link" href="<?= url('admin/books.php?page='.$p.($q!==''?'&q='.urlencode($q):'')) ?>"><?= $p ?></a></li>
    <?php endfor; ?>
  </ul></nav>
<?php endif; ?>

<?php
include INCLUDES_PATH . '/dash_close.php';
include INCLUDES_PATH . '/footer.php';
