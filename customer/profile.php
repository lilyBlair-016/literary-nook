<?php
/**
 * customer/profile.php — View/update profile, preferred genres, change password.
 */
require_once __DIR__ . '/../config/config.php';
require_login();
$uid = (int) $_SESSION['user_id'];

/* ---- Handle POST actions --------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'profile') {
        $first = clean($_POST['first_name'] ?? '');
        $last  = clean($_POST['last_name'] ?? '');
        $phone = normalize_phone($_POST['phone'] ?? '');   // keep digits, drop punctuation
        $genres = array_map('intval', $_POST['genres'] ?? []);

        $errors = [];
        if (!valid_name($first)) $errors[] = 'First name may only contain letters, spaces, hyphens and apostrophes (2–50 characters).';
        if (!valid_name($last))  $errors[] = 'Last name may only contain letters, spaces, hyphens and apostrophes (2–50 characters).';
        if (!valid_phone($phone)) $errors[] = 'Phone number must be 7–15 digits.';

        if ($errors) {
            foreach ($errors as $e) set_flash($e, 'danger');
        } else {
            db_exec('UPDATE users SET first_name = ?, last_name = ?, phone = ? WHERE user_id = ?',
                    [$first, $last, $phone, $uid]);

            // Reset preferred genres (M:N) — clear then re-insert selected.
            db_exec('DELETE FROM user_preferred_genres WHERE user_id = ?', [$uid]);
            foreach ($genres as $gid) {
                if ($gid > 0) db_exec(
                    'INSERT INTO user_preferred_genres (user_id, genre_id) VALUES (?, ?)', [$uid, $gid]);
            }
            $_SESSION['user_name'] = $first . ' ' . $last;   // keep navbar in sync
            set_flash('Profile updated successfully.', 'success');
        }
        redirect('customer/profile.php');
    }

    /* Profile picture — posted on its own from the avatar in the summary card,
       so choosing a file applies immediately without touching the details form. */
    if ($action === 'avatar') {
        $newAvatar = handle_image_upload('avatar', $avatarError, 'avatar_');

        if ($avatarError) {
            set_flash($avatarError, 'danger');
        } elseif (!$newAvatar) {
            set_flash('Please choose an image first.', 'warning');
        } else {
            $old = db_scalar('SELECT avatar FROM users WHERE user_id = ?', [$uid]);
            db_exec('UPDATE users SET avatar = ? WHERE user_id = ?', [$newAvatar, $uid]);
            delete_upload($old);                  // reclaim the replaced file
            $_SESSION['avatar'] = $newAvatar;     // keep the navbar in sync
            set_flash('Profile picture updated.', 'success');
        }
        redirect('customer/profile.php');
    }

    if ($action === 'remove_avatar') {
        $old = db_scalar('SELECT avatar FROM users WHERE user_id = ?', [$uid]);
        if ($old) {
            db_exec('UPDATE users SET avatar = NULL WHERE user_id = ?', [$uid]);
            delete_upload($old);
            $_SESSION['avatar'] = null;
            set_flash('Profile picture removed.', 'success');
        }
        redirect('customer/profile.php');
    }

    if ($action === 'password') {
        $cur = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $cf  = $_POST['confirm_password'] ?? '';
        $me  = db_one('SELECT password_hash FROM users WHERE user_id = ?', [$uid]);

        if (!password_verify($cur, $me['password_hash'])) {
            set_flash('Your current password is incorrect.', 'danger');
        } elseif (!valid_password($new)) {
            set_flash(password_rule_text(), 'danger');
        } elseif ($new !== $cf) {
            set_flash('New password and confirmation do not match.', 'danger');
        } else {
            db_exec('UPDATE users SET password_hash = ? WHERE user_id = ?',
                    [password_hash($new, PASSWORD_DEFAULT), $uid]);
            set_flash('Password changed successfully.', 'success');
        }
        redirect('customer/profile.php');
    }
}

