/* =============================================================================
   main.js — small front-end helpers (toasts, confirms, loading indicator)
   Loaded on every page via includes/footer.php
   ========================================================================== */
(function () {
    'use strict';

    /* ---- Toast notifications ---------------------------------------------
       Usage from anywhere:  showToast('Saved!', 'success');                  */
    window.showToast = function (message, type) {
        type = type || 'primary';
        var container = document.querySelector('.toast-container');
        if (!container) return;

        var el = document.createElement('div');
        el.className = 'toast align-items-center text-bg-' + type + ' border-0';
        el.setAttribute('role', 'alert');
        el.innerHTML =
            '<div class="d-flex">' +
            '  <div class="toast-body">' + message + '</div>' +
            '  <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>' +
            '</div>';
        container.appendChild(el);
        var toast = new bootstrap.Toast(el, { delay: 3500 });
        toast.show();
        el.addEventListener('hidden.bs.toast', function () { el.remove(); });
    };

    document.addEventListener('DOMContentLoaded', function () {

        /* ---- data-confirm: ask before dangerous actions -------------------
           Any element carrying data-confirm="…" opens the Bootstrap dialog in
           includes/footer.php instead of the browser's native confirm() box.

           Optional attributes:
             data-confirm-title  heading text        (default "Are you sure?")
             data-confirm-ok     confirm button text (default "Confirm")

           The dialog's colour follows the trigger: a btn-*-danger element gets
           a red Confirm button, anything else gets amber.                     */
        var modalEl = document.getElementById('confirmModal');
        if (modalEl) {
            var modal     = new bootstrap.Modal(modalEl);
            var okBtn     = document.getElementById('confirmModalOk');
            var bodyEl    = document.getElementById('confirmModalBody');
            var titleEl   = document.getElementById('confirmModalTitleText');
            var iconEl    = document.getElementById('confirmModalIcon');
            var pending   = null;   // element awaiting confirmation

            // Delegated, so buttons rendered later still work.
            document.addEventListener('click', function (e) {
                var trigger = e.target.closest('[data-confirm]');
                if (!trigger) return;

                // Second pass: we already asked and the user said yes — let it through.
                if (trigger.dataset.confirmed === 'yes') {
                    delete trigger.dataset.confirmed;
                    return;
                }

                e.preventDefault();
                pending = trigger;

                var danger = /btn-(outline-)?danger/.test(trigger.className);
                bodyEl.textContent  = trigger.getAttribute('data-confirm') || '';
                titleEl.textContent = trigger.getAttribute('data-confirm-title') || 'Are you sure?';
                okBtn.textContent   = trigger.getAttribute('data-confirm-ok') || 'Confirm';
                okBtn.className     = 'btn ' + (danger ? 'btn-danger' : 'btn-warning');
                iconEl.className    = 'bi bi-exclamation-triangle-fill ' +
                                      (danger ? 'text-danger' : 'text-warning');
                modal.show();
            });

            okBtn.addEventListener('click', function () {
                if (!pending) return;
                var trigger = pending;
                pending = null;
                modal.hide();
                // Flag it so the delegated handler above lets this click pass, then
                // replay it — submitting the form (preserving the button's name/value)
                // or following the link, exactly as the user originally intended.
                trigger.dataset.confirmed = 'yes';
                trigger.click();
            });

            // Dismissing without confirming simply drops the pending action.
            modalEl.addEventListener('hidden.bs.modal', function () { pending = null; });
        }

        /* ---- Show a loading overlay when a form is submitted ---------------
           Only for submits that will actually navigate. A form carrying BOTH
           data-loading and needs-validation would otherwise show the spinner
           and then have its submit cancelled by the validation handler below,
           leaving the overlay up forever with nothing to dismiss it.          */
        var loader = document.getElementById('page-loader');

        function hideLoader() { if (loader) loader.classList.remove('show'); }

        document.querySelectorAll('form[data-loading]').forEach(function (form) {
            form.addEventListener('submit', function () {
                // Invalid: the needs-validation handler is about to preventDefault().
                if (typeof form.checkValidity === 'function' && !form.checkValidity()) return;
                if (loader) loader.classList.add('show');

                // Last-resort escape hatch. If the navigation has not happened after
                // 15s, something went wrong — drop the overlay rather than leave the
                // customer staring at a spinner they cannot dismiss.
                setTimeout(hideLoader, 15000);
            });
        });

        // Safety nets: never let the overlay strand the user.
        window.addEventListener('pageshow', hideLoader);   // returning via the Back button
        window.addEventListener('pagehide', hideLoader);

        /* ---- Auto-dismiss flash alerts after a few seconds --------------- */
        setTimeout(function () {
            document.querySelectorAll('.alert-dismissible').forEach(function (a) {
                var alert = bootstrap.Alert.getOrCreateInstance(a);
                if (alert) alert.close();
            });
        }, 6000);

        /* ---- Bootstrap client-side form validation styles ---------------- */
        document.querySelectorAll('form.needs-validation').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                if (!form.checkValidity()) {
                    e.preventDefault();
                    e.stopPropagation();
                }
                form.classList.add('was-validated');
            });
        });

        /* ---- Trim whitespace from every text field before it is submitted --
           The server trims too; doing it here means the browser's own pattern
           check sees the trimmed value, so "  " no longer passes as a name.  */
        document.querySelectorAll('form').forEach(function (form) {
            form.addEventListener('submit', function () {
                form.querySelectorAll('input[type=text], input[type=email], input[type=tel], input[type=search], textarea')
                    .forEach(function (f) { f.value = f.value.trim(); });
            }, true);   // capture phase: runs before the validation handler above
        });

        /* ---- Confirm-password fields: data-match="#password" -------------- */
        document.querySelectorAll('[data-match]').forEach(function (confirm) {
            var target = document.querySelector(confirm.getAttribute('data-match'));
            if (!target) return;
            function compare() {
                confirm.setCustomValidity(
                    confirm.value === target.value ? '' : 'Passwords do not match.');
            }
            confirm.addEventListener('input', compare);
            target.addEventListener('input', compare);
        });

        /* ---- ISBN: numbers only (hyphens allowed, stripped on the server) -- */
        document.querySelectorAll('[data-isbn]').forEach(function (input) {
            function checkIsbn() {
                var digits = input.value.replace(/[\s\-]/g, '');
                input.setCustomValidity(
                    digits === '' || /^(\d{10}|\d{13})$/.test(digits)
                        ? '' : 'ISBN must be 10 or 13 digits, numbers only.');
            }
            input.addEventListener('input', checkIsbn);
        });

        /* ---- Live password-strength meter --------------------------------- */
        document.querySelectorAll('[data-password]').forEach(function (input) {
            var meter = input.closest('div').querySelector('.password-meter');
            if (!meter) return;
            var RULES = [
                [/.{8,}/,        '8+ characters'],
                [/[a-z]/,        'lowercase letter'],
                [/[A-Z]/,        'uppercase letter'],
                [/\d/,           'number'],
                [/[^A-Za-z0-9]/, 'special character']
            ];
            input.addEventListener('input', function () {
                if (!input.value) { meter.innerHTML = ''; return; }
                var missing = RULES.filter(function (r) { return !r[0].test(input.value); })
                                   .map(function (r) { return r[1]; });
                var passed  = RULES.length - missing.length;
                var pct     = (passed / RULES.length) * 100;
                var colour  = passed <= 2 ? 'bg-danger' : (passed < RULES.length ? 'bg-warning' : 'bg-success');
                meter.innerHTML =
                    '<div class="progress" style="height:5px;">' +
                    '  <div class="progress-bar ' + colour + '" style="width:' + pct + '%"></div>' +
                    '</div>' +
                    (missing.length
                        ? '<small class="text-danger">Still needs: ' + missing.join(', ') + '</small>'
                        : '<small class="text-success">Strong password</small>');
            });
        });

        /* ---- Prevent duplicate submissions --------------------------------
           Double-clicking "Place Order" would otherwise POST twice and create
           two orders. Disable the button once a VALID submit is under way.
           The disabling is deferred by a tick so the button's own name/value is
           still included in the POST body.                                    */
        document.querySelectorAll('form').forEach(function (form) {
            form.addEventListener('submit', function () {
                if (typeof form.checkValidity === 'function' && !form.checkValidity()) return;

                setTimeout(function () {
                    form.querySelectorAll('button[type=submit], button:not([type]), input[type=submit]')
                        .forEach(function (btn) {
                            if (btn.disabled) return;
                            btn.disabled = true;
                            if (btn.tagName === 'BUTTON' && !btn.querySelector('.spinner-border')) {
                                btn.dataset.originalHtml = btn.innerHTML;
                                btn.innerHTML =
                                    '<span class="spinner-border spinner-border-sm me-1" role="status"></span>' +
                                    'Processing…';
                            }
                        });
                }, 0);
            });
        });

        // If the user comes back via the Back button, the form is restored from
        // the bfcache with its buttons still disabled. Re-enable them.
        window.addEventListener('pageshow', function () {
            document.querySelectorAll('button[disabled][data-original-html]').forEach(function (btn) {
                btn.disabled  = false;
                btn.innerHTML = btn.dataset.originalHtml;
            });
        });
    });
})();
