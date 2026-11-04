<?php
/**
 * customScript of the edit-business form (see generic_form.php): saving a changed business slug moves the store's
 * address at once, so it is confirmed first. The check runs before the form builder's submit handler (a capturing
 * listener on the document); once confirmed, the form is submitted again and goes through.
 *
 * @var string $formId
 * @var string $slug    the business slug the form was opened with
 * @var string $baseUrl the store address without the slug (LINK_MART)
 * @var array  $confirm ['title', 'text' ({0} old address, {1} new address), 'confirm', 'cancel']
 */
$config = ['formId' => $formId, 'slug' => $slug, 'baseUrl' => $baseUrl, 'confirm' => $confirm];
?>
(function (cfg) {
  'use strict';
  function init() {
    var form = document.getElementById(cfg.formId);
    var slug = form && form.elements.business_slug;
    if (!slug) { return; }
    var confirmed = false;

    function ask(next) {
      var text = cfg.confirm.text.replace('{0}', cfg.baseUrl + cfg.slug).replace('{1}', cfg.baseUrl + next);
      if (window.Swal && typeof window.Swal.fire === 'function') {
        return window.Swal.fire({
          icon: 'warning',
          title: cfg.confirm.title,
          text: text,
          showCancelButton: true,
          focusCancel: true,
          confirmButtonText: cfg.confirm.confirm,
          cancelButtonText: cfg.confirm.cancel
        }).then(function (res) { return !!res.isConfirmed; });
      }
      return Promise.resolve(window.confirm(cfg.confirm.title + '\n\n' + text));
    }

    document.addEventListener('submit', function (e) {
      if (e.target !== form) { return; }
      if (confirmed) { confirmed = false; return; } // the confirmed submit: the form builder sends it
      var next = slug.value.trim();
      if (next === '' || next === cfg.slug) { return; }
      // with errors on the form, the form builder shows them and sends nothing: no need to ask yet
      var fb = window.FormBuilder && window.FormBuilder[cfg.formId];
      if (fb && fb.validateAll().length) { return; }
      e.preventDefault();
      e.stopImmediatePropagation();
      ask(next).then(function (ok) {
        if (!ok) { slug.focus(); return; }
        confirmed = true;
        if (typeof form.requestSubmit === 'function') { form.requestSubmit(); }
        else { form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true })); }
      });
    }, true);
  }

  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})(<?= json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>);
