<?php
/**
 * admin/book_form.php — Add or edit a book, its genres, cover image, and the
 * four possible formats (hardcover/paperback/ebook/audiobook) with price + stock.
 */
require_once __DIR__ . '/../config/config.php';
require_admin();

$id     = (int) ($_GET['id'] ?? 0);
$isEdit = $id > 0;

/* Format types and whether each is digital (digital = unlimited stock / NULL). */
$FORMAT_TYPES = ['hardcover' => 0, 'paperback' => 0, 'ebook' => 1, 'audiobook' => 1];

/**
 * True when $id exists in a lookup table. Guards against a tampered <select>
 * posting an id that was never offered (or has since been deleted), which would
 * otherwise fail as a foreign-key error rather than a clean validation message.
 *
 * $table/$pk are literals supplied by the callers below, never user input.
 */
function lookup_exists(string $table, string $pk, int $id): bool
{
    return $id > 0 && (int) db_scalar("SELECT COUNT(*) FROM {$table} WHERE {$pk} = ?", [$id]) === 1;
}

/* ---- Handle submit --------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id     = (int) ($_POST['book_id'] ?? 0);
    $isEdit = $id > 0;

    $title = clean($_POST['title'] ?? '');
    $isbn  = normalize_isbn($_POST['isbn'] ?? '');     // strip hyphens/spaces first
    // Author / publisher / category are picked from the catalogue lookups managed
    // on admin/taxonomy.php, so the form posts ids.
    $authorId    = (int) ($_POST['author_id'] ?? 0);
    $publisherId = (int) ($_POST['publisher_id'] ?? 0) ?: null;
    $categoryId  = (int) ($_POST['category_id'] ?? 0) ?: null;
    $yearRaw = trim((string) ($_POST['publication_year'] ?? ''));
    $year  = $yearRaw === '' ? null : (int) $yearRaw;
    $desc  = clean($_POST['description'] ?? '');
    $active = !empty($_POST['is_active']) ? 1 : 0;
    $genres = array_map('intval', $_POST['genres'] ?? []);

    /* Validate core fields */
    $errors = [];
    if (!valid_title($title)) {
        $errors[] = $title === ''
            ? 'Title is required.'
            : 'Title must be ' . TITLE_MAX . ' characters or fewer.';
    }
    if ($isbn === '')            $errors[] = 'ISBN is required.';
    elseif (!valid_isbn($isbn))  $errors[] = 'ISBN must be 10 or 13 digits, numbers only.';

    // The dropdowns must resolve to rows that actually exist — a hand-crafted POST
    // could otherwise send any number and trip a foreign-key error.
    if (!lookup_exists('authors', 'author_id', $authorId)) {
        $errors[] = 'Please choose an author.';
    }
    if ($publisherId !== null && !lookup_exists('publishers', 'publisher_id', $publisherId)) {
        $errors[] = 'That publisher does not exist.';
    }
    if ($categoryId !== null && !lookup_exists('categories', 'category_id', $categoryId)) {
        $errors[] = 'That category does not exist.';
    }

    // Publication year: numeric, realistic, never in the future.
    if ($yearRaw !== '' && !valid_year($yearRaw)) {
        $errors[] = 'Publication year must be between ' . YEAR_MIN . ' and ' . date('Y') . '.';
    }

    // ISBN uniqueness (excluding self on edit).
    if (valid_isbn($isbn)) {
        $dupe = db_scalar('SELECT COUNT(*) FROM books WHERE isbn = ? AND book_id <> ?', [$isbn, $id]);
        if ($dupe) $errors[] = 'That ISBN is already used by another book.';
    }

    // Duplicate title by the same author is almost always a mistake.
    if ($title !== '' && $authorId > 0) {
        $dupeTitle = db_scalar(
            'SELECT COUNT(*) FROM books WHERE title = ? AND author_id = ? AND book_id <> ?',
            [$title, $authorId, $id]);
        if ($dupeTitle) $errors[] = 'A book with that title by that author already exists.';
    }

    /* Collect + validate formats: need at least one enabled with a price. */
    $formatRows = [];
    foreach ($FORMAT_TYPES as $t => $digital) {
        if (empty($_POST['fmt'][$t]['enabled'])) continue;

        $priceRaw = trim((string) ($_POST['fmt'][$t]['price'] ?? ''));
        if (!valid_price($priceRaw)) {
            $errors[] = ucfirst($t) . ' price must be a number greater than 0 with at most 2 decimal places.';
            continue;
        }
        $stock = null;
        if (!$digital) {
            $stockRaw = trim((string) ($_POST['fmt'][$t]['stock'] ?? '0'));
            if ($stockRaw === '') $stockRaw = '0';
            if (!valid_stock($stockRaw)) {
                $errors[] = ucfirst($t) . ' stock must be a whole number of 0 or more.';
                continue;
            }
            $stock = (int) $stockRaw;
        }
        $formatRows[$t] = ['digital' => $digital, 'price' => (float) $priceRaw, 'stock' => $stock];
    }
    if (!$formatRows) $errors[] = 'Enable at least one format with a valid price.';

    /* Cover upload (optional) */
    $uploadErr = null;
    $newCover  = handle_image_upload('cover', $uploadErr);
    if ($uploadErr) $errors[] = $uploadErr;

    if ($errors) {
        if ($newCover) delete_upload($newCover);   // roll back a stray upload
        foreach ($errors as $e) set_flash($e, 'danger');
        flash_old($_POST);
        redirect('admin/book_form.php' . ($isEdit ? '?id=' . $id : ''));
    }

    /* Persist the book */
    if ($isEdit) {
        $oldCover = db_scalar('SELECT cover_image FROM books WHERE book_id = ?', [$id]);
        $cover = $newCover ?: $oldCover;
        db_exec('UPDATE books SET title=?, isbn=?, author_id=?, publisher_id=?, category_id=?,
                 publication_year=?, description=?, cover_image=?, is_active=? WHERE book_id=?',
                [$title,$isbn,$authorId,$publisherId,$categoryId,$year,$desc,$cover,$active,$id]);
        if ($newCover && $oldCover) delete_upload($oldCover);
    } else {
        $id = db_insert('INSERT INTO books (title,isbn,author_id,publisher_id,category_id,
                 publication_year,description,cover_image,is_active)
                 VALUES (?,?,?,?,?,?,?,?,?)',
                [$title,$isbn,$authorId,$publisherId,$categoryId,$year,$desc,$newCover,$active]);
    }

    /* Sync genres (M:N): clear then re-insert. */
    db_exec('DELETE FROM book_genres WHERE book_id = ?', [$id]);
    foreach ($genres as $gid) if ($gid > 0)
        db_exec('INSERT IGNORE INTO book_genres (book_id, genre_id) VALUES (?, ?)', [$id, $gid]);

    /* Sync formats: upsert enabled ones, delete disabled ones. */
    foreach ($FORMAT_TYPES as $t => $digital) {
        $existing = db_one('SELECT format_id FROM book_formats WHERE book_id = ? AND format_type = ?', [$id, $t]);
        if (isset($formatRows[$t])) {
            $r = $formatRows[$t];
            if ($existing) {
                db_exec('UPDATE book_formats SET is_digital=?, price=?, stock_qty=? WHERE format_id=?',
                        [$r['digital'], $r['price'], $r['stock'], (int) $existing['format_id']]);
            } else {
                db_exec('INSERT INTO book_formats (book_id, format_type, is_digital, price, stock_qty)
                         VALUES (?,?,?,?,?)', [$id, $t, $r['digital'], $r['price'], $r['stock']]);
            }
        } elseif ($existing) {
            db_exec('DELETE FROM book_formats WHERE format_id = ?', [(int) $existing['format_id']]);
        }
    }

    clear_old();
    set_flash('Book "' . e($title) . '" saved.', 'success');
    redirect('admin/books.php');
}

