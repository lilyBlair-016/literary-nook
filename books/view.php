<?php
/**
 * books/view.php — Book detail: metadata, formats + stock, add to cart/wishlist.
 */
require_once __DIR__ . '/../config/config.php';
$id = (int) ($_GET['id'] ?? 0);

$book = db_one(
    'SELECT b.*, a.name AS author, a.bio AS author_bio,
            p.name AS publisher, c.name AS category
     FROM books b
     JOIN authors a       ON a.author_id = b.author_id
     LEFT JOIN publishers p ON p.publisher_id = b.publisher_id
     LEFT JOIN categories c ON c.category_id = b.category_id
     WHERE b.book_id = ? AND b.is_active = 1', [$id]);

if (!$book) {
    set_flash('Book not found.', 'danger');
    redirect('books/browse.php');
}

$formats = db_all('SELECT * FROM book_formats WHERE book_id = ? ORDER BY price', [$id]);
$genres  = db_all('SELECT g.name FROM book_genres bg
                   JOIN genres g ON g.genre_id = bg.genre_id
                   WHERE bg.book_id = ? ORDER BY g.name', [$id]);
$inWishlist = is_logged_in() && db_scalar(
    'SELECT COUNT(*) FROM wishlists WHERE user_id = ? AND book_id = ?',
    [(int) $_SESSION['user_id'], $id]);

$page_title = $book['title'];
include INCLUDES_PATH . '/header.php';
?>
<div class="container">
  <a href="<?= url('books/browse.php') ?>" class="btn btn-sm btn-outline-secondary mb-3"><i class="bi bi-arrow-left me-1"></i>Back to catalog</a>

  <div class="row g-4">
    <!-- Cover -->
    <div class="col-md-4 col-lg-3">
      <?php if (!empty($book['cover_image']) && file_exists(UPLOAD_PATH . '/' . $book['cover_image'])): ?>
        <img src="<?= e(UPLOAD_URL . $book['cover_image']) ?>" class="book-cover rounded shadow-sm" alt="<?= e($book['title']) ?>">
      <?php else: ?>
        <div class="book-cover-placeholder rounded shadow-sm"><i class="bi bi-book"></i></div>
      <?php endif; ?>

      <?php if (is_logged_in()): ?>
        <form method="post" action="<?= url('customer/wishlist.php') ?>" class="mt-3">
          <?= csrf_field() ?>
          <input type="hidden" name="book_id" value="<?= (int) $book['book_id'] ?>">
          <input type="hidden" name="return" value="books/view.php?id=<?= (int) $book['book_id'] ?>">
          <input type="hidden" name="action" value="<?= $inWishlist ? 'remove' : 'add' ?>">
          <button class="btn btn-outline-danger w-100">
            <i class="bi bi-heart<?= $inWishlist ? '-fill' : '' ?>"></i>
            <?= $inWishlist ? 'In wishlist — remove' : 'Add to wishlist' ?>
          </button>
        </form>
      <?php endif; ?>
    </div>

    <!-- Details -->
    <div class="col-md-8 col-lg-9">
      <h1 class="h3 mb-1"><?= e($book['title']) ?></h1>
      <p class="text-muted mb-2">by <strong><?= e($book['author']) ?></strong>
        <?= $book['publication_year'] ? ' · ' . (int) $book['publication_year'] : '' ?></p>

      <div class="mb-3">
        <?php foreach ($genres as $g): ?>
          <span class="badge text-bg-secondary"><?= e($g['name']) ?></span>
        <?php endforeach; ?>
        <?php if ($book['category']): ?><span class="badge text-bg-light border"><?= e($book['category']) ?></span><?php endif; ?>
      </div>

      <table class="table table-sm w-auto small text-muted mb-3">
        <tr><td class="pe-3">ISBN</td><td><?= e($book['isbn']) ?></td></tr>
        <?php if ($book['publisher']): ?><tr><td class="pe-3">Publisher</td><td><?= e($book['publisher']) ?></td></tr><?php endif; ?>
      </table>

      <p><?= nl2br(e($book['description'])) ?></p>

      <!-- Formats + add to cart -->
      <div class="card shadow-sm mt-4">
        <div class="card-header bg-white fw-semibold">Available Formats</div>
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead class="table-light"><tr><th>Format</th><th>Price</th><th>Availability</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($formats as $f):
                $digital   = (int) $f['is_digital'] === 1;
                $inStock   = $digital || (int) $f['stock_qty'] > 0; ?>
              <tr>
                <td><span class="badge text-bg-dark text-capitalize"><?= e($f['format_type']) ?></span></td>
                <td class="price-tag"><?= money($f['price']) ?></td>
                <td>
                  <?php if ($digital): ?><span class="text-success"><i class="bi bi-cloud-download"></i> Digital · instant</span>
                  <?php elseif ($inStock): ?><span class="text-success"><?= (int) $f['stock_qty'] ?> in stock</span>
                  <?php else: ?><span class="text-danger">Out of stock</span><?php endif; ?>
                </td>
                <td class="text-end">
                  <?php if ($inStock): ?>
                    <form method="post" action="<?= url('orders/cart.php') ?>" class="d-flex gap-2 justify-content-end">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="add">
                      <input type="hidden" name="format_id" value="<?= (int) $f['format_id'] ?>">
                      <input type="number" name="quantity" value="1" min="1"
                             <?= $digital ? '' : 'max="' . (int) $f['stock_qty'] . '"' ?>
                             class="form-control form-control-sm" style="width:70px;">
                      <button class="btn btn-sm btn-warning"><i class="bi bi-cart-plus"></i> Add</button>
                    </form>
                  <?php else: ?>
                    <button class="btn btn-sm btn-secondary" disabled>Unavailable</button>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <?php if (!is_logged_in()): ?>
        <p class="text-muted small mt-2"><a href="<?= url('authentication/login.php') ?>">Log in</a> to add items to your cart or wishlist.</p>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php include INCLUDES_PATH . '/footer.php'; ?>
