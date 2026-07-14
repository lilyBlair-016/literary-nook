<?php
/**
 * functions/functions.php
 * -----------------------------------------------------------------------------
 * Shared, reusable helper library for the whole application. Keeping these in
 * one place avoids duplicated code (project coding-style requirement) and lets
 * every page call the same escaping, security, and formatting logic.
 *
 * Grouped: Output/Escaping · URLs · Flash messages · CSRF · Auth guards ·
 *          Forms · Formatting · Misc.
 * -----------------------------------------------------------------------------
 */

/* =========================================================================
 *  OUTPUT ESCAPING  (Module 5/Security: XSS protection)
 * ========================================================================= */

/** Escape a value for safe HTML output. Use EVERYWHERE user data is printed. */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** Trim + strip tags from a raw input string (Module 4: trim, strip_tags). */
function clean(?string $value): string
{
    return trim(strip_tags((string) $value));
}

/* =========================================================================
 *  URLs & REDIRECTS
 * ========================================================================= */

/** Build an absolute app URL from a relative path. */
function url(string $path = ''): string
{
    return BASE_URL . ltrim($path, '/');
}

/** Redirect to an app path and stop execution. */
function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

/* =========================================================================
 *  FLASH MESSAGES  (one-time notices shown after a redirect)
 * ========================================================================= */

/** Queue a flash message. $type = success | danger | warning | info (Bootstrap). */
function set_flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'][] = ['message' => $message, 'type' => $type];
}

/** Pull (and clear) all queued flash messages. */
function get_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

/* =========================================================================
 *  CSRF PROTECTION  (Security: CSRF tokens on every POST form)
 * ========================================================================= */

/** Get (creating if needed) the current session CSRF token. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Hidden input to drop inside every <form>. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Verify a submitted token; kills the request if it fails. */
function verify_csrf(): void
{
    $session = $_SESSION['csrf_token'] ?? '';
    $sent    = $_POST['csrf_token'] ?? '';
    // Must have a real token in the session AND it must match. Guarding against
    // the empty === empty case where hash_equals('','') would wrongly pass.
    if ($session === '' || !hash_equals($session, $sent)) {
        http_response_code(419);
        die('Invalid or expired form token (CSRF check failed). Please go back and retry.');
    }
}

/* =========================================================================
 *  AUTHENTICATION GUARDS  (used from Phase 3 onward; safe to define now)
 * ========================================================================= */

/** True if a user is logged in this session. */
function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

/** True if the logged-in user is an admin. */
function is_admin(): bool
{
    return is_logged_in() && ($_SESSION['role'] ?? '') === 'admin';
}

/** Current user's session data (id, name, role...) or null. */
function current_user(): ?array
{
    return is_logged_in() ? [
        'id'     => $_SESSION['user_id'],
        'name'   => $_SESSION['user_name'] ?? '',
        'email'  => $_SESSION['user_email'] ?? '',
        'role'   => $_SESSION['role'] ?? 'customer',
        'avatar' => $_SESSION['avatar'] ?? null,
    ] : null;
}

/**
 * URL of a user's profile picture, or null when they have not set one
 * (callers fall back to the bi-person-circle placeholder icon).
 */
function avatar_url(?string $filename): ?string
{
    if (!$filename || !file_exists(UPLOAD_PATH . '/' . $filename)) {
        return null;
    }
    return UPLOAD_URL . rawurlencode($filename);
}

/**
 * URL of the site logo (assets/images/logo.svg|png|webp|jpg), or null when no
 * such file exists — callers then fall back to the bi-book-half icon.
 * The ?v= stamp is the file's mtime so a replaced logo is never served stale.
 */
function site_logo_url(): ?string
{
    static $cached = false, $url = null;      // resolved once per request
    if ($cached) return $url;
    $cached = true;

    foreach (['svg', 'png', 'webp', 'jpg', 'jpeg'] as $ext) {
        $path = ROOT_PATH . '/assets/images/logo.' . $ext;
        if (file_exists($path)) {
            $url = ASSETS_URL . 'images/logo.' . $ext . '?v=' . filemtime($path);
            break;
        }
    }
    return $url;
}

