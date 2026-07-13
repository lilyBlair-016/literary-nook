<?php
/**
 * config/credentials.example.php
 * -----------------------------------------------------------------------------
 * TEMPLATE. Copy this file to `credentials.php` in the same folder and fill in
 * your own values. `credentials.php` is git-ignored and must never be committed.
 *
 *     cp config/credentials.example.php config/credentials.php
 *
 * The correct block is chosen automatically from the hostname, so the code needs
 * no editing when moving between a local server and a live host.
 * -----------------------------------------------------------------------------
 */

/* Local = served from localhost / 127.0.0.1, or run from the command line. */
$host = strtolower($_SERVER['HTTP_HOST'] ?? 'localhost');
define('IS_LOCAL',
       $host === 'localhost'
    || str_starts_with($host, 'localhost:')
    || str_starts_with($host, '127.0.0.1')
    || PHP_SAPI === 'cli');

if (IS_LOCAL) {
    /* ---- Development (XAMPP defaults) ---- */
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'bookstore_db');
} else {
    /* ---- Production -------------------------------------------------------
       Take these from your hosting control panel. On shared hosting DB_HOST is
       usually NOT "localhost" — MySQL runs on a separate server.             */
    define('DB_HOST', 'your-mysql-host');
    define('DB_USER', 'your-db-user');
    define('DB_PASS', 'your-db-password');
    define('DB_NAME', 'your-db-name');
}

/* ---- Outgoing mail (Brevo) --------------------------------------------------
   Mail is sent through Brevo's REST API over HTTPS, because shared hosting
   blocks the outgoing SMTP ports. Create a free account, verify your sender
   address, then paste the API key here.

   Leave the key empty to disable delivery: messages are still archived to
   storage/mail/ and nothing blocks.                                          */
define('MAIL_API_KEY', '');                      // paste your Brevo API key here
define('MAIL_FROM',    'you@example.com');       // must be a verified Brevo sender
