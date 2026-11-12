<?php
/**
 * customScript part of the create- and edit-business forms: a time of day (the booking notice's booking_notice_time,
 * the free-cancellation deadline's cancel_notice_time) is for a notice in days only. With a notice in minutes it
 * hides, in both the edit and the view pane, and is emptied, so the API is sent no time (and drops one stored
 * before); the API checks the rest.
 *
 * @var string $formId
 * @var string $days  the unit value of a notice in days
 * @var array  $pairs [['unit' => unit field, 'time' => time field], ...]
 */
$config = ['formId' => $formId, 'days' => $days, 'pairs' => $pairs];
?>
(function (cfg) {
  'use strict';
  function init() {
    var form = document.getElementById(cfg.formId);
    if (!form) { return; }
    cfg.pairs.forEach(function (pair) {
      var unit = form.elements[pair.unit];
      var time = form.elements[pair.time];
      if (!unit || !time) { return; }

      function toggle() {
        var days = unit.value === cfg.days;
        var rows = '[data-fb-row="' + CSS.escape(pair.time) + '"], [data-fb-view="' + CSS.escape(pair.time) + '"]';
        form.querySelectorAll(rows).forEach(function (el) { el.classList.toggle('d-none', !days); });
        time.required = days;
        if (!days) { time.value = ''; }
      }

      unit.addEventListener('change', toggle);
      form.addEventListener('reset', function () { setTimeout(toggle, 0); });
      toggle();
    });
  }

  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})(<?= json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>);
