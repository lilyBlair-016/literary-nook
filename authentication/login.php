<?php
/**
 * authentication/login.php — Session login with optional "Remember Me".
 */
require_once __DIR__ . '/../config/config.php';

if (is_logged_in()) redirect('index.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $v = validate_login($_POST);
    if ($v['errors']) {
        flash_old($_POST);
        foreach ($v['errors'] as $e) set_flash($e, 'danger');
        redirect('authentication/login.php');
    }

    $remember = !empty($_POST['remember']);
    $user     = attempt_login($v['data']['email'], $v['data']['pw']);

    if (!$user) {
        flash_old($_POST);
        set_flash('Invalid email or password.', 'danger');   // deliberately vague
        redirect('authentication/login.php');
    }

    clear_old();
    login_user($user, $remember);
    set_flash('Welcome back, ' . e($user['first_name']) . '!', 'success');

    // Send admins to their panel, customers to the storefront.
    redirect($user['role'] === 'admin' ? 'admin/dashboard.php' : 'index.php');
}

$page_title = 'Login';
include INCLUDES_PATH . '/header.php';
?>
<div class="container">
  <div class="row justify-content-center">
    <div class="col-lg-5 col-md-7">
      <div class="card shadow-sm border-0">
        <div class="card-body p-4 p-md-5">
          <h1 class="h3 mb-1"><i class="bi bi-box-arrow-in-right text-warning me-2"></i>Welcome back</h1>
          <p class="text-muted mb-4">Log in to your <?= e(SITE_NAME) ?> account.</p>

          <form method="post" action="<?= url('authentication/login.php') ?>" class="needs-validation" novalidate>
            <?= csrf_field() ?>
            <div class="mb-3">
              <label class="form-label">Email</label>
              <input type="email" name="email" class="form-control" value="<?= old('email') ?>" required autofocus>
              <div class="invalid-feedback">Enter your email.</div>
            </div>
            <div class="mb-3">
              <label class="form-label">Password</label>
              <input type="password" name="password" class="form-control" required>
              <div class="invalid-feedback">Enter your password.</div>
            </div>
            <div class="d-flex justify-content-between align-items-center mb-3">
              <div class="form-check">
                <input type="checkbox" name="remember" value="1" class="form-check-input" id="remember">
                <label class="form-check-label" for="remember">Remember me</label>
              </div>
              <a href="<?= url('authentication/forgot_password.php') ?>" class="small">Forgot password?</a>
            </div>
            <button type="submit" class="btn btn-warning w-100">Log in</button>
          </form>

          <p class="text-center text-muted mt-3 mb-0">
            New here? <a href="<?= url('authentication/register.php') ?>">Create an account</a>
          </p>
        </div>
      </div>
    </div>
  </div>
</div>
<?php clear_old(); include INCLUDES_PATH . '/footer.php'; ?>
