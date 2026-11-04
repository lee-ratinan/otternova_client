<?php
/**
 * customScript of the create-business form (see generic_form.php): the currency and subdivision dropdowns start
 * empty, and are filled from admin/api/currencies and admin/api/subdivisions each time the country is picked.
 *
 * @var string $formId
 * @var array  $urls   ['currencies' => ..., 'subdivisions' => ...]
 * @var string $select the empty option's text
 * @var string $failed the message when the lists cannot be loaded
 */
$config = ['formId' => $formId, 'urls' => $urls, 'select' => $select, 'failed' => $failed];
?>
(function (cfg) {
  'use strict';
  function init() {
    var form = document.getElementById(cfg.formId);
    if (!form) { return; }
    var country = form.elements.country_code;
    var currency = form.elements.currency_code;
    var subdivision = form.elements.contact_subdivision_code;
    if (!country || !currency || !subdivision) { return; }
    var seq = 0;

    function notifyError(text) {
      if (window.toastr) { window.toastr.error(text); } else { window.alert(text); }
    }

    // replace the options, keeping the choice when the new list still has it; an empty list leaves the
    // dropdown disabled, so it is neither checked nor sent
    function fill(select, list, withCode) {
      var keep = select.value;
      var codes = Object.keys(list || {});
      select.length = 0;
      select.add(new Option(cfg.select, ''));
      codes.forEach(function (code) {
        select.add(new Option(withCode ? code + ' - ' + list[code] : list[code], code));
      });
      select.value = codes.indexOf(keep) > -1 ? keep : '';
      select.disabled = codes.length === 0;
      // an error shown for the old list no longer applies
      select.classList.remove('is-invalid');
      var slot = form.querySelector('[data-fb-error="' + CSS.escape(select.name) + '"]');
      if (slot) { slot.textContent = ''; slot.style.display = ''; }
    }

    async function get(url, code) {
      var res = await fetch(url + '?country-code=' + encodeURIComponent(code), {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin'
      });
      var body = await res.json();
      if (!res.ok || body.status !== 'success') { throw new Error(body.message || cfg.failed); }
      return body.data || {};
    }

    async function load() {
      var code = country.value;
      var mine = ++seq;
      if (!code) { fill(currency, {}, true); fill(subdivision, {}); return; }
      var lists;
      try {
        lists = await Promise.all([get(cfg.urls.currencies, code), get(cfg.urls.subdivisions, code)]);
      } catch (err) {
        if (mine === seq) { fill(currency, {}, true); fill(subdivision, {}); notifyError(cfg.failed); }
        return;
      }
      if (mine !== seq) { return; } // the country changed again meanwhile
      fill(currency, lists[0], true);
      fill(subdivision, lists[1]);
    }

    country.addEventListener('change', load);
    // reset puts the country back; the lists follow it
    form.addEventListener('reset', function () { setTimeout(load, 0); });
    load();
  }

  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); }
  else { init(); }
})(<?= json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>);
