<?php
/**
 * customScript of the store hours page (StoreHours::index()):
 * - the openings editors (stores/openings_editor): the weekly one has a row for each day, Monday first; the special
 *   one a single row, shown only while the special-hours form says the store opens. Each opening is a pair of time
 *   inputs, marked "next day" when it closes at or before it opens. Every change goes into the editor's hidden field
 *   as JSON, as the API takes the openings; Cancel brings back the saved ones.
 * - the special hours table (stores/hours_exceptions): Edit loads a date into the special-hours form, Delete removes
 *   it after asking, and the page reloads.
 * A person's working hours page (Staff::hours()) uses it too, with $stores: each shift then has the store it is at,
 * sent as store_public_id, and the other options below say what differs.
 *
 * @var string $publicId    business_store.public_id
 * @var array  $weekly      [{day_of_week, open_time, close_time}], as v1/client/store/hours sends them
 * @var array  $exceptions  [{exception_date, exception_reason, closed, hours}]
 * @var int    $maxOpenings the most openings a day may have
 * @var array  $fields      ['weekly' => hidden field, 'special' => hidden field]
 * @var array  $kinds       ['closed' => ..., 'open' => ...], the values of the exception_kind radios
 * @var string $deleteUrl
 * Optional, for other pages:
 * @var array  $stores      [['public_id' => ..., 'label' => ...]]: a store dropdown on each opening
 * @var array  $identity    what the delete request names the page by (default ['public_id' => $publicId])
 * @var array  $forms       the forms' ids (default weekly-hours-form, special-hours-form)
 * @var array  $labels      labels in place of the defaults below
 * @var array  $fill        more fields Edit fills from an exception, field name => its key (e.g. exception_type)
 */
