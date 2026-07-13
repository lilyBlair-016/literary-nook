<?php
/**
 * authentication/reset_password.php — Set a new password from a valid reset link.
 */
require_once __DIR__ . '/../config/config.php';

if (is_logged_in()) redirect('index.php');

/** Look up a still-valid reset row matching uid + raw token, or null. */
function find_valid_reset(int $uid, string $token): ?array
{
    if ($uid <= 0 || $token === '') return null;
    $row = db_one(
        'SELECT * FROM password_resets
         WHERE user_id = ? AND used = 0 AND expires_at > NOW()
         ORDER BY created_at DESC LIMIT 1',
        [$uid]
    );
    if ($row && hash_equals($row['token_hash'], hash('sha256', $token))) {
        return $row;
    }
    return null;
}

/* ---- Handle the new-password submission ------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $uid   = (int) ($_POST['uid'] ?? 0);
    $token = $_POST['token'] ?? '';
    $reset = find_valid_reset($uid, $token);

    if (!$reset) {
        set_flash('This reset link is invalid or has expired.', 'danger');
        redirect('authentication/forgot_password.php');
    }

    $pw  = $_POST['password'] ?? '';
    $pw2 = $_POST['confirm_password'] ?? '';
    $errors = [];
    if (!valid_password($pw)) $errors[] = password_rule_text();
    if ($pw !== $pw2)         $errors[] = 'Passwords do not match.';

    if ($errors) {
        foreach ($errors as $e) set_flash($e, 'danger');
        redirect('authentication/reset_password.php?uid=' . $uid . '&token=' . urlencode($token));
    }

    // Update password, consume the token, and revoke any remember cookies.
    $hash = password_hash($pw, PASSWORD_DEFAULT);
    db_exec('UPDATE users SET password_hash = ? WHERE user_id = ?', [$hash, $uid]);
    db_exec('UPDATE password_resets SET used = 1 WHERE reset_id = ?', [(int) $reset['reset_id']]);
    db_exec('DELETE FROM remember_tokens WHERE user_id = ?', [$uid]);

    set_flash('Your password has been reset. You can now log in.', 'success');
    redirect('authentication/login.php');
}

/* ---- GET: validate the link and show the form ------------------------------ */
$uid   = (int) ($_GET['uid'] ?? 0);
$token = $_GET['token'] ?? '';
$valid = find_valid_reset($uid, $token) !== null;

$page_title = 'Reset Password';
include INCLUDES_PATH . '/header.php';
?>
<div class="container">
  <div class="row justify-content-center">
    <div class="col-lg-5 col-md-7">
      <div class="card shadow-sm border-0">
        <div class="card-body p-4 p-md-5">
          <h1 class="h4 mb-4"><i class="bi bi-shield-lock text-warning me-2"></i>Reset password</h1>

          <?php if (!$valid): ?>
            <div class="alert alert-danger">This reset link is invalid or has expired.</div>
            <a href="<?= url('authentication/forgot_password.php') ?>" class="btn btn-warning w-100">Request a new link</a>
          <?php else: ?>
            <form method="post" action="<?= url('authentication/reset_password.php') ?>" class="needs-validation" novalidate>
              <?= csrf_field() ?>
              <input type="hidden" name="uid" value="<?= (int) $uid ?>">
              <input type="hidden" name="token" value="<?= e($token) ?>">
              <div class="mb-3">
                <label class="form-label">New password</label>
                <input type="password" name="password" id="password" class="form-control"
                       required minlength="8" pattern="<?= e(RE_PASSWORD) ?>" data-password>
                <div class="invalid-feedback"><?= e(password_rule_text()) ?></div>
                <div class="form-text">8+ chars with upper, lower, a number and a symbol.</div>
                <div class="password-meter mt-1"></div>
              </div>
              <div class="mb-3">
                <label class="form-label">Confirm new password</label>
                <input type="password" name="confirm_password" class="form-control"
                       required minlength="8" data-match="#password">
                <div class="invalid-feedback">Passwords do not match.</div>
              </div>
              <button type="submit" class="btn btn-warning w-100">Update password</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<?php include INCLUDES_PATH . '/footer.php'; ?>
