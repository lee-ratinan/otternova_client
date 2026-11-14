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
    ],
    'text'     => [
        'failed'        => lang('Sales.order.save-failed'),
        'paymentTitle'  => lang('Sales.order.form.payment-title'),
        'refundTitle'   => lang('Sales.order.form.refund-title'),
        'confirmVoid'   => lang('Sales.order.confirm-void'),
        'failurePrompt' => lang('Sales.order.failure-prompt'),
    ],
    'csrf'     => ['header' => csrf_header(), 'hash' => csrf_hash()],
];
?>
(function (cfg) {
  'use strict';
  var csrfHash = cfg.csrf.hash;
  var FLASH = 'sales-order-flash';

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
        post(cfg.urls.payment, body, payForm, payForm.querySelector('[type="submit"]'));
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
