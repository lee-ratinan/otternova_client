<?php
/**
 * customScript of the option form (see generic_form.php): the choices editor (artifacts/attribute_values_editor),
 * one row per choice with its name in each UI language, in order (the arrows move a row), kept as JSON in the hidden
 * field the form submits. Saved choices cannot be removed: the API never deletes one, as variants may use it.
 *
 * @var string $formId      the option form's DOM id
 * @var string $valuesField the hidden field of the choices
 * @var array  $values      the saved choices, as v1/client/artifact-attributes sends them
 * @var array  $languages   websiteLanguages(), the languages of the business's web pages (none: no name columns)
 * @var int    $max         the most choices the API takes at a time
 */
$config = [
    'formId'      => $formId,
    'valuesField' => $valuesField,
    'values'      => array_values($values),
    'languages'   => (object) $languages,
    'max'         => $max,
    'labels'      => [
        'value'       => lang('ArtifactAttributes.values.value'),
        'inLanguage'  => lang('ArtifactAttributes.values.in-language'),
        'new'         => lang('ArtifactAttributes.values.new'),
        'remove'      => lang('ArtifactAttributes.values.remove'),
        'moveEarlier' => lang('ArtifactAttributes.values.move-earlier'),
        'moveLater'   => lang('ArtifactAttributes.values.move-later'),
        'count'       => lang('ArtifactAttributes.values.count'),
    ],
];
?>
(function (cfg) {
  'use strict';
  var L = cfg.labels;
  var form = document.getElementById(cfg.formId);
  var root = form && form.querySelector('[data-values-editor]');
  if (!root) { return; }
  var hidden = root.querySelector('input[name="' + cfg.valuesField + '"]');
  var languages = Object.keys(cfg.languages);
  var rows;

  function esc(s) {
    return String(s === null || s === undefined ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function fmt(text, args) {
    return String(text).replace(/\{(\d+)\}/g, function (m, i) { return args[i] !== undefined ? args[i] : m; });
  }
  function blank() { return { public_id: '', attribute_value: '', translations: {} }; }
  // the choices as saved, in their order; a new option starts with one empty row
  function start() {
    rows = cfg.values.map(function (v) {
      var names = {};
      languages.forEach(function (l) { names[l] = (v.translations && v.translations[l]) || ''; });
      return { public_id: v.public_id, attribute_value: v.attribute_value || '', translations: names };
    });
    if (!rows.length) { rows.push(blank()); }
  }

  function render() {
    var head = '<thead><tr><th>#</th><th>' + esc(L.value) + ' <span class="text-danger">*</span></th>'
      + languages.map(function (l) { return '<th>' + esc(fmt(L.inLanguage, [cfg.languages[l]])) + '</th>'; }).join('') + '<th></th></tr></thead>';
    var body = rows.map(function (r, i) {
      return '<tr><td class="text-muted text-nowrap">' + (i + 1) + (r.public_id ? '' : ' <span class="badge bg-info">' + esc(L.new) + '</span>') + '</td>'
        + '<td><input type="text" class="form-control form-control-sm" style="min-width: 8rem;" maxlength="128" data-row="' + i + '" value="' + esc(r.attribute_value) + '" aria-label="' + esc(L.value) + '"></td>'
        + languages.map(function (l) {
          return '<td><input type="text" class="form-control form-control-sm" style="min-width: 8rem;" maxlength="128" data-row="' + i + '" data-language="' + esc(l) + '" value="'
            + esc(r.translations[l] || '') + '" aria-label="' + esc(fmt(L.inLanguage, [cfg.languages[l]])) + '"></td>';
        }).join('')
        + '<td class="text-end text-nowrap">'
        + '<button type="button" class="btn btn-sm btn-outline-secondary me-1" data-move="-1" data-index="' + i + '" title="' + esc(L.moveEarlier) + '" aria-label="' + esc(L.moveEarlier) + '"' + (i === 0 ? ' disabled' : '') + '><i class="bi bi-arrow-up"></i></button>'
        + '<button type="button" class="btn btn-sm btn-outline-secondary me-1" data-move="1" data-index="' + i + '" title="' + esc(L.moveLater) + '" aria-label="' + esc(L.moveLater) + '"' + (i === rows.length - 1 ? ' disabled' : '') + '><i class="bi bi-arrow-down"></i></button>'
        + (r.public_id ? '' : '<button type="button" class="btn btn-sm btn-outline-danger" data-remove="' + i + '" title="' + esc(L.remove) + '" aria-label="' + esc(L.remove) + '"><i class="bi bi-x-lg"></i></button>')
        + '</td></tr>';
    }).join('');
    root.querySelector('[data-values-table]').innerHTML = head + '<tbody>' + body + '</tbody>';
    root.querySelector('[data-values-add]').disabled = rows.length >= cfg.max;
    root.querySelector('[data-values-count]').textContent = fmt(L.count, [rows.length]);
    write();
  }

  // the choices as the API takes them: a new row left empty is left out, the order is the rows'
  function write() {
    hidden.value = JSON.stringify(rows.filter(function (r) { return r.public_id || r.attribute_value.trim() !== ''; }).map(function (r) {
      var v = { attribute_value: r.attribute_value.trim(), translations: r.translations };
      if (r.public_id) { v.public_id = r.public_id; }
      return v;
    }));
  }

  root.addEventListener('input', function (e) {
    var r = rows[Number(e.target.getAttribute('data-row'))];
    if (!e.target.hasAttribute('data-row') || !r) { return; }
    if (e.target.hasAttribute('data-language')) { r.translations[e.target.getAttribute('data-language')] = e.target.value.trim(); }
    else { r.attribute_value = e.target.value; }
    write();
  });
  root.addEventListener('click', function (e) {
    var remove = e.target.closest('[data-remove]');
    var move = e.target.closest('[data-move]');
    if (remove) {
      rows.splice(Number(remove.getAttribute('data-remove')), 1);
      if (!rows.length) { rows.push(blank()); }
      render();
    } else if (move) {
      var from = Number(move.getAttribute('data-index'));
      var to = from + Number(move.getAttribute('data-move'));
      if (to < 0 || to >= rows.length) { return; }
      rows.splice(to, 0, rows.splice(from, 1)[0]);
      render();
    } else if (e.target.closest('[data-values-add]') && rows.length < cfg.max) {
      rows.push(blank());
      render();
      var inputs = root.querySelectorAll('[data-values-table] tbody tr:last-child input');
      if (inputs[0]) { inputs[0].focus(); }
    }
  });
  // Cancel resets the form's inputs: the choices go back to the saved ones too
  form.addEventListener('reset', function () { setTimeout(function () { start(); render(); }, 0); });

  start();
  render();
})(<?= json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>);
