<?php
/**
 * orders/place_order.php — Create an order from the cart in one DB transaction:
 * snapshot line items, decrement physical stock, create a pending payment and
 * (for physical orders) a shipment, clear the cart, and notify the customer.
 */
require_once __DIR__ . '/../config/config.php';
require_login();
$uid = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('orders/cart.php');
verify_csrf();

$items = get_cart_items($uid);
if (!$items) { set_flash('Your cart is empty.', 'warning'); redirect('orders/cart.php'); }
if (cart_stock_problems($items)) { set_flash('Some items are out of stock.', 'danger'); redirect('orders/cart.php'); }

$needsShipping = cart_shipping($items) > 0;
$addressId = (int) ($_POST['address_id'] ?? 0) ?: null;
$method    = $_POST['payment_method'] ?? '';

$validMethods = ['credit_card','debit_card','paypal','gcash','cod'];
if (!in_array($method, $validMethods, true)) { set_flash('Choose a payment method.', 'danger'); redirect('orders/checkout.php'); }
if ($needsShipping && !$addressId) { set_flash('Choose a shipping address.', 'danger'); redirect('orders/checkout.php'); }

// Validate the address belongs to this user.
if ($addressId) {
    $owns = db_scalar('SELECT COUNT(*) FROM addresses WHERE address_id = ? AND user_id = ?', [$addressId, $uid]);
    if (!$owns) { set_flash('Invalid shipping address.', 'danger'); redirect('orders/checkout.php'); }
}

$subtotal = cart_subtotal($items);
$shipping = cart_shipping($items);
$promo    = !empty($_SESSION['promo_code']) ? validate_promo($_SESSION['promo_code'], $subtotal)['promo'] : null;
$discount = promo_discount($promo, $subtotal);
$total    = max(0, $subtotal - $discount) + $shipping;

global $conn;
try {
    mysqli_begin_transaction($conn);

    // 1) Order header (temporary number, finalised after we know the id).
    $orderId = db_insert(
        'INSERT INTO orders (order_number, user_id, shipping_address_id, promo_id, status,
                             subtotal, discount_amount, shipping_fee, total_amount)
         VALUES (?,?,?,?,?,?,?,?,?)',
        ['PENDING', $uid, $addressId, $promo['promo_id'] ?? null, 'pending',
         $subtotal, $discount, $shipping, $total]);

    $orderNumber = 'ORD-' . date('Y') . '-' . str_pad((string) $orderId, 4, '0', STR_PAD_LEFT);
    db_exec('UPDATE orders SET order_number = ? WHERE order_id = ?', [$orderNumber, $orderId]);

    // 2) Line items (snapshots) + stock decrement for physical formats.
    foreach ($items as $it) {
        db_exec(
            'INSERT INTO order_items (order_id, format_id, book_title, format_type, unit_price, quantity, line_total)
             VALUES (?,?,?,?,?,?,?)',
            [$orderId, (int) $it['format_id'], $it['title'], $it['format_type'],
             $it['price'], (int) $it['quantity'], $it['line_total']]);

        if ((int) $it['is_digital'] === 0) {
            // Conditional decrement guards against a race with another buyer.
            $affected = db_exec(
                'UPDATE book_formats SET stock_qty = stock_qty - ?
                 WHERE format_id = ? AND stock_qty >= ?',
                [(int) $it['quantity'], (int) $it['format_id'], (int) $it['quantity']]);
            if ($affected === 0) throw new RuntimeException('Insufficient stock for ' . $it['title']);
        }
    }

    // 3) Pending payment record.
    db_insert(
        'INSERT INTO payments (order_id, payment_method, amount, status) VALUES (?,?,?,?)',
        [$orderId, $method, $total, 'pending']);

    // 4) Shipment for physical orders.
    if ($needsShipping) {
        db_insert('INSERT INTO shipments (order_id, status) VALUES (?, ?)', [$orderId, 'preparing']);
    }

    // 5) Clear the cart + promo.
    db_exec('DELETE FROM cart_items WHERE user_id = ?', [$uid]);
    unset($_SESSION['promo_code']);

    mysqli_commit($conn);
} catch (Throwable $e) {
    mysqli_rollback($conn);
    set_flash('We could not place your order (' . e($e->getMessage()) . '). Please try again.', 'danger');
    redirect('orders/cart.php');
}

// 6) Notify + "email" the customer (order confirmation).
notify($uid, 'order', 'Order ' . $orderNumber . ' placed',
       'Thank you! Your order ' . $orderNumber . ' totalling ' . money($total) . ' has been received.');
send_app_mail($_SESSION['user_email'], 'Your ' . SITE_NAME . ' order ' . $orderNumber,
    "<p>Hi " . e($_SESSION['user_name']) . ",</p><p>We've received your order <strong>{$orderNumber}</strong> " .
    "for <strong>" . money($total) . "</strong>. You can track it in your account.</p>");

// Cash on delivery needs no online payment; others go to the payment gateway.
if ($method === 'cod') {
    // COD skips payment.php, so the receipt must be sent from here — otherwise
    // these customers would never receive one.
    send_app_mail($_SESSION['user_email'],
        'Receipt for ' . $orderNumber . ' — ' . SITE_NAME,
        order_receipt_html($orderId));

    set_flash('Order placed! Pay on delivery.', 'success');
    redirect('orders/confirmation.php?order=' . $orderId);
}
redirect('orders/payment.php?order=' . $orderId);
