<?php
/**
 * customScript of the my-businesses list (see generic_list.php): the Make Default and Switch buttons of each row
 * post { business_slug } to their URL. Make Default reloads the table; Switch opens the dashboard of the business
 * the session now holds, so the header and sidebar show it.
 *
 * @var string $tableId the table's DOM id
 * @var array  $urls    ['make-default' => ..., 'switch' => ...]
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

    table.addEventListener('click', function (e) {
      var button = e.target.closest('[data-business-action]');
      if (!button || button.disabled) { return; }
      var action = button.getAttribute('data-business-action');
      if (!cfg.urls[action]) { return; }
      button.disabled = true;

      var headers = { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
      headers[cfg.csrf.header] = csrfHash;
      fetch(cfg.urls[action], {
        method: 'POST',
        headers: headers,
        credentials: 'same-origin',
        body: JSON.stringify({ business_slug: button.getAttribute('data-business-slug') })
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
          if (json.redirect) { window.location.href = json.redirect; return; }
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
