<?php
/**
 * The buttons and comments of messages/view: a status button posts { public_id, status } to urls.status (Spam after
 * confirming; Mark unread then goes back to the list, as staying would read it again); the comment form posts
 * { public_id, text } to urls.add, a Delete button { public_id, comment_public_id } to urls.delete after confirming.
 * The page reloads after each.
 *
 * @var string $messagePublicId
 * @var array  $urls ['status' => ..., 'add' => ..., 'delete' => ..., 'list' => ...]
 */
$config = [
    'message'  => $messagePublicId,
    'urls'     => $urls,
    'messages' => [
        'failed'        => lang('Messages.view.save-failed'),
        'confirmSpam'   => lang('Messages.action.confirm-spam'),
        'confirmDelete' => lang('Messages.timeline.confirm-delete'),
    ],
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

  /** Post, then reload (or go to `then`); on failure show why and let the button be clicked again */
  function act(button, url, body, then) {
    button.disabled = true;
    return post(url, body)
      .then(function (result) {
        if (!result.ok) { notify('error', result.json.message || cfg.messages.failed); button.disabled = false; return result; }
        notify('success', result.json.message);
        if (then) { window.location.href = then; } else { window.location.reload(); }
        return result;
      })
      .catch(function () { notify('error', cfg.messages.failed); button.disabled = false; });
  }

  function init() {
    document.addEventListener('click', function (e) {
      var statusButton = e.target.closest('[data-message-status]');
      if (statusButton && !statusButton.disabled) {
        if (statusButton.hasAttribute('data-confirm') && !window.confirm(cfg.messages.confirmSpam)) { return; }
        act(statusButton, cfg.urls.status, { public_id: cfg.message, status: statusButton.getAttribute('data-message-status') },
          statusButton.hasAttribute('data-leave') ? cfg.urls.list : null);
        return;
      }
      var deleteButton = e.target.closest('[data-delete-comment]');
      if (deleteButton && !deleteButton.disabled && window.confirm(cfg.messages.confirmDelete)) {
        act(deleteButton, cfg.urls.delete, { public_id: cfg.message, comment_public_id: deleteButton.getAttribute('data-delete-comment') });
      }
    });

    var form = document.getElementById('message-comment-form');
    if (form) {
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        var field = form.elements.text;
        var feedback = form.querySelector('[data-error-for="text"]');
        field.classList.remove('is-invalid');
        if (!field.value.trim()) { field.classList.add('is-invalid'); field.focus(); return; }
        act(form.querySelector('[type="submit"]'), cfg.urls.add, { public_id: cfg.message, text: field.value }).then(function (result) {
          var error = result && !result.ok ? (result.json.errors || {}).text : null;
          if (error) { field.classList.add('is-invalid'); feedback.textContent = error; }
        });
      });
    }
  }

  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})(<?= json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>);
