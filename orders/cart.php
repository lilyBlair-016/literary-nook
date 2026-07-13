<?php
/**
 * orders/cart.php — Shopping cart: add (from book pages), update qty, remove,
 * apply/clear promo code, and a live order summary with stock validation.
 */
require_once __DIR__ . '/../config/config.php';
require_login();
$uid = (int) $_SESSION['user_id'];

/* ---- POST actions ---------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $fid    = (int) ($_POST['format_id'] ?? 0);
        $qtyRaw = trim((string) ($_POST['quantity'] ?? '1'));
        $fmt    = db_one('SELECT * FROM book_formats WHERE format_id = ?', [$fid]);

        if (!valid_quantity($qtyRaw)) {
            set_flash('Quantity must be a whole number of at least 1.', 'danger');
        } elseif (!$fmt) {
            set_flash('That item is unavailable.', 'danger');
        } else {
            $qty       = (int) $qtyRaw;
            $isDigital = (int) $fmt['is_digital'] === 1;
            $stock     = (int) $fmt['stock_qty'];

            if (!$isDigital && $stock < 1) {
                // Guard: without this, $want clamps to 0 and we would store a
                // zero-quantity cart line for an out-of-stock item.
                set_flash('That format is out of stock.', 'danger');
                redirect('orders/cart.php');
            }

            // Merge with any existing line, then clamp physical qty to stock.
            $current = (int) db_scalar('SELECT quantity FROM cart_items WHERE user_id = ? AND format_id = ?', [$uid, $fid]);
            $want    = $current + $qty;
            if (!$isDigital && $want > $stock) {
                $want = $stock;
                set_flash('Only ' . $stock . ' in stock — quantity capped at ' . $stock . '.', 'warning');
            } else {
                set_flash('Added to cart.', 'success');
            }
            db_exec('INSERT INTO cart_items (user_id, format_id, quantity) VALUES (?,?,?)
                     ON DUPLICATE KEY UPDATE quantity = ?', [$uid, $fid, $want, $want]);
        }
        redirect('orders/cart.php');
    }

    if ($action === 'update') {
        foreach (($_POST['qty'] ?? []) as $cartItemId => $q) {
            $cartItemId = (int) $cartItemId; $q = (int) $q;
            $row = db_one('SELECT ci.*, f.is_digital, f.stock_qty FROM cart_items ci
                           JOIN book_formats f ON f.format_id = ci.format_id
                           WHERE ci.cart_item_id = ? AND ci.user_id = ?', [$cartItemId, $uid]);
            if (!$row) continue;
            if ($q < 1) { db_exec('DELETE FROM cart_items WHERE cart_item_id = ?', [$cartItemId]); continue; }
            if ((int) $row['is_digital'] === 0 && $q > (int) $row['stock_qty']) $q = (int) $row['stock_qty'];
            db_exec('UPDATE cart_items SET quantity = ? WHERE cart_item_id = ?', [$q, $cartItemId]);
        }
        set_flash('Cart updated.', 'success');
        redirect('orders/cart.php');
    }

    if ($action === 'remove') {
        db_exec('DELETE FROM cart_items WHERE cart_item_id = ? AND user_id = ?',
                [(int) $_POST['cart_item_id'], $uid]);
        set_flash('Item removed.', 'info');
        redirect('orders/cart.php');
    }

    if ($action === 'promo') {
        $items = get_cart_items($uid);
        $res = validate_promo($_POST['code'] ?? '', cart_subtotal($items));
        if ($res['error']) {
            unset($_SESSION['promo_code']);
            set_flash($res['error'], 'danger');
        } elseif ($res['promo']) {
            $_SESSION['promo_code'] = $res['promo']['code'];
            set_flash('Promo "' . e($res['promo']['code']) . '" applied.', 'success');
        }
        redirect('orders/cart.php');
    }

    if ($action === 'clear_promo') {
        unset($_SESSION['promo_code']);
        set_flash('Promo removed.', 'info');
        redirect('orders/cart.php');
    }
}

/* ---- Build the cart view --------------------------------------------------- */
$items    = get_cart_items($uid);
$subtotal = cart_subtotal($items);
$shipping = cart_shipping($items);

$promo = null;
if (!empty($_SESSION['promo_code'])) {
    $res = validate_promo($_SESSION['promo_code'], $subtotal);
    $promo = $res['promo'];
    if (!$promo) unset($_SESSION['promo_code']);   // dropped below min, etc.
}
$discount = promo_discount($promo, $subtotal);
$total    = max(0, $subtotal - $discount) + $shipping;
$problems = cart_stock_problems($items);

