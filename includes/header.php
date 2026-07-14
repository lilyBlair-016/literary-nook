<?php
/**
 * includes/header.php
 * Opens the HTML document, loads Bootstrap 5, and renders the top navigation.
 * Pages set  $page_title  before including this file.
 */
if (!defined('SITE_NAME')) { require_once dirname(__DIR__) . '/config/config.php'; }
$page_title = $page_title ?? SITE_NAME;
$u = current_user();

$logoUrl = site_logo_url();   // assets/images/logo.* — null when absent
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?> · <?= e(SITE_NAME) ?></title>
    <?php if ($logoUrl): ?>
      <link rel="icon" href="<?= e($logoUrl) ?>">
    <?php endif; ?>

    <!-- Bootstrap 5 + Icons (CDN) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Custom styles. The ?v= stamp is the file's mtime, so browsers re-fetch
         the stylesheet whenever it changes instead of serving a stale cache. -->
    <?php $css = dirname(__DIR__) . '/assets/css/style.css'; ?>
    <link href="<?= e(ASSETS_URL) ?>css/style.css?v=<?= @filemtime($css) ?: '1' ?>" rel="stylesheet">
</head>
<body class="d-flex flex-column min-vh-100 bg-body-tertiary">

<!-- ===================== TOP NAVIGATION ===================== -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm sticky-top">
  <div class="container">
    <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="<?= url('index.php') ?>">
      <?php if ($logoUrl): ?>
        <img src="<?= e($logoUrl) ?>" alt="<?= e(SITE_NAME) ?> logo" class="site-logo">
      <?php else: ?>
        <i class="bi bi-book-half text-warning"></i>
      <?php endif; ?>
      <span><?= e(SITE_NAME) ?></span>
    </a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
            data-bs-target="#topnav" aria-controls="topnav" aria-expanded="false">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="topnav">
      <!-- Search -->
      <form class="d-flex mx-lg-auto my-2 my-lg-0" role="search" action="<?= url('books/browse.php') ?>" method="get">
        <div class="input-group">
          <input class="form-control" type="search" name="q" placeholder="Search books, authors, ISBN…"
                 value="<?= e($_GET['q'] ?? '') ?>" aria-label="Search">
          <button class="btn btn-warning" type="submit"><i class="bi bi-search"></i></button>
        </div>
      </form>

      <ul class="navbar-nav ms-lg-3 align-items-lg-center">
        <li class="nav-item"><a class="nav-link" href="<?= url('books/browse.php') ?>">Browse</a></li>

        <?php if ($u): ?>
          <!-- Cart -->
          <li class="nav-item">
            <a class="nav-link position-relative" href="<?= url('orders/cart.php') ?>">
              <i class="bi bi-cart3"></i> Cart
              <?php $cc = cart_count(); if ($cc): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-warning text-dark"><?= $cc ?></span>
              <?php endif; ?>
            </a>
          </li>
          <!-- Notifications bell -->
          <li class="nav-item dropdown">
            <a class="nav-link position-relative" href="#" role="button" data-bs-toggle="dropdown">
              <i class="bi bi-bell"></i>
              <?php $nc = notif_count(); if ($nc): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?= $nc ?></span>
              <?php endif; ?>
            </a>
            <ul class="dropdown-menu dropdown-menu-end" style="min-width:280px;">
              <li><h6 class="dropdown-header">Notifications</h6></li>
              <?php $recent = recent_notifications(5); if (!$recent): ?>
                <li><span class="dropdown-item-text text-muted small">No notifications.</span></li>
              <?php else: foreach ($recent as $rn): ?>
                <li><a class="dropdown-item small <?= $rn['is_read'] ? '' : 'fw-semibold' ?>" href="<?= url('notifications.php') ?>">
                  <i class="bi bi-dot text-warning"></i><?= e(excerpt($rn['subject'], 34)) ?></a></li>
              <?php endforeach; endif; ?>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item text-center small" href="<?= url('notifications.php') ?>">View all</a></li>
            </ul>
          </li>
          <!-- User dropdown -->
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
              <?php $navAvatar = avatar_url($u['avatar'] ?? null); ?>
              <?php if ($navAvatar): ?>
                <img src="<?= e($navAvatar) ?>" alt="" class="rounded-circle"
                     width="24" height="24" style="object-fit:cover;">
              <?php else: ?>
                <i class="bi bi-person-circle"></i>
              <?php endif; ?>
              <?= e($u['name']) ?>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
              <?php if ($u['role'] === 'admin'): ?>
                <li><a class="dropdown-item" href="<?= url('admin/dashboard.php') ?>"><i class="bi bi-speedometer2 me-2"></i>Admin Dashboard</a></li>
              <?php else: ?>
                <li><a class="dropdown-item" href="<?= url('customer/dashboard.php') ?>"><i class="bi bi-grid me-2"></i>My Dashboard</a></li>
                <li><a class="dropdown-item" href="<?= url('customer/orders.php') ?>"><i class="bi bi-bag me-2"></i>My Orders</a></li>
                <li><a class="dropdown-item" href="<?= url('customer/wishlist.php') ?>"><i class="bi bi-heart me-2"></i>Wishlist</a></li>
              <?php endif; ?>
              <li><a class="dropdown-item" href="<?= url('customer/profile.php') ?>"><i class="bi bi-gear me-2"></i>Profile</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item text-danger" href="<?= url('authentication/logout.php') ?>"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
            </ul>
          </li>
        <?php else: ?>
          <li class="nav-item"><a class="nav-link" href="<?= url('authentication/login.php') ?>">Login</a></li>
          <li class="nav-item"><a class="btn btn-warning btn-sm ms-lg-2" href="<?= url('authentication/register.php') ?>">Register</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<!-- Flash messages -->
<?php $flashes = get_flashes(); ?>
<?php if ($flashes): ?>
  <div class="container mt-3">
    <?php foreach ($flashes as $f): ?>
      <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show" role="alert">
        <?= e($f['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<!-- Page content opens here; pages close their own containers before footer -->
<main class="flex-grow-1 py-4">
