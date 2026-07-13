<?php
/**
 * includes/dash_open.php
 * Opens the two-column dashboard layout (sidebar + content).
 * Set  $active  (sidebar highlight key) and optionally $dash_title before include.
 */
?>
<div class="container">
  <div class="row g-4">
    <aside class="col-lg-3">
      <?php include INCLUDES_PATH . '/sidebar.php'; ?>
    </aside>
    <section class="col-lg-9">
      <?php if (!empty($dash_title)): ?>
        <h1 class="h3 mb-4"><?= e($dash_title) ?></h1>
      <?php endif; ?>
