<?php
/**
 * Script of sales/order: saves the statuses, records a payment or a refund from the modal form and settles a pending
 * one, each through admin/sales/order/*; on success the page loads again and shows the message.
 *
 * @var string $publicId the order's
 * @var array  $amounts  ['payment' => balance due, 'refund' => what can be given back], the form's first amount
 */
$config = [
    'publicId' => $publicId,
    'amounts'  => $amounts,
    'urls'     => [
        'status'  => base_url('admin/sales/order/status'),
        'payment' => base_url('admin/sales/order/payment'),
        'settle'  => base_url('admin/sales/order/payment/settle'),
        'cancel'  => base_url('admin/sales/order/cancel'),
        'rescheduleSlots' => base_url('admin/sales/order/reschedule-slots'),
        'reschedule'      => base_url('admin/sales/order/reschedule'),
        'noShow'  => base_url('admin/sales/order/no-show'),
        'refundClose' => base_url('admin/sales/order/refund-close'),
    ],
    'text'     => [
        'failed'        => lang('Sales.order.save-failed'),
        'paymentTitle'  => lang('Sales.order.form.payment-title'),
        'refundTitle'   => lang('Sales.order.form.refund-title'),
        'confirmVoid'   => lang('Sales.order.confirm-void'),
        'failurePrompt' => lang('Sales.order.failure-prompt'),
        'noShowConfirm' => lang('Sales.order.no-show-confirm'),
        'rescheduleTitle' => lang('Sales.order.reschedule-title'),
        'noTimes'         => lang('Sales.new.no-times'),
        'refundClosePrompt' => lang('Sales.order.refund-close-prompt'),
    ],
    'csrf'     => ['header' => csrf_header(), 'hash' => csrf_hash()],
];
?>
(function (cfg) {
  'use strict';
  var csrfHash = cfg.csrf.hash;
  var FLASH = 'sales-order-flash';
  // set when a payment is recorded (or a counter order taken paid): the page loaded again offers the receipt
  var PRINT = 'sales-order-print';

  function notify(type, text) {
    if (!text) { return; }
    if (window.toastr) { window.toastr[type](text); } else if (type === 'error') { window.alert(text); }
  }

  function clearErrors(form) {
    form.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
    form.querySelectorAll('[data-error-for]').forEach(function (el) { el.textContent = ''; });
  }

  function showErrors(form, errors) {
    var shown = false;
    Object.keys(errors || {}).forEach(function (name) {
      var field = form.querySelector('[name="' + name + '"]');
      var box = form.querySelector('[data-error-for="' + name + '"]');
      if (field && box) {
        field.classList.add('is-invalid');
        box.textContent = errors[name];
        shown = true;
      }
    });
    return shown;
  }

  // posts JSON; on success keeps the message for the page loaded again
  function post(url, body, form, button) {
    var headers = { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    headers[cfg.csrf.header] = csrfHash;
    if (button) { button.disabled = true; }
    if (form) { clearErrors(form); }
    return fetch(url, { method: 'POST', headers: headers, credentials: 'same-origin', body: JSON.stringify(body) })
      .then(function (response) {
        return response.json().catch(function () { return {}; }).then(function (json) { return { ok: response.ok, json: json }; });
      })
      .then(function (result) {
        var json = result.json || {};
        if (json.csrf_hash) { csrfHash = json.csrf_hash; }
        if (!result.ok || !json.success) {
          try { window.sessionStorage.removeItem(PRINT); } catch (e) { /* no storage */ }
          if (json.redirect && json.status === 'error') { window.location.href = json.redirect; return; } // session expired
          var inForm = form && showErrors(form, json.errors);
          notify('error', inForm && json.message ? json.message : (json.message || cfg.text.failed));
          if (!inForm && json.errors) {
            Object.keys(json.errors).forEach(function (name) { notify('error', json.errors[name]); });
          }
          if (button) { button.disabled = false; }
          return;
        }
        try { window.sessionStorage.setItem(FLASH, json.message || ''); } catch (e) { /* no storage: no message */ }
        window.location.reload();
      })
      .catch(function () {
        notify('error', cfg.text.failed);
        if (button) { button.disabled = false; }
      });
  }

  function values(form) {
    var out = {};
    new FormData(form).forEach(function (value, name) { out[name] = String(value).trim(); });
    return out;
  }

  function init() {
    try {
      var flash = window.sessionStorage.getItem(FLASH);
      if (flash !== null) { window.sessionStorage.removeItem(FLASH); notify('success', flash); }
    } catch (e) { /* no storage */ }

    // the receipt: offered once the order is paid; the form opens it in a new tab
    var receiptModal = document.getElementById('sales-receipt-modal');
    var receiptForm = document.getElementById('sales-receipt-form');
    try {
      if (window.sessionStorage.getItem(PRINT) !== null) {
        window.sessionStorage.removeItem(PRINT);
        if (receiptModal && window.bootstrap) { window.bootstrap.Modal.getOrCreateInstance(receiptModal).show(); }
      }
    } catch (e) { /* no storage */ }
    if (receiptForm && receiptModal && window.bootstrap) {
      receiptForm.addEventListener('submit', function () {
        setTimeout(function () { window.bootstrap.Modal.getOrCreateInstance(receiptModal).hide(); }, 0);
      });
    }

    // the statuses
    var statusForm = document.getElementById('sales-status-form');
    if (statusForm) {
      statusForm.addEventListener('submit', function (e) {
        e.preventDefault();
        var body = values(statusForm);
        body.public_id = cfg.publicId;
        post(cfg.urls.status, body, statusForm, statusForm.querySelector('[type="submit"]'));
      });
    }

    // a payment or a refund
    var modalEl = document.getElementById('sales-payment-modal');
    var payForm = document.getElementById('sales-payment-form');
    var modal = modalEl && window.bootstrap ? window.bootstrap.Modal.getOrCreateInstance(modalEl) : null;
    var failureBox = payForm ? payForm.querySelector('[data-failure-only]') : null;
    function toggleFailure() {
      if (failureBox) { failureBox.classList.toggle('d-none', payForm.elements.transaction_status.value !== 'failed'); }
    }
    document.querySelectorAll('[data-payment-form]').forEach(function (button) {
      button.addEventListener('click', function () {
        if (!payForm || !modal) { return; }
        var type = button.getAttribute('data-payment-form');
        payForm.reset();
        clearErrors(payForm);
        payForm.elements.transaction_type.value = type;
        payForm.elements.amount.value = cfg.amounts[type] || '';
        document.getElementById('sales-payment-title').textContent = type === 'refund' ? cfg.text.refundTitle : cfg.text.paymentTitle;
        toggleFailure();
        modal.show();
      });
    });
    if (payForm) {
      payForm.elements.transaction_status.addEventListener('change', toggleFailure);
      payForm.addEventListener('submit', function (e) {
        e.preventDefault();
        var body = values(payForm);
        if (body.transaction_status !== 'failed') { delete body.failure_reason; }
        body.public_id = cfg.publicId;
        if (body.transaction_type === 'payment' && body.transaction_status !== 'failed' && body.transaction_status !== 'pending') {
          try { window.sessionStorage.setItem(PRINT, '1'); } catch (err) { /* no storage */ }
        }
        post(cfg.urls.payment, body, payForm, payForm.querySelector('[type="submit"]'));
      });
    }

    // moving an appointment: the free times of a day, its own aside, then the one picked
    var moveModal = document.getElementById('sales-reschedule-modal');
    var moveForm = document.getElementById('sales-reschedule-form');
    var move = null;
    function esc(s) {
      return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; });
    }
    function loadMoveTimes() {
      var box = moveForm.querySelector('[data-reschedule-options]');
      var save = moveForm.querySelector('[data-reschedule-save]');
      box.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
      move.choice = null;
      save.disabled = true;
      var headers = { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
      headers[cfg.csrf.header] = csrfHash;
      fetch(cfg.urls.rescheduleSlots, { method: 'POST', headers: headers, credentials: 'same-origin',
        body: JSON.stringify({ public_id: cfg.publicId, line_number: move.line, date: document.getElementById('reschedule-date').value }) })
        .then(function (r) { return r.json().catch(function () { return {}; }); })
        .then(function (json) {
          if (json.csrf_hash) { csrfHash = json.csrf_hash; }
          if (!json.success) { box.innerHTML = '<span class="text-danger small">' + esc(json.message || cfg.text.failed) + '</span>'; return; }
          var names = {};
          (json.data.staff || []).forEach(function (p) { names[p.public_id] = p.name; });
          move.slots = json.data.slots || [];
          box.innerHTML = move.slots.length ? move.slots.map(function (s, i) {
            return '<button type="button" class="btn btn-sm btn-outline-secondary" data-move-option="' + i + '">' + esc(s.time + '–' + s.end_time)
              + '<span class="d-block small text-muted">' + esc(s.staff.map(function (id) { return names[id] || ''; }).join(', ')) + '</span></button>';
          }).join('') : '<span class="text-muted small">' + esc(cfg.text.noTimes) + '</span>';
        })
        .catch(function () { box.innerHTML = '<span class="text-danger small">' + esc(cfg.text.failed) + '</span>'; });
    }
    document.querySelectorAll('[data-reschedule]').forEach(function (button) {
      button.addEventListener('click', function () {
        if (!moveModal || !window.bootstrap) { return; }
        move = { line: Number(button.getAttribute('data-reschedule')), choice: null, slots: [] };
        document.getElementById('sales-reschedule-title').textContent = cfg.text.rescheduleTitle.replace('{0}', button.getAttribute('data-reschedule-name'));
        document.getElementById('reschedule-date').value = button.getAttribute('data-reschedule-date');
        loadMoveTimes();
        window.bootstrap.Modal.getOrCreateInstance(moveModal).show();
      });
    });
    if (moveForm) {
      document.getElementById('reschedule-date').addEventListener('change', loadMoveTimes);
      moveForm.addEventListener('click', function (e) {
        var option = e.target.closest('[data-move-option]');
        if (!option) { return; }
        moveForm.querySelectorAll('[data-move-option]').forEach(function (el) { el.classList.remove('active'); });
        option.classList.add('active');
        move.choice = move.slots[Number(option.getAttribute('data-move-option'))];
        moveForm.querySelector('[data-reschedule-save]').disabled = false;
      });
      moveForm.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!move || !move.choice) { return; }
        post(cfg.urls.reschedule, { public_id: cfg.publicId, line_number: move.line, starts_at: move.choice.starts_at, staff: move.choice.staff },
          null, moveForm.querySelector('[data-reschedule-save]'));
      });
    }

    // cancelling: the reason is required, the products go back to stock unless unticked
    var cancelForm = document.getElementById('sales-cancel-form');
    if (cancelForm) {
      cancelForm.addEventListener('submit', function (e) {
        e.preventDefault();
        var restock = cancelForm.elements.restock;
        post(cfg.urls.cancel, {
          public_id: cfg.publicId,
          reason: cancelForm.elements.reason.value.trim(),
          restock: restock ? (restock.checked ? '1' : '0') : '1'
        }, cancelForm, cancelForm.querySelector('[type="submit"]'));
      });
    }

    var noShowButton = document.querySelector('[data-no-show]');
    if (noShowButton) {
      noShowButton.addEventListener('click', function () {
        if (!window.confirm(cfg.text.noShowConfirm)) { return; }
        post(cfg.urls.noShow, { public_id: cfg.publicId }, null, noShowButton);
      });
    }

    var closeButton = document.querySelector('[data-refund-close]');
    if (closeButton) {
      closeButton.addEventListener('click', function () {
        var reason = window.prompt(cfg.text.refundClosePrompt, '');
        if (reason === null) { return; }
        post(cfg.urls.refundClose, { public_id: cfg.publicId, reason: reason.trim() }, null, closeButton);
      });
    }

    // a pending one settled
    document.querySelectorAll('[data-settle]').forEach(function (button) {
      button.addEventListener('click', function () {
        var status = button.getAttribute('data-settle');
        var body = { public_id: button.getAttribute('data-payment-id'), transaction_status: status };
        if (status === 'voided' && !window.confirm(cfg.text.confirmVoid)) { return; }
        if (status === 'failed') {
          var reason = window.prompt(cfg.text.failurePrompt, '');
          if (reason === null) { return; }
          if (reason.trim() !== '') { body.failure_reason = reason.trim(); }
        }
        post(cfg.urls.settle, body, null, button);
      });
    });
  }

  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})(<?= json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>);
