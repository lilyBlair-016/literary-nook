<?php
/**
 * customer/notifications.php — Notification center: list, mark read, mark all.
 */
require_once __DIR__ . '/../config/config.php';
require_login();
$uid = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'read')      db_exec('UPDATE notifications SET is_read=1 WHERE notification_id=? AND user_id=?', [(int)$_POST['id'], $uid]);
    if ($action === 'read_all')  db_exec('UPDATE notifications SET is_read=1 WHERE user_id=?', [$uid]);
    if ($action === 'delete')    db_exec('DELETE FROM notifications WHERE notification_id=? AND user_id=?', [(int)$_POST['id'], $uid]);
    redirect('customer/notifications.php');
}

$notifs = db_all('SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC', [$uid]);

$icons = ['registration'=>'bi-person-check','order'=>'bi-bag-check','shipping'=>'bi-truck','promo'=>'bi-megaphone','system'=>'bi-gear'];

$page_title = 'Notifications';
$active = 'dashboard';
$dash_title = 'Notifications';
include INCLUDES_PATH . '/header.php';
include INCLUDES_PATH . '/dash_open.php';
?>
<?php if ($notifs): ?>
  <form method="post" class="mb-3 text-end"><?= csrf_field() ?><input type="hidden" name="action" value="read_all">
    <button class="btn btn-sm btn-outline-dark"><i class="bi bi-check2-all me-1"></i>Mark all read</button></form>
<?php endif; ?>

<div class="card shadow-sm">
  <div class="list-group list-group-flush">
    <?php if (!$notifs): ?>
      <div class="empty-state"><i class="bi bi-bell-slash d-block mb-2"></i>No notifications.</div>
    <?php else: foreach ($notifs as $n): ?>
      <div class="list-group-item d-flex align-items-start gap-3 <?= $n['is_read']?'':'bg-warning-subtle' ?>">
        <i class="bi <?= $icons[$n['type']] ?? 'bi-bell' ?> fs-4 text-warning"></i>
        <div class="flex-grow-1">
          <div class="d-flex justify-content-between">
            <strong><?= e($n['subject']) ?></strong>
            <small class="text-muted"><?= nice_datetime($n['created_at']) ?></small>
          </div>
          <div class="text-muted small"><?= e($n['message']) ?></div>
        </div>
        <div class="d-flex gap-1">
          <?php if (!$n['is_read']): ?>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="read">
              <input type="hidden" name="id" value="<?= (int)$n['notification_id'] ?>">
              <button class="btn btn-sm btn-outline-secondary" title="Mark read"><i class="bi bi-check"></i></button></form>
          <?php endif; ?>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$n['notification_id'] ?>">
            <button class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button></form>
        </div>
      </div>
    <?php endforeach; endif; ?>
  </div>
</div>

<?php include INCLUDES_PATH . '/dash_close.php'; include INCLUDES_PATH . '/footer.php'; ?>
