<?php
/**
 * books/browse.php — Public catalog: search + filter + sort + pagination.
 * Search:  q (title/author/ISBN), genre, category, year.  Sort: newest/price/title.
 */
require_once __DIR__ . '/../config/config.php';

/* ---- Read + sanitise filter inputs (Module 5: $_GET) ----------------------- */
$q        = clean($_GET['q'] ?? '');
$genreId  = (int) ($_GET['genre'] ?? 0);
$catId    = (int) ($_GET['category'] ?? 0);
$year     = (int) ($_GET['year'] ?? 0);
$sort     = $_GET['sort'] ?? 'newest';
$page     = max(1, (int) ($_GET['page'] ?? 1));
$offset   = ($page - 1) * ITEMS_PER_PAGE;

/* ---- Build the WHERE clause dynamically (all values bound) ----------------- */
$joins  = '';
$where  = ['b.is_active = 1'];
$params = [];

if ($q !== '') {
    $where[] = '(b.title LIKE ? OR a.name LIKE ? OR b.isbn LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}
if ($genreId > 0) {
    $joins  .= ' JOIN book_genres bg ON bg.book_id = b.book_id ';
    $where[] = 'bg.genre_id = ?';
    $params[] = $genreId;
}
if ($catId > 0)  { $where[] = 'b.category_id = ?';      $params[] = $catId; }
if ($year > 0)   { $where[] = 'b.publication_year = ?'; $params[] = $year; }

$whereSql = implode(' AND ', $where);

/* ---- Sorting (whitelisted — never interpolate raw user input) -------------- */
$sortMap = [
    'newest'     => 'b.created_at DESC',
    'price_low'  => 'from_price ASC',
    'price_high' => 'from_price DESC',
    'title'      => 'b.title ASC',
];
$orderSql = $sortMap[$sort] ?? $sortMap['newest'];

/* ---- Total count for pagination (COUNT DISTINCT, Module 7) ----------------- */
$total = (int) db_scalar(
    "SELECT COUNT(DISTINCT b.book_id)
     FROM books b JOIN authors a ON a.author_id = b.author_id $joins
     WHERE $whereSql", $params);
$totalPages = max(1, (int) ceil($total / ITEMS_PER_PAGE));

/* ---- Fetch the page of results --------------------------------------------- */
$listParams = array_merge($params, [ITEMS_PER_PAGE, $offset]);
$books = db_all(
    "SELECT b.book_id, b.title, b.cover_image, b.publication_year, a.name AS author,
            MIN(f.price) AS from_price, COUNT(f.format_id) AS formats
     FROM books b
     JOIN authors a ON a.author_id = b.author_id
     LEFT JOIN book_formats f ON f.book_id = b.book_id
     $joins
     WHERE $whereSql
     GROUP BY b.book_id, b.title, b.cover_image, b.publication_year, a.name
     ORDER BY $orderSql
     LIMIT ? OFFSET ?", $listParams);

/* ---- Filter dropdown data -------------------------------------------------- */
$genres     = db_all('SELECT genre_id, name FROM genres ORDER BY name');
$categories = db_all('SELECT category_id, name FROM categories ORDER BY name');
$years      = array_column(db_all('SELECT DISTINCT publication_year FROM books
                                   WHERE publication_year IS NOT NULL ORDER BY publication_year DESC'),
                           'publication_year');

/** Build a browse URL preserving current filters but overriding some keys. */
function browse_url(array $override = []): string
{
    $params = array_merge($_GET, $override);
    $params = array_filter($params, fn($v) => $v !== '' && $v !== 0 && $v !== '0');
    return url('books/browse.php') . ($params ? '?' . http_build_query($params) : '');
}

$page_title = 'Browse Books';
include INCLUDES_PATH . '/header.php';
?>
<div class="container">
  <div class="row g-4">

    <!-- ===== Filter sidebar ===== -->
    <aside class="col-lg-3">
      <form method="get" action="<?= url('books/browse.php') ?>" class="card shadow-sm">
        <div class="card-body">
          <h6 class="fw-bold mb-3"><i class="bi bi-funnel me-1"></i>Filters</h6>

          <label class="form-label small">Keyword</label>
          <input type="text" name="q" class="form-control form-control-sm mb-3"
                 value="<?= e($q) ?>" placeholder="Title, author, ISBN">

          <label class="form-label small">Genre</label>
          <select name="genre" class="form-select form-select-sm mb-3">
            <option value="0">All genres</option>
            <?php foreach ($genres as $g): ?>
              <option value="<?= (int) $g['genre_id'] ?>" <?= $genreId === (int) $g['genre_id'] ? 'selected' : '' ?>><?= e($g['name']) ?></option>
            <?php endforeach; ?>
          </select>

          <label class="form-label small">Category</label>
          <select name="category" class="form-select form-select-sm mb-3">
            <option value="0">All categories</option>
            <?php foreach ($categories as $c): ?>
              <option value="<?= (int) $c['category_id'] ?>" <?= $catId === (int) $c['category_id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>

          <label class="form-label small">Publication year</label>
          <select name="year" class="form-select form-select-sm mb-3">
            <option value="0">Any year</option>
            <?php foreach ($years as $y): ?>
              <option value="<?= (int) $y ?>" <?= $year === (int) $y ? 'selected' : '' ?>><?= (int) $y ?></option>
            <?php endforeach; ?>
          </select>

          <button class="btn btn-warning btn-sm w-100">Apply filters</button>
          <a href="<?= url('books/browse.php') ?>" class="btn btn-outline-secondary btn-sm w-100 mt-2">Reset</a>
        </div>
      </form>
    </aside>

    <!-- ===== Results ===== -->
    <section class="col-lg-9">
      <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
          <h1 class="h4 mb-0">Browse Books</h1>
          <small class="text-muted"><?= $total ?> result<?= $total === 1 ? '' : 's' ?><?= $q !== '' ? ' for “' . e($q) . '”' : '' ?></small>
        </div>
        <form method="get" class="d-flex align-items-center gap-2">
          <?php foreach (['q'=>$q,'genre'=>$genreId,'category'=>$catId,'year'=>$year] as $k=>$v)
                  if ($v) echo '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">'; ?>
          <label class="small text-muted mb-0">Sort</label>
          <select name="sort" class="form-select form-select-sm" onchange="this.form.submit()" style="width:auto;">
            <?php foreach (['newest'=>'Newest','price_low'=>'Price ↑','price_high'=>'Price ↓','title'=>'Title A–Z'] as $k=>$lbl): ?>
              <option value="<?= $k ?>" <?= $sort === $k ? 'selected' : '' ?>><?= $lbl ?></option>
            <?php endforeach; ?>
          </select>
        </form>
      </div>

      <?php if (!$books): ?>
        <div class="empty-state"><i class="bi bi-search d-block mb-2"></i>No books match your search.</div>
      <?php else: ?>
        <div class="row row-cols-2 row-cols-md-3 g-4">
          <?php foreach ($books as $b): ?>
            <div class="col">
              <div class="card book-card h-100 shadow-sm">
                <a href="<?= url('books/view.php?id=' . (int) $b['book_id']) ?>">
                  <?php if (!empty($b['cover_image']) && file_exists(UPLOAD_PATH . '/' . $b['cover_image'])): ?>
                    <img src="<?= e(UPLOAD_URL . $b['cover_image']) ?>" class="book-cover card-img-top" alt="<?= e($b['title']) ?>">
                  <?php else: ?>
                    <div class="book-cover-placeholder card-img-top"><i class="bi bi-book"></i></div>
                  <?php endif; ?>
                </a>
                <div class="card-body d-flex flex-column">
                  <h6 class="card-title mb-1"><?= e($b['title']) ?></h6>
                  <p class="text-muted small mb-2">by <?= e($b['author']) ?> · <?= (int) $b['publication_year'] ?></p>
                  <div class="mt-auto d-flex justify-content-between align-items-center">
                    <span class="price-tag"><?= $b['from_price'] ? 'from ' . money($b['from_price']) : 'N/A' ?></span>
                    <a href="<?= url('books/view.php?id=' . (int) $b['book_id']) ?>" class="btn btn-sm btn-outline-dark">Details</a>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
          <nav class="mt-4">
            <ul class="pagination justify-content-center">
              <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= e(browse_url(['page' => $page - 1])) ?>">Previous</a>
              </li>
              <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                  <a class="page-link" href="<?= e(browse_url(['page' => $p])) ?>"><?= $p ?></a>
                </li>
              <?php endfor; ?>
              <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= e(browse_url(['page' => $page + 1])) ?>">Next</a>
              </li>
            </ul>
          </nav>
        <?php endif; ?>
      <?php endif; ?>
    </section>
  </div>
</div>
<?php include INCLUDES_PATH . '/footer.php'; ?>
