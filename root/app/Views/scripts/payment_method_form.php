<?php
/**
 * customScript of the payment method form (see generic_form.php):
 * - "Handled by" offers only the handlers that take the chosen method (cash cannot be a PromptPay QR); when the
 *   chosen one no longer fits, the first that does is picked
 * - a setting (the PromptPay ID) shows only while its handler is chosen, in both the edit and the view pane, and is
 *   disabled otherwise so it is neither checked nor sent
 * - the instruction's label says "(optional)" only where the handler shows the customer something else
 *
 * @var string $formId
 * @var array  $handlers handler_code => {methods: list of method_code, settings: list of field names, instruction: bool}
 * @var array  $settings every settings field name of the form
 */
$config = ['formId' => $formId, 'handlers' => (object) $handlers, 'settings' => $settings, 'optional' => lang('Form.optional')];
?>
(function (cfg) {
  'use strict';
  function init() {
    var form = document.getElementById(cfg.formId);
    var method = form && form.elements.method_code;
    var handler = form && form.elements.handler_code;
    if (!method || !handler) { return; }

    function rows(name) {
      return form.querySelectorAll('[data-fb-row="' + CSS.escape(name) + '"], [data-fb-view="' + CSS.escape(name) + '"]');
    }

    function fitHandlers() {
      var first = null;
      Array.prototype.forEach.call(handler.options, function (option) {
        var h = cfg.handlers[option.value];
        var fits = !option.value || !h || h.methods.indexOf(method.value) !== -1;
        option.disabled = !fits;
        option.hidden = !fits;
        if (fits && option.value && first === null) { first = option.value; }
      });
      var chosen = handler.options[handler.selectedIndex];
      if ((!chosen || chosen.disabled || !chosen.value) && first !== null) {
        handler.value = first;
      }
    }

    function toggleSettings() {
      var h = cfg.handlers[handler.value] || { settings: [], instruction: true };
      cfg.settings.forEach(function (name) {
        var shown = h.settings.indexOf(name) !== -1;
        rows(name).forEach(function (el) { el.classList.toggle('d-none', !shown); });
        var input = form.elements[name];
        if (input) { input.disabled = !shown; }
      });
      // "(optional)" beside the instruction where the handler does not need it
      form.querySelectorAll('[data-fb-row="instruction"] label').forEach(function (label) {
        var marker = label.querySelector('[data-pm-optional]');
        if (!h.instruction && !marker) {
          marker = document.createElement('span');
          marker.setAttribute('data-pm-optional', '');
          marker.className = 'form-label-optional';
          marker.textContent = cfg.optional;
          label.appendChild(document.createTextNode(' '));
          label.appendChild(marker);
        } else if (h.instruction && marker) {
          if (marker.previousSibling && marker.previousSibling.nodeType === 3) { marker.previousSibling.remove(); }
          marker.remove();
        }
      });
    }

    function update() { fitHandlers(); toggleSettings(); }

    method.addEventListener('change', update);
    handler.addEventListener('change', toggleSettings);
    form.addEventListener('reset', function () { setTimeout(update, 0); });
    update();
  }

  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})(<?= json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>);
