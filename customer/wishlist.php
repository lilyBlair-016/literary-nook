<?php
/**
 * customer/wishlist.php — View wishlist; add/remove handler reused by book pages.
 * Book pages POST { action:add|remove, book_id, return } here.
 */
require_once __DIR__ . '/../config/config.php';
require_login();
$uid = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $bookId = (int) ($_POST['book_id'] ?? 0);
    $return = $_POST['return'] ?? 'customer/wishlist.php';

    if ($bookId > 0 && $action === 'add') {
        // INSERT IGNORE via the UNIQUE (user_id, book_id) constraint.
        db_exec('INSERT IGNORE INTO wishlists (user_id, book_id) VALUES (?, ?)', [$uid, $bookId]);
        set_flash('Added to your wishlist.', 'success');
    } elseif ($bookId > 0 && $action === 'remove') {
        db_exec('DELETE FROM wishlists WHERE user_id = ? AND book_id = ?', [$uid, $bookId]);
        set_flash('Removed from your wishlist.', 'info');
    }
    // Only allow internal redirect targets.
    redirect(preg_match('#^[a-z0-9_/.\-?=&]+$#i', $return) ? $return : 'customer/wishlist.php');
}

/* Wishlist with lowest price per book (Module 6: JOIN, GROUP BY, MIN). */
$items = db_all(
    "SELECT b.book_id, b.title, b.cover_image, a.name AS author,
            MIN(f.price) AS from_price, w.added_at
     FROM wishlists w
     JOIN books b        ON b.book_id = w.book_id
     JOIN authors a      ON a.author_id = b.author_id
     LEFT JOIN book_formats f ON f.book_id = b.book_id
     WHERE w.user_id = ?
     GROUP BY b.book_id, b.title, b.cover_image, a.name, w.added_at
     ORDER BY w.added_at DESC", [$uid]);

$page_title = 'My Wishlist';
$active = 'wishlist';
$dash_title = 'My Wishlist';
include INCLUDES_PATH . '/header.php';
include INCLUDES_PATH . '/dash_open.php';
?>

<?php if (!$items): ?>
  <div class="empty-state">
    <i class="bi bi-heart d-block mb-2"></i>Your wishlist is empty.
    <div><a href="<?= url('books/browse.php') ?>" class="btn btn-sm btn-warning mt-2">Browse books</a></div>
  </div>
<?php else: ?>
  <div class="row row-cols-2 row-cols-md-3 row-cols-lg-4 g-4">
    <?php foreach ($items as $b): ?>
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
            <span class="price-tag mb-2"><?= $b['from_price'] ? 'from ' . money($b['from_price']) : 'N/A' ?></span>
            <div class="mt-auto d-flex gap-2">
              <a href="<?= url('books/view.php?id=' . (int) $b['book_id']) ?>" class="btn btn-sm btn-warning flex-grow-1">View</a>
              <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="remove">
                <input type="hidden" name="book_id" value="<?= (int) $b['book_id'] ?>">
                <button class="btn btn-sm btn-outline-danger" data-confirm="Remove from wishlist?"><i class="bi bi-trash"></i></button>
              </form>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php
include INCLUDES_PATH . '/dash_close.php';
include INCLUDES_PATH . '/footer.php';
