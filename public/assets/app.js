// beauty-parlour-api web UI - small progressive enhancements only
(function () {
  'use strict';

  // Confirm before submitting forms marked with data-confirm
  document.addEventListener('submit', function (event) {
    var form = event.target;
    if (form instanceof HTMLFormElement && form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
      event.preventDefault();
    }
  });

  // Auto-hide success messages
  document.querySelectorAll('.flash-success').forEach(function (el) {
    setTimeout(function () { el.style.transition = 'opacity .4s'; el.style.opacity = '0'; }, 3500);
    setTimeout(function () { el.remove(); }, 4000);
  });

  // Prevent double submits
  document.addEventListener('submit', function (event) {
    var form = event.target;
    if (!(form instanceof HTMLFormElement) || event.defaultPrevented) { return; }
    form.querySelectorAll('button[type="submit"]').forEach(function (b) { b.disabled = true; });
  });
})();
