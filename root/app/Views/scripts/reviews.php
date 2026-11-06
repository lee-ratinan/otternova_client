<?php
/**
 * customScript of the reviews list (see generic_list.php): the Approve and Hide buttons of each row post
 * { public_id } to their URL, then the table reloads.
 *
 * @var string $tableId the table's DOM id
 * @var array  $urls    ['approve' => ..., 'hide' => ...]
 * @var string $failed  the message when the request fails without one of its own
 */
$config = [
    'tableId' => $tableId,
    'urls'    => $urls,
    'failed'  => $failed,
    'csrf'    => ['header' => csrf_header(), 'hash' => csrf_hash()],
];
?>
(function (cfg) {
  'use strict';
  var csrfHash = cfg.csrf.hash;

  function notify(type, text) {
    if (window.toastr) { window.toastr[type](text); } else if (type === 'error') { window.alert(text); }
  }

  function init() {
    var table = document.getElementById(cfg.tableId);
    if (!table) { return; }
    // the table's data requests refresh the token too
    if (window.jQuery) {
      window.jQuery(table).on('xhr.dt', function (e, settings, json) { if (json && json.csrf_hash) { csrfHash = json.csrf_hash; } });
    }

    table.addEventListener('click', function (e) {
      var button = e.target.closest('[data-review-action]');
      if (!button || button.disabled) { return; }
      var action = button.getAttribute('data-review-action');
      if (!cfg.urls[action]) { return; }
      button.disabled = true;

      var headers = { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
      headers[cfg.csrf.header] = csrfHash;
      fetch(cfg.urls[action], {
        method: 'POST',
        headers: headers,
        credentials: 'same-origin',
        body: JSON.stringify({ public_id: button.getAttribute('data-review-id') })
      })
        .then(function (response) {
          return response.json().catch(function () { return {}; }).then(function (json) { return { ok: response.ok, json: json }; });
        })
        .then(function (result) {
          var json = result.json || {};
          if (json.csrf_hash) { csrfHash = json.csrf_hash; }
          if (!result.ok || !json.success) {
            if (json.redirect && !json.success && json.status === 'error') { window.location.href = json.redirect; return; } // session expired
            notify('error', json.message || cfg.failed);
            button.disabled = false;
            return;
          }
          notify('success', json.message);
          if (window.TableBuilder && window.TableBuilder[cfg.tableId]) { window.TableBuilder[cfg.tableId].reload(); }
        })
        .catch(function () {
          notify('error', cfg.failed);
          button.disabled = false;
        });
    });
  }

  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})(<?= json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>);
