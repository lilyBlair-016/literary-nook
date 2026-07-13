<?php
/**
 * functions/auth.php
 * -----------------------------------------------------------------------------
 * Session-based authentication + "Remember Me" cookie handling.
 * Demonstrates Module 5: $_SESSION, $_COOKIE, setcookie, session_destroy.
 * Security: password_hash/verify, session-id regeneration, hashed cookie tokens.
 * -----------------------------------------------------------------------------
 */

define('REMEMBER_COOKIE', 'ln_remember');
define('REMEMBER_DAYS', 30);

/* ---- Establish a logged-in session for a user row -------------------------- */
function login_user(array $user, bool $remember = false): void
{
    // Prevent session fixation: issue a brand-new session id on privilege change.
    session_regenerate_id(true);

    $_SESSION['user_id']    = (int) $user['user_id'];
    $_SESSION['user_name']  = $user['first_name'] . ' ' . $user['last_name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['role']       = $user['role'];
    $_SESSION['avatar']     = $user['avatar'] ?? null;

    if ($remember) {
        set_remember_cookie((int) $user['user_id']);
    }
}

/* ---- Verify email + password, return the user row or null ------------------ */
function attempt_login(string $email, string $password): ?array
{
    $user = db_one(
        'SELECT * FROM users WHERE email = ? AND is_active = 1',
        [strtolower(trim($email))]
    );
    if ($user && password_verify($password, $user['password_hash'])) {
        // Transparently upgrade the hash if PHP's default algo has changed.
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $new = password_hash($password, PASSWORD_DEFAULT);
            db_exec('UPDATE users SET password_hash = ? WHERE user_id = ?',
                    [$new, (int) $user['user_id']]);
        }
        return $user;
    }
    return null;
}

/* ---- "Remember Me" — create a selector:validator cookie + DB record -------- */
function set_remember_cookie(int $userId): void
{
    $selector  = bin2hex(random_bytes(16));   // 32 hex chars, public
    $validator = bin2hex(random_bytes(32));   // secret half
    $hash      = hash('sha256', $validator);
    $expires   = date('Y-m-d H:i:s', time() + REMEMBER_DAYS * 86400);

    db_exec(
        'INSERT INTO remember_tokens (user_id, selector, token_hash, expires_at)
         VALUES (?, ?, ?, ?)',
        [$userId, $selector, $hash, $expires]
    );

    setcookie(REMEMBER_COOKIE, $selector . ':' . $validator, [
        'expires'  => time() + REMEMBER_DAYS * 86400,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/* ---- Auto-login from a valid remember cookie (called on every request) ----- */
function auth_check_remember(): void
{
    if (is_logged_in() || empty($_COOKIE[REMEMBER_COOKIE])) return;

    [$selector, $validator] = array_pad(explode(':', $_COOKIE[REMEMBER_COOKIE], 2), 2, '');
    if ($selector === '' || $validator === '') return;

    $row = db_one(
        'SELECT rt.*, u.* FROM remember_tokens rt
         JOIN users u ON u.user_id = rt.user_id
         WHERE rt.selector = ? AND rt.expires_at > NOW() AND u.is_active = 1',
        [$selector]
    );

    if ($row && hash_equals($row['token_hash'], hash('sha256', $validator))) {
        // Valid cookie → log in. Rotate the token so a stolen cookie is short-lived.
        db_exec('DELETE FROM remember_tokens WHERE selector = ?', [$selector]);
        login_user($row, true);
    } else {
        // Bad/forged cookie → clear it.
        clear_remember_cookie();
    }
}

/* ---- Remove the current remember cookie + its DB record -------------------- */
function clear_remember_cookie(): void
{
    if (!empty($_COOKIE[REMEMBER_COOKIE])) {
        $selector = explode(':', $_COOKIE[REMEMBER_COOKIE], 2)[0] ?? '';
        if ($selector !== '') {
            db_exec('DELETE FROM remember_tokens WHERE selector = ?', [$selector]);
        }
        setcookie(REMEMBER_COOKIE, '', [
            'expires' => time() - 3600, 'path' => '/', 'httponly' => true,
        ]);
        unset($_COOKIE[REMEMBER_COOKIE]);
    }
}

/* ---- Full logout (Module 5: session_destroy) ------------------------------- */
function logout_user(): void
{
    clear_remember_cookie();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
                  $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
