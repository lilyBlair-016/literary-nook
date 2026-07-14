<?php
/**
 * admin/notifications.php — Broadcast promotional alerts to customers.
 * Creates in-app notifications + logs an "email" per recipient.
 */
require_once __DIR__ . '/../config/config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $subject = clean($_POST['subject'] ?? '');
    $message = clean($_POST['message'] ?? '');
    $segment = $_POST['segment'] ?? 'all';

    if ($subject === '' || $message === '') {
        set_flash('Subject and message are required.', 'danger');
        redirect('admin/notifications.php');
    }

    // Target audience (Module 2: conditional segment selection).
    $sql = "SELECT user_id, email, first_name FROM users WHERE role='customer' AND is_active=1";
    $params = [];
    if (in_array($segment, ['regular','silver','gold','vip'], true)) {
        $sql .= ' AND membership_status = ?';
        $params[] = $segment;
    }
    $recipients = db_all($sql, $params);

    $sent = 0;
    foreach ($recipients as $r) {
        notify((int) $r['user_id'], 'promo', $subject, $message);
        send_app_mail($r['email'], $subject, "<p>Hi {$r['first_name']},</p><p>" . nl2br(e($message)) . "</p>");
        $sent++;
    }
    set_flash("Promotional alert sent to {$sent} customer(s).", 'success');
    redirect('admin/notifications.php');
}

/* Recent promo broadcasts (grouped by subject). */
$recent = db_all(
    "SELECT subject, COUNT(*) AS recipients, MAX(created_at) AS sent_at
     FROM notifications WHERE type='promo'
     GROUP BY subject ORDER BY sent_at DESC LIMIT 10");

$counts = db_all("SELECT membership_status, COUNT(*) n FROM users WHERE role='customer' GROUP BY membership_status");
$total  = (int) db_scalar("SELECT COUNT(*) FROM users WHERE role='customer'");

$page_title = 'Send Notifications';
$active = 'alerts';
$dash_title = 'Promotional Alerts';
include INCLUDES_PATH . '/header.php';
include INCLUDES_PATH . '/dash_open.php';
?>
<div class="row g-4">
  <div class="col-lg-6">
    <div class="card shadow-sm">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-megaphone me-1"></i>Compose Alert</div>
      <div class="card-body">
        <form method="post" class="needs-validation" novalidate>
          <?= csrf_field() ?>
          <div class="mb-3"><label class="form-label">Audience</label>
            <select name="segment" class="form-select">
              <option value="all">All customers (<?= $total ?>)</option>
              <?php foreach ($counts as $c): ?>
                <option value="<?= e($c['membership_status']) ?>"><?= ucfirst($c['membership_status']) ?> members (<?= (int)$c['n'] ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3"><label class="form-label">Subject *</label>
            <input name="subject" class="form-control" required placeholder="New arrivals this week!"></div>
          <div class="mb-3"><label class="form-label">Message *</label>
            <textarea name="message" rows="5" class="form-control" required placeholder="Tell customers about the promotion…"></textarea></div>
          <button class="btn btn-warning" data-confirm="Send this alert to the selected audience?"><i class="bi bi-send me-1"></i>Send Alert</button>
        </form>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card shadow-sm">
      <div class="card-header bg-white fw-semibold">Recent Broadcasts</div>
      <div class="list-group list-group-flush">
        <?php if (!$recent): ?>
          <div class="empty-state"><i class="bi bi-megaphone d-block mb-2"></i>No broadcasts yet.</div>
        <?php else: foreach ($recent as $r): ?>
          <div class="list-group-item">
            <div class="d-flex justify-content-between">
              <strong><?= e($r['subject']) ?></strong>
              <small class="text-muted"><?= nice_date($r['sent_at']) ?></small>
            </div>
            <small class="text-muted"><?= (int)$r['recipients'] ?> recipient(s)</small>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
  </div>
</div>
<?php include INCLUDES_PATH . '/dash_close.php'; include INCLUDES_PATH . '/footer.php'; ?>
