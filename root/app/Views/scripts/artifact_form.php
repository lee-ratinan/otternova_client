<?php
/**
 * customScript of the artifact form (see generic_form.php):
 * - the tax rate shows only when tax is included in the price or added on top
 * - the variants editor (artifacts/variants_editor): the options the variants differ by, one row per variant with
 *   its price and, for a product, its stock per store, or, for a service, how it is booked (an appointment, or a
 *   group whose sessions take up to its capacity of customers) and the staff it needs, kept as JSON in the hidden field the form submits. Saved
 *   variants cannot be removed (the API never deletes one; it is taken off sale instead). Add Variant stops at the
 *   plan's cap of variants per artifact.
 * - the images card (artifacts/images): upload, delete and reorder, each posted at once; Upload stops at the plan's
 *   cap of images per artifact
 *
 * @var string     $formId        the artifact form's DOM id
 * @var string     $variantsField the hidden field of the variants
 * @var array      $editor        type, variants, attributes, stores, labels, max, planName (see Artifacts::form())
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
        'options'       => lang('Artifacts.variants.options'),
        'optionsHelp'   => lang('Artifacts.variants.options-help'),
        'noAttributes'  => lang('Artifacts.variants.no-attributes'),
        'choose'        => lang('Artifacts.variants.choose'),
        'new'           => lang('Artifacts.variants.new'),
        'remove'        => lang('Artifacts.variants.remove'),
        'count'         => lang('Artifacts.variants.count'),
        'limitReached'  => lang('Artifacts.variants.limit-reached'),
        'needsOption'   => lang('Artifacts.variants.needs-option'),
        'stock'         => lang('Artifacts.variants.stock'),
        'stockHelp'     => lang('Artifacts.variants.stock-help'),
        'column'        => lang('Artifacts.variants.column'),
        'statuses'      => lang('Artifacts.enum.variant_status'),
        'bookingTypes'  => lang('Artifacts.enum.booking_type'),
        'capacityHelp'  => lang('Artifacts.variants.capacity-help'),
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
        booking_type: 'A', staff_required: '1', capacity: '', values: {}, stock: {} };
    }
    function text(v) { return v === null || v === undefined ? '' : String(v); }
    // the variants as saved, and the options they use
    function start() {
      used = {};
      rows = (ed.variants || []).map(function (v) {
        var values = {};
        (v.attribute_values || []).forEach(function (x) { values[x.attribute_public_id] = x.value_public_id; used[x.attribute_public_id] = true; });
        var stock = {};
        (v.inventory || []).forEach(function (s) { stock[s.store_public_id] = text(s.quantity); });
        return {
          public_id: v.public_id, sku: text(v.sku), barcode: text(v.barcode), price_active: text(v.price_active), price_compare: text(v.price_compare),
          variant_status: v.variant_status || 'A', weight_grams: text(v.weight_grams), duration_minutes: text(v.duration_minutes),
          booking_type: v.booking_type || 'A', staff_required: text(v.staff_required || 1), capacity: text(v.capacity), values: values, stock: stock
        };
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
        + '<th>' + esc(L.column[product ? 'weight_grams' : 'duration_minutes']) + '</th>'
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
                        : field(i, 'duration_minutes', r.duration_minutes, 'number', ' min="1" step="1" style="min-width: 5rem;"');
        if (!product) {
          var group = r.booking_type === 'G';
          html += '<td><select class="form-select form-select-sm" style="min-width: 7rem;" data-row="' + i + '" data-key="booking_type" aria-label="' + esc(L.column.booking_type) + '">'
            + ['A', 'G'].map(function (b) { return '<option value="' + b + '"' + (r.booking_type === b ? ' selected' : '') + '>' + esc(L.bookingTypes[b]) + '</option>'; }).join('')
            + '</select></td>';
          html += field(i, 'staff_required', r.staff_required, 'number', ' min="1" max="255" step="1" required style="min-width: 4.5rem;"');
          // only a group has a capacity; an appointment is one customer's
          html += field(i, 'capacity', group ? r.capacity : '', 'number', ' min="1" max="65535" step="1" style="min-width: 5rem;"'
            + (group ? ' required' : ' disabled') + ' title="' + esc(L.capacityHelp) + '"');
        }
        html += '<td><select class="form-select form-select-sm" style="min-width: 6rem;" data-row="' + i + '" data-key="variant_status" aria-label="' + esc(L.column.variant_status) + '">'
          + ['A', 'I'].map(function (s) { return '<option value="' + s + '"' + (r.variant_status === s ? ' selected' : '') + '>' + esc(L.statuses[s]) + '</option>'; }).join('')
          + '</select></td>';
        html += '<td class="text-end">' + (r.public_id ? '' : '<button type="button" class="btn btn-sm btn-outline-danger" data-remove="' + i + '" title="' + esc(L.remove) + '" aria-label="' + esc(L.remove) + '"'
          + (rows.length === 1 ? ' disabled' : '') + '><i class="bi bi-x-lg"></i></button>') + '</td></tr>';
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
      renderCount();
      write();
    }

    // Add Variant stops at the plan's cap (one over it after moving to a smaller plan keeps its variants), and
    // needs an option to tell the variants apart: with none, every variant would have the same (empty) choices
    function renderCount() {
      var full = rows.length >= ed.max;
      var alike = rows.length >= 1 && !usedAttributes().length;
      var count = root.querySelector('[data-variants-count]');
      root.querySelector('[data-variants-add]').disabled = full || alike;
      count.textContent = full ? fmt(L.limitReached, [ed.max, ed.planName])
        : fmt(L.count, [rows.length, ed.max, ed.planName]) + (alike && attributes.length ? ' ' + L.needsOption : '');
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
          v.capacity = r.booking_type === 'G' ? r.capacity : '';
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
        // the capacity is for a group only
        if (t.getAttribute('data-key') === 'booking_type' && e.type === 'change') { renderTable(); return; }
      }
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
        rows.push(blank());
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