/** Require any logged-in user; otherwise bounce to login. */
function require_login(): void
{
    if (!is_logged_in()) {
        set_flash('Please log in to continue.', 'warning');
        redirect('authentication/login.php');
    }
}

/** Require an admin; otherwise deny. */
function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        set_flash('You do not have permission to access that page.', 'danger');
        redirect('index.php');
    }
}

/* =========================================================================
 *  FORM HELPERS
 * ========================================================================= */

/** Re-fill a form field after a validation failure (old input). */
function old(string $key, string $default = ''): string
{
    return e($_SESSION['old'][$key] ?? $default);
}

/** Store submitted input so the form can repopulate after a redirect. */
function flash_old(array $data): void
{
    // never echo passwords back
    unset($data['password'], $data['confirm_password'], $data['csrf_token']);
    $_SESSION['old'] = $data;
}

/** Clear stored old input (call after a page renders successfully). */
function clear_old(): void
{
    unset($_SESSION['old']);
}

/* =========================================================================
 *  FORMATTING  (Module 4: number_format, date)
 * ========================================================================= */

/** Format a money amount, e.g. money(1234.5) => "₱1,234.50". */
function money($amount): string
{
    return CURRENCY . number_format((float) $amount, 2);
}

/**
 * Build the itemised receipt e-mailed to the customer after a transaction.
 * Mirrors the printable receipt at orders/receipt.php.
 *
 * Inline styles are deliberate: e-mail clients strip <style> blocks and have no
 * access to the site's stylesheet.
 */