/* ---- Load data for display ------------------------------------------------- */
$me = db_one('SELECT * FROM users WHERE user_id = ?', [$uid]);
$allGenres = db_all('SELECT genre_id, name FROM genres ORDER BY name');
$myGenreIds = array_column(
    db_all('SELECT genre_id FROM user_preferred_genres WHERE user_id = ?', [$uid]), 'genre_id');
$myGenreIds = array_map('intval', $myGenreIds);

$page_title = 'My Profile';
$active = 'profile';
$dash_title = 'My Profile';
include INCLUDES_PATH . '/header.php';
include INCLUDES_PATH . '/dash_open.php';
?>

<div class="row g-4">
  <!-- Account summary -->
  <div class="col-lg-4">
    <div class="card shadow-sm text-center">
      <div class="card-body">
        <?php $avatar = avatar_url($me['avatar']); ?>

        <!-- Profile picture: the picture itself is the upload button. Picking a
             file submits this form straight away (see the script at the bottom). -->
        <form method="post" enctype="multipart/form-data" id="avatarForm">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="avatar">
          <input type="file" name="avatar" id="avatarInput" class="d-none"
                 accept="image/jpeg,image/png,image/webp,image/gif">

          <label for="avatarInput" class="avatar-picker" role="button" tabindex="0"
                 title="<?= $avatar ? 'Change your profile picture' : 'Add a profile picture' ?>">
            <?php if ($avatar): ?>
              <img src="<?= e($avatar) ?>" alt="Profile picture of <?= e($me['first_name']) ?>"
                   class="rounded-circle border avatar-img">
            <?php else: ?>
              <span class="avatar-img avatar-empty rounded-circle border d-flex align-items-center justify-content-center">
                <i class="bi bi-person-fill text-secondary"></i>
              </span>
            <?php endif; ?>

            <span class="avatar-badge rounded-circle bg-warning text-dark shadow-sm
                         d-flex align-items-center justify-content-center">
              <i class="bi bi-camera-fill"></i>
            </span>
            <span class="avatar-overlay rounded-circle text-white
                         d-flex align-items-center justify-content-center small fw-semibold">
              <?= $avatar ? 'Change' : 'Add photo' ?>
            </span>
          </label>
        </form>

        <p class="text-muted small mt-2 mb-0">
          <?= $avatar ? 'Click your picture to change it' : 'Click the circle to add a photo' ?>
        </p>

        <h5 class="mt-2 mb-0"><?= e($me['first_name'] . ' ' . $me['last_name']) ?></h5>
        <p class="text-muted small"><?= e($me['email']) ?></p>
        <span class="badge text-bg-dark text-uppercase"><?= e($me['membership_status']) ?> member</span>

        <?php if ($avatar): ?>
          <form method="post" class="mt-3">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="remove_avatar">
            <button class="btn btn-sm btn-outline-danger">
              <i class="bi bi-trash me-1"></i>Remove photo
            </button>
          </form>
        <?php endif; ?>

        <hr>
        <p class="small text-muted mb-0">Member since <?= nice_date($me['created_at']) ?></p>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <!-- Edit details -->
    <div class="card shadow-sm mb-4">
      <div class="card-header bg-white fw-semibold">Edit Details</div>
      <div class="card-body">
        <form method="post" class="needs-validation" novalidate>
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="profile">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">First name</label>
              <input type="text" name="first_name" class="form-control" value="<?= e($me['first_name']) ?>"
                     required maxlength="50" pattern="<?= e(RE_NAME) ?>">
              <div class="invalid-feedback">Letters, spaces, hyphens and apostrophes only (2–50).</div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Last name</label>
              <input type="text" name="last_name" class="form-control" value="<?= e($me['last_name']) ?>"
                     required maxlength="50" pattern="<?= e(RE_NAME) ?>">
              <div class="invalid-feedback">Letters, spaces, hyphens and apostrophes only (2–50).</div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Email <span class="text-muted small">(read-only)</span></label>
              <input type="email" class="form-control" value="<?= e($me['email']) ?>" disabled>
            </div>
            <div class="col-md-6">
              <label class="form-label">Phone</label>
              <input type="tel" name="phone" class="form-control" value="<?= e($me['phone']) ?>"
                     inputmode="numeric" pattern="<?= e(RE_PHONE) ?>" placeholder="09171234567">
              <div class="invalid-feedback">Digits only, 7–15 of them.</div>
            </div>
            <div class="col-12">
              <label class="form-label d-block">Preferred genres</label>
              <?php foreach ($allGenres as $g): ?>
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="checkbox" name="genres[]"
                         value="<?= (int) $g['genre_id'] ?>" id="g<?= (int) $g['genre_id'] ?>"
                         <?= in_array((int) $g['genre_id'], $myGenreIds, true) ? 'checked' : '' ?>>
                  <label class="form-check-label" for="g<?= (int) $g['genre_id'] ?>"><?= e($g['name']) ?></label>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
          <button class="btn btn-warning mt-3">Save changes</button>
        </form>
      </div>
    </div>

    <!-- Change password -->
    <div class="card shadow-sm">
      <div class="card-header bg-white fw-semibold">Change Password</div>
      <div class="card-body">
        <form method="post" class="needs-validation" novalidate>
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="password">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Current password</label>
              <input type="password" name="current_password" class="form-control" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">New password</label>
              <input type="password" name="new_password" id="new_password" class="form-control"
                     required minlength="8" pattern="<?= e(RE_PASSWORD) ?>" data-password>
              <div class="invalid-feedback"><?= e(password_rule_text()) ?></div>
              <div class="password-meter mt-1"></div>
            </div>
            <div class="col-md-4">
              <label class="form-label">Confirm new</label>
              <input type="password" name="confirm_password" class="form-control"
                     required minlength="8" data-match="#new_password">
              <div class="invalid-feedback">Passwords do not match.</div>
            </div>
          </div>
          <button class="btn btn-outline-dark mt-3">Update password</button>
        </form>
      </div>
    </div>
  </div>
