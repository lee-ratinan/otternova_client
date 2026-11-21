<?php
/**
 * The unread messages badges of _layout_admin (the header's Messages button, the mobile menu's and the sidebar's
 * Messages item, each [data-message-badge]): the count from admin/messages/unread-count when the page loads, then
 * every minute while the tab is visible, as notification-center.js does. Hidden at 0, "9+" above 9.
 * The Messages list sends its own unread_count with each page of rows, so the badges follow it at once there.
 *
 * @var string $url admin/messages/unread-count
 */
$config = [
    'url'  => $url,
    'poll' => 60,
    'csrf' => ['header' => csrf_header(), 'hash' => csrf_hash()],
];
?>
(function (cfg) {
  'use strict';
  var csrfHash = cfg.csrf.hash;
  var stopped = false;

  function show(count) {
    count = Math.max(0, parseInt(count, 10) || 0);
    document.querySelectorAll('[data-message-badge]').forEach(function (badge) {
      badge.textContent = count > 9 ? '9+' : String(count);
      badge.classList.toggle('d-none', count === 0);
    });
  }

  function refresh() {
    if (stopped) { return; }
    var headers = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    headers[cfg.csrf.header] = csrfHash;
    fetch(cfg.url, { method: 'POST', headers: headers, credentials: 'same-origin' })
      .then(function (response) {
        // signed out or the session ended: stop asking until the page is loaded again
        if (response.status === 401 || response.status === 403) { stopped = true; }
        return response.json();
      })
      .then(function (json) {
        if (json.csrf_hash) { csrfHash = json.csrf_hash; }
        if (json.success && json.data) { show(json.data.unread_count); }
      })
      .catch(function () { /* the badges stay as they are until the next try */ });
  }

  // the Messages list's own answers carry the count too
  if (window.jQuery) {
    window.jQuery(document).on('xhr.dt', function (e, settings, json) {
      if (json && typeof json.unread_count === 'number') { show(json.unread_count); }
    });
  }

  refresh();
  setInterval(function () { if (!document.hidden) { refresh(); } }, Math.max(15, cfg.poll) * 1000);
})(<?= json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>);
