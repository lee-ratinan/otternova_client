<?php
/**
 * customScript of the create-business form (see generic_form.php):
 * - the currency and subdivision dropdowns start empty, and are filled from admin/api/currencies and
 *   admin/api/subdivisions each time the country is picked;
 * - the business slug is filled from the business name through admin/api/generate-slug, until the user types a slug
 *   of their own (emptying it hands it back to the name).
 *
 * @var string $formId
 * @var array  $urls   ['currencies' => ..., 'subdivisions' => ..., 'slug' => ...]
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

    // ---- the slug follows the name until the user writes their own ----
    var name = form.elements.business_name;
    var slug = form.elements.business_slug;
    var slugOwn = !!(slug && slug.value.trim() !== '');
    var slugSeq = 0;

    async function makeSlug() {
      if (!name || !slug || slugOwn) { return; }
      var text = name.value.trim();
      var mine = ++slugSeq;
      if (text === '') { return; }
      var body = new FormData();
      body.set('text', text);
      body.set('strict', '1'); // business_slug: a-z, 0-9 and single hyphens only
      var csrf = form.querySelector('[data-fb-csrf]');
      if (csrf) { body.set(csrf.name, csrf.value); }
      var data;
      try {
        var res = await fetch(cfg.urls.slug, {
          method: 'POST', body: body, credentials: 'same-origin',
          headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        data = await res.json();
        if (csrf && data.csrf_hash) { csrf.value = data.csrf_hash; }
        if (!res.ok || data.status !== 'success') { return; } // e.g. no letters to make one from: left as it is
      } catch (err) { return; }
      // the user may have typed a slug, or changed the name again, while it was on its way
      if (mine !== slugSeq || slugOwn) { return; }
      slug.value = data.data.slug;
      slug.dispatchEvent(new Event('change', { bubbles: true })); // the form builder checks it
    }

    if (name && slug) {
      name.addEventListener('change', makeSlug);
      slug.addEventListener('input', function () { slugOwn = slug.value.trim() !== ''; });
    }
    // reset puts the country back; the lists follow it
    form.addEventListener('reset', function () { slugOwn = false; slugSeq++; setTimeout(load, 0); });
    load();
  }

  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); }
  else { init(); }
})(<?= json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>);
