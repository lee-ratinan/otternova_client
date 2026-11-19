<?php
/**
 * The notes of customers/view: the form posts { customer_public_id, note_text } to urls.add, a Delete button posts
 * { public_id } to urls.delete after confirming; the page reloads after either.
 *
 * @var string $customerPublicId
 * @var array  $urls ['add' => ..., 'delete' => ...]
 */
$config = [
    'customer' => $customerPublicId,
    'urls'     => $urls,
    'messages' => ['failed' => lang('Customers.notes.save-failed'), 'confirm' => lang('Customers.notes.confirm')],
    'csrf'     => ['header' => csrf_header(), 'hash' => csrf_hash()],
];
?>
(function (cfg) {
  'use strict';
  var csrfHash = cfg.csrf.hash;

  function notify(type, text) {
    if (window.toastr) { window.toastr[type](text); } else if (type === 'error') { window.alert(text); }
  }

  function post(url, body) {
    var headers = { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    headers[cfg.csrf.header] = csrfHash;
    return fetch(url, { method: 'POST', headers: headers, credentials: 'same-origin', body: JSON.stringify(body) })
      .then(function (response) {
        return response.json().catch(function () { return {}; }).then(function (json) {
          if (json.csrf_hash) { csrfHash = json.csrf_hash; }
          if (json.redirect && !json.success && json.status === 'error') { window.location.href = json.redirect; } // session expired
          return { ok: response.ok && !!json.success, json: json };
        });
      });
  }

  function init() {
    var form = document.getElementById('customer-note-form');
    if (form) {
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        var field = form.elements.note_text;
        var feedback = form.querySelector('[data-error-for="note_text"]');
        var button = form.querySelector('[type="submit"]');
        field.classList.remove('is-invalid');
        if (!field.value.trim()) { field.classList.add('is-invalid'); field.focus(); return; }
        button.disabled = true;
        post(cfg.urls.add, { customer_public_id: cfg.customer, note_text: field.value })
          .then(function (result) {
            if (!result.ok) {
              var error = (result.json.errors || {}).note_text;
              if (error) { field.classList.add('is-invalid'); feedback.textContent = error; }
              notify('error', result.json.message || cfg.messages.failed);
              button.disabled = false;
              return;
            }
            notify('success', result.json.message);
            window.location.reload();
          })
          .catch(function () { notify('error', cfg.messages.failed); button.disabled = false; });
      });
    }

    document.addEventListener('click', function (e) {
      var button = e.target.closest('[data-delete-note]');
      if (!button || button.disabled || !window.confirm(cfg.messages.confirm)) { return; }
      button.disabled = true;
      post(cfg.urls.delete, { public_id: button.getAttribute('data-delete-note') })
        .then(function (result) {
          if (!result.ok) { notify('error', result.json.message || cfg.messages.failed); button.disabled = false; return; }
          notify('success', result.json.message);
          window.location.reload();
        })
        .catch(function () { notify('error', cfg.messages.failed); button.disabled = false; });
    });
  }

  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})(<?= json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>);
