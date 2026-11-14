<?php
/**
 * Script of sales/new_order: the counter's store, the catalog search, booking a time or a session, the lines, the price
 * (admin/sales/counter/quote after each change) and creating the order (admin/sales/counter/order), then going to it.
 * The lines are kept here; the API prices and checks them every time, so what is shown is what will be charged.
 */
$config = [
    'urls'   => ['counter' => base_url('admin/sales/counter/'), 'order' => base_url('admin/sales/order/')],
    'locale' => service('request')->getLocale(),
    'text'   => lang('Sales.new'),
    'csrf'   => ['header' => csrf_header(), 'hash' => csrf_hash()],
];
?>
(function (cfg) {
  'use strict';
  var T = cfg.text;
  var csrfHash = cfg.csrf.hash;
  var STORE_KEY = 'counter-order-store';
  var root = document.getElementById('counter-order');
  if (!root) { return; }
  var $ = function (sel, el) { return (el || root).querySelector(sel); };
  var form = document.getElementById('co-form');
  var state = { store: '', lines: [], quote: null, seq: 0, booking: null };
  var timers = {};

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; });
  }
  function fill(text, value) { return String(text).replace('{0}', value); }
  function later(name, fn, ms) { clearTimeout(timers[name]); timers[name] = setTimeout(fn, ms); }
  function money(amount) {
    var currency = state.quote && state.quote.currency;
    try { return currency ? new Intl.NumberFormat(cfg.locale, { style: 'currency', currency: currency }).format(Number(amount)) : amount; } catch (e) { return amount; }
  }
  function when(iso) { return iso ? String(iso).slice(0, 16).replace('T', ' ') : ''; }
  function notify(type, text) { if (window.toastr) { window.toastr[type](text); } else if (type === 'error') { window.alert(text); } }

  // POST JSON to admin/sales/counter/<what>: { ok, status, json }
  function api(what, body) {
    var headers = { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    headers[cfg.csrf.header] = csrfHash;
    return fetch(cfg.urls.counter + what, { method: 'POST', headers: headers, credentials: 'same-origin', body: JSON.stringify(body || {}) })
      .then(function (r) { return r.json().catch(function () { return {}; }).then(function (json) { return { ok: r.ok, status: r.status, json: json }; }); })
      .then(function (res) {
        if (res.json.csrf_hash) { csrfHash = res.json.csrf_hash; }
        if (res.json.redirect && res.json.status === 'error') { window.location.href = res.json.redirect; }
        return res;
      });
  }

  // --- the counter's store ---------------------------------------------------------------------------------------
  function loadStores() {
    api('stores').then(function (res) {
      var stores = (res.json.data || {}).stores || [];
      var select = $('[data-co-store]');
      if (!stores.length) { showErrors([T['no-stores']]); return; }
      var saved = null;
      try { saved = window.localStorage.getItem(STORE_KEY); } catch (e) { /* no storage */ }
      var pick = stores.filter(function (s) { return s.public_id === saved; })[0] || stores.filter(function (s) { return s.mine; })[0] || stores[0];
      select.innerHTML = stores.map(function (s) {
        return '<option value="' + esc(s.public_id) + '"' + (s.public_id === pick.public_id ? ' selected' : '') + '>' + esc(s.store_name) + '</option>';
      }).join('');
      state.store = pick.public_id;
      search();
    });
  }

  // --- the catalog -----------------------------------------------------------------------------------------------
  function search() {
    var box = $('[data-co-results]');
    api('catalog', { store_public_id: state.store, search: $('[data-co-search]').value }).then(function (res) {
      var items = (res.json.data || {}).items;
      if (!items) { box.innerHTML = '<div class="text-danger small">' + esc(res.json.message || T.failed) + '</div>'; return; }
      if (!items.length) { box.innerHTML = '<div class="text-muted small py-2">' + esc(T['no-results']) + '</div>'; return; }
      state.catalog = items;
      box.innerHTML = items.map(function (item, i) {
        var bits = [T.kind[item.kind]];
        if (item.kind === 'P') { bits.push(item.stock === null ? T['no-count'] : fill(T['in-stock'], item.stock)); }
        if (item.duration_minutes) { bits.push(fill(T.minutes, item.duration_minutes)); }
        if (item.kind === 'A' && item.people > 1) { bits.push(fill(T['for-people'], item.people)); }
        return '<div class="list-group-item d-flex align-items-center gap-2 px-0">'
          + '<div class="me-auto"><div class="fw-semibold">' + esc(item.name) + (item.label ? ' <span class="text-muted fw-normal">' + esc(item.label) + '</span>' : '') + '</div>'
          + '<div class="small text-muted">' + esc(bits.join(' · ')) + (item.sku ? ' · ' + esc(item.sku) : '') + '</div></div>'
          + '<div class="text-nowrap">' + esc(item.price) + '</div>'
          + '<button type="button" class="btn btn-sm btn-outline-primary" data-co-add="' + i + '"' + (item.kind === 'P' && item.stock === 0 ? ' disabled' : '') + '>'
          + '<i class="bi bi-plus-lg"></i> ' + esc(T.add) + '</button></div>';
      }).join('');
    });
  }

  function addItem(item) {
    if (item.kind === 'P') {
      var same = state.lines.filter(function (l) { return l.kind === 'P' && l.variant === item.variant; })[0];
      if (same) { same.quantity += 1; } else { state.lines.push({ key: 'l' + (++state.seq), kind: 'P', variant: item.variant, name: item.name, label: item.label, quantity: 1 }); }
      changed();
      return;
    }
    openBooking(item);
  }

  // --- booking a time (A) or a session (G) ------------------------------------------------------------------------
  var modalEl = document.getElementById('co-book-modal');
  var bookForm = document.getElementById('co-book-form');
  function modal() { return window.bootstrap ? window.bootstrap.Modal.getOrCreateInstance(modalEl) : null; }

  function openBooking(item) {
    state.booking = { item: item, choice: null, staff: {} };
    $('#co-book-title', modalEl).textContent = fill(T['book-title'], item.name + (item.label ? ' · ' + item.label : ''));
    $('[data-co-book-date]', modalEl).classList.toggle('d-none', item.kind !== 'A');
    $('[data-co-book-seats]', modalEl).classList.toggle('d-none', item.kind !== 'G');
    $('[data-co-book-heading]', modalEl).textContent = item.kind === 'A' ? T.times : T.sessions;
    $('#co-book-seat-count', modalEl).value = 1;
    var day = $('#co-book-day', modalEl);
    if (!day.value) { var now = new Date(); day.value = new Date(now.getTime() - now.getTimezoneOffset() * 60000).toISOString().slice(0, 10); }
    renderNames(item.kind === 'A' ? item.people : 1);
    loadOptions();
    var m = modal();
    if (m) { m.show(); }
  }

  function loadOptions() {
    var b = state.booking;
    var box = $('[data-co-book-options]', modalEl);
    box.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    b.choice = null;
    $('[data-co-book-add]', modalEl).disabled = true;
    var what = b.item.kind === 'A' ? 'slots' : 'sessions';
    var body = { store_public_id: state.store, variant: b.item.variant };
    if (b.item.kind === 'A') { body.date = $('#co-book-day', modalEl).value; }
    api(what, body).then(function (res) {
      var data = res.json.data;
      if (!data) { box.innerHTML = '<span class="text-danger small">' + esc(res.json.message || T.failed) + '</span>'; return; }
      if (b.item.kind === 'A') {
        b.staff = {};
        (data.staff || []).forEach(function (p) { b.staff[p.public_id] = p.name; });
        b.options = data.slots || [];
        box.innerHTML = b.options.length ? b.options.map(function (s, i) {
          return '<button type="button" class="btn btn-sm btn-outline-secondary" data-co-option="' + i + '">' + esc(s.time + '–' + s.end_time)
            + '<span class="d-block small text-muted">' + esc(s.staff.map(function (id) { return b.staff[id] || ''; }).join(', ')) + '</span></button>';
        }).join('') : '<span class="text-muted small">' + esc(T['no-times']) + '</span>';
      } else {
        b.options = data.sessions || [];
        box.innerHTML = b.options.length ? b.options.map(function (s, i) {
          return '<button type="button" class="btn btn-sm btn-outline-secondary text-start" data-co-option="' + i + '">' + esc(s.name)
            + '<span class="d-block small text-muted">' + esc(when(s.starts_at) + ' · ' + fill(T['seats-left'], s.seats_available)) + '</span></button>';
        }).join('') : '<span class="text-muted small">' + esc(T['no-sessions']) + '</span>';
      }
    });
  }

  function renderNames(count, keep) {
    var box = $('[data-co-book-names]', modalEl);
    var old = keep ? Array.prototype.map.call(box.querySelectorAll('input'), function (i) { return i.value; }) : [];
    var html = '<label class="form-label">' + esc(T.names) + '</label>';
    for (var i = 0; i < count; i++) {
      html += '<input type="text" class="form-control form-control-sm mb-1" maxlength="100" placeholder="' + esc(fill(T['name-of'], i + 1)) + '" value="' + esc(old[i] || '') + '">';
    }
    box.innerHTML = html;
  }

  modalEl.addEventListener('click', function (e) {
    var button = e.target.closest('[data-co-option]');
    if (!button) { return; }
    modalEl.querySelectorAll('[data-co-option]').forEach(function (el) { el.classList.remove('active'); });
    button.classList.add('active');
    state.booking.choice = state.booking.options[Number(button.getAttribute('data-co-option'))];
    $('[data-co-book-add]', modalEl).disabled = false;
  });
  $('#co-book-day', modalEl).addEventListener('change', loadOptions);
  $('#co-book-seat-count', modalEl).addEventListener('input', function () {
    renderNames(Math.max(1, Math.min(99, Number(this.value) || 1)), true);
  });
  bookForm.addEventListener('submit', function (e) {
    e.preventDefault();
    var b = state.booking;
    if (!b || !b.choice) { return; }
    var names = Array.prototype.map.call(bookForm.querySelectorAll('[data-co-book-names] input'), function (i) { return i.value.trim(); });
    var line = { key: 'l' + (++state.seq), kind: b.item.kind, variant: b.item.variant, name: b.item.name, label: b.item.label, participants: names };
    if (b.item.kind === 'A') {
      line.quantity = 1;
      line.store = state.store;
      line.starts_at = b.choice.starts_at;
      line.staff = b.choice.staff;
      line.when = b.choice.starts_at.slice(0, 10) + ' ' + b.choice.time + '–' + b.choice.end_time + ' · ' + b.choice.staff.map(function (id) { return b.staff[id] || ''; }).join(', ');
    } else {
      line.quantity = names.length;
      line.session = b.choice.public_id;
      line.when = b.choice.name + ' · ' + when(b.choice.starts_at);
    }
    state.lines.push(line);
    var m = modal();
    if (m) { m.hide(); }
    changed();
  });

  // --- the lines ---------------------------------------------------------------------------------------------------
  function quoteLine(key) {
    return ((state.quote || {}).lines || []).filter(function (l) { return l.key === key; })[0] || null;
  }

  function renderLines() {
    var body = $('[data-co-lines]');
    if (!state.lines.length) {
      body.innerHTML = '<tr><td class="text-muted text-center py-4">' + esc(T.empty) + '</td></tr>';
      return;
    }
    body.innerHTML = state.lines.map(function (line, i) {
      var q = quoteLine(line.key);
      var problem = q && q.problem ? fill((T.problem[q.problem.code] || q.problem.code), q.problem.available) : '';
      var names = '';
      if (line.kind !== 'P') {
        names = '<div class="mt-1">' + line.participants.map(function (n, j) {
          return '<input type="text" class="form-control form-control-sm d-inline-block me-1 mb-1" style="width: 11rem;" maxlength="100" data-co-name="' + i + '" data-co-seat="' + j
            + '" value="' + esc(n) + '" placeholder="' + esc(fill(T['name-of'], j + 1)) + '">';
        }).join('') + '</div>';
      }
      return '<tr><td>'
        + '<div class="fw-semibold">' + esc(line.name) + (line.label ? ' <span class="text-muted fw-normal">' + esc(line.label) + '</span>' : '') + '</div>'
        + '<div class="small text-muted"><span class="badge bg-light text-dark border">' + esc(T.kind[line.kind]) + '</span> ' + esc(line.when || '') + '</div>'
        + (problem ? '<div class="small text-danger">' + esc(problem) + '</div>' : '') + names + '</td>'
        + '<td style="width: 6rem;">' + (line.kind === 'P'
          ? '<input type="number" class="form-control form-control-sm" min="1" max="99" value="' + line.quantity + '" data-co-qty="' + i + '">'
          : '<span class="text-muted">× ' + line.quantity + '</span>') + '</td>'
        + '<td class="text-end text-nowrap">' + (q ? esc(money(q.total)) : '') + '</td>'
        + '<td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger" data-co-remove="' + i + '" title="' + esc(T.remove) + '"><i class="bi bi-x-lg"></i></button></td></tr>';
    }).join('');
  }

  root.addEventListener('click', function (e) {
    var add = e.target.closest('[data-co-add]');
    if (add) { addItem(state.catalog[Number(add.getAttribute('data-co-add'))]); return; }
    var remove = e.target.closest('[data-co-remove]');
    if (remove) { state.lines.splice(Number(remove.getAttribute('data-co-remove')), 1); changed(); }
  });
  root.addEventListener('input', function (e) {
    var t = e.target;
    if (t.hasAttribute('data-co-qty')) {
      state.lines[Number(t.getAttribute('data-co-qty'))].quantity = Math.max(1, Math.min(99, Number(t.value) || 1));
      later('quote', quote, 400);
    } else if (t.hasAttribute('data-co-name')) {
      state.lines[Number(t.getAttribute('data-co-name'))].participants[Number(t.getAttribute('data-co-seat'))] = t.value.trim();
    } else if (t.hasAttribute('data-co-search')) {
      later('search', search, 300);
    }
  });
  $('[data-co-store]').addEventListener('change', function () {
    state.store = this.value;
    try { window.localStorage.setItem(STORE_KEY, state.store); } catch (err) { /* no storage */ }
    // a booking is at its store: the times found were this one's
    search();
    changed();
  });

  // --- the price ---------------------------------------------------------------------------------------------------
  function choices() {
    return {
      store_public_id: state.store,
      shipping: form.elements.shipping.value,
      pickup_store: form.elements.pickup_store.value,
      discount: form.elements.discount.value.trim(),
      payment: form.elements.payment.value,
      handed_over: form.elements.handed_over.checked
    };
  }
  function cartLines(withPeople) {
    return state.lines.map(function (l) {
      var out = { key: l.key, kind: l.kind, variant: l.variant, quantity: l.quantity };
      if (l.kind === 'A') { out.store = l.store; out.starts_at = l.starts_at; out.staff = l.staff; }
      if (l.kind === 'G') { out.session = l.session; }
      if (withPeople && l.kind !== 'P') { out.participants = l.participants; }
      return out;
    });
  }

  function changed() {
    renderLines();
    $('[data-co-delivery]').classList.toggle('d-none', !state.lines.some(function (l) { return l.kind === 'P'; }));
    quote();
  }

  function quote() {
    if (!state.lines.length || !state.store) {
      state.quote = null;
      renderQuote();
      return;
    }
    api('quote', Object.assign(choices(), { lines: cartLines(false) })).then(function (res) {
      if (!res.ok) { showErrors([res.json.message || T.failed]); return; }
      state.quote = res.json.data;
      renderQuote();
      renderLines();
    });
  }

  function options(select, list, placeholder) {
    var current = select.value;
    select.innerHTML = (placeholder ? '<option value="">' + esc(placeholder) + '</option>' : '') + list.map(function (o) {
      return '<option value="' + esc(o.value) + '"' + (o.disabled ? ' disabled' : '') + '>' + esc(o.label) + '</option>';
    }).join('');
    if (list.some(function (o) { return o.value === current && !o.disabled; })) { select.value = current; }
  }

  function renderQuote() {
    var q = state.quote;
    var totals = $('[data-co-totals]');
    $('[data-co-create]').disabled = !q || !state.lines.length || q.problems > 0;
    if (!q) { totals.innerHTML = ''; return; }
    var row = function (label, value, strong) {
      return '<dt class="col-8 col-md-10 fw-normal' + (strong ? ' fs-6 fw-bold' : '') + '">' + esc(label) + '</dt><dd class="col-4 col-md-2 mb-1' + (strong ? ' fs-6 fw-bold' : '') + '">' + esc(money(value)) + '</dd>';
    };
    var t = q.totals;
    totals.innerHTML = row(T.subtotal, t.subtotal) + (Number(t.discount) ? row(T.discount, '-' + t.discount) : '')
      + (q.needs_shipping ? row(T['shipping-fee'], t.shipping) : '') + (Number(t.tax_added) ? row(T['tax-added'], t.tax_added) : '') + row(T.total, t.total, true);

    // shipping, when the products are not handed over
    var shipping = $('[data-co-shipping]');
    shipping.classList.toggle('d-none', !q.needs_shipping);
    options(form.elements.shipping, (q.shipping_methods || []).map(function (m) {
      return { value: m.public_id, label: m.name + (m.fee !== null ? ' · ' + money(m.fee) : ''), disabled: !m.available };
    }), T.choose);
    var method = (q.shipping_methods || []).filter(function (m) { return m.public_id === form.elements.shipping.value; })[0];
    $('[data-co-address]').classList.toggle('d-none', !method || method.type !== 'H');
    $('[data-co-pickup-point]').classList.toggle('d-none', !method || method.type !== 'L');
    $('[data-co-pickup-store]').classList.toggle('d-none', !method || !method.pickup_stores);
    options(form.elements.pickup_store, ((method && method.pickup_stores) || []).map(function (s) { return { value: s.public_id, label: s.name }; }), T.choose);

    // payment: the first method unless one is chosen
    options(form.elements.payment, (q.payment_methods || []).map(function (m) { return { value: m.public_id, label: m.name }; }), null);
    $('[data-co-discount-error]').textContent = q.discount_error ? fill(T['discount-error'][q.discount_error.code] || q.discount_error.code, q.discount_error.amount) : '';
  }

  form.addEventListener('change', function (e) {
    if (/^(shipping|pickup_store|payment|handed_over)$/.test(e.target.name)) { quote(); }
  });
  form.elements.discount.addEventListener('input', function () { later('quote', quote, 500); });

  // --- the order ---------------------------------------------------------------------------------------------------
  function showErrors(messages) {
    var box = $('[data-co-errors]');
    box.classList.toggle('d-none', !messages.length);
    box.innerHTML = messages.map(function (m) { return '<div>' + esc(m) + '</div>'; }).join('');
    if (messages.length) { box.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
  }
  function errorTexts(errors) {
    var out = [];
    Object.keys(errors || {}).forEach(function (key) {
      var text = T.errors[key]
        || (/\.participants$/.test(key) && T.errors.participants)
        || (/^address\./.test(key) && T.errors.address)
        || (/^lines\./.test(key) && T.errors.problems)
        || key;
      if (out.indexOf(text) < 0) { out.push(text); }
    });
    return out;
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var button = $('[data-co-create]');
    button.disabled = true;
    showErrors([]);
    var f = form.elements;
    var body = Object.assign(choices(), {
      lines: cartLines(true),
      paid_now: f.paid_now.checked,
      customer: { first_name: f.first_name.value.trim(), last_name: f.last_name.value.trim(), email: f.email.value.trim(), telephone: f.telephone.value.trim() },
      delivery: { line_1: f.line_1.value.trim(), line_2: f.line_2.value.trim(), city: f.city.value.trim(), postal_code: f.postal_code.value.trim() },
      pickup_point: f.pickup_point.value.trim()
    });
    api('order', body).then(function (res) {
      if (res.status === 201 && res.json.data) {
        try {
          window.sessionStorage.setItem('sales-order-flash', res.json.message || '');
          // paid at the counter: the order's page offers the receipt
          if (body.paid_now) { window.sessionStorage.setItem('sales-order-print', '1'); }
        } catch (err) { /* no storage */ }
        window.location.href = cfg.urls.order + encodeURIComponent(res.json.data.public_id);
        return;
      }
      var data = res.json.data || {};
      if (data.quote) { state.quote = data.quote; renderQuote(); renderLines(); }
      var texts = errorTexts(res.json.errors);
      showErrors(texts.length ? texts : [res.json.message || T.failed]);
      button.disabled = false;
    }).catch(function () {
      showErrors([T.failed]);
      button.disabled = false;
    });
  });

  renderLines();
  loadStores();
})(<?= json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>);
