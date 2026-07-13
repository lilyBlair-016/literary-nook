<?php
/**
 * authentication/register.php — Customer registration.
 * Regex validation · unique email · password_hash · welcome email + notification.
 */
require_once __DIR__ . '/../config/config.php';

// Already logged in? No need to register.
if (is_logged_in()) redirect('index.php');

// ---- Handle submission (before any HTML output, so redirects work) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $result = validate_registration($_POST);
    $errors = $result['errors'];
    $d      = $result['data'];

    if ($errors) {
        flash_old($_POST);
        foreach ($errors as $err) set_flash($err, 'danger');
        redirect('authentication/register.php');
    }

    // Create the account (prepared statement, hashed password).
    $hash   = password_hash($d['pw'], PASSWORD_DEFAULT);
    $userId = db_insert(
        'INSERT INTO users (first_name, last_name, email, password_hash, phone, role)
         VALUES (?, ?, ?, ?, ?, "customer")',
        [$d['first'], $d['last'], $d['email'], $hash, $d['phone']]
    );

    // Save the delivery address they gave us as their default, so the account
    // is ready to check out with from the very first login.
    $a = $d['address'];
    db_insert(
        'INSERT INTO addresses (user_id,label,recipient_name,line1,line2,city,state,postal_code,phone,is_default)
         VALUES (?,?,?,?,?,?,?,?,?,1)',
        [$userId, $a['label'], $a['recipient_name'], $a['line1'], $a['line2'],
         $a['city'], $a['state'], $a['postal_code'], $a['phone']]
    );

    // Registration confirmation (notification + logged "email").
    $name = $d['first'];
    notify($userId, 'registration', 'Welcome to ' . SITE_NAME . '!',
           "Hi {$name}, your account has been created successfully. Happy reading!");
    send_app_mail($d['email'], 'Welcome to ' . SITE_NAME,
        "<p>Hi {$name},</p><p>Thanks for registering at <strong>" . SITE_NAME .
        "</strong>. You can now log in, build a wishlist, and place orders.</p>");

    clear_old();
    // Log the new user straight in for a smooth first experience.
    $user = db_one('SELECT * FROM users WHERE user_id = ?', [$userId]);
    login_user($user, false);
    set_flash('Welcome aboard, ' . e($name) . '! Your account is ready.', 'success');
    redirect('index.php');
}

$page_title = 'Register';
include INCLUDES_PATH . '/header.php';
?>
<div class="container">
  <div class="row justify-content-center">
    <div class="col-lg-7 col-md-9">
      <div class="card shadow-sm border-0">
        <div class="card-body p-4 p-md-5">
          <h1 class="h3 mb-1"><i class="bi bi-person-plus text-warning me-2"></i>Create your account</h1>
          <p class="text-muted mb-4">Join <?= e(SITE_NAME) ?> — it's free.</p>

          <form method="post" action="<?= url('authentication/register.php') ?>" class="needs-validation" novalidate>
            <?= csrf_field() ?>
            <div class="row g-3">
              <!-- Every pattern/min/max below mirrors the PHP rule in
                   functions/validation.php. The browser check is a convenience;
                   the server re-validates everything regardless. -->
              <div class="col-md-6">
                <label class="form-label">First name</label>
                <input type="text" name="first_name" class="form-control" value="<?= old('first_name') ?>"
                       required maxlength="50" pattern="<?= e(RE_NAME) ?>">
                <div class="invalid-feedback">Letters, spaces, hyphens and apostrophes only (2–50).</div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Last name</label>
                <input type="text" name="last_name" class="form-control" value="<?= old('last_name') ?>"
                       required maxlength="50" pattern="<?= e(RE_NAME) ?>">
                <div class="invalid-feedback">Letters, spaces, hyphens and apostrophes only (2–50).</div>
              </div>
              <div class="col-12">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="<?= old('email') ?>"
                       required maxlength="120">
                <div class="invalid-feedback">Enter a valid email address.</div>
              </div>
              <div class="col-12">
                <label class="form-label">Phone</label>
                <input type="tel" name="phone" class="form-control" value="<?= old('phone') ?>"
                       required inputmode="numeric" pattern="<?= e(RE_PHONE) ?>" placeholder="09171234567">
                <div class="invalid-feedback">Digits only, 7–15 of them.</div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Password</label>
                <input type="password" name="password" id="password" class="form-control"
                       required minlength="8" pattern="<?= e(RE_PASSWORD) ?>" data-password>
                <div class="invalid-feedback"><?= e(password_rule_text()) ?></div>
                <div class="form-text">8+ chars with upper, lower, a number and a symbol.</div>
                <div class="password-meter mt-1"></div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Confirm password</label>
                <input type="password" name="confirm_password" class="form-control"
                       required minlength="8" data-match="#password">
                <div class="invalid-feedback">Passwords do not match.</div>
              </div>
            </div>

            <hr class="my-4">
            <h2 class="h5 mb-1"><i class="bi bi-geo-alt text-warning me-2"></i>Delivery address</h2>
            <p class="text-muted small mb-3">
              We'll save this as your default address. You can add more later from your address book.
            </p>

            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label">Label</label>
                <input type="text" name="label" class="form-control"
                       value="<?= old('label') ?: 'Home' ?>" placeholder="Home">
                <div class="form-text">e.g. Home, Work.</div>
              </div>
              <div class="col-md-8">
                <label class="form-label">Address line 1</label>
                <input type="text" name="line1" class="form-control" value="<?= old('line1') ?>"
                       required maxlength="150" placeholder="House/unit no., street">
                <div class="invalid-feedback">Address line 1 is required.</div>
              </div>
              <div class="col-12">
                <label class="form-label">Address line 2 <span class="text-muted small">(optional)</span></label>
                <input type="text" name="line2" class="form-control" value="<?= old('line2') ?>"
                       maxlength="150" placeholder="Barangay, subdivision, landmark">
              </div>
              <div class="col-md-5">
                <label class="form-label">City</label>
                <input type="text" name="city" class="form-control" value="<?= old('city') ?>"
                       required maxlength="60">
                <div class="invalid-feedback">City is required.</div>
              </div>
              <div class="col-md-4">
                <label class="form-label">State/Province</label>
                <input type="text" name="state" class="form-control" value="<?= old('state') ?>"
                       required maxlength="60">
                <div class="invalid-feedback">State/Province is required.</div>
              </div>
              <div class="col-md-3">
                <label class="form-label">Postal code</label>
                <input type="text" name="postal_code" class="form-control" value="<?= old('postal_code') ?>"
                       required maxlength="20">
                <div class="invalid-feedback">Postal code is required.</div>
              </div>
            </div>

            <button type="submit" class="btn btn-warning w-100 mt-4">Create account</button>
          </form>

          <p class="text-center text-muted mt-3 mb-0">
            Already have an account? <a href="<?= url('authentication/login.php') ?>">Log in</a>
          </p>
        </div>
      </div>
    </div>
  </div>
</div>
<?php clear_old(); include INCLUDES_PATH . '/footer.php'; ?>
