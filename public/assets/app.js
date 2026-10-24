/* beauty-parlour web UI - small progressive enhancements, no framework */
(function () {
    'use strict';

    var $ = function (sel, root) { return (root || document).querySelector(sel); };
    var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };

    // 1. Auto-submit a form when a control with data-autosubmit changes (date pickers, filters).
    $$('[data-autosubmit]').forEach(function (el) {
        el.addEventListener('change', function () { if (el.form) { el.form.submit(); } });
    });

    // 2. Confirmation before destructive forms.
    $$('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (!window.confirm(form.getAttribute('data-confirm'))) { e.preventDefault(); }
        });
    });

    // 3. After the WhatsApp link was tapped, highlight the "sent" button of that reminder.
    $$('[data-wa-open]').forEach(function (link) {
        link.addEventListener('click', function () {
            var card = document.getElementById(link.getAttribute('data-wa-open'));
            if (card) { card.classList.add('wa-opened'); }
        });
    });

    // 4. Flash messages fade out.
    $$('.flash-success, .flash-info').forEach(function (el) {
        setTimeout(function () { el.style.transition = 'opacity .6s'; el.style.opacity = '0'; }, 4000);
        setTimeout(function () { if (el.parentNode) { el.parentNode.removeChild(el); } }, 4700);
    });

    // 5. Customer picker: search by name/phone, select -> hidden customer_id.
    $$('[data-customer-picker]').forEach(function (picker) {
        var input = $('[data-picker-input]', picker);
        var hidden = $('[data-picker-id]', picker);
        var results = $('[data-picker-results]', picker);
        var selected = $('[data-picker-selected]', picker);
        var selectedName = $('[data-picker-selected-name]', picker);
        var clearBtn = $('[data-picker-clear]', picker);
        var newToggle = $('[data-picker-new-toggle]', picker);
        var newFields = $('[data-picker-new]', picker);
        var url = picker.getAttribute('data-customer-picker');
        var timer = null;

        if (!input || !hidden || !results) { return; }

        function showSelected(name, phone) {
            hidden.value = String(name ? arguments[2] : '');
            if (selected && selectedName) {
                selectedName.textContent = name ? (name + ' · ' + phone) : '';
                selected.classList.toggle('hidden', !name);
            }
            input.classList.toggle('hidden', !!name);
            results.innerHTML = '';
            results.classList.add('hidden');
            if (newFields && name) { newFields.classList.add('hidden'); }
        }

        function render(list) {
            results.innerHTML = '';
            if (!list.length) {
                var empty = document.createElement('div');
                empty.className = 'muted small';
                empty.style.padding = '10px 14px';
                empty.textContent = 'કોઈ ગ્રાહક મળ્યો નહીં. નીચે "નવો ગ્રાહક" ભરો.';
                results.appendChild(empty);
            }
            list.forEach(function (c) {
                var b = document.createElement('button');
                b.type = 'button';
                b.innerHTML = '<span></span><span class="muted"></span>';
                b.firstChild.textContent = c.name;
                b.lastChild.textContent = c.phone;
                b.addEventListener('click', function () { showSelected(c.name, c.phone, c.id); });
                results.appendChild(b);
            });
            results.classList.remove('hidden');
        }

        function search() {
            var q = input.value.trim();
            var xhr = new XMLHttpRequest();
            xhr.open('GET', url + (url.indexOf('?') === -1 ? '?' : '&') + 'q=' + encodeURIComponent(q), true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.onload = function () {
                if (xhr.status !== 200) { return; }
                try { render(JSON.parse(xhr.responseText).customers || []); } catch (e) { /* ignore */ }
            };
            xhr.send();
        }

        input.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(search, 220);
        });
        input.addEventListener('focus', function () { if (!results.children.length) { search(); } });
        document.addEventListener('click', function (e) {
            if (!picker.contains(e.target)) { results.classList.add('hidden'); }
        });
        if (clearBtn) {
            clearBtn.addEventListener('click', function () { showSelected('', '', ''); input.focus(); });
        }
        if (newToggle && newFields) {
            newToggle.addEventListener('click', function () {
                newFields.classList.toggle('hidden');
                if (!newFields.classList.contains('hidden')) {
                    showSelected('', '', '');
                    results.classList.add('hidden');
                    var first = $('input', newFields);
                    if (first) { first.focus(); }
                }
            });
        }
    });

    // 6. Booking form: service rows with quantity, live totals.
    var booking = $('[data-booking-form]');
    if (booking) {
        var rows = $$('.svc-row', booking);
        var discount = $('[name=discount]', booking);
        var customPrice = $('[name=custom_price]', booking);
        var customQty = $('[name=custom_quantity]', booking);
        var customName = $('[name=custom_name]', booking);
        var customMinutes = $('[name=custom_minutes]', booking);
        var outSub = $('[data-subtotal]', booking);
        var outDisc = $('[data-discount]', booking);
        var outTotal = $('[data-total]', booking);
        var outMin = $('[data-minutes]', booking);

        function fmt(n) {
            n = Math.max(0, Math.round(n));
            var s = String(n), last = s.slice(-3), rest = s.slice(0, -3);
            if (rest) { rest = rest.replace(/\B(?=(\d{2})+(?!\d))/g, ','); return '₹' + rest + ',' + last; }
            return '₹' + s;
        }

        function recalc() {
            var sub = 0, minutes = 0;
            rows.forEach(function (row) {
                var cb = $('input[type=checkbox]', row);
                var qty = $('.qty', row);
                var on = cb && cb.checked;
                row.classList.toggle('selected', !!on);
                if (qty) { qty.disabled = !on; }
                if (on) {
                    var q = Math.max(1, parseInt(qty && qty.value, 10) || 1);
                    sub += (parseInt(row.getAttribute('data-price'), 10) || 0) * q;
                    minutes += (parseInt(row.getAttribute('data-minutes'), 10) || 0) * q;
                }
            });
            if (customName && customName.value.trim() !== '' && customPrice) {
                var cq = Math.max(1, parseInt(customQty && customQty.value, 10) || 1);
                sub += (parseInt(customPrice.value, 10) || 0) * cq;
                minutes += (parseInt(customMinutes && customMinutes.value, 10) || 30) * cq;
            }
            var d = Math.min(sub, Math.max(0, parseInt(discount && discount.value, 10) || 0));
            if (outSub) { outSub.textContent = fmt(sub); }
            if (outDisc) { outDisc.textContent = '- ' + fmt(d); }
            if (outTotal) { outTotal.textContent = fmt(sub - d); }
            if (outMin) { outMin.textContent = String(Math.max(15, minutes)); }
        }

        booking.addEventListener('change', recalc);
        booking.addEventListener('input', recalc);
        rows.forEach(function (row) {
            row.addEventListener('click', function (e) {
                if (e.target.tagName === 'INPUT' || e.target.tagName === 'LABEL') { return; }
                var cb = $('input[type=checkbox]', row);
                if (cb) { cb.checked = !cb.checked; recalc(); }
            });
        });
        recalc();
    }

    // 7. Phone inputs: digits only.
    $$('input[data-digits]').forEach(function (el) {
        el.addEventListener('input', function () {
            var v = el.value.replace(/\D+/g, '');
            if (v !== el.value) { el.value = v; }
        });
    });
})();