$page_title = 'My Cart';
include INCLUDES_PATH . '/header.php';
?>
<div class="container">
  <h1 class="h3 mb-4"><i class="bi bi-cart3 me-2"></i>My Cart</h1>

  <?php if (!$items): ?>
    <div class="empty-state">
      <i class="bi bi-cart-x d-block mb-2"></i>Your cart is empty.
      <div><a href="<?= url('books/browse.php') ?>" class="btn btn-sm btn-warning mt-2">Browse books</a></div>
    </div>
  <?php else: ?>
    <div class="row g-4">
      <!-- Items -->
      <div class="col-lg-8">
        <?php foreach ($problems as $p): ?>
          <div class="alert alert-warning py-2"><i class="bi bi-exclamation-triangle me-1"></i><?= e($p) ?></div>
        <?php endforeach; ?>

        <form method="post">
          <?= csrf_field() ?><input type="hidden" name="action" value="update">
          <div class="card shadow-sm">
            <div class="table-responsive">
              <table class="table align-middle mb-0">
                <thead class="table-light"><tr><th colspan="2">Item</th><th>Price</th><th style="width:110px;">Qty</th><th class="text-end">Total</th></tr></thead>
                <tbody>
                <?php foreach ($items as $it): ?>
                  <tr>
                    <td style="width:56px;">
                      <?php if (!empty($it['cover_image']) && file_exists(UPLOAD_PATH.'/'.$it['cover_image'])): ?>
                        <img src="<?= e(UPLOAD_URL.$it['cover_image']) ?>" style="width:40px;height:58px;object-fit:cover;" class="rounded">
                      <?php else: ?><i class="bi bi-book text-muted fs-3"></i><?php endif; ?>
                    </td>
                    <td>
                      <a href="<?= url('books/view.php?id='.(int)$it['book_id']) ?>" class="text-decoration-none fw-semibold text-dark"><?= e($it['title']) ?></a>
                      <div><span class="badge text-bg-light text-capitalize"><?= e($it['format_type']) ?></span></div>
                    </td>
                    <td><?= money($it['price']) ?></td>
                    <td>
                      <input type="number" name="qty[<?= (int)$it['cart_item_id'] ?>]" value="<?= (int)$it['quantity'] ?>"
                             min="0" <?= (int)$it['is_digital']===0 ? 'max="'.(int)$it['stock_qty'].'"' : '' ?>
                             class="form-control form-control-sm">
                    </td>
                    <td class="text-end fw-semibold"><?= money($it['line_total']) ?></td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <div class="card-footer bg-white d-flex justify-content-between">
              <a href="<?= url('books/browse.php') ?>" class="btn btn-outline-dark btn-sm"><i class="bi bi-arrow-left"></i> Continue shopping</a>
              <button class="btn btn-outline-primary btn-sm"><i class="bi bi-arrow-clockwise"></i> Update quantities</button>
            </div>
          </div>
        </form>

        <!-- Per-item remove buttons (separate forms) -->
        <div class="d-flex flex-wrap gap-2 mt-2">
          <?php foreach ($items as $it): ?>
            <form method="post">
              <?= csrf_field() ?><input type="hidden" name="action" value="remove">
              <input type="hidden" name="cart_item_id" value="<?= (int)$it['cart_item_id'] ?>">
              <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> <?= e(excerpt($it['title'],18)) ?></button>
            </form>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Summary -->
      <div class="col-lg-4">
        <div class="card shadow-sm">
          <div class="card-header bg-white fw-semibold">Order Summary</div>
          <div class="card-body">
            <!-- Promo -->
            <?php if ($promo): ?>
              <div class="alert alert-success py-2 d-flex justify-content-between align-items-center">
                <span><i class="bi bi-tag"></i> <strong><?= e($promo['code']) ?></strong> applied</span>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="clear_promo">
                  <button class="btn-close" title="Remove"></button></form>
              </div>
            <?php else: ?>
              <form method="post" class="input-group input-group-sm mb-3">
                <?= csrf_field() ?><input type="hidden" name="action" value="promo">
                <input type="text" name="code" class="form-control" placeholder="Promo code">
                <button class="btn btn-outline-dark">Apply</button>
              </form>
            <?php endif; ?>

            <div class="d-flex justify-content-between"><span>Subtotal</span><span><?= money($subtotal) ?></span></div>
            <?php if ($discount > 0): ?>
              <div class="d-flex justify-content-between text-success"><span>Discount</span><span>−<?= money($discount) ?></span></div>
            <?php endif; ?>
            <div class="d-flex justify-content-between"><span>Shipping</span><span><?= $shipping > 0 ? money($shipping) : 'Free' ?></span></div>
            <hr>
            <div class="d-flex justify-content-between fw-bold fs-5"><span>Total</span><span><?= money($total) ?></span></div>

            <div class="d-grid mt-3">
              <?php if ($problems): ?>
                <button class="btn btn-secondary" disabled>Resolve stock issues to checkout</button>
              <?php else: ?>
                <a href="<?= url('orders/checkout.php') ?>" class="btn btn-warning btn-lg">Proceed to Checkout</a>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>
<?php include INCLUDES_PATH . '/footer.php'; ?>
