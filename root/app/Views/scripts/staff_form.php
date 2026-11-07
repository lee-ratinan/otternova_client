<?php
/**
 * customScript of a person's page (Staff::form()): in the services editor (staff/services_editor), a service's
 * commission inputs are off while it is not ticked, and come back on, as saved, after Cancel.
 *
 * @var string $field the name the editor's inputs start with
 */
?>
(function () {
  'use strict';

  function sync(editor) {
    editor.querySelectorAll('[data-service-row]').forEach(function (row) {
      var on = row.querySelector('[data-service-assigned]').checked;
      row.querySelectorAll('[data-service-input]').forEach(function (input) { input.disabled = !on; });
    });
  }

  function init() {
    var editor = document.querySelector('[data-services-editor]');
    if (!editor) { return; }
    editor.addEventListener('change', function (e) { if (e.target.matches('[data-service-assigned]')) { sync(editor); } });
    var form = editor.closest('form');
    if (form) { form.addEventListener('reset', function () { setTimeout(function () { sync(editor); }, 0); }); }
    sync(editor);
  }

  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})();
