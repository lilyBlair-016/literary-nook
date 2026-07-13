<?php
/**
 * index.php — Storefront landing page.
 * Proves the whole foundation works end-to-end: config bootstrap → prepared
 * DB query (live data, Module 7) → layout includes → escaped, formatted output.
 */
require_once __DIR__ . '/config/config.php';

$page_title = 'Home';

/* Featured books: newest active titles + their lowest format price (Module 6:
   JOIN, GROUP BY, MIN, ORDER BY, LIMIT). */
$featured = db_all(
    "SELECT b.book_id, b.title, b.cover_image, a.name AS author,
            MIN(f.price)      AS from_price,
            COUNT(f.format_id) AS formats
     FROM books b
     JOIN authors a      ON a.author_id = b.author_id
     JOIN book_formats f ON f.book_id   = b.book_id
     WHERE b.is_active = 1
     GROUP BY b.book_id, b.title, b.cover_image, a.name
     ORDER BY b.created_at DESC, b.title
     LIMIT ?",
    [ITEMS_PER_PAGE]
);

/* A couple of live stats for the hero (Module 7: COUNT aggregates). */
$totalBooks  = (int) db_scalar("SELECT COUNT(*) FROM books WHERE is_active = 1");
$totalGenres = (int) db_scalar("SELECT COUNT(*) FROM genres");

include INCLUDES_PATH . '/header.php';
?>

<div class="container">

  <!-- ===================== HERO ===================== -->
  <section class="hero p-4 p-md-5 mb-5">
    <div class="row">
      <div class="col-lg-7">
        <h1 class="display-5">Discover your next great read.</h1>
        <p class="lead text-warning-emphasis"><?= e(SITE_TAGLINE) ?></p>
        <p class="mb-4">
          Browse <strong><?= $totalBooks ?></strong> titles across
          <strong><?= $totalGenres ?></strong> genres — in paperback, hardcover,
          e-book and audiobook.
        </p>
        <a href="<?= url('books/browse.php') ?>" class="btn btn-warning btn-lg">
          <i class="bi bi-search me-1"></i> Browse the Catalog
        </a>
        <?php if (!is_logged_in()): ?>
          <a href="<?= url('authentication/register.php') ?>" class="btn btn-outline-light btn-lg ms-2">
            Join Free
          </a>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <!-- ===================== FEATURED BOOKS ===================== -->
  <section class="mb-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2 class="h4 mb-0"><i class="bi bi-stars text-warning me-1"></i> Featured Books</h2>
      <a href="<?= url('books/browse.php') ?>" class="btn btn-sm btn-outline-dark">View all</a>
    </div>

    <?php if (!$featured): ?>
      <div class="empty-state">
        <i class="bi bi-inbox d-block mb-2"></i>
        <p>No books yet. Import <code>database/bookstore.sql</code> to load the catalog.</p>
      </div>
    <?php else: ?>
      <div class="row row-cols-2 row-cols-md-3 row-cols-lg-4 g-4">
        <?php foreach ($featured as $b): ?>
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
                <p class="text-muted small mb-2">by <?= e($b['author']) ?></p>
                <div class="mt-auto d-flex justify-content-between align-items-center">
                  <span class="price-tag">from <?= money($b['from_price']) ?></span>
                  <span class="badge text-bg-light format-chip"><?= (int) $b['formats'] ?> formats</span>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

</div>

<?php include INCLUDES_PATH . '/footer.php'; ?>
