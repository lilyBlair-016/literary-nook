<?php
/**
 * config/database.php
 * -----------------------------------------------------------------------------
 * MySQL connection using mysqli. Provides a single shared $conn plus thin
 * PREPARED-STATEMENT helpers used by every query in the system, so SQL is never
 * built by string concatenation and the app is safe from SQL injection.
 *
 * Credentials are not stored here: see config/credentials.php.
 * -----------------------------------------------------------------------------
 */

require_once __DIR__ . '/credentials.php';   // DB_HOST/USER/PASS/NAME + IS_LOCAL

/* ---- Connect ---------------------------------------------------------------- */
// Make mysqli throw exceptions instead of silent warnings, so failures are caught.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    mysqli_set_charset($conn, 'utf8mb4');
} catch (mysqli_sql_exception $e) {
    // Never expose the host, user or the driver's message to a visitor.
    error_log('Database connection failed: ' . $e->getMessage());
    http_response_code(503);
    echo '<div style="font-family:system-ui,sans-serif;max-width:640px;margin:3rem auto;'
       . 'padding:1.5rem;border:1px solid #e3d3bc;border-radius:.75rem">'
       . '<h3 style="color:#a8412f;margin-top:0">Service temporarily unavailable</h3>'
       . '<p>The site cannot reach its database right now. Please try again shortly.</p>'
       . '</div>';
    exit;
}

/* =============================================================================
 *  PREPARED-STATEMENT HELPERS  (reusable across the whole app)
 * =============================================================================
 * $sql    : SQL with ? placeholders
 * $params : array of values to bind
 * $types  : optional bind-type string ("i","s","d"...). Auto-detected if omitted.
 */

/** Detect mysqli bind types ("i" int, "d" float, "s" string) from a values array. */
function db_types(array $params): string
{
    $types = '';
    foreach ($params as $p) {
        if (is_int($p))        $types .= 'i';
        elseif (is_float($p))  $types .= 'd';
        else                   $types .= 's';
    }
    return $types;
}

/** Run any prepared statement; returns the mysqli_stmt (or throws). */
function db_run(string $sql, array $params = [], string $types = '')
{
    global $conn;
    $stmt = mysqli_prepare($conn, $sql);
    if ($params) {
        if ($types === '') $types = db_types($params);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    return $stmt;
}

/** SELECT many rows -> array of associative arrays. */
function db_all(string $sql, array $params = [], string $types = ''): array
{
    $stmt = db_run($sql, $params, $types);
    $res  = mysqli_stmt_get_result($stmt);
    $rows = mysqli_fetch_all($res, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $rows;
}

/** SELECT a single row -> associative array or null. */
function db_one(string $sql, array $params = [], string $types = ''): ?array
{
    $stmt = db_run($sql, $params, $types);
    $res  = mysqli_stmt_get_result($stmt);
    $row  = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

/** SELECT a single scalar value (first column of first row). */
function db_scalar(string $sql, array $params = [], string $types = '')
{
    $row = db_one($sql, $params, $types);
    return $row ? array_values($row)[0] : null;
}

/** INSERT/UPDATE/DELETE -> affected row count (Module 7: affected_rows). */
function db_exec(string $sql, array $params = [], string $types = ''): int
{
    $stmt = db_run($sql, $params, $types);
    $n    = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    return $n;
}

/** INSERT and return the new AUTO_INCREMENT id. */
function db_insert(string $sql, array $params = [], string $types = ''): int
{
    global $conn;
    $stmt = db_run($sql, $params, $types);
    $id   = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    return (int) $id;
}
