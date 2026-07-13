<?php
/**
 * functions/cart.php
 * -----------------------------------------------------------------------------
 * Shopping-cart helpers shared by cart.php, checkout.php, and order placement.
 * The cart lives in the `cart_items` table (one row per user+format).
 * -----------------------------------------------------------------------------
 */

/** Full cart rows for a user, joined with format + book + live stock. */
function get_cart_items(int $uid): array
{
    return db_all(
        "SELECT ci.cart_item_id, ci.quantity, ci.format_id,
                f.format_type, f.price, f.is_digital, f.stock_qty,
                b.book_id, b.title, b.cover_image,
                (ci.quantity * f.price) AS line_total
         FROM cart_items ci
         JOIN book_formats f ON f.format_id = ci.format_id
         JOIN books b        ON b.book_id   = f.book_id
         WHERE ci.user_id = ?
         ORDER BY ci.added_at DESC", [$uid]);
}

/** Sum of all line totals (Module 3: foreach accumulation). */
function cart_subtotal(array $items): float
{
    $sum = 0.0;
    foreach ($items as $it) $sum += (float) $it['line_total'];
    return $sum;
}

/**
 * Look up + validate a promo code against a subtotal.
 * @return array [ 'promo' => row|null, 'error' => string|null ]
 */
function validate_promo(string $code, float $subtotal): array
{
    $code = strtoupper(trim($code));
    if ($code === '') return ['promo' => null, 'error' => null];

    $promo = db_one(
        "SELECT * FROM promotions
         WHERE code = ? AND is_active = 1
           AND (start_date IS NULL OR start_date <= CURDATE())
           AND (end_date   IS NULL OR end_date   >= CURDATE())", [$code]);

    if (!$promo)                              return ['promo' => null, 'error' => 'Invalid or expired promo code.'];
    if ($subtotal < (float) $promo['min_order'])
        return ['promo' => null, 'error' => 'Order must be at least ' . money($promo['min_order']) . ' for this code.'];

    return ['promo' => $promo, 'error' => null];
}

/** Discount amount for a promo against a subtotal (never exceeds subtotal). */
function promo_discount(?array $promo, float $subtotal): float
{
    if (!$promo) return 0.0;
    $d = $promo['discount_type'] === 'percent'
        ? $subtotal * ((float) $promo['discount_value'] / 100)
        : (float) $promo['discount_value'];
    return round(min($d, $subtotal), 2);
}

/**
 * Check every cart line against current stock.
 * @return array list of human-readable problems (empty = all good).
 */
function cart_stock_problems(array $items): array
{
    $problems = [];
    foreach ($items as $it) {
        if ((int) $it['is_digital'] === 1) continue;             // digital = unlimited
        if ((int) $it['quantity'] > (int) $it['stock_qty']) {
            $problems[] = $it['title'] . ' (' . $it['format_type'] . '): only '
                        . (int) $it['stock_qty'] . ' in stock.';
        }
    }
    return $problems;
}

/** Shipping fee: free for all-digital carts, else the flat SHIPPING_FEE. */
function cart_shipping(array $items): float
{
    foreach ($items as $it) if ((int) $it['is_digital'] === 0) return (float) SHIPPING_FEE;
    return 0.0;
}
