<?php
/**
 * customScript of the discount form (see generic_form.php):
 * - the API keeps valid_from and expires_at in UTC; the inputs show them in the viewer's local time (their saved
 *   value too, so that Reset goes back to it), and the view pane in the viewer's locale
 * - the hidden timezone field carries the browser's IANA timezone, by which Discounts::save() turns the local
 *   times back into UTC
 * - a percentage cannot go above 100
 *
 * @var string                $formId
 * @var string                $timezoneField the hidden field of the browser's timezone
 * @var array<string, string> $utc           the saved datetimes, field => UTC "Y-m-d H:i:s" (null or missing: none)
 * @var list<string>          $datetimes     the datetime fields
 */
$config = [
    'formId'        => $formId,
    'timezoneField' => $timezoneField,
    'utc'           => (object) array_filter($utc, static fn ($v) => null !== $v && '' !== $v),
    'datetimes'     => $datetimes,
];
?>
(function (cfg) {
  'use strict';
  function pad(n) { return (n < 10 ? '0' : '') + n; }

  // "2026-12-01 02:00:00" (UTC) as a Date
  function fromUtc(text) {
    var d = new Date(String(text).replace(' ', 'T') + 'Z');
    return isNaN(d.getTime()) ? null : d;
  }

  // a Date as a datetime-local value in the viewer's time
  function localValue(d) {
    return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes());
  }

  function init() {
    var form = document.getElementById(cfg.formId);
    if (!form) { return; }

    var zone = '';
    try { zone = Intl.DateTimeFormat().resolvedOptions().timeZone || ''; } catch (e) { zone = ''; }
    var tz = form.elements[cfg.timezoneField];
    if (tz && zone) { tz.value = zone; tz.defaultValue = zone; }
    var formatter = new Intl.DateTimeFormat(document.documentElement.lang || undefined, { dateStyle: 'medium', timeStyle: 'short' });

    cfg.datetimes.forEach(function (name) {
      var d = cfg.utc[name] ? fromUtc(cfg.utc[name]) : null;
      if (!d) { return; }
      // without a timezone the server reads the inputs as UTC, so they stay as they are
      var input = form.elements[name];
      if (input && zone) { input.value = localValue(d); input.defaultValue = input.value; }
      var view = document.querySelector('[data-fb-view="' + CSS.escape(name) + '"] > div:last-child');
      if (view) { view.textContent = formatter.format(d); view.title = d.toISOString(); }
    });

    // a percentage is at most 100; an amount has no upper bound
    var type = form.elements.discount_type;
    var value = form.elements.discount_value;
    function fitValue() {
      if (!type || !value) { return; }
      if (type.value === 'P') { value.setAttribute('max', '100'); } else { value.removeAttribute('max'); }
    }
    if (type) {
      type.addEventListener('change', fitValue);
      form.addEventListener('reset', function () { setTimeout(fitValue, 0); });
    }
    fitValue();
  }

  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})(<?= json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>);
