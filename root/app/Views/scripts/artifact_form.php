<?php
/**
 * customScript of the artifact form (see generic_form.php):
 * - the tax rate shows only when tax is included in the price or added on top
 * - the variants editor (artifacts/variants_editor): the options the variants differ by, one row per variant with
 *   its price and, for a product, its stock per store, or, for a service, how it is booked (an appointment, or a
 *   group whose sessions take up to its capacity of customers), the staff it needs and its booking window (the
 *   business's, or one of its own), kept as JSON in the hidden field the form submits. Saved
 *   variants cannot be removed (the API never deletes one; it is taken off sale instead). Add Variant stops at the
 *   plan's cap of variants per artifact.
 * - the images card (artifacts/images): upload, delete and reorder, each posted at once; Upload stops at the plan's
 *   cap of images per artifact
 *
 * @var string     $formId        the artifact form's DOM id
 * @var string     $variantsField the hidden field of the variants
 * @var array      $editor        type, variants, attributes, stores, labels, window, windowRule, max, planName (see Artifacts::form())
 * @var string     $taxNone       tax_calculation of no tax
 * @var array|null $images        artifact, images, max, planName, urls; null for a new artifact
 */
$config = [
    'formId'        => $formId,
    'variantsField' => $variantsField,
    'editor'        => $editor,
    'taxNone'       => $taxNone,
    'images'        => $images,
    'csrf'          => ['header' => csrf_header()],
    'labels'        => [
        'help'          => lang('Artifacts.variants.help'),
        'helpService'   => lang('Artifacts.variants.help-service'),
        'duplicate'     => lang('Artifacts.variants.duplicate'),
        'options'       => lang('Artifacts.variants.options'),
        'optionsHelp'   => lang('Artifacts.variants.options-help'),
        'noAttributes'  => lang('Artifacts.variants.no-attributes'),
        'choose'        => lang('Artifacts.variants.choose'),
        'new'           => lang('Artifacts.variants.new'),
        'remove'        => lang('Artifacts.variants.remove'),
        'count'         => lang('Artifacts.variants.count'),
        'limitReached'  => lang('Artifacts.variants.limit-reached'),
        'needsOption'   => lang('Artifacts.variants.needs-option'),
        'needsAttributes' => lang('Artifacts.variants.needs-attributes'),
        'stock'         => lang('Artifacts.variants.stock'),
        'stockHelp'     => lang('Artifacts.variants.stock-help'),
        'column'        => lang('Artifacts.variants.column'),
        'statuses'      => lang('Artifacts.enum.variant_status'),
        'bookingTypes'  => lang('Artifacts.enum.booking_type'),
        'capacityHelp'  => lang('Artifacts.variants.capacity-help'),
        'peopleHelp'    => lang('Artifacts.variants.people-help'),
        'hours'         => lang('Artifacts.variants.hours'),
        'minutes'       => lang('Artifacts.variants.minutes-short'),
        'durationInvalid' => lang('Artifacts.variants.duration-invalid'),
        'window'        => lang('Artifacts.variants.window'),
        'windowDefault' => lang('Artifacts.variants.window-default', [$editor['window'] ?? '']),
        'windowOwn'     => lang('Artifacts.variants.window-own'),
        'windowHelp'    => lang('Artifacts.variants.window-help'),
        'windowAhead'   => lang('Artifacts.variants.window-ahead'),
        'windowNotice'  => lang('Artifacts.variants.window-notice'),
        'windowBy'      => lang('Artifacts.variants.window-by'),
        'windowUnits'   => lang('Artifacts.variants.window-units'),
        'cover'         => lang('Artifacts.images.cover'),
        'moveEarlier'   => lang('Artifacts.images.move-earlier'),
        'moveLater'     => lang('Artifacts.images.move-later'),
        'delete'        => lang('Artifacts.images.delete'),
        'keep'          => lang('Artifacts.images.keep'),
        'confirmDelete' => lang('Artifacts.images.confirm-delete'),
        'choosImage'    => lang('Artifacts.images.choose'),
        'imagesCount'   => lang('Artifacts.images.count'),
        'imagesLimit'   => lang('Artifacts.images.limit-reached'),
        'failed'        => lang('Artifacts.images.failed'),
    ],
];
?>
(function (cfg) {
  'use strict';
  var L = cfg.labels;
  var form = document.getElementById(cfg.formId);
  if (!form) { return; }

  function esc(s) {
    return String(s === null || s === undefined ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function fmt(text, args) {
    return String(text).replace(/\{(\d+)\}/g, function (m, i) { return args[i] !== undefined ? args[i] : m; });
  }
  function notify(type, text) {
    if (window.toastr) { window.toastr[type](esc(text)); } else if (type === 'error') { window.alert(text); }
  }
  // the form's CSRF input, which the form builder keeps fresh too
  function csrfInput() { return form.querySelector('[data-fb-csrf]'); }
  function setCsrf(hash) { var input = csrfInput(); if (input && hash) { input.value = hash; } }

  async function post(url, body, asJson) {
    var headers = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    if (csrfInput()) { headers[cfg.csrf.header] = csrfInput().value; }
    if (asJson) { headers['Content-Type'] = 'application/json'; body = JSON.stringify(body); }
    var res = await fetch(url, { method: 'POST', headers: headers, body: body, credentials: 'same-origin' });
    var data = {};
    try { data = await res.json(); } catch (e) { data = {}; }
    if (data && data.csrf_hash) { setCsrf(data.csrf_hash); }
    return { ok: res.ok && data.success !== false, data: data || {} };
  }

  // ---- the tax rate, for tax included or added on top only ----------------------------
  var tax = form.querySelector('select[name="tax_calculation"]');
  function syncTax() {
    var row = form.querySelector('[data-fb-pane="edit"] [data-fb-row="tax_percentage"]');
    if (tax && row) { row.classList.toggle('d-none', tax.value === '' || tax.value === cfg.taxNone); }
  }
  if (tax) { tax.addEventListener('change', syncTax); syncTax(); }

  // ---- the variants editor -------------------------------------------------------------
  var ed = cfg.editor;
  var root = form.querySelector('[data-variants-editor]');
  if (root) { initEditor(); }

  function initEditor() {
    var hidden = root.querySelector('input[name="' + cfg.variantsField + '"]');
    var typeSelect = form.querySelector('select[name="artifact_type"]');
    var attributes = ed.attributes || [];
    var stores = ed.stores || [];
    var used, rows;

    function blank() {
      return { public_id: '', sku: '', barcode: '', price_active: '', price_compare: '', variant_status: 'A', weight_grams: '', duration_minutes: '',
        duration_h: '', duration_m: '', booking_type: 'A', staff_required: '1', capacity: '', values: {}, stock: {},
        own_window: false, win_horizon: '', win_unit: 'M', win_value: '', win_time: '' };
    }
    function text(v) { return v === null || v === undefined ? '' : String(v); }
    // a service's length is typed as hours and minutes, and kept (and saved) as minutes
    function splitMinutes(r) {
      var m = r.duration_minutes === '' ? NaN : Number(r.duration_minutes);
      r.duration_h = isNaN(m) ? '' : String(Math.floor(m / 60));
      r.duration_m = isNaN(m) ? '' : String(m % 60);
      return r;
    }
    function joinMinutes(r) {
      var h = r.duration_h === '' ? 0 : Number(r.duration_h);
      var m = r.duration_m === '' ? 0 : Number(r.duration_m);
      r.duration_minutes = r.duration_h === '' && r.duration_m === '' ? '' : String(Math.round(h * 60 + m));
    }
    // the variants as saved, and the options they use
    function start() {
      used = {};
      rows = (ed.variants || []).map(function (v) {
        var values = {};
        (v.attribute_values || []).forEach(function (x) { values[x.attribute_public_id] = x.value_public_id; used[x.attribute_public_id] = true; });
        var stock = {};
        (v.inventory || []).forEach(function (s) { stock[s.store_public_id] = text(s.quantity); });
        return splitMinutes({
          public_id: v.public_id, sku: text(v.sku), barcode: text(v.barcode), price_active: text(v.price_active), price_compare: text(v.price_compare),
          variant_status: v.variant_status || 'A', weight_grams: text(v.weight_grams), duration_minutes: text(v.duration_minutes),
          booking_type: v.booking_type || 'A', staff_required: text(v.staff_required || 1), capacity: text(v.capacity), values: values, stock: stock,
          // its own booking window, or none (null) for the business's
          own_window: v.booking_horizon_days !== null && v.booking_horizon_days !== undefined, win_horizon: text(v.booking_horizon_days),
          win_unit: v.booking_notice_unit || 'M', win_value: text(v.booking_notice_value), win_time: text(v.booking_notice_time).slice(0, 5)
        });
      });
      if (!rows.length) { rows.push(blank()); }
    }
    function type() { return ed.type || (typeSelect ? typeSelect.value : '') || 'P'; }
    function usedAttributes() { return attributes.filter(function (a) { return used[a.public_id]; }); }

    function renderOptions() {
      var box = root.querySelector('[data-variants-options]');
      if (!attributes.length) { box.innerHTML = '<div class="form-text mt-0">' + esc(L.noAttributes) + '</div>'; return; }
      box.innerHTML = '<div class="fw-semibold mb-1">' + esc(L.options) + '</div>' + attributes.map(function (a, i) {
        return '<div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" id="variant-option-' + i + '" data-variant-attr="' + esc(a.public_id) + '"'
          + (used[a.public_id] ? ' checked' : '') + '><label class="form-check-label" for="variant-option-' + i + '">' + esc(a.name) + '</label></div>';
      }).join('') + '<div class="form-text mt-0">' + esc(L.optionsHelp) + '</div>';
    }

    function field(i, key, value, type, attrs) {
      return '<td><input type="' + type + '" class="form-control form-control-sm" data-row="' + i + '" data-key="' + key + '" value="' + esc(value) + '"'
        + (attrs || '') + ' aria-label="' + esc(L.column[key] || key) + '"></td>';
    }

    function renderTable() {
      var product = type() === 'P';
      var columns = usedAttributes();
      // #, options, sku, barcode, price, compare, weight, status, remove; a service has duration, booking type, staff
      // and capacity in place of barcode and weight
      var width = (product ? 8 : 10) + columns.length;
      var head = '<thead><tr><th>#</th>' + columns.map(function (a) { return '<th>' + esc(a.name) + '</th>'; }).join('')
        + '<th>' + esc(L.column.sku) + '</th>' + (product ? '<th>' + esc(L.column.barcode) + '</th>' : '')
        + '<th>' + esc(L.column.price_active) + ' <span class="text-danger">*</span></th><th>' + esc(L.column.price_compare) + '</th>'
        + '<th>' + esc(L.column[product ? 'weight_grams' : 'duration_minutes']) + (product ? '' : ' <span class="text-danger">*</span>') + '</th>'
        + (product ? '' : '<th>' + esc(L.column.booking_type) + '</th><th>' + esc(L.column.staff_required) + '</th>'
          + '<th title="' + esc(L.capacityHelp) + '">' + esc(L.column.capacity) + '</th>')
        + '<th>' + esc(L.column.variant_status) + '</th><th></th></tr></thead>';
      var body = rows.map(function (r, i) {
        var html = '<tr><td class="text-muted text-nowrap">' + (i + 1) + (r.public_id ? '' : ' <span class="badge bg-info">' + esc(L.new) + '</span>') + '</td>';
        columns.forEach(function (a) {
          html += '<td><select class="form-select form-select-sm" style="min-width: 7rem;" data-row="' + i + '" data-attr="' + esc(a.public_id) + '" aria-label="' + esc(a.name) + '">'
            + '<option value="">' + esc(L.choose) + '</option>' + a.values.map(function (v) {
              return '<option value="' + esc(v.public_id) + '"' + (r.values[a.public_id] === v.public_id ? ' selected' : '') + '>' + esc(v.name) + '</option>';
            }).join('') + '</select></td>';
        });
        html += field(i, 'sku', r.sku, 'text', ' maxlength="64" style="min-width: 7rem;"');
        if (product) { html += field(i, 'barcode', r.barcode, 'text', ' maxlength="64" style="min-width: 7rem;"'); }
        html += field(i, 'price_active', r.price_active, 'number', ' min="0" step="0.01" required style="min-width: 6rem;"');
        html += field(i, 'price_compare', r.price_compare, 'number', ' min="0" step="0.01" style="min-width: 6rem;"');
        html += product ? field(i, 'weight_grams', r.weight_grams, 'number', ' min="0" step="1" style="min-width: 5rem;"')
                        : '<td><div class="input-group input-group-sm flex-nowrap" style="min-width: 9rem;">'
                          + '<input type="number" class="form-control" min="0" max="1092" step="1" data-row="' + i + '" data-key="duration_h" value="' + esc(r.duration_h)
                          + '" placeholder="' + esc(L.hours) + '" aria-label="' + esc(L.column.duration_minutes + ' (' + L.hours + ')') + '">'
                          + '<span class="input-group-text">:</span>'
                          + '<input type="number" class="form-control" min="0" max="59" step="1" data-row="' + i + '" data-key="duration_m" value="' + esc(r.duration_m)
                          + '" placeholder="' + esc(L.minutes) + '" aria-label="' + esc(L.column.duration_minutes + ' (' + L.minutes + ')') + '">'
                          + '</div></td>';
        if (!product) {
          var group = r.booking_type === 'G';
          html += '<td><select class="form-select form-select-sm" style="min-width: 7rem;" data-row="' + i + '" data-key="booking_type" aria-label="' + esc(L.column.booking_type) + '">'
            + ['A', 'G'].map(function (b) { return '<option value="' + b + '"' + (r.booking_type === b ? ' selected' : '') + '>' + esc(L.bookingTypes[b]) + '</option>'; }).join('')
            + '</select></td>';
          html += field(i, 'staff_required', r.staff_required, 'number', ' min="1" max="255" step="1" required style="min-width: 4.5rem;"');
          // a group: the most customers in one session (required); an appointment: the customers one booking is
          // for, 1 when left empty (2 for a couples massage)
          html += field(i, 'capacity', r.capacity, 'number', ' min="1" max="' + (group ? 65535 : 255) + '" step="1" style="min-width: 5rem;"'
            + (group ? ' required' : ' placeholder="1"') + ' title="' + esc(group ? L.capacityHelp : L.peopleHelp) + '"');
        }
        html += '<td><select class="form-select form-select-sm" style="min-width: 6rem;" data-row="' + i + '" data-key="variant_status" aria-label="' + esc(L.column.variant_status) + '">'
          + ['A', 'I'].map(function (s) { return '<option value="' + s + '"' + (r.variant_status === s ? ' selected' : '') + '>' + esc(L.statuses[s]) + '</option>'; }).join('')
          + '</select></td>';
        html += '<td class="text-end">' + (r.public_id ? '' : '<button type="button" class="btn btn-sm btn-outline-danger" data-remove="' + i + '" title="' + esc(L.remove) + '" aria-label="' + esc(L.remove) + '"'
          + (rows.length === 1 ? ' disabled' : '') + '><i class="bi bi-x-lg"></i></button>') + '</td></tr>';
        if (!product) { html += windowRow(r, i, width); }
        if (product && stores.length) {
          html += '<tr><td></td><td colspan="' + (width - 1) + '" class="pt-0 border-top-0"><div class="d-flex flex-wrap align-items-center gap-2 small">'
            + '<span class="text-muted" title="' + esc(L.stockHelp) + '">' + esc(L.stock) + '</span>' + stores.map(function (s) {
              return '<label class="d-inline-flex align-items-center gap-1">' + esc(s.name) + ' <input type="number" step="1" class="form-control form-control-sm" style="width: 6rem;" data-row="' + i
                + '" data-store="' + esc(s.public_id) + '" value="' + esc(r.stock[s.public_id] || '') + '"></label>';
            }).join('') + '</div></td></tr>';
        }
        return html;
      }).join('');
      root.querySelector('[data-variants-table]').innerHTML = head + '<tbody>' + body + '</tbody>';
      root.querySelector('[data-variants-help]').textContent = product ? L.help : L.helpService;
      renderCount();
      markDuplicates();
      write();
    }

    // a service variant's booking window, under its row: the business's, or one of its own (how many days ahead,
    // and the notice in minutes, or in days by a time of day)
    function windowRow(r, i, width) {
      // kept within the first columns' width, so its fields wrap where they can be seen rather than off to the right
      var html = '<tr><td></td><td colspan="' + (width - 1) + '" class="pt-0 border-top-0"><div class="d-flex flex-wrap align-items-center gap-2 small" style="max-width: 34rem;">'
        + '<span class="text-muted" title="' + esc(L.windowHelp) + '">' + esc(L.window) + '</span>'
        + '<select class="form-select form-select-sm w-auto mw-100 text-truncate" data-row="' + i + '" data-key="own_window" aria-label="' + esc(L.window) + '" title="' + esc(r.own_window ? L.windowOwn : L.windowDefault) + '">'
        + '<option value=""' + (r.own_window ? '' : ' selected') + '>' + esc(L.windowDefault) + '</option>'
        + '<option value="1"' + (r.own_window ? ' selected' : '') + '>' + esc(L.windowOwn) + '</option></select>';
      if (r.own_window) {
        html += '<label class="d-inline-flex align-items-center gap-1">' + esc(L.windowAhead)
          + ' <input type="number" class="form-control form-control-sm" style="width: 5rem;" min="1" max="365" step="1" required data-row="' + i + '" data-key="win_horizon" value="' + esc(r.win_horizon) + '"></label>'
          + '<label class="d-inline-flex align-items-center gap-1">' + esc(L.windowNotice)
          + ' <input type="number" class="form-control form-control-sm" style="width: 5.5rem;" min="0" max="' + (r.win_unit === 'D' ? 365 : 65535) + '" step="1" required data-row="' + i + '" data-key="win_value" value="' + esc(r.win_value) + '"></label>'
          + '<select class="form-select form-select-sm w-auto" data-row="' + i + '" data-key="win_unit" aria-label="' + esc(L.windowNotice) + '">'
          + ['M', 'D'].map(function (u) { return '<option value="' + u + '"' + (r.win_unit === u ? ' selected' : '') + '>' + esc(L.windowUnits[u]) + '</option>'; }).join('')
          + '</select>';
        if (r.win_unit === 'D') {
          html += '<label class="d-inline-flex align-items-center gap-1">' + esc(L.windowBy)
            + ' <input type="time" class="form-control form-control-sm" style="width: 7.5rem;" required data-row="' + i + '" data-key="win_time" value="' + esc(r.win_time) + '"></label>';
        }
      }
      return html + '</div></td></tr>';
    }

    // a service's variants differ by their length and booking type as well as their options: flag the rows that
    // repeat another (the API refuses them too), once their length is filled in
    function markDuplicates() {
      var service = type() !== 'P';
      var columns = usedAttributes();
      var seen = {};
      rows.forEach(function (r, i) {
        var input = root.querySelector('input[data-row="' + i + '"][data-key="duration_h"]');
        if (!input) { return; }
        // a length is required: at least a minute, at most what the API holds
        var length = r.duration_minutes === '' ? 0 : Number(r.duration_minutes);
        var bad = service && !(length >= 1 && length <= 65535);
        var key = JSON.stringify([columns.map(function (a) { return r.values[a.public_id] || ''; }), length, r.booking_type]);
        var taken = service && !bad && seen[key];
        seen[key] = true;
        var message = bad ? L.durationInvalid : (taken ? L.duplicate : '');
        // an empty length is not flagged in red until the form is sent
        input.classList.toggle('is-invalid', !!message && r.duration_minutes !== '');
        input.setCustomValidity(message);
        input.title = message;
      });
    }

    // Add Variant stops at the plan's cap (one over it after moving to a smaller plan keeps its variants), and, for
    // a product, needs an option to tell the variants apart: with none, every variant would have the same (empty)
    // choices. A service's variants can differ by their length and booking type instead.
    function renderCount() {
      var full = rows.length >= ed.max;
      var alike = type() === 'P' && rows.length >= 1 && !usedAttributes().length;
      var count = root.querySelector('[data-variants-count]');
      root.querySelector('[data-variants-add]').disabled = full || alike;
      count.textContent = full ? fmt(L.limitReached, [ed.max, ed.planName])
        : fmt(L.count, [rows.length, ed.max, ed.planName]) + (alike ? ' ' + (attributes.length ? L.needsOption : L.needsAttributes) : '');
      count.classList.toggle('text-warning', full);
    }

    // the variants as the API takes them: a store left empty is left out
    function write() {
      var product = type() === 'P';
      var columns = usedAttributes();
      hidden.value = JSON.stringify(rows.map(function (r) {
        var v = {
          sku: r.sku, price_active: r.price_active, price_compare: r.price_compare, variant_status: r.variant_status,
          attribute_values: columns.map(function (a) { return r.values[a.public_id] || ''; }).filter(Boolean)
        };
        if (r.public_id) { v.public_id = r.public_id; }
        if (product) {
          v.barcode = r.barcode;
          v.weight_grams = r.weight_grams;
          v.inventory = stores.map(function (s) { return { store_public_id: s.public_id, quantity: r.stock[s.public_id] || '' }; })
            .filter(function (s) { return s.quantity !== ''; });
        } else {
          v.duration_minutes = r.duration_minutes;
          v.booking_type = r.booking_type;
          v.staff_required = r.staff_required;
          v.capacity = r.capacity;
          // all four, or all null for the business's
          v.booking_horizon_days = r.own_window ? r.win_horizon : null;
          v.booking_notice_unit = r.own_window ? r.win_unit : null;
          v.booking_notice_value = r.own_window ? r.win_value : null;
          v.booking_notice_time = r.own_window && r.win_unit === 'D' ? r.win_time : null;
        }
        return v;
      }));
    }

    function onEdit(e) {
      var t = e.target;
      if (t.hasAttribute('data-variant-attr')) {
        if (e.type === 'change') { used[t.getAttribute('data-variant-attr')] = t.checked; renderTable(); }
        return;
      }
      var r = rows[Number(t.getAttribute('data-row'))];
      if (!t.hasAttribute('data-row') || !r) { return; }
      if (t.hasAttribute('data-attr')) { r.values[t.getAttribute('data-attr')] = t.value; }
      else if (t.hasAttribute('data-store')) { r.stock[t.getAttribute('data-store')] = t.value.trim(); }
      else if (t.hasAttribute('data-key')) {
        r[t.getAttribute('data-key')] = t.value.trim();
        if (/^duration_[hm]$/.test(t.getAttribute('data-key'))) { joinMinutes(r); }
        if (t.getAttribute('data-key') === 'own_window') {
          r.own_window = t.value !== '';
          // a window of its own starts from the business's
          if (r.own_window && r.win_horizon === '') {
            var b = ed.windowRule || {};
            r.win_horizon = text(b.booking_horizon_days);
            r.win_unit = b.booking_notice_unit || 'M';
            r.win_value = text(b.booking_notice_value);
            r.win_time = text(b.booking_notice_time).slice(0, 5);
          }
        }
        // the capacity means another thing for a group; the window's fields follow its choices
        if (/^(booking_type|own_window|win_unit)$/.test(t.getAttribute('data-key')) && e.type === 'change') { renderTable(); return; }
      }
      markDuplicates();
      write();
    }
    root.addEventListener('input', onEdit);
    root.addEventListener('change', onEdit);
    root.addEventListener('click', function (e) {
      var remove = e.target.closest('[data-remove]');
      if (remove) {
        rows.splice(Number(remove.getAttribute('data-remove')), 1);
        if (!rows.length) { rows.push(blank()); }
        renderTable();
        return;
      }
      if (e.target.closest('[data-variants-add]') && !root.querySelector('[data-variants-add]').disabled) {
        var row = blank();
        var last = rows[rows.length - 1];
        // a new service variant: the booking of the last one, its length left to fill in
        if (type() !== 'P' && last) {
          row.booking_type = last.booking_type;
          row.staff_required = last.staff_required;
          row.capacity = last.capacity;
          ['own_window', 'win_horizon', 'win_unit', 'win_value', 'win_time'].forEach(function (k) { row[k] = last[k]; });
        }
        rows.push(row);
        renderTable();
      }
    });
    if (typeSelect && !ed.type) { typeSelect.addEventListener('change', renderTable); }
    // Cancel resets the form's inputs: the variants go back to the saved ones too
    form.addEventListener('reset', function () {
      setTimeout(function () { start(); renderOptions(); renderTable(); syncTax(); }, 0);
    });

    start();
    renderOptions();
    renderTable();
  }

  // ---- the images card -----------------------------------------------------------------
  var im = cfg.images;
  var card = document.getElementById('artifact-images-card');
  if (im && card) { initImages(); }

  function initImages() {
    var images = (im.images || []).slice();
    var grid = card.querySelector('[data-images-grid]');
    var empty = card.querySelector('[data-images-empty]');
    var upload = card.querySelector('[data-images-upload]');
    var count = card.querySelector('[data-images-count]');
    var busy = false;

    function render() {
      grid.innerHTML = images.map(function (img, i) {
        var variant = img.variant_public_id ? (ed.labels[img.variant_public_id] || '') : '';
        return '<div class="col-6 col-sm-4 col-md-3 col-xl-2"><div class="border rounded-2 p-2 h-100 d-flex flex-column">'
          + '<div class="ratio ratio-1x1 mb-2"><img src="' + esc(img.url) + '" alt="" class="rounded-2" style="object-fit: cover;" loading="lazy"></div>'
          + (i === 0 ? '<span class="badge bg-primary align-self-start mb-1">' + esc(L.cover) + '</span>' : '')
          + (variant ? '<div class="small text-muted text-truncate mb-1" title="' + esc(variant) + '">' + esc(variant) + '</div>' : '')
          + '<div class="mt-auto d-flex gap-1">'
          + '<button type="button" class="btn btn-sm btn-outline-secondary" data-image-move="-1" data-index="' + i + '" title="' + esc(L.moveEarlier) + '" aria-label="' + esc(L.moveEarlier) + '"' + (i === 0 || busy ? ' disabled' : '') + '><i class="bi bi-arrow-left"></i></button>'
          + '<button type="button" class="btn btn-sm btn-outline-secondary" data-image-move="1" data-index="' + i + '" title="' + esc(L.moveLater) + '" aria-label="' + esc(L.moveLater) + '"' + (i === images.length - 1 || busy ? ' disabled' : '') + '><i class="bi bi-arrow-right"></i></button>'
          + '<button type="button" class="btn btn-sm btn-outline-danger ms-auto" data-image-delete="' + i + '" title="' + esc(L.delete) + '" aria-label="' + esc(L.delete) + '"' + (busy ? ' disabled' : '') + '><i class="bi bi-trash"></i></button>'
          + '</div></div></div>';
      }).join('');
      empty.classList.toggle('d-none', images.length > 0);
      // Upload stops at the plan's cap; one over it (after moving to a smaller plan) keeps its images
      var full = images.length >= im.max;
      upload.querySelectorAll('input, select, button').forEach(function (el) { el.disabled = full || busy; });
      count.textContent = full ? fmt(L.imagesLimit, [im.max, im.planName]) : fmt(L.imagesCount, [images.length, im.max, im.planName]);
      count.classList.toggle('text-warning', full);
    }
    function setBusy(on) { busy = on; render(); }

    upload.addEventListener('submit', async function (e) {
      e.preventDefault();
      var input = upload.querySelector('input[type="file"]');
      if (busy) { return; }
      if (!input.files || !input.files[0]) { notify('error', L.choosImage); return; }
      var body = new FormData();
      body.set('artifact_public_id', im.artifact);
      body.set('variant_public_id', upload.querySelector('select').value);
      body.set('image', input.files[0], input.files[0].name);
      setBusy(true);
      try {
        var r = await post(im.urls.upload, body, false);
        if (!r.ok) { notify('error', r.data.message || L.failed); return; }
        images.push(r.data.image);
        upload.reset();
        notify('success', r.data.message);
      } catch (err) {
        notify('error', L.failed);
      } finally {
        setBusy(false);
      }
    });

    async function confirmDelete() {
      if (window.Swal && typeof window.Swal.fire === 'function') {
        var res = await window.Swal.fire({
          title: L.confirmDelete, icon: 'warning', showCancelButton: true,
          customClass: { title: 'card-title', confirmButton: 'btn btn-danger', cancelButton: 'btn btn-outline-secondary' },
          confirmButtonText: L.delete, cancelButtonText: L.keep
        });
        return res.isConfirmed;
      }
      return window.confirm(L.confirmDelete);
    }

    grid.addEventListener('click', async function (e) {
      if (busy) { return; }
      var del = e.target.closest('[data-image-delete]');
      var move = e.target.closest('[data-image-move]');
      if (del) {
        var image = images[Number(del.getAttribute('data-image-delete'))];
        if (!image || !(await confirmDelete())) { return; }
        setBusy(true);
        try {
          var r = await post(im.urls.delete, { public_id: image.public_id }, true);
          if (!r.ok) { notify('error', r.data.message || L.failed); return; }
          images = images.filter(function (x) { return x.public_id !== image.public_id; });
          notify('success', r.data.message);
        } catch (err) {
          notify('error', L.failed);
        } finally {
          setBusy(false);
        }
      } else if (move) {
        var from = Number(move.getAttribute('data-index'));
        var to = from + Number(move.getAttribute('data-image-move'));
        if (to < 0 || to >= images.length) { return; }
        var before = images.slice();
        images.splice(to, 0, images.splice(from, 1)[0]);
        setBusy(true);
        try {
          var res = await post(im.urls.reorder, { artifact_public_id: im.artifact, order: images.map(function (x) { return x.public_id; }) }, true);
          if (!res.ok) { images = before; notify('error', res.data.message || L.failed); return; }
          if (res.data.images && res.data.images.length) { images = res.data.images; }
        } catch (err) {
          images = before;
          notify('error', L.failed);
        } finally {
          setBusy(false);
        }
      }
    });

    render();
  }
})(<?= json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>);
