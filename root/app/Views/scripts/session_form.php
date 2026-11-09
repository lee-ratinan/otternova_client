<?php
/**
 * customScript of the session form (see generic_form.php): the slots editor (sessions/slots_editor), one row per slot
 * with its date and times in the store's timezone and the people and resources it holds, kept as JSON in the hidden
 * field the form submits. A saved slot cannot be removed, only cancelled; a new one can. Picking another service or
 * store (a new session only) loads who and what a slot may hold there, and the service's capacity.
 *
 * @var string $formId     the session form's DOM id
 * @var string $slotsField the hidden field of the slots
 * @var array  $editor     variants, staff, resources, needs, slots (see ServiceSessions::form())
 * @var array  $urls       options
 */
$config = [
    'formId'     => $formId,
    'slotsField' => $slotsField,
    'editor'     => $editor,
    'urls'       => $urls,
    'csrf'       => ['header' => csrf_header()],
    'labels'     => [
        'column'      => lang('ServiceSessions.slots.column'),
        'statuses'    => lang('ServiceSessions.enum.slot_status'),
        'new'         => lang('ServiceSessions.slots.new'),
        'remove'      => lang('ServiceSessions.slots.remove'),
        'pickFirst'   => lang('ServiceSessions.slots.pick-first'),
        'needs'       => lang('ServiceSessions.slots.needs'),
        'noStaff'     => lang('ServiceSessions.slots.no-staff'),
        'noResources' => lang('ServiceSessions.slots.no-resources'),
        'people'      => lang('ServiceSessions.slots.people'),
        'failed'      => lang('ServiceSessions.form.options-failed'),
    ],
];
?>
(function (cfg) {
  'use strict';
  var L = cfg.labels;
  var form = document.getElementById(cfg.formId);
  var root = form ? form.querySelector('[data-slots-editor]') : null;
  if (!root) { return; }
  var ed = cfg.editor;
  var hidden = root.querySelector('input[name="' + cfg.slotsField + '"]');
  var variantSelect = form.querySelector('select[name="variant_public_id"]');
  var storeSelect = form.querySelector('select[name="store_public_id"]');
  var capacityInput = form.querySelector('input[name="capacity"]');
  var rows = [];

  function esc(s) {
    return String(s === null || s === undefined ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function fmt(text, args) {
    return String(text).replace(/\{(\d+)\}/g, function (m, i) { return args[i] !== undefined ? args[i] : m; });
  }
  function pad(n) { return (n < 10 ? '0' : '') + n; }
  // dates and times are the store's local ones, as strings: no Date timezone conversion anywhere
  function addDays(date, days) {
    var d = new Date(date + 'T00:00:00Z');
    d.setUTCDate(d.getUTCDate() + days);
    return d.getUTCFullYear() + '-' + pad(d.getUTCMonth() + 1) + '-' + pad(d.getUTCDate());
  }
  function addMinutes(time, minutes) {
    var parts = time.split(':');
    var total = (Number(parts[0]) * 60 + Number(parts[1]) + minutes) % 1440;
    return pad(Math.floor(total / 60)) + ':' + pad(total % 60);
  }
  function variant() {
    var id = variantSelect ? variantSelect.value : '';
    return (ed.variants || []).filter(function (v) { return v.public_id === id; })[0] || null;
  }
  function placed() { return !!(variantSelect && variantSelect.value && storeSelect && storeSelect.value); }

  function set(list) { var out = {}; (list || []).forEach(function (k) { out[k] = true; }); return out; }
  function keys(obj) { return Object.keys(obj).filter(function (k) { return obj[k]; }); }

  // the slots as saved, by when they start
  function start() {
    rows = (ed.slots || []).map(function (s) {
      var starts = s.local_starts_at || '';
      var ends = s.local_ends_at || '';
      return {
        slot_number: s.slot_number, slot_status: s.slot_status || 'S',
        date: starts.slice(0, 10), start: starts.slice(11, 16), end: ends.slice(11, 16),
        staff: set((s.staff || []).map(function (p) { return p.uid; })),
        resources: set((s.resources || []).map(function (r) { return r.public_id; }))
      };
    });
  }
  function blank() {
    return { slot_number: null, slot_status: 'S', date: '', start: '', end: '', staff: {}, resources: {} };
  }
  // the next slot: a week after the last, at the same times, holding the same
  function after(last, weeks) {
    var row = blank();
    if (!last) { return row; }
    row.date = last.date ? addDays(last.date, 7 * (weeks || 1)) : '';
    row.start = last.start;
    row.end = last.end;
    row.staff = Object.assign({}, last.staff);
    row.resources = Object.assign({}, last.resources);
    return row;
  }

  function renderNeeds() {
    var box = root.querySelector('[data-slots-needs]');
    var v = variant();
    if (!placed() || !v) { box.textContent = L.pickFirst; return; }
    var parts = (ed.needs || []).map(function (n) { return n.type_name + ' × ' + n.quantity; });
    box.textContent = fmt(L.needs, [v.staff_required || 1, parts.length ? parts.join(', ') : '—']);
  }

  function checks(i, kind, options, selected) {
    if (!options.length) {
      return '<span class="text-muted small">' + esc(kind === 'staff' ? L.noStaff : L.noResources) + '</span>';
    }
    return options.map(function (o) {
      var id = kind === 'staff' ? o.uid : o.public_id;
      var label = kind === 'staff' ? o.full_name : o.resource_name + ' (' + o.type_name + ', ' + fmt(L.people, [o.capacity]) + ')';
      var inputId = 'slot-' + i + '-' + kind + '-' + id;
      return '<div class="form-check mb-0"><input class="form-check-input" type="checkbox" id="' + esc(inputId) + '" data-row="' + i + '" data-'
        + kind + '="' + esc(id) + '"' + (selected[id] ? ' checked' : '') + '><label class="form-check-label small text-nowrap" for="' + esc(inputId) + '">'
        + esc(label) + '</label></div>';
    }).join('');
  }

  function render() {
    var C = L.column;
    var head = '<thead><tr><th>#</th><th>' + esc(C.date) + '</th><th>' + esc(C.starts_at) + '</th><th>' + esc(C.ends_at) + '</th><th>'
      + esc(C.staff) + '</th><th>' + esc(C.resources) + '</th><th>' + esc(C.slot_status) + '</th></tr></thead>';
    var staff = ed.staff || [];
    var resources = ed.resources || [];
    var body = rows.map(function (r, i) {
      var cancelled = r.slot_status === 'X';
      var html = '<tr class="' + (cancelled ? 'text-muted' : '') + '"><td class="text-nowrap">' + (i + 1)
        + (r.slot_number ? '' : ' <span class="badge bg-info">' + esc(L.new) + '</span>') + '</td>';
      html += '<td><input type="date" class="form-control form-control-sm" style="min-width: 9rem;" data-row="' + i + '" data-key="date" value="' + esc(r.date) + '" aria-label="' + esc(C.date) + '"></td>';
      html += '<td><input type="time" class="form-control form-control-sm" style="min-width: 6rem;" data-row="' + i + '" data-key="start" value="' + esc(r.start) + '" aria-label="' + esc(C.starts_at) + '"></td>';
      html += '<td><input type="time" class="form-control form-control-sm" style="min-width: 6rem;" data-row="' + i + '" data-key="end" value="' + esc(r.end) + '" aria-label="' + esc(C.ends_at) + '"></td>';
      html += '<td>' + (cancelled ? '' : checks(i, 'staff', staff, r.staff)) + '</td>';
      html += '<td>' + (cancelled ? '' : checks(i, 'resources', resources, r.resources)) + '</td>';
      if (r.slot_number) {
        html += '<td><select class="form-select form-select-sm" style="min-width: 7rem;" data-row="' + i + '" data-key="slot_status" aria-label="' + esc(C.slot_status) + '">'
          + ['S', 'X'].map(function (s) { return '<option value="' + s + '"' + (r.slot_status === s ? ' selected' : '') + '>' + esc(L.statuses[s]) + '</option>'; }).join('')
          + '</select></td>';
      } else {
        html += '<td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger" data-remove="' + i + '" title="' + esc(L.remove) + '" aria-label="'
          + esc(L.remove) + '"><i class="bi bi-x-lg"></i></button></td>';
      }
      return html + '</tr>';
    }).join('');
    root.querySelector('[data-slots-table]').innerHTML = head + '<tbody>' + body + '</tbody>';
    var ready = placed();
    ['[data-slots-add]', '[data-slots-repeat]', '[data-slots-copy]'].forEach(function (sel) { root.querySelector(sel).disabled = !ready; });
    root.querySelector('[data-slots-copy]').disabled = !ready || rows.length < 2;
    renderNeeds();
    write();
  }

  // the slots as the API takes them; an end not after the start is on the next day
  function write() {
    hidden.value = JSON.stringify(rows.map(function (r) {
      var slot = {
        slot_status: r.slot_status,
        starts_at: r.date && r.start ? r.date + 'T' + r.start : '',
        ends_at: r.date && r.end ? (r.end <= r.start ? addDays(r.date, 1) : r.date) + 'T' + r.end : '',
        staff: keys(r.staff),
        resources: keys(r.resources)
      };
      if (r.slot_number) { slot.slot_number = r.slot_number; }
      return slot;
    }));
  }

  function onEdit(e) {
    var t = e.target;
    var r = rows[Number(t.getAttribute('data-row'))];
    if (!r) { return; }
    if (t.hasAttribute('data-staff')) { r.staff[t.getAttribute('data-staff')] = t.checked; }
    else if (t.hasAttribute('data-resources')) { r.resources[t.getAttribute('data-resources')] = t.checked; }
    else if (t.hasAttribute('data-key')) {
      var key = t.getAttribute('data-key');
      r[key] = t.value;
      // a start with no end yet ends after the service's duration
      var v = variant();
      if (key === 'start' && r.start && !r.end && v && v.duration_minutes) {
        r.end = addMinutes(r.start, v.duration_minutes);
        var end = root.querySelector('[data-row="' + t.getAttribute('data-row') + '"][data-key="end"]');
        if (end) { end.value = r.end; }
      }
      if (key === 'slot_status' && e.type === 'change') { render(); return; }
    }
    write();
  }
  root.addEventListener('input', onEdit);
  root.addEventListener('change', onEdit);
  root.addEventListener('click', function (e) {
    var remove = e.target.closest('[data-remove]');
    if (remove) {
      rows.splice(Number(remove.getAttribute('data-remove')), 1);
      render();
      return;
    }
    var last = rows.filter(function (r) { return r.slot_status !== 'X'; }).slice(-1)[0];
    if (e.target.closest('[data-slots-add]')) {
      rows.push(after(last, 1));
      render();
    } else if (e.target.closest('[data-slots-repeat]')) {
      var count = Math.max(1, Math.min(52, Number(root.querySelector('[data-slots-repeat-count]').value) || 1));
      if (!last) { rows.push(blank()); last = rows[0]; count--; }
      for (var w = 1; w <= count; w++) { rows.push(after(last, w)); }
      render();
    } else if (e.target.closest('[data-slots-copy]')) {
      // the people and resources of the first slot, to every other scheduled one
      var first = rows.filter(function (r) { return r.slot_status !== 'X'; })[0];
      rows.forEach(function (r) {
        if (r !== first && r.slot_status !== 'X') { r.staff = Object.assign({}, first.staff); r.resources = Object.assign({}, first.resources); }
      });
      render();
    }
  });

  // a new session: who and what may be held depends on the service and the store picked
  async function reload() {
    var v = variant();
    if (v && capacityInput && (capacityInput.value === '' || capacityInput.getAttribute('data-from-variant') === capacityInput.value)) {
      capacityInput.value = v.capacity || '';
      capacityInput.setAttribute('data-from-variant', capacityInput.value);
      capacityInput.dispatchEvent(new Event('input', { bubbles: true }));
    }
    if (!placed()) { ed.staff = []; ed.resources = []; ed.needs = []; render(); return; }
    var body = new FormData();
    body.append('variant_public_id', variantSelect.value);
    body.append('store_public_id', storeSelect.value);
    var csrf = form.querySelector('[data-fb-csrf]');
    var headers = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    if (csrf) { headers[cfg.csrf.header] = csrf.value; }
    var data = {};
    try {
      var res = await fetch(cfg.urls.options, { method: 'POST', headers: headers, body: body, credentials: 'same-origin' });
      data = await res.json();
    } catch (e) { data = {}; }
    if (data.csrf_hash && csrf) { csrf.value = data.csrf_hash; }
    if (!data.success) {
      if (window.toastr) { window.toastr.error(esc(data.message || L.failed)); }
      return;
    }
    ed.staff = data.staff || [];
    ed.resources = data.resources || [];
    ed.needs = data.needs || [];
    // what is no longer offered is no longer picked
    var staffIds = set(ed.staff.map(function (p) { return p.uid; }));
    var resourceIds = set(ed.resources.map(function (r) { return r.public_id; }));
    rows.forEach(function (r) {
      keys(r.staff).forEach(function (k) { if (!staffIds[k]) { delete r.staff[k]; } });
      keys(r.resources).forEach(function (k) { if (!resourceIds[k]) { delete r.resources[k]; } });
    });
    render();
  }
  [variantSelect, storeSelect].forEach(function (select) {
    if (select && !select.disabled) { select.addEventListener('change', reload); }
  });

  // Cancel resets the form's inputs: the slots go back to the saved ones too
  form.addEventListener('reset', function () { setTimeout(function () { start(); render(); }, 0); });

  start();
  if (!rows.length) { rows.push(blank()); }
  render();
})(<?= json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>);
