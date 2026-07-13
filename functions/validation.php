<?php
/**
 * functions/validation.php
 * -----------------------------------------------------------------------------
 * Server-side form validation (Module 5: regular expressions via preg_match,
 * input sanitisation). Each validator returns an array of human-readable error
 * messages — empty array means "valid".
 * -----------------------------------------------------------------------------
 */

/* ---- Shared rule constants -------------------------------------------------
   These are echoed into the HTML as `pattern="..."` / min / max attributes, so
   the browser enforces exactly the same rule the server does. Client-side checks
   are a convenience only — every one of them is re-run in PHP below.          */

const RE_NAME     = "^[A-Za-z][A-Za-z '\\-]{1,49}$";   // letters, space, ' and -
const RE_PHONE    = '^\\+?[0-9]{7,15}$';               // digits, optional leading +
const RE_ISBN     = '^[0-9]{10}$|^[0-9]{13}$';         // after hyphens are stripped
const RE_PASSWORD = '^(?=.*[a-z])(?=.*[A-Z])(?=.*\\d)(?=.*[^A-Za-z0-9]).{8,}$';

const YEAR_MIN     = 1450;          // ~Gutenberg; anything older is a typo
const PRICE_MAX    = 99999.99;      // fits DECIMAL(7,2)
const STOCK_MAX    = 100000;
const TITLE_MAX    = 200;

/* ---- Reusable field rules (Module 5: PCRE patterns) ------------------------ */

/** Person name: letters, spaces, hyphens, apostrophes. No digits or symbols. */
function valid_name(string $name): bool
{
    return (bool) preg_match('/' . RE_NAME . '/', $name);
}

