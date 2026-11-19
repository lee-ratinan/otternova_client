<?php
/**
 * Script of _layout_admin: when the signed-in session (its JWT, session 'expires_at') runs out, ends it and goes to the
 * login page. The deadline is counted from the seconds left when the page was served, so the client's clock does not
 * matter. A timer can fire late after the device sleeps, so the deadline is checked again when the tab comes back.
 */
$secondsLeft = max(0, (int) session('expires_at') - time());
$config      = [
    'secondsLeft' => $secondsLeft,
    'logoutUrl'   => base_url('logout?expired=1'),
];
?>
(function (cfg) {
  'use strict';
  var deadline = Date.now() + cfg.secondsLeft * 1000;
  var done = false;

  function expire() {
    if (done) { return; }
    done = true;
    window.location.replace(cfg.logoutUrl);
  }

  function check() {
    if (Date.now() >= deadline) { expire(); }
  }

  // setTimeout cannot wait longer than 2^31-1 ms; sessions are capped well below that
  setTimeout(expire, Math.min(cfg.secondsLeft * 1000, 2147483647));
  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'visible') { check(); }
  });
  window.addEventListener('focus', check);
  window.addEventListener('pageshow', check);
})(<?= json_encode($config, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>);