/* ---- GET: load data -------------------------------------------------------- */
$book = $isEdit ? db_one('SELECT * FROM books WHERE book_id = ?', [$id]) : null;
if ($isEdit && !$book) { set_flash('Book not found.', 'danger'); redirect('admin/books.php'); }

// Catalogue lookups that populate the dropdowns (managed on admin/taxonomy.php).
$authors    = db_all('SELECT author_id, name FROM authors ORDER BY name');
$publishers = db_all('SELECT publisher_id, name FROM publishers ORDER BY name');
$categories = db_all('SELECT category_id, name FROM categories ORDER BY name');
$allGenres  = db_all('SELECT genre_id, name FROM genres ORDER BY name');

$bookGenres = $isEdit ? array_map('intval',
    array_column(db_all('SELECT genre_id FROM book_genres WHERE book_id = ?', [$id]), 'genre_id')) : [];

// Existing formats keyed by type for pre-filling the grid.
$existingFmts = [];
if ($isEdit) foreach (db_all('SELECT * FROM book_formats WHERE book_id = ?', [$id]) as $f)
    $existingFmts[$f['format_type']] = $f;

$page_title = $isEdit ? 'Edit Book' : 'Add Book';
$active = 'books';
$dash_title = $isEdit ? 'Edit Book' : 'Add Book';
include INCLUDES_PATH . '/header.php';
include INCLUDES_PATH . '/dash_open.php';
?>
<a href="<?= url('admin/books.php') ?>" class="btn btn-sm btn-outline-secondary mb-3"><i class="bi bi-arrow-left me-1"></i>Back to books</a>