/** Valid email — regex (Module 5) backed up by PHP's own validator. */
function valid_email(string $email): bool
{
    $pattern = '/^[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}$/';
    return (bool) preg_match($pattern, $email)
        && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/** Strip the punctuation people type into phone fields, leaving digits (and +). */
function normalize_phone(string $phone): string
{
    return preg_replace('/[\s()\-.]/', '', trim($phone));
}

/** Optional phone: digits only (an international + is allowed), 7–15 digits. */
function valid_phone(string $phone): bool
{
    $phone = normalize_phone($phone);
    return $phone === '' || (bool) preg_match('/' . RE_PHONE . '/', $phone);
}

/**
 * Strong password: >= 8 chars with an uppercase letter, a lowercase letter,
 * a digit AND a special character.
 */
function valid_password(string $pw): bool
{
    return (bool) preg_match('/' . RE_PASSWORD . '/', $pw);
}

/** Human-readable statement of the password rule — reused by every form. */
function password_rule_text(): string
{
    return 'Password must be at least 8 characters and include an uppercase letter, '
         . 'a lowercase letter, a number and a special character.';
}

/* ---- Book / catalogue field rules ------------------------------------------ */

/** Drop the hyphens and spaces people paste into ISBNs. */
function normalize_isbn(string $isbn): string
{
    return preg_replace('/[\s\-]/', '', trim($isbn));
}

/** ISBN: digits only, exactly 10 or 13 of them once separators are removed. */
function valid_isbn(string $isbn): bool
{
    return (bool) preg_match('/' . RE_ISBN . '/', normalize_isbn($isbn));
}

/** Publication year: a real 4-digit year, never in the future. */
function valid_year($year): bool
{
    if (!is_numeric($year)) return false;
    $y = (int) $year;
    return $y >= YEAR_MIN && $y <= (int) date('Y');
}

/** Price: numeric, greater than zero, at most two decimal places. */
function valid_price($price): bool
{
    if (!is_numeric($price)) return false;
    if (!preg_match('/^\d+(\.\d{1,2})?$/', trim((string) $price))) return false;   // max 2 dp
    $p = (float) $price;
    return $p > 0 && $p <= PRICE_MAX;
}

/** Stock: a whole number, zero or more. */
function valid_stock($qty): bool
{
    if (!is_numeric($qty)) return false;
    if ((string) (int) $qty !== trim((string) $qty)) return false;   // reject 1.5 / "3abc"
    $q = (int) $qty;
    return $q >= 0 && $q <= STOCK_MAX;
}

/** Book title: non-empty once trimmed, within the column's width. */
function valid_title(string $title): bool
{
    $title = trim($title);
    return $title !== '' && mb_strlen($title) <= TITLE_MAX;
}

/** Quantity ordered: a whole number of at least 1. */
function valid_quantity($qty): bool
{
    return valid_stock($qty) && (int) $qty >= 1;
}

/* ---- Composite validators -------------------------------------------------- */

/**
 * Validate a registration submission, including the first delivery address that
 * is saved as the new customer's default (see authentication/register.php).
 * @return array [ 'errors' => string[], 'data' => cleaned fields ]
 */
function validate_registration(array $in): array
{
    $errors = [];

    $first = clean($in['first_name'] ?? '');
    $last  = clean($in['last_name']  ?? '');
    $email = strtolower(trim($in['email'] ?? ''));
    $phone = clean($in['phone'] ?? '');
    $pw    = $in['password'] ?? '';
    $pw2   = $in['confirm_password'] ?? '';

    $phone = normalize_phone($phone);          // store digits, not the punctuation

    if (!valid_name($first)) $errors[] = 'First name may only contain letters, spaces, hyphens and apostrophes (2–50 characters).';
    if (!valid_name($last))  $errors[] = 'Last name may only contain letters, spaces, hyphens and apostrophes (2–50 characters).';
    if (!valid_email($email)) $errors[] = 'Please enter a valid email address.';
    if ($phone === '')        $errors[] = 'Phone number is required.';
    elseif (!valid_phone($phone)) $errors[] = 'Phone number must be 7–15 digits.';
    if (!valid_password($pw)) $errors[] = password_rule_text();
    if ($pw !== $pw2)         $errors[] = 'Password and confirmation do not match.';

    // Uniqueness check (prepared statement) only if the email itself is OK.
    if (valid_email($email)) {
        $exists = db_scalar('SELECT COUNT(*) FROM users WHERE email = ?', [$email]);
        if ($exists) $errors[] = 'That email is already registered. Try logging in.';
    }

    /* ---- Delivery address ---- */
    $label = clean($in['label'] ?? '') ?: 'Home';
    $line1 = clean($in['line1'] ?? '');
    $line2 = clean($in['line2'] ?? '');
    $city  = clean($in['city']  ?? '');
    $state = clean($in['state'] ?? '');
    $zip   = clean($in['postal_code'] ?? '');

    if ($line1 === '') $errors[] = 'Address line 1 is required.';
    if ($city  === '') $errors[] = 'City is required.';
    if ($state === '') $errors[] = 'State/Province is required.';
    if ($zip   === '') $errors[] = 'Postal code is required.';

    // The address is filled in by the person creating the account, so the
    // recipient is that person; they can add other recipients later.
    $address = [
        'label'          => $label,
        'recipient_name' => trim($first . ' ' . $last),
        'line1'          => $line1,
        'line2'          => $line2,
        'city'           => $city,
        'state'          => $state,
        'postal_code'    => $zip,
        'phone'          => $phone,
    ];

    return [
        'errors' => $errors,
        'data'   => compact('first', 'last', 'email', 'phone', 'pw', 'address'),
    ];
}

/** Validate a login submission (shape only; credentials checked separately). */
function validate_login(array $in): array
{
    $errors = [];
    $email  = strtolower(trim($in['email'] ?? ''));
    $pw     = $in['password'] ?? '';

    if ($email === '')       $errors[] = 'Email is required.';
    if ($pw === '')          $errors[] = 'Password is required.';

    return ['errors' => $errors, 'data' => ['email' => $email, 'pw' => $pw]];
}
