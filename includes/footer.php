<?php
/**
 * includes/footer.php
 * Closes the document, renders the site footer, loads Bootstrap JS + app JS.
 */
?>
</main><!-- /.flex-grow-1 -->

<footer class="bg-dark text-light py-4 mt-auto">
  <div class="container">
    <div class="row gy-3">
      <div class="col-md-6">
        <h5 class="fw-bold d-flex align-items-center gap-2">
          <?php if ($footerLogo = site_logo_url()): ?>
            <img src="<?= e($footerLogo) ?>" alt="" class="site-logo">
          <?php else: ?>
            <i class="bi bi-book-half text-warning"></i>
          <?php endif; ?>
          <span><?= e(SITE_NAME) ?></span>
        </h5>
        <p class="text-secondary mb-0"><?= e(SITE_TAGLINE) ?></p>
      </div>
      <div class="col-md-3">
        <h6 class="text-uppercase text-secondary">Shop</h6>
        <ul class="list-unstyled">
          <li><a class="link-light text-decoration-none" href="<?= url('books/browse.php') ?>">Browse Books</a></li>
          <li><a class="link-light text-decoration-none" href="<?= url('orders/cart.php') ?>">My Cart</a></li>
        </ul>
      </div>
      <div class="col-md-3">
        <h6 class="text-uppercase text-secondary">Account</h6>
        <ul class="list-unstyled">
          <li><a class="link-light text-decoration-none" href="<?= url('authentication/login.php') ?>">Login</a></li>
          <li><a class="link-light text-decoration-none" href="<?= url('authentication/register.php') ?>">Register</a></li>
        </ul>
      </div>
    </div>
    <hr class="border-secondary">
    <p class="text-center text-secondary mb-0 small">
      &copy; <?= date('Y') ?> <?= e(SITE_NAME) ?>.
    </p>
  </div>
</footer>

<!-- Toast container (JS notifications) -->
<div class="toast-container position-fixed bottom-0 end-0 p-3"></div>

<!-- Confirmation dialog — driven by any [data-confirm] element (see assets/js/main.js) -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title d-flex align-items-center gap-2" id="confirmModalTitle">
          <i class="bi bi-exclamation-triangle-fill" id="confirmModalIcon"></i>
          <span id="confirmModalTitleText">Are you sure?</span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body pt-2">
        <p class="mb-0 text-secondary" id="confirmModalBody"></p>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="confirmModalOk">Confirm</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php $js = dirname(__DIR__) . '/assets/js/main.js'; ?>
<script src="<?= e(ASSETS_URL) ?>js/main.js?v=<?= @filemtime($js) ?: '1' ?>"></script>
</body>
</html>