function order_receipt_html(int $orderId): string
{
    $o = db_one(
        'SELECT o.*, CONCAT(u.first_name," ",u.last_name) AS customer,
                a.recipient_name, a.line1, a.line2, a.city, a.state, a.postal_code
         FROM orders o
         JOIN users u          ON u.user_id    = o.user_id
         LEFT JOIN addresses a ON a.address_id = o.shipping_address_id
         WHERE o.order_id = ?', [$orderId]);
    if (!$o) return '';

    $items = db_all('SELECT * FROM order_items WHERE order_id = ?', [$orderId]);
    $pay   = db_one('SELECT * FROM payments WHERE order_id = ? ORDER BY payment_id DESC LIMIT 1', [$orderId]);

    $th = 'padding:8px;border-bottom:2px solid #5E3023;text-align:left;font-size:13px;';
    $td = 'padding:8px;border-bottom:1px solid #E3D3BC;font-size:13px;';

    $h  = '<p>Hi ' . e($o['customer']) . ',</p>';
    $h .= '<p>Thank you for your order. Here is your receipt.</p>';

    $h .= '<table style="width:100%;border-collapse:collapse;margin:16px 0;">'
        . '<tr><td style="' . $td . '"><strong>Order number</strong></td><td style="' . $td . '">' . e($o['order_number']) . '</td></tr>'
        . '<tr><td style="' . $td . '"><strong>Date</strong></td><td style="' . $td . '">' . nice_datetime($o['placed_at']) . '</td></tr>';

    if ($pay) {
        $method = ucwords(str_replace('_', ' ', $pay['payment_method']));
        $status = $pay['status'] === 'completed'
            ? 'Paid'
            : ($pay['payment_method'] === 'cod' ? 'To be paid on delivery' : ucfirst($pay['status']));
        $h .= '<tr><td style="' . $td . '"><strong>Payment</strong></td><td style="' . $td . '">'
            . e($method) . ' &mdash; ' . e($status) . '</td></tr>';
        if (!empty($pay['transaction_ref'])) {
            $h .= '<tr><td style="' . $td . '"><strong>Reference</strong></td><td style="' . $td . '">'
                . e($pay['transaction_ref']) . '</td></tr>';
        }
    }
    $h .= '</table>';

    /* Line items */
    $h .= '<table style="width:100%;border-collapse:collapse;margin:16px 0;">'
        . '<tr><th style="' . $th . '">Item</th><th style="' . $th . '">Format</th>'
        . '<th style="' . $th . 'text-align:center;">Qty</th>'
        . '<th style="' . $th . 'text-align:right;">Total</th></tr>';
    foreach ($items as $it) {
        $h .= '<tr>'
            . '<td style="' . $td . '">' . e($it['book_title']) . '</td>'
            . '<td style="' . $td . 'text-transform:capitalize;">' . e($it['format_type']) . '</td>'
            . '<td style="' . $td . 'text-align:center;">' . (int) $it['quantity'] . '</td>'
            . '<td style="' . $td . 'text-align:right;">' . money($it['line_total']) . '</td>'
            . '</tr>';
    }
    $h .= '</table>';

    /* Totals */
    $row = fn(string $l, string $v, string $extra = '') =>
        '<tr><td style="' . $td . 'text-align:right;' . $extra . '">' . $l . '</td>'
      . '<td style="' . $td . 'text-align:right;width:120px;' . $extra . '">' . $v . '</td></tr>';

    $h .= '<table style="width:100%;border-collapse:collapse;">';
    $h .= $row('Subtotal', money($o['subtotal']));
    if ((float) $o['discount_amount'] > 0) {
        $h .= $row('Discount', '&minus;' . money($o['discount_amount']), 'color:#4F6F52;');
    }
    $h .= $row('Shipping', (float) $o['shipping_fee'] > 0 ? money($o['shipping_fee']) : 'Free');
    $h .= $row('<strong>Total</strong>', '<strong>' . money($o['total_amount']) . '</strong>',
               'border-top:2px solid #5E3023;font-size:15px;');
    $h .= '</table>';

    if (!empty($o['line1'])) {
        $addr = array_filter([$o['recipient_name'], $o['line1'], $o['line2'],
                              trim($o['city'] . ', ' . $o['state'] . ' ' . $o['postal_code'])]);
        $h .= '<p style="margin-top:16px;"><strong>Delivering to</strong><br>'
            . e(implode(', ', $addr)) . '</p>';
    }

    $h .= '<p style="margin-top:20px;color:#895737;font-size:12px;">'
        . 'You can view this receipt any time from your account. Thank you for shopping with '
        . e(SITE_NAME) . '.</p>';

    return $h;
}

/** Human-friendly date, e.g. "Jul 04, 2026". */
function nice_date($datetime): string
{
    if (!$datetime) return '—';
    return date('M d, Y', strtotime($datetime));
}

/** Date + time, e.g. "Jul 04, 2026 3:45 PM". */
function nice_datetime($datetime): string
{
    if (!$datetime) return '—';
    return date('M d, Y g:i A', strtotime($datetime));
}

/**
 * A badge colour for an order/payment status, drawn from the site palette:
 * light = cream, warning = caramel, secondary = coffee, dark = brownie.
 * Failure states keep `danger` so they stay unmistakable.
 */
function status_badge(string $status): string
{
    $map = [
        'pending'   => 'light',     'confirmed' => 'warning',
        'shipped'   => 'secondary', 'delivered' => 'dark',
        'cancelled' => 'danger',    'completed' => 'dark',
        'failed'    => 'danger',    'refunded'  => 'warning',
    ];
    $color = $map[$status] ?? 'light';
    $extra = $color === 'light' ? ' border' : '';
    return '<span class="badge text-bg-' . $color . $extra . ' text-capitalize">' . e($status) . '</span>';
}

/* =========================================================================
 *  MISC
 * ========================================================================= */

/** Number of items in the current user's cart (for the navbar badge). */
function cart_count(): int
{
    if (!is_logged_in()) return 0;
    return (int) db_scalar(
        'SELECT COALESCE(SUM(quantity),0) FROM cart_items WHERE user_id = ?',
        [(int) $_SESSION['user_id']]
    );
}

/** Count of unread notifications for the navbar bell. */
function notif_count(): int
{
    if (!is_logged_in()) return 0;
    return (int) db_scalar(
        'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0',
        [(int) $_SESSION['user_id']]
    );
}

/** A few recent notifications for the navbar dropdown. */
function recent_notifications(int $limit = 5): array
{
    if (!is_logged_in()) return [];
    return db_all(
        'SELECT type, subject, is_read, created_at FROM notifications
         WHERE user_id = ? ORDER BY created_at DESC LIMIT ?',
        [(int) $_SESSION['user_id'], $limit]
    );
}

/** Truncate a long string for previews (Module 4: substr). */
function excerpt(?string $text, int $length = 120): string
{
    $text = (string) $text;
    return strlen($text) > $length ? substr($text, 0, $length) . '…' : $text;
}
