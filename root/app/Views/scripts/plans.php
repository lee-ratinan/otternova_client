<?php
/**
 * The script of admin_plans.php: the monthly/yearly switch of the plan cards, the checkout modal (listed discount
 * codes, subscribe), the discount and payment forms of the invoice waiting for payment, and the buttons that cancel,
 * resume or change the plan (asked first). Every change reloads the page, which the server answers with 'redirect'.
 *
 * @var array $urls Plans::index() urls
 */
$config = [
    'urls'     => $urls,
    'csrf'     => ['header' => csrf_header(), 'hash' => csrf_hash()],
    'messages' => [
        'failed'        => lang('BusinessPlans.save-failed'),
        'slipRequired'  => lang('BusinessPlans.pay.slip-required'),
        'confirmTitle'  => lang('BusinessPlans.confirm.title'),
        'confirmYes'    => lang('BusinessPlans.confirm.yes'),
        'confirmNo'     => lang('BusinessPlans.confirm.no'),
        'discountsNone' => lang('BusinessPlans.checkout.no-discounts'),
    ],
];
?>
(function (cfg) {
  'use strict';
  var csrfHash = cfg.csrf.hash;

  function notify(type, text) {
    if (window.toastr) { window.toastr[type](text); } else if (type === 'error') { window.alert(text); }
  }

  /** POST JSON (or a FormData, sent as multipart) and resolve with { ok, json } */
  function post(url, body) {
    var headers = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    headers[cfg.csrf.header] = csrfHash;
    var isForm = body instanceof FormData;
    if (!isForm) { headers['Content-Type'] = 'application/json'; }
    return fetch(url, { method: 'POST', headers: headers, credentials: 'same-origin', body: isForm ? body : JSON.stringify(body || {}) })
      .then(function (response) {
        return response.json().catch(function () { return {}; }).then(function (json) {
          if (json.csrf_hash) { csrfHash = json.csrf_hash; }
          return { ok: response.ok && !!json.success, json: json };
        });
      })
      .catch(function () { return { ok: false, json: {} }; });
  }

  function showErrors(root, errors) {
    Array.prototype.forEach.call(root.querySelectorAll('[data-error-for]'), function (el) {
      el.textContent = (errors && errors[el.getAttribute('data-error-for')]) || '';
    });
  }

  /** Reload on success, or show the message and the field errors */
  function finish(result, root, button) {
    var json = result.json || {};
    if (result.ok) {
      notify('success', json.message);
      window.location.href = json.redirect || window.location.href;
      return;
    }
    if (root) { showErrors(root, json.errors); }
    notify('error', json.message || cfg.messages.failed);
    if (button) { button.disabled = false; }
  }

  /** Ask before a change; html may hold <time> tags, shown in local time */
  function ask(html, danger) {
    if (!html) { return Promise.resolve(true); }
    if (window.Swal && typeof window.Swal.fire === 'function') {
      return window.Swal.fire({
        icon: danger ? 'warning' : 'question',
        title: cfg.messages.confirmTitle,
        html: html,
        showCancelButton: true,
        focusCancel: !!danger,
        confirmButtonText: cfg.messages.confirmYes,
        cancelButtonText: cfg.messages.confirmNo,
        didRender: function (popup) { if (window.formatLocalTimes) { window.formatLocalTimes(popup); } }
      }).then(function (res) { return !!res.isConfirmed; });
    }
    var div = document.createElement('div');
    div.innerHTML = html;
    if (window.formatLocalTimes) { window.formatLocalTimes(div); }
    return Promise.resolve(window.confirm(div.textContent));
  }

  function init() {
    var page = document.getElementById('plans-page');
    if (!page) { return; }

    // the monthly / yearly switch of the plan cards
    Array.prototype.forEach.call(page.querySelectorAll('input[name="plans-cycle"]'), function (radio) {
      radio.addEventListener('change', function () {
        Array.prototype.forEach.call(page.querySelectorAll('#plans-card [data-cycle]'), function (el) {
          el.hidden = el.getAttribute('data-cycle') !== radio.value;
        });
      });
    });

    // cancel, resume, change plan
    page.addEventListener('click', function (e) {
      var button = e.target.closest('[data-plan-action]');
      if (!button || button.disabled) { return; }
      var action = button.getAttribute('data-plan-action');
      if (!cfg.urls[action]) { return; }
      ask(button.getAttribute('data-confirm'), button.hasAttribute('data-danger')).then(function (ok) {
        if (!ok) { return; }
        button.disabled = true;
        var body = action === 'change'
          ? { plan_code: button.getAttribute('data-plan-code'), billing_cycle: button.getAttribute('data-billing-cycle') }
          : {};
        post(cfg.urls[action], body).then(function (result) { finish(result, null, button); });
      });
    });

    // the checkout
    var modalEl = document.getElementById('checkout-modal');
    var checkout = document.getElementById('checkout-form');
    if (modalEl && checkout && window.bootstrap) {
      var modal = window.bootstrap.Modal.getOrCreateInstance(modalEl);
      var field = function (name) { return checkout.querySelector('[data-checkout="' + name + '"]'); };

      page.addEventListener('click', function (e) {
        var button = e.target.closest('[data-plan-checkout]');
        if (!button) { return; }
        checkout.reset();
        showErrors(checkout, null);
        checkout.elements.plan_code.value = button.getAttribute('data-plan-code');
        checkout.elements.billing_cycle.value = button.getAttribute('data-billing-cycle');
        field('plan').textContent = button.getAttribute('data-plan-name');
        field('cycle').textContent = button.getAttribute('data-cycle-name');
        field('price').textContent = button.getAttribute('data-price-text');
        field('discounts').hidden = true;
        field('discount-list').textContent = '';
        modal.show();

        // the discount codes listed for this plan and cycle, to pick from
        post(cfg.urls.discounts, { plan_code: checkout.elements.plan_code.value, billing_cycle: checkout.elements.billing_cycle.value })
          .then(function (result) {
            var list = (result.ok && result.json.discounts) || [];
            if (!list.length) { return; }
            list.forEach(function (discount) {
              var chip = document.createElement('button');
              chip.type = 'button';
              chip.className = 'btn btn-sm btn-outline-info';
              chip.textContent = discount.discount_code;
              chip.title = discount.discount_description || '';
              chip.addEventListener('click', function () { checkout.elements.discount_code.value = discount.discount_code; });
              field('discount-list').appendChild(chip);
            });
            field('discounts').hidden = false;
          });
      });

      checkout.addEventListener('submit', function (e) {
        e.preventDefault();
        var submit = checkout.querySelector('[type="submit"]');
        submit.disabled = true;
        showErrors(checkout, null);
        post(cfg.urls.subscribe, {
          plan_code: checkout.elements.plan_code.value,
          billing_cycle: checkout.elements.billing_cycle.value,
          discount_code: checkout.elements.discount_code.value.trim()
        }).then(function (result) { finish(result, checkout, submit); });
      });
    }

    // a discount code on the invoice waiting for payment
    var discountForm = document.getElementById('apply-discount-form');
    if (discountForm) {
      discountForm.addEventListener('submit', function (e) {
        e.preventDefault();
        var submit = discountForm.querySelector('[type="submit"]');
        submit.disabled = true;
        showErrors(discountForm, null);
        post(cfg.urls['apply-discount'], {
          invoice_public_id: discountForm.elements.invoice_public_id.value,
          discount_code: discountForm.elements.discount_code.value.trim()
        }).then(function (result) { finish(result, discountForm, submit); });
      });
    }

    // the payment: method, slip and reference, sent as multipart
    var payForm = document.getElementById('pay-form');
    if (payForm) {
      payForm.addEventListener('submit', function (e) {
        e.preventDefault();
        showErrors(payForm, null);
        if (!payForm.elements.slip.files.length) {
          showErrors(payForm, { slip: cfg.messages.slipRequired });
          return;
        }
        var submit = payForm.querySelector('[type="submit"]');
        submit.disabled = true;
        post(cfg.urls.pay, new FormData(payForm)).then(function (result) { finish(result, payForm, submit); });
      });
    }
  }

  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})(<?= json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>);
