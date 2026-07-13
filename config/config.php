<?php
/**
 * config/config.php
 * -----------------------------------------------------------------------------
 * Central application bootstrap. EVERY page starts with:
 *     require_once __DIR__ . '/../config/config.php';   (adjust depth)
 *
 * Responsibilities:
 *   - Define application-wide CONSTANTS (Module 1 & 4: define()).
 *   - Configure error reporting, timezone, and a secure session.
 *   - Load the database connection and the shared function library.
 * -----------------------------------------------------------------------------
 */

/* ---- Environment / error reporting -----------------------------------------
   Errors are shown while developing on XAMPP, but never on the live host: a PHP
   warning there would print file paths and SQL to whoever is looking. IS_LOCAL
   is defined in config/database.php, which is loaded further down, so the check
   is deferred until after that require. */
error_reporting(E_ALL);
date_default_timezone_set('Asia/Manila'); // Module 4: date functions

/* ---- Application constants (Module 1: constants via define) ---------------- */
define('SITE_NAME', 'The Literary Nook');
define('SITE_TAGLINE', 'Your neighbourhood bookstore, online.');

/* Absolute filesystem paths --------------------------------------------------- */
define('ROOT_PATH', dirname(__DIR__));                 // project root folder
define('CONFIG_PATH', ROOT_PATH . '/config');
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('FUNCTIONS_PATH', ROOT_PATH . '/functions');
define('UPLOAD_PATH', ROOT_PATH . '/assets/uploads');  // where covers are saved

/* Public URLs (auto-detected so the app works in ANY folder / host) ----------- */
$docRoot  = str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/'));
$appPath  = str_replace('\\', '/', ROOT_PATH);
$basePath = $docRoot && strpos($appPath, $docRoot) === 0
          ? substr($appPath, strlen($docRoot))
          : '';                                        // '' when using php -S
define('BASE_URL', rtrim($basePath, '/') . '/');       // e.g. /bookstore_management/
define('ASSETS_URL', BASE_URL . 'assets/');
define('UPLOAD_URL', BASE_URL . 'assets/uploads/');

/* Business / behaviour constants --------------------------------------------- */
define('ITEMS_PER_PAGE', 8);            // pagination size
define('SHIPPING_FEE', 150.00);         // flat shipping for physical orders (PHP)
define('CURRENCY', '₱');                // display currency symbol — Philippine peso

/* Everything that prints an amount goes through money() in functions/functions.php,
   which prefixes CURRENCY. The reports charts read the same constant, so the
   symbol only ever needs changing here. Note this changes the SYMBOL only —
   the amounts themselves live in the database (see database/convert_to_php.sql). */

/* ---- Secure session start (Module 5: sessions) ----------------------------- */
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,             // JS cannot read the session cookie (XSS)
        'samesite' => 'Lax',            // basic CSRF hardening
    ]);
    session_start();
}

/* ---- Load core libraries ---------------------------------------------------- */
require_once CONFIG_PATH . '/database.php';   // gives us $conn + db helpers, and IS_LOCAL

/* Show errors on XAMPP; hide (but still log) them on the live server. */
ini_set('display_errors', IS_LOCAL ? '1' : '0');
ini_set('log_errors', '1');
require_once FUNCTIONS_PATH . '/functions.php';
require_once FUNCTIONS_PATH . '/validation.php';
require_once FUNCTIONS_PATH . '/auth.php';
require_once FUNCTIONS_PATH . '/mailer.php';
require_once FUNCTIONS_PATH . '/upload.php';
require_once FUNCTIONS_PATH . '/cart.php';

/* ---- Honour a "Remember Me" cookie (auto-login) ---------------------------- */
auth_check_remember();