$days = [];
foreach (range(1, 7) as $day) {
    $days[] = ['day' => $day, 'key' => (string) $day, 'label' => lang('StoreHours.days.' . $day)];
}
$config = [
    'publicId'    => $publicId,
    'weekly'      => $weekly,
    'exceptions'  => $exceptions,
    'maxOpenings' => $maxOpenings,
    'fields'      => $fields,
    'kinds'       => $kinds,
    'deleteUrl'   => $deleteUrl,
    'days'        => $days,
    'stores'      => $stores ?? [],
    'identity'    => $identity ?? ['public_id' => $publicId],
    'fill'        => $fill ?? [],
    'forms'       => ($forms ?? []) + ['weekly' => 'weekly-hours-form', 'special' => 'special-hours-form'],
    'labels'      => ($labels ?? []) + [
        'closed'        => lang('StoreHours.editor.closed'),
        'add'           => lang('StoreHours.editor.add'),
        'opens'         => lang('StoreHours.editor.opens'),
        'closes'        => lang('StoreHours.editor.closes'),
        'nextDay'       => lang('StoreHours.editor.next-day'),
        'remove'        => lang('StoreHours.editor.remove'),
        'confirmDelete' => lang('StoreHours.list.confirm-delete'),
        'delete'        => lang('StoreHours.list.delete'),
        'cancel'        => lang('Form.button.cancel'),
        'failed'        => lang('StoreHours.save-failed'),
        'store'         => lang('StoreHours.editor.store'),
    ],
    'csrf'        => ['header' => csrf_header(), 'hash' => csrf_hash()],
];
?>
(function (cfg) {
  'use strict';
  var csrfHash = cfg.csrf.hash;

  function notify(type, text) {
    if (window.toastr) { window.toastr[type](text); } else if (type === 'error') { window.alert(text); }
  }
  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  // a closing time at or before the opening time is on the next day
  function nextDay(open, close) { return !!open && !!close && close <= open; }

  /**
   * An openings editor
   * @param root  the [data-openings-editor] element
   * @param days  [{key, label, day}]: one row each; label '' for a row without one; day goes out as day_of_week
   */
  function Editor(root, days) {
    var hidden = root.querySelector('input[type="hidden"]');
    var box = root.querySelector('[data-openings-days]');
    var state = {};

    function storeSelect(o) {
      if (!cfg.stores.length) { return ''; }
      return '<select class="form-select form-select-sm" style="width: auto; max-width: 14rem" data-store aria-label="' + esc(cfg.labels.store) + '">'
        + cfg.stores.map(function (st) {
          return '<option value="' + esc(st.public_id) + '"' + (st.public_id === o.store ? ' selected' : '') + '>' + esc(st.label) + '</option>';
        }).join('') + '</select>';
    }

    function slot(i, o) {
      return '<div class="d-flex flex-wrap align-items-center gap-2 mb-2" data-slot="' + i + '">'
        + storeSelect(o)
        + '<input type="time" step="60" class="form-control form-control-sm" style="width: 8.5rem" data-open aria-label="' + esc(cfg.labels.opens) + '" value="' + esc(o.open) + '">'
        + '<span aria-hidden="true">–</span>'
        + '<input type="time" step="60" class="form-control form-control-sm" style="width: 8.5rem" data-close aria-label="' + esc(cfg.labels.closes) + '" value="' + esc(o.close) + '">'
        + '<span class="badge text-bg-info" data-next-day' + (nextDay(o.open, o.close) ? '' : ' hidden') + '>' + esc(cfg.labels.nextDay) + '</span>'
        + '<button type="button" class="btn btn-sm btn-outline-danger" data-remove title="' + esc(cfg.labels.remove) + '" aria-label="' + esc(cfg.labels.remove) + '"><i class="bi bi-x-lg"></i></button>'
        + '</div>';
    }

    function render() {
      box.innerHTML = days.map(function (d) {
        var list = state[d.key];
        var body = list.map(function (o, i) { return slot(i, o); }).join('')
          + (list.length ? '' : '<div class="text-muted small mb-1">' + esc(cfg.labels.closed) + '</div>')
          + '<button type="button" class="btn btn-sm btn-link px-0" data-add' + (list.length >= cfg.maxOpenings ? ' disabled' : '') + '>'
          + '<i class="bi bi-plus-circle"></i> ' + esc(cfg.labels.add) + '</button>';
        return '<div class="row g-2 py-2' + (d.label ? ' border-bottom' : '') + '" data-day="' + esc(d.key) + '">'
          + (d.label ? '<div class="col-sm-3 col-md-2 fw-semibold pt-1">' + esc(d.label) + '</div><div class="col-sm-9 col-md-10">' : '<div class="col-12">')
          + body + '</div></div>';
      }).join('');
      sync();
    }

    function sync() {
      var out = [];
      days.forEach(function (d) {
        state[d.key].forEach(function (o) {
          var row = d.day ? { day_of_week: d.day } : {};
          if (cfg.stores.length) { row.store_public_id = o.store; }
          row.open_time = o.open;
          row.close_time = o.close;
          out.push(row);
        });
      });
      hidden.value = JSON.stringify(out);
    }

    /** @param byKey key => [{open_time, close_time}] */
    function load(byKey) {
      state = {};
      days.forEach(function (d) {
        state[d.key] = (byKey[d.key] || []).map(function (o) { return { open: o.open_time || '', close: o.close_time || '', store: o.store_public_id || firstStore() }; });
      });
      render();
    }

    function onEdit(e) {
      var row = e.target.closest('[data-slot]');
      var day = e.target.closest('[data-day]');
      if (!row || !day) { return; }
      var o = state[day.getAttribute('data-day')][+row.getAttribute('data-slot')];
      o.open = row.querySelector('[data-open]').value;
      o.close = row.querySelector('[data-close]').value;
      var store = row.querySelector('[data-store]');
      if (store) { o.store = store.value; }
      row.querySelector('[data-next-day]').hidden = !nextDay(o.open, o.close);
      sync();
    }
    box.addEventListener('input', onEdit);
    box.addEventListener('change', onEdit);
    box.addEventListener('click', function (e) {
      var day = e.target.closest('[data-day]');
      if (!day) { return; }
      var list = state[day.getAttribute('data-day')];
      if (e.target.closest('[data-add]')) {
        if (list.length >= cfg.maxOpenings) { return; }
        // a new shift is at the store of the one before it, the first store otherwise
        list.push({ open: '', close: '', store: list.length ? list[list.length - 1].store : firstStore() });
        render();
        var inputs = box.querySelectorAll('[data-day="' + CSS.escape(day.getAttribute('data-day')) + '"] [data-open]');
        if (inputs.length) { inputs[inputs.length - 1].focus(); }
      } else if (e.target.closest('[data-remove]')) {
        list.splice(+e.target.closest('[data-slot]').getAttribute('data-slot'), 1);
        render();
      }
    });

    return { load: load, count: function (key) { return state[key].length; }, add: function (key) { state[key].push({ open: '', close: '', store: firstStore() }); render(); } };
  }

  function firstStore() { return cfg.stores.length ? cfg.stores[0].public_id : ''; }

  function init() {
    // ---- the weekly hours ----------------------------------------------------------------
    var weeklyForm = document.getElementById(cfg.forms.weekly);
    var weeklyRoot = weeklyForm && weeklyForm.querySelector('[data-openings-editor="weekly"]');
    if (weeklyRoot) {
      var weekly = Editor(weeklyRoot, cfg.days);
      var saved = {};
      cfg.weekly.forEach(function (o) { (saved[o.day_of_week] = saved[o.day_of_week] || []).push(o); });
      weekly.load(saved);
      weeklyForm.addEventListener('reset', function () { setTimeout(function () { weekly.load(saved); }, 0); });
    }

    // ---- the special hours ---------------------------------------------------------------
    var specialForm = document.getElementById(cfg.forms.special);
    var specialRoot = specialForm && specialForm.querySelector('[data-openings-editor="special"]');
    if (!specialRoot) { return; }
    var special = Editor(specialRoot, [{ key: 'x', label: '', day: null }]);
    var openings = specialForm.querySelector('[data-special-openings]');
    special.load({});

    function kind() {
      var checked = specialForm.querySelector('input[name="exception_kind"]:checked');
      return checked ? checked.value : cfg.kinds.closed;
    }
    function syncKind() {
      var open = kind() === cfg.kinds.open;
      openings.hidden = !open;
      if (open && special.count('x') === 0) { special.add('x'); }
    }
    specialForm.addEventListener('change', function (e) { if (e.target.name === 'exception_kind') { syncKind(); } });
    specialForm.addEventListener('reset', function () { setTimeout(function () { special.load({}); syncKind(); }, 0); });
    syncKind();

    function setValue(name, value) {
      var input = specialForm.querySelector('[name="' + name + '"]');
      if (!input) { return; }
      input.value = value;
      input.dispatchEvent(new Event('input', { bubbles: true }));
      input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    var table = document.querySelector('[data-hours-exceptions]');
    if (!table) { return; }
    table.addEventListener('click', function (e) {
      var edit = e.target.closest('[data-exception-edit]');
      var del = e.target.closest('[data-exception-delete]');
      if (edit) {
        var date = edit.getAttribute('data-exception-edit');
        var exception = cfg.exceptions.filter(function (x) { return x.exception_date === date; })[0];
        if (!exception) { return; }
        setValue('exception_date', exception.exception_date);
        setValue('exception_reason', exception.exception_reason || '');
        Object.keys(cfg.fill).forEach(function (name) { setValue(name, exception[cfg.fill[name]] || ''); });
        specialForm.querySelectorAll('input[name="exception_kind"]').forEach(function (radio) {
          radio.checked = radio.value === (exception.closed ? cfg.kinds.closed : cfg.kinds.open);
        });
        special.load({ x: exception.hours });
        syncKind();
        specialForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
      } else if (del && !del.disabled) {
        confirmDelete().then(function (yes) { if (yes) { remove(del); } });
      }
    });
  }

  function confirmDelete() {
    if (window.Swal) {
      return window.Swal.fire({ text: cfg.labels.confirmDelete, icon: 'warning', showCancelButton: true,
        confirmButtonText: cfg.labels['delete'], cancelButtonText: cfg.labels.cancel }).then(function (r) { return !!r.isConfirmed; });
    }
    return Promise.resolve(window.confirm(cfg.labels.confirmDelete));
  }

  function remove(button) {
    button.disabled = true;
    var headers = { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    headers[cfg.csrf.header] = csrfHash;
    fetch(cfg.deleteUrl, {
      method: 'POST', headers: headers, credentials: 'same-origin',
      body: JSON.stringify(Object.assign({ exception_date: button.getAttribute('data-exception-delete') }, cfg.identity))
    })
      .then(function (response) {
        return response.json().catch(function () { return {}; }).then(function (json) { return { ok: response.ok, json: json }; });
      })
      .then(function (result) {
        var json = result.json || {};
        if (json.csrf_hash) { csrfHash = json.csrf_hash; }
        if (!result.ok || !json.success) {
          notify('error', json.message || cfg.labels.failed);
          button.disabled = false;
          return;
        }
        notify('success', json.message);
        setTimeout(function () { window.location.reload(); }, 600);
      })
      .catch(function () { notify('error', cfg.labels.failed); button.disabled = false; });
  }

  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})(<?= json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>);
