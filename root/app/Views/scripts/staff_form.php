<?php
/**
 * customScript of a person's page (Staff::form()): in the services editor (staff/services_editor), a service's
 * commission inputs are off while it is not ticked, and come back on, as saved, after Cancel. A service the person is
 * still assigned to upcoming sessions of cannot be unticked: it is ticked again, with an alert to take them off those
 * sessions first.
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

  // unticking a service the person still has upcoming sessions of: tick it again and say why
  function guard(editor, box) {
    if (box.checked) { return; }
    var row = box.closest('[data-service-row]');
    var sessions = [];
    try { sessions = JSON.parse(row.getAttribute('data-service-sessions') || '[]'); } catch (e) { sessions = []; }
    if (!sessions.length) { return; }
    box.checked = true;
    var text = (editor.getAttribute('data-sessions-message') || '')
      .replace('{name}', row.getAttribute('data-service-name') || '')
      .replace('{sessions}', sessions.join(', '));
    if (window.Swal && typeof window.Swal.fire === 'function') {
      window.Swal.fire({
        title: editor.getAttribute('data-sessions-title'), text: text, icon: 'warning',
        showCancelButton: true, confirmButtonText: editor.getAttribute('data-sessions-open'), cancelButtonText: editor.getAttribute('data-sessions-close')
      }).then(function (res) { if (res.isConfirmed) { window.location.href = editor.getAttribute('data-sessions-url'); } });
    } else {
      window.alert(text);
    }
  }

  function init() {
    var editor = document.querySelector('[data-services-editor]');
    if (!editor) { return; }
    editor.addEventListener('change', function (e) {
      if (e.target.matches('[data-service-assigned]')) { guard(editor, e.target); sync(editor); }
    });
    var form = editor.closest('form');
    if (form) { form.addEventListener('reset', function () { setTimeout(function () { sync(editor); }, 0); }); }
    sync(editor);
  }

  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})();
