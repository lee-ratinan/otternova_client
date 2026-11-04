<?php
/**
 * customScript of the store form (see generic_form.php): an online store has no address, so its address fields
 * (and their heading) hide, in both the edit and the view pane, and are disabled so they are neither checked nor
 * sent. The API clears an online store's address either way.
 *
 * @var string $formId
 * @var string $online  the store_type value of an online store
 * @var array  $address the address field names
 */
$config = ['formId' => $formId, 'online' => $online, 'address' => $address];
?>
(function (cfg) {
  'use strict';
  function init() {
    var form = document.getElementById(cfg.formId);
    var type = form && form.elements.store_type;
    if (!type) { return; }

    function toggle() {
      var online = type.value === cfg.online;
      form.querySelectorAll('[data-store-address]').forEach(function (el) { el.classList.toggle('d-none', online); });
      cfg.address.forEach(function (name) {
        form.querySelectorAll('[data-fb-row="' + CSS.escape(name) + '"], [data-fb-view="' + CSS.escape(name) + '"]').forEach(function (el) {
          el.classList.toggle('d-none', online);
        });
        var input = form.elements[name];
        if (input) { input.disabled = online; }
      });
    }

    type.addEventListener('change', toggle);
    form.addEventListener('reset', function () { setTimeout(toggle, 0); });
    toggle();
  }

  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})(<?= json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>);