</div>

<style>
  .avatar-picker      { position: relative; display: inline-block; cursor: pointer; }
  .avatar-img         { width: 112px; height: 112px; object-fit: cover; }
  .avatar-empty       { background: #f1f3f5; font-size: 3rem; }

  /* Camera badge sits on the rim of the circle. */
  .avatar-badge       { position: absolute; right: 2px; bottom: 2px; width: 32px; height: 32px;
                        border: 2px solid #fff; transition: transform .15s ease; }

  /* Dark "Add photo" / "Change" veil, revealed on hover or keyboard focus. */
  .avatar-overlay     { position: absolute; inset: 0; background: rgba(0,0,0,.5);
                        opacity: 0; transition: opacity .15s ease; }
  .avatar-picker:hover .avatar-overlay,
  .avatar-picker:focus-visible .avatar-overlay { opacity: 1; }
  .avatar-picker:hover .avatar-badge           { transform: scale(1.1); }
  .avatar-picker:focus-visible                 { outline: 3px solid #ffc107; outline-offset: 3px; border-radius: 50%; }
</style>

<script>
  // Picking a file applies it straight away — no separate "save" step.
  (function () {
    var input = document.getElementById('avatarInput');
    var form  = document.getElementById('avatarForm');
    if (!input || !form) return;

    input.addEventListener('change', function () {
      if (input.files.length) form.submit();
    });

    // Space/Enter on the focused circle should open the picker, like a real button.
    var label = form.querySelector('.avatar-picker');
    label.addEventListener('keydown', function (ev) {
      if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); input.click(); }
    });
  })();
</script>

<?php
include INCLUDES_PATH . '/dash_close.php';
include INCLUDES_PATH . '/footer.php';