<form method="post" enctype="multipart/form-data" class="needs-validation" data-loading novalidate>
  <?= csrf_field() ?>
  <input type="hidden" name="book_id" value="<?= (int) $id ?>">
  <div class="row g-4">
    <!-- Main fields -->
    <div class="col-lg-8">
      <div class="card shadow-sm mb-4">
        <div class="card-header bg-white fw-semibold">Book Details</div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-12"><label class="form-label">Title *</label>
              <input name="title" class="form-control" required maxlength="<?= TITLE_MAX ?>"
                     value="<?= e($book['title'] ?? old('title')) ?>">
              <div class="invalid-feedback">Title is required (max <?= TITLE_MAX ?> characters).</div></div>
            <div class="col-md-6"><label class="form-label">ISBN *</label>
              <input name="isbn" class="form-control" required inputmode="numeric"
                     pattern="[0-9\-\s]*" data-isbn
                     placeholder="10 or 13 digits"
                     value="<?= e($book['isbn'] ?? old('isbn')) ?>">
              <div class="invalid-feedback">ISBN must be 10 or 13 digits (numbers only).</div></div>
            <div class="col-md-6"><label class="form-label">Publication year</label>
              <input name="publication_year" type="number" class="form-control"
                     min="<?= YEAR_MIN ?>" max="<?= date('Y') ?>" step="1"
                     value="<?= e($book['publication_year'] ?? old('publication_year')) ?>">
              <div class="invalid-feedback">Enter a year between <?= YEAR_MIN ?> and <?= date('Y') ?>.</div></div>
            <!-- Author / Publisher / Category come from the catalogue lookups.
                 Add new ones on the Catalog Data (taxonomy) page. -->
            <?php $selAuthor    = (int) ($book['author_id']    ?? old('author_id'));
                  $selPublisher = (int) ($book['publisher_id'] ?? old('publisher_id'));
                  $selCategory  = (int) ($book['category_id']  ?? old('category_id')); ?>

            <div class="col-md-6"><label class="form-label" for="author_id">Author *</label>
              <select name="author_id" id="author_id" class="form-select" required>
                <option value="">— choose —</option>
                <?php foreach ($authors as $a): ?>
                  <option value="<?= (int) $a['author_id'] ?>" <?= $selAuthor === (int) $a['author_id'] ? 'selected' : '' ?>><?= e($a['name']) ?></option>
                <?php endforeach; ?>
              </select>
              <div class="invalid-feedback">Please choose an author.</div>
              <div class="form-text">
                Not listed? <a href="<?= url('admin/taxonomy.php?type=authors') ?>">Add an author</a>.
              </div>
            </div>
            <div class="col-md-6"><label class="form-label" for="publisher_id">Publisher</label>
              <select name="publisher_id" id="publisher_id" class="form-select">
                <option value="">— none —</option>
                <?php foreach ($publishers as $p): ?>
                  <option value="<?= (int) $p['publisher_id'] ?>" <?= $selPublisher === (int) $p['publisher_id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
                <?php endforeach; ?>
              </select>
              <div class="form-text">
                Not listed? <a href="<?= url('admin/taxonomy.php?type=publishers') ?>">Add a publisher</a>.
              </div>
            </div>
            <div class="col-md-6"><label class="form-label" for="category_id">Category</label>
              <select name="category_id" id="category_id" class="form-select">
                <option value="">— none —</option>
                <?php foreach ($categories as $c): ?>
                  <option value="<?= (int) $c['category_id'] ?>" <?= $selCategory === (int) $c['category_id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
              </select>
              <div class="form-text">
                Not listed? <a href="<?= url('admin/taxonomy.php?type=categories') ?>">Add a category</a>.
              </div>
            </div>
            <div class="col-md-6 d-flex align-items-end">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1"
                       <?= (!$isEdit || $book['is_active']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="is_active">Visible in store</label>
              </div>
            </div>
            <div class="col-12"><label class="form-label">Description</label>
              <textarea name="description" rows="4" class="form-control"><?= e($book['description'] ?? old('description')) ?></textarea></div>
            <div class="col-12"><label class="form-label d-block">Genres</label>
              <?php foreach ($allGenres as $g): ?>
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="checkbox" name="genres[]" value="<?= (int) $g['genre_id'] ?>"
                         id="bg<?= (int) $g['genre_id'] ?>" <?= in_array((int)$g['genre_id'],$bookGenres,true)?'checked':'' ?>>
                  <label class="form-check-label" for="bg<?= (int) $g['genre_id'] ?>"><?= e($g['name']) ?></label>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Formats grid -->
      <div class="card shadow-sm">
        <div class="card-header bg-white fw-semibold">Formats, Pricing &amp; Stock</div>
        <div class="card-body">
          <p class="text-muted small">Tick a format to offer it. E-book &amp; audiobook are digital (unlimited stock).</p>
          <table class="table align-middle">
            <thead class="table-light"><tr><th>Offer</th><th>Format</th><th>Price</th><th>Stock</th></tr></thead>
            <tbody>
            <?php foreach ($FORMAT_TYPES as $t => $digital):
                $ex = $existingFmts[$t] ?? null; ?>
              <tr>
                <td><input class="form-check-input" type="checkbox" name="fmt[<?= $t ?>][enabled]" value="1" <?= $ex?'checked':'' ?>></td>
                <td class="text-capitalize"><?= $t ?> <?php if ($digital): ?><span class="badge text-bg-info">digital</span><?php endif; ?></td>
                <td style="max-width:150px;">
                  <div class="input-group input-group-sm">
                    <span class="input-group-text"><?= e(CURRENCY) ?></span>
                    <!-- step .01 => the browser rejects a third decimal place;
                         min .01 => zero and negatives are rejected. -->
                    <input type="number" step="0.01" min="0.01" max="<?= PRICE_MAX ?>"
                           name="fmt[<?= $t ?>][price]" class="form-control" placeholder="0.00"
                           value="<?= $ex ? e($ex['price']) : '' ?>">
                  </div>
                </td>
                <td style="max-width:120px;">
                  <?php if ($digital): ?><span class="text-muted small">∞ unlimited</span>
                  <?php else: ?>
                    <input type="number" min="0" max="<?= STOCK_MAX ?>" step="1"
                           name="fmt[<?= $t ?>][stock]" class="form-control form-control-sm"
                           value="<?= $ex ? (int) $ex['stock_qty'] : '0' ?>">
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Cover + save -->
    <div class="col-lg-4">
      <div class="card shadow-sm">
        <div class="card-header bg-white fw-semibold">Cover Image</div>
        <div class="card-body text-center">
          <?php if ($isEdit && !empty($book['cover_image']) && file_exists(UPLOAD_PATH.'/'.$book['cover_image'])): ?>
            <img src="<?= e(UPLOAD_URL.$book['cover_image']) ?>" class="book-cover rounded mb-3" style="max-width:160px;">
          <?php else: ?>
            <div class="book-cover-placeholder rounded mb-3 mx-auto" style="max-width:160px;"><i class="bi bi-image"></i></div>
          <?php endif; ?>
          <input type="file" name="cover" accept="image/*" class="form-control form-control-sm">
          <div class="form-text">JPG/PNG/WEBP/GIF, max 2 MB.<?= $isEdit ? ' Leave empty to keep current.' : '' ?></div>
        </div>
      </div>
      <div class="d-grid mt-3">
        <button class="btn btn-warning btn-lg"><i class="bi bi-save me-1"></i>Save Book</button>
      </div>
    </div>
  </div>
</form>

<div id="page-loader"><div class="spinner-border text-warning" role="status"></div></div>

<?php
clear_old();
include INCLUDES_PATH . '/dash_close.php';
include INCLUDES_PATH . '/footer.php';
