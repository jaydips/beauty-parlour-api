// beauty-parlour-api web UI - small progressive enhancements only (no framework)
(function () {
  'use strict';

  // Confirm before submitting forms marked with data-confirm
  document.addEventListener('submit', function (event) {
    var form = event.target;
    if (form instanceof HTMLFormElement && form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
      event.preventDefault();
    }
  });

  // Prevent double submits
  document.addEventListener('submit', function (event) {
    var form = event.target;
    if (!(form instanceof HTMLFormElement) || event.defaultPrevented) { return; }
    form.querySelectorAll('button[type="submit"]').forEach(function (b) { b.disabled = true; });
  });

  // Auto-hide success messages
  document.querySelectorAll('.flash-success').forEach(function (el) {
    setTimeout(function () { el.style.transition = 'opacity .4s'; el.style.opacity = '0'; }, 3500);
    setTimeout(function () { el.remove(); }, 4000);
  });

  // Customer autocomplete on the appointment form
  var query = document.getElementById('customer_query');
  var hidden = document.getElementById('customer_id');
  var list = document.getElementById('customer_suggestions');
  var newBox = document.getElementById('new_customer');
  if (query && hidden && list) {
    var timer = null;
    var url = query.dataset.searchUrl;

    function select(c) {
      hidden.value = c.id;
      query.value = c.name + ' · ' + c.phone;
      list.innerHTML = '';
      list.hidden = true;
      if (newBox) { newBox.hidden = true; }
    }

    query.addEventListener('input', function () {
      hidden.value = '';
      if (newBox) { newBox.hidden = false; }
      clearTimeout(timer);
      var q = query.value.trim();
      if (q.length < 2) { list.innerHTML = ''; list.hidden = true; return; }
      timer = setTimeout(function () {
        fetch(url + '?q=' + encodeURIComponent(q), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
          .then(function (r) { return r.ok ? r.json() : []; })
          .then(function (rows) {
            list.innerHTML = '';
            rows.forEach(function (c) {
              var b = document.createElement('button');
              b.type = 'button';
              b.className = 'suggestion';
              b.textContent = c.name + ' · ' + c.phone;
              b.addEventListener('click', function () { select(c); });
              list.appendChild(b);
            });
            list.hidden = rows.length === 0;
          })
          .catch(function () { list.hidden = true; });
      }, 250);
    });
  }

  // Live total on the appointment form: sum(price * qty) - discount
  var form = document.getElementById('appointment_form');
  if (form) {
    var totalEl = document.getElementById('live_total');
    function recalc() {
      var sum = 0;
      form.querySelectorAll('.svc-row').forEach(function (row) {
        var cb = row.querySelector('input[type="checkbox"]');
        var qty = row.querySelector('input.qty');
        if (cb && qty) { cb.value = String(Math.max(1, parseInt(qty.value, 10) || 1)); }
        if (cb && cb.checked) {
          sum += (parseInt(row.dataset.price, 10) || 0) * Math.max(1, parseInt(qty && qty.value, 10) || 1);
        }
      });
      var discount = parseInt((form.querySelector('[name="discount"]') || {}).value, 10) || 0;
      if (totalEl) { totalEl.textContent = '₹' + Math.max(0, sum - discount).toLocaleString('en-IN'); }
    }
    form.addEventListener('change', recalc);
    form.addEventListener('input', recalc);
    recalc();
  }
})();
