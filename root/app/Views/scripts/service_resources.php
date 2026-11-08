<?php
/**
 * Part of the customScript of a service's page (Artifacts::form()): in the "Resources needed" editor
 * (resources/needs_editor), a type's quantity is off while it is not ticked, and comes back on, as saved, after
 * Cancel.
 */
?>
(function () {
  'use strict';

  function sync(editor) {
    editor.querySelectorAll('[data-need-row]').forEach(function (row) {
      var on = row.querySelector('[data-need-ticked]').checked;
      row.querySelectorAll('[data-need-input]').forEach(function (input) { input.disabled = !on; });
    });
  }

  function init() {
    var editor = document.querySelector('[data-needs-editor]');
    if (!editor) { return; }
    editor.addEventListener('change', function (e) { if (e.target.matches('[data-need-ticked]')) { sync(editor); } });
    var form = editor.closest('form');
    if (form) { form.addEventListener('reset', function () { setTimeout(function () { sync(editor); }, 0); }); }
    sync(editor);
  }

  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})();
