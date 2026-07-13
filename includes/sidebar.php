<?php
/**
 * includes/sidebar.php
 * Role-aware dashboard sidebar. Include it INSIDE a Bootstrap row/column layout
 * on admin & customer dashboard pages. Highlights the current page via $active.
 *
 *   $active = 'dashboard';               // set before including
 *   include INCLUDES_PATH . '/sidebar.php';
 */
$active = $active ?? '';
$isAdmin = is_admin();

/* Menu definition: key => [label, icon, path] */
$menu = $isAdmin ? [
    'dashboard' => ['Dashboard',   'bi-speedometer2', 'admin/dashboard.php'],
    'books'     => ['Manage Books','bi-book',         'admin/books.php'],
    'catalog'   => ['Catalog Data','bi-tags',         'admin/taxonomy.php?type=authors'],
    'inventory' => ['Inventory',   'bi-boxes',        'admin/inventory.php'],
    'orders'    => ['Orders',      'bi-bag-check',    'admin/orders.php'],
    'payments'  => ['Payments',    'bi-credit-card',  'admin/payments.php'],
    'customers' => ['Customers',   'bi-people',       'admin/customers.php'],
    'reports'   => ['Reports',     'bi-graph-up',     'admin/reports.php'],
    'users'     => ['User Mgmt',   'bi-person-badge', 'admin/users.php'],
] : [
    'dashboard' => ['Dashboard',    'bi-grid',       'customer/dashboard.php'],
    'orders'    => ['My Orders',    'bi-bag',        'customer/orders.php'],
    'wishlist'  => ['Wishlist',     'bi-heart',      'customer/wishlist.php'],
    'addresses' => ['Addresses',    'bi-geo-alt',    'customer/addresses.php'],
    'profile'   => ['Profile',      'bi-person',     'customer/profile.php'],
];
?>
<div class="list-group shadow-sm sticky-lg-top" style="top:5rem;">
  <div class="list-group-item bg-dark text-white fw-bold text-uppercase small">
    <i class="bi <?= $isAdmin ? 'bi-shield-lock' : 'bi-person-circle' ?> me-1"></i>
    <?= $isAdmin ? 'Admin Panel' : 'My Account' ?>
  </div>
  <?php foreach ($menu as $key => [$label, $icon, $path]): ?>
    <a href="<?= url($path) ?>"
       class="list-group-item list-group-item-action d-flex align-items-center<?= $active === $key ? ' active' : '' ?>">
      <i class="bi <?= $icon ?> me-2"></i><?= e($label) ?>
    </a>
  <?php endforeach; ?>
</div>
