<?php
/**
 * customScript of the add-employee page (Employees::create()): the find form looks the email address up instead of
 * saving (a capturing submit listener, before the form builder's), then shows one of the two forms below it:
 * - an address with a client account never linked here: the add form, with who it is
 * - an address with no account: the invite form
 * Someone who works here, or did, is not added again: the message links to their page (rehire is there).
 * Both forms start hidden, and hide again when the address changes.
 *
 * @var array $urls     ['find' => ...]
 * @var array $messages ['add', 'invite' ({0} the address), 'active', 'former', 'open', 'failed']
 */
$config = [
    'urls'     => $urls,
    'messages' => $messages,
    'csrf'     => ['header' => csrf_header(), 'hash' => csrf_hash()],
];
?>
(function (cfg) {
  'use strict';
  var csrfHash = cfg.csrf.hash;

  function notify(type, html) {
    if (window.toastr) { window.toastr[type](html); } else { window.alert(html.replace(/<[^>]+>/g, '')); }
  }

  function esc(text) {
    var div = document.createElement('div');
    div.textContent = text == null ? '' : String(text);
    return div.innerHTML;
  }

  function init() {
    var find = document.getElementById('find-employee-form');
    var add = document.getElementById('add-employee-form');
    var invite = document.getElementById('invite-employee-form');
    if (!find || !add || !invite) { return; }
    // each extra form sits in its own wrapper around its card
    var cards = { add: add.closest('.card').parentElement, invite: invite.closest('.card').parentElement };

    function show(which) {
      cards.add.classList.toggle('d-none', which !== 'add');
      cards.invite.classList.toggle('d-none', which !== 'invite');
    }
    show(null);

    find.elements.email_address.addEventListener('input', function () { show(null); });

    function person(form, kind, html) {
      form.querySelector('[data-' + kind + '-person]').innerHTML = html;
    }

    function found(json, email) {
      if (!json.found) {
        invite.elements.email_address.value = email;
        person(invite, 'invite', '<p class="mb-0">' + esc(cfg.messages.invite.replace('{0}', email)) + '</p>');
        show('invite');
        return;
      }
      if (json.client_status) {
        show(null);
        var text = json.client_status === 'A' ? cfg.messages.active : cfg.messages.former;
        notify('info', esc(text.replace('{0}', json.account.full_name)) + ' <a href="' + esc(json.url) + '">' + esc(cfg.messages.open) + '</a>');
        return;
      }
      add.elements.email_address.value = json.account.email_address;
      person(add, 'add', '<div class="d-flex align-items-center gap-3">'
        + '<img src="' + esc(json.account.avatar) + '" alt="" class="rounded-circle" width="48" height="48">'
        + '<div><div class="fw-semibold">' + esc(json.account.full_name) + '</div>'
        + '<div class="text-muted small">' + esc(json.account.email_address) + '</div></div></div>'
        + '<p class="mb-0 mt-2">' + esc(cfg.messages.add) + '</p>');
      show('add');
    }

    document.addEventListener('submit', function (e) {
      if (e.target !== find) { return; }
      e.preventDefault();
      e.stopImmediatePropagation();
      var fb = window.FormBuilder && window.FormBuilder[find.id];
      if (fb && fb.validateAll().length) { return; }
      var email = find.elements.email_address.value.trim();
      var button = find.querySelector('button[type="submit"]');
      if (button) { button.disabled = true; }

      var body = new FormData();
      body.append('email_address', email);
      var headers = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
      headers[cfg.csrf.header] = csrfHash;
      fetch(cfg.urls.find, { method: 'POST', headers: headers, credentials: 'same-origin', body: body })
        .then(function (response) {
          return response.json().catch(function () { return {}; }).then(function (json) { return { ok: response.ok, json: json }; });
        })
        .then(function (result) {
          var json = result.json || {};
          if (json.csrf_hash) { csrfHash = json.csrf_hash; }
          if (!result.ok || !json.success) {
            if (json.redirect && json.status === 'error') { window.location.href = json.redirect; return; } // session expired
            var errors = json.errors ? Object.keys(json.errors).map(function (k) { return json.errors[k]; }) : [];
            notify('error', esc(errors[0] || json.message || cfg.messages.failed));
            return;
          }
          found(json, email);
        })
        .catch(function () { notify('error', esc(cfg.messages.failed)); })
        .then(function () { if (button) { button.disabled = false; } });
    }, true);
  }

  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})(<?= json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>);
