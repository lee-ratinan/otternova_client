<?php
/**
 * customScript of the employee form (see generic_form.php): ending an employment (or leaving the business) is
 * confirmed first. The check runs before the form builder's submit handler (a capturing listener on the
 * document); once confirmed, the form is submitted again and goes through.
 *
 * @var string $formId  the terminate form
 * @var array  $confirm ['title', 'text' ({0} the termination date), 'confirm', 'cancel']
 */
$config = ['formId' => $formId, 'confirm' => $confirm];
?>
(function (cfg) {
  'use strict';
  function init() {
    var form = document.getElementById(cfg.formId);
    var date = form && form.elements.termination_date;
    if (!date) { return; }
    var confirmed = false;

    function ask() {
      var text = cfg.confirm.text.replace('{0}', date.value);
      if (window.Swal && typeof window.Swal.fire === 'function') {
        return window.Swal.fire({
          icon: 'warning',
          title: cfg.confirm.title,
          text: text,
          showCancelButton: true,
          focusCancel: true,
          customClass: { confirmButton: 'btn btn-danger', cancelButton: 'btn btn-secondary ms-2' },
          buttonsStyling: false,
          confirmButtonText: cfg.confirm.confirm,
          cancelButtonText: cfg.confirm.cancel
        }).then(function (res) { return !!res.isConfirmed; });
      }
      return Promise.resolve(window.confirm(cfg.confirm.title + '\n\n' + text));
    }

    document.addEventListener('submit', function (e) {
      if (e.target !== form) { return; }
      if (confirmed) { confirmed = false; return; } // the confirmed submit: the form builder sends it
      // with errors on the form, the form builder shows them and sends nothing: no need to ask yet
      var fb = window.FormBuilder && window.FormBuilder[cfg.formId];
      if (fb && fb.validateAll().length) { return; }
      e.preventDefault();
      e.stopImmediatePropagation();
      ask().then(function (ok) {
        if (!ok) { date.focus(); return; }
        confirmed = true;
        if (typeof form.requestSubmit === 'function') { form.requestSubmit(); }
        else { form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true })); }
      });
    }, true);
  }

  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})(<?= json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>);
