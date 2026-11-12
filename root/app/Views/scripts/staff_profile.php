<?php
/**
 * customScript of a person's profile page (StaffProfiles::form()):
 * - the languages editor (staff/languages_editor): one row per language with how well they speak it
 * - the details editor (staff/details_editor): per attribute of the business, the person's values in their order (the
 *   arrows move a value), each with its text in each language of the business's web pages
 * Each keeps its rows as JSON in the hidden field its form submits, and goes back to the saved rows on Cancel.
 *
 * @var string $languagesField the languages editor's hidden field
 * @var string $valuesField    the details editor's hidden field
 * @var array  $languages      the person's, as v1/client/staff/profile sends them
 * @var array  $attributes     the business's with the person's values, as v1/client/staff/profile sends them
 * @var array  $spoken         language_code => name, the languages the editor offers
 * @var array  $proficiencies  proficiency => name
 * @var array  $webLanguages   websiteLanguages(), the languages of the business's web pages
 * @var string $locale         the UI's
 */
$config = [
    'languagesField' => $languagesField,
    'valuesField'    => $valuesField,
    'languages'      => array_values($languages),
    'attributes'     => array_values($attributes),
    'spoken'         => array_map(static fn ($code, $name) => ['code' => (string) $code, 'name' => $name], array_keys($spoken), $spoken),
    'proficiencies'  => (object) $proficiencies,
    'webLanguages'   => (object) $webLanguages,
    'locale'         => $locale,
    'labels'         => [
        'language'    => lang('StaffProfiles.languages.language'),
        'proficiency' => lang('StaffProfiles.languages.proficiency'),
        'choose'      => lang('StaffProfiles.languages.choose'),
        'noLanguages' => lang('StaffProfiles.languages.none'),
        'value'       => lang('StaffProfiles.details.value'),
        'inLanguage'  => lang('StaffProfiles.details.in-language'),
        'addValue'    => lang('StaffProfiles.details.add'),
        'noValues'    => lang('StaffProfiles.details.no-values'),
        'public'      => lang('StaffAttributes.visibility.public'),
        'private'     => lang('StaffAttributes.visibility.private'),
        'new'         => lang('StaffProfiles.new'),
        'remove'      => lang('StaffProfiles.remove'),
        'moveEarlier' => lang('StaffProfiles.move-earlier'),
        'moveLater'   => lang('StaffProfiles.move-later'),
    ],
];
?>
(function (cfg) {
  'use strict';
  var L = cfg.labels;

  function esc(s) {
    return String(s === null || s === undefined ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function fmt(text, args) {
    return String(text).replace(/\{(\d+)\}/g, function (m, i) { return args[i] !== undefined ? args[i] : m; });
  }
  function button(attrs, icon, label, disabled, kind) {
    return '<button type="button" class="btn btn-sm btn-outline-' + (kind || 'secondary') + ' me-1" ' + attrs + ' title="' + esc(label) + '" aria-label="'
      + esc(label) + '"' + (disabled ? ' disabled' : '') + '><i class="bi bi-' + icon + '"></i></button>';
  }
  // Cancel resets the form's inputs: the editor goes back to the saved rows too
  function onReset(root, restart) {
    var form = root.closest('form');
    if (form) { form.addEventListener('reset', function () { setTimeout(restart, 0); }); }
  }

  // ---- languages ------------------------------------------------------------------------------------------------
  (function () {
    var root = document.querySelector('[data-languages-editor]');
    if (!root) { return; }
    var hidden = root.querySelector('input[name="' + cfg.languagesField + '"]');
    var levels = Object.keys(cfg.proficiencies);
    var rows;

    function start() {
      rows = cfg.languages.map(function (l) { return { language_code: l.language_code, proficiency: l.proficiency }; });
    }
    function render() {
      var taken = {};
      rows.forEach(function (r) { if (r.language_code) { taken[r.language_code] = true; } });
      var body = rows.map(function (r, i) {
        var options = '<option value="">' + esc(L.choose) + '</option>' + cfg.spoken.map(function (l) {
          // a language another row has is not offered again
          if (taken[l.code] && l.code !== r.language_code) { return ''; }
          return '<option value="' + esc(l.code) + '"' + (l.code === r.language_code ? ' selected' : '') + '>' + esc(l.name) + '</option>';
        }).join('');
        var levelOptions = levels.map(function (p) {
          return '<option value="' + p + '"' + (p === r.proficiency ? ' selected' : '') + '>' + esc(cfg.proficiencies[p]) + '</option>';
        }).join('');
        return '<tr><td><select class="form-select form-select-sm" style="min-width: 12rem;" data-language-row="' + i + '" aria-label="' + esc(L.language) + '">' + options + '</select></td>'
          + '<td><select class="form-select form-select-sm" style="min-width: 10rem;" data-proficiency-row="' + i + '" aria-label="' + esc(L.proficiency) + '">' + levelOptions + '</select></td>'
          + '<td class="text-end">' + button('data-language-remove="' + i + '"', 'x-lg', L.remove, false, 'danger') + '</td></tr>';
      }).join('');
      root.querySelector('[data-languages-table]').innerHTML = rows.length
        ? '<thead><tr><th>' + esc(L.language) + '</th><th>' + esc(L.proficiency) + '</th><th></th></tr></thead><tbody>' + body + '</tbody>'
        : '<tbody><tr><td class="text-muted">' + esc(L.noLanguages) + '</td></tr></tbody>';
      write();
    }
    // a row with no language chosen yet is left out
    function write() {
      hidden.value = JSON.stringify(rows.filter(function (r) { return r.language_code; }));
    }

    root.addEventListener('change', function (e) {
      var i = e.target.getAttribute('data-language-row');
      if (i !== null) { rows[Number(i)].language_code = e.target.value; render(); return; }
      i = e.target.getAttribute('data-proficiency-row');
      if (i !== null) { rows[Number(i)].proficiency = e.target.value; write(); }
    });
    root.addEventListener('click', function (e) {
      var remove = e.target.closest('[data-language-remove]');
      if (remove) {
        rows.splice(Number(remove.getAttribute('data-language-remove')), 1);
        render();
      } else if (e.target.closest('[data-languages-add]')) {
        rows.push({ language_code: '', proficiency: 'F' });
        render();
        var selects = root.querySelectorAll('[data-language-row]');
        if (selects.length) { selects[selects.length - 1].focus(); }
      }
    });
    onReset(root, function () { start(); render(); });
    start();
    render();
  })();

  // ---- details --------------------------------------------------------------------------------------------------
  (function () {
    var root = document.querySelector('[data-details-editor]');
    var blocks = root && root.querySelector('[data-details-blocks]');
    if (!blocks) { return; }
    var hidden = root.querySelector('input[name="' + cfg.valuesField + '"]');
    var languages = Object.keys(cfg.webLanguages);
    var values; // attribute public_id => rows

    function blank() { return { public_id: '', attribute_value: '', translations: {} }; }
    function start() {
      values = {};
      cfg.attributes.forEach(function (a) {
        values[a.public_id] = (a.values || []).map(function (v) {
          var names = {};
          languages.forEach(function (l) { names[l] = (v.translations && v.translations[l]) || ''; });
          return { public_id: v.public_id, attribute_value: v.attribute_value || '', translations: names };
        });
      });
    }
    function nameOf(a) { return (a.translations && a.translations[cfg.locale]) || a.attribute_name; }

    function render() {
      blocks.innerHTML = cfg.attributes.map(function (a) {
        var rows = values[a.public_id];
        var head = '<thead><tr><th>#</th><th>' + esc(L.value) + '</th>'
          + languages.map(function (l) { return '<th>' + esc(fmt(L.inLanguage, [cfg.webLanguages[l]])) + '</th>'; }).join('') + '<th></th></tr></thead>';
        var body = rows.map(function (r, i) {
          var at = 'data-attribute="' + esc(a.public_id) + '" data-index="' + i + '"';
          return '<tr><td class="text-muted text-nowrap">' + (i + 1) + (r.public_id ? '' : ' <span class="badge bg-info">' + esc(L.new) + '</span>') + '</td>'
            + '<td><input type="text" class="form-control form-control-sm" style="min-width: 10rem;" maxlength="255" ' + at + ' value="' + esc(r.attribute_value) + '" aria-label="' + esc(L.value) + '"></td>'
            + languages.map(function (l) {
              return '<td><input type="text" class="form-control form-control-sm" style="min-width: 8rem;" maxlength="255" ' + at + ' data-language="' + esc(l) + '" value="'
                + esc(r.translations[l] || '') + '" aria-label="' + esc(fmt(L.inLanguage, [cfg.webLanguages[l]])) + '"></td>';
            }).join('')
            + '<td class="text-end text-nowrap">'
            + button(at + ' data-move="-1"', 'arrow-up', L.moveEarlier, i === 0)
            + button(at + ' data-move="1"', 'arrow-down', L.moveLater, i === rows.length - 1)
            + button(at + ' data-remove', 'x-lg', L.remove, false, 'danger')
            + '</td></tr>';
        }).join('');
        var table = rows.length
          ? '<div class="table-responsive"><table class="table table-sm align-middle mb-2">' + head + '<tbody>' + body + '</tbody></table></div>'
          : '<p class="text-muted small mb-2">' + esc(L.noValues) + '</p>';
        return '<div class="border rounded p-3 mb-3">'
          + '<div class="d-flex flex-wrap align-items-center gap-2 mb-2"><h6 class="mb-0">' + esc(nameOf(a)) + '</h6>'
          + '<span class="badge ' + (a.is_public ? 'bg-success' : 'bg-secondary') + '">' + esc(a.is_public ? L.public : L.private) + '</span></div>'
          + table
          + '<button type="button" class="btn btn-sm btn-outline-primary" data-add="' + esc(a.public_id) + '"><i class="bi bi-plus-circle"></i> ' + esc(L.addValue) + '</button>'
          + '</div>';
      }).join('');
      write();
    }

    // the values as the API takes them: a new row left empty is left out, the order within an attribute is the rows'
    function write() {
      var out = [];
      cfg.attributes.forEach(function (a) {
        var place = 0;
        values[a.public_id].forEach(function (r) {
          if (!r.public_id && r.attribute_value.trim() === '') { return; }
          var v = { attribute_public_id: a.public_id, attribute_value: r.attribute_value.trim(), sort_order: place++, translations: r.translations };
          if (r.public_id) { v.public_id = r.public_id; }
          out.push(v);
        });
      });
      hidden.value = JSON.stringify(out);
    }

    function rowOf(el) {
      var rows = values[el.getAttribute('data-attribute')];
      return rows ? { rows: rows, i: Number(el.getAttribute('data-index')) } : null;
    }
    blocks.addEventListener('input', function (e) {
      if (!e.target.hasAttribute('data-attribute')) { return; }
      var at = rowOf(e.target);
      var r = at && at.rows[at.i];
      if (!r) { return; }
      if (e.target.hasAttribute('data-language')) { r.translations[e.target.getAttribute('data-language')] = e.target.value.trim(); }
      else { r.attribute_value = e.target.value; }
      write();
    });
    blocks.addEventListener('click', function (e) {
      var add = e.target.closest('[data-add]');
      var move = e.target.closest('[data-move]');
      var remove = e.target.closest('[data-remove]');
      if (add) {
        var id = add.getAttribute('data-add');
        values[id].push(blank());
        render();
        var inputs = blocks.querySelectorAll('input[data-attribute="' + CSS.escape(id) + '"]:not([data-language])');
        if (inputs.length) { inputs[inputs.length - 1].focus(); }
      } else if (move) {
        var at = rowOf(move);
        var to = at.i + Number(move.getAttribute('data-move'));
        if (to < 0 || to >= at.rows.length) { return; }
        at.rows.splice(to, 0, at.rows.splice(at.i, 1)[0]);
        render();
      } else if (remove) {
        var gone = rowOf(remove);
        gone.rows.splice(gone.i, 1);
        render();
      }
    });
    onReset(root, function () { start(); render(); });
    start();
    render();
  })();
})(<?= json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>);
