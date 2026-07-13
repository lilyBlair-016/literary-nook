<?php
/**
 * authentication/forgot_password.php — Request a password-reset link.
 * To avoid user enumeration we always show the same confirmation, whether or
 * not the email exists.
 */
require_once __DIR__ . '/../config/config.php';

if (is_logged_in()) redirect('index.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = strtolower(trim($_POST['email'] ?? ''));

    if (!valid_email($email)) {
        set_flash('Please enter a valid email address.', 'danger');
        flash_old($_POST);
        redirect('authentication/forgot_password.php');
    }

    $user = db_one('SELECT * FROM users WHERE email = ? AND is_active = 1', [$email]);
    if ($user) {
        $token = bin2hex(random_bytes(32));                       // raw, emailed once
        $hash  = hash('sha256', $token);
        $exp   = date('Y-m-d H:i:s', time() + 3600);              // valid 1 hour

        // Invalidate previous outstanding resets, then store the new one.
        db_exec('UPDATE password_resets SET used = 1 WHERE user_id = ? AND used = 0',
                [(int) $user['user_id']]);
        db_insert(
            'INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)',
            [(int) $user['user_id'], $hash, $exp]
        );

        $link = url('authentication/reset_password.php?uid=' . (int) $user['user_id'] . '&token=' . $token);
        // Full absolute link for the email body.
        $absolute = (isset($_SERVER['HTTP_HOST']) ? 'http://' . $_SERVER['HTTP_HOST'] : '') . $link;

        send_app_mail($email, 'Reset your ' . SITE_NAME . ' password',
            "<p>Hi {$user['first_name']},</p>" .
            "<p>Click the link below to reset your password (valid for 1 hour):</p>" .
            "<p><a href=\"{$absolute}\">{$absolute}</a></p>" .
            "<p>If you didn't request this, you can ignore this email.</p>");
    }

    set_flash('If that email is registered, a reset link has been sent.', 'success');
    redirect('authentication/forgot_password.php');
}

$page_title = 'Forgot Password';
include INCLUDES_PATH . '/header.php';
?>
<div class="container">
  <div class="row justify-content-center">
    <div class="col-lg-5 col-md-7">
      <div class="card shadow-sm border-0">
        <div class="card-body p-4 p-md-5">
          <h1 class="h4 mb-1"><i class="bi bi-key text-warning me-2"></i>Forgot your password?</h1>
          <p class="text-muted mb-4">Enter your email and we'll send a reset link.</p>

          <form method="post" action="<?= url('authentication/forgot_password.php') ?>" class="needs-validation" novalidate>
            <?= csrf_field() ?>
            <div class="mb-3">
              <label class="form-label">Email</label>
              <input type="email" name="email" class="form-control" value="<?= old('email') ?>" required autofocus>
              <div class="invalid-feedback">Enter a valid email.</div>
            </div>
            <button type="submit" class="btn btn-warning w-100">Send reset link</button>
          </form>
          <div class="d-grid mt-3">
            <a href="<?= url('authentication/login.php') ?>" class="btn btn-outline-secondary btn-sm">
              <i class="bi bi-arrow-left me-1"></i>Back to login
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php clear_old(); include INCLUDES_PATH . '/footer.php'; ?>
