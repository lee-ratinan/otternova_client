<?php
/**
 * The script of website/index.php. It keeps the settings in one state object, draws the language list, a tab of
 * fonts, meta tags and main-page text for each language, and the featured pickers from it, updates the preview as
 * anything changes, and saves colours, languages and featured products together with one button. The logo is
 * uploaded and removed straight away. Errors come back by the API's field names (languages.0.heading_font), which
 * the fields carry in data-error-for.
 *
 * @var array $settings v1/client/website
 * @var array $urls     Website::index() urls
 */
helper('list');
$defaultColors = [];
foreach ((listDefaultThemeVariables()['colors'] ?? []) as $variable => $value) {
    $defaultColors[str_replace('-', '_', trim($variable, '-'))] = $value;
}
$textFields = ['meta_title', 'meta_description', 'meta_keywords', 'hero_badge', 'hero_headline', 'hero_paragraph'];
$labels     = [];
foreach (['heading_font', 'nav_font', 'default_font', ...$textFields] as $field) {
    $labels[$field] = ['label' => lang('Website.content.' . $field), 'help' => lang('Website.content.' . $field . '-help')];
}
$config = [
    'settings'      => $settings,
    'urls'          => $urls,
    'csrf'          => ['header' => csrf_header(), 'hash' => csrf_hash()],
    'defaultColors' => $defaultColors,
    'textFields'    => $textFields,
    'labels'        => $labels,
    'messages'      => [
        'default'          => lang('Website.languages.default'),
        'makeDefault'      => lang('Website.languages.make-default'),
        'moveUp'           => lang('Website.languages.move-up'),
        'moveDown'         => lang('Website.languages.move-down'),
        'remove'           => lang('Website.languages.remove'),
        'addPlaceholder'   => lang('Website.languages.add-placeholder'),
        'fonts'            => lang('Website.content.fonts'),
        'meta'             => lang('Website.content.meta'),
        'metaHelp'         => lang('Website.content.meta-help'),
        'hero'             => lang('Website.content.hero'),
        'heroHelp'         => lang('Website.content.hero-help'),
        'fontSample'       => lang('Website.content.font-sample'),
        'none'             => lang('Website.featured.none-option'),
        'inactive'         => lang('Website.featured.inactive-removed'),
        'artifactTypes'    => ['P' => lang('Website.featured.type-P'), 'S' => lang('Website.featured.type-S')],
        'sampleBadge'      => lang('Website.preview.sample-badge'),
        'sampleHeadline'   => lang('Website.preview.sample-headline'),
        'sampleParagraph'  => lang('Website.preview.sample-paragraph'),
        'shapes'           => ['S' => lang('Website.logo.shape-S'), 'W' => lang('Website.logo.shape-W')],
        'confirmRemoveLogo'=> lang('Website.logo.confirm-remove'),
        'confirmYes'       => lang('Website.logo.confirm-yes'),
        'confirmNo'        => lang('Website.logo.confirm-no'),
        'chooseLogo'       => lang('Website.logo.choose'),
        'logoFailed'       => lang('Website.logo.failed'),
        'saved'            => lang('Website.saved'),
        'saveFailed'       => lang('Website.save-failed'),
        'saving'           => lang('Website.saving'),
        'save'             => lang('Website.save'),
        'leave'            => lang('Website.unsaved-leave'),
    ],
];
?>
(function (cfg) {
  'use strict';

  var options = cfg.settings.options || {};
  var languageNames = options.languages || {};
  var typefaces = options.typefaces || {};
  var limits = options.text_limits || {};
  var maxFeatured = options.max_featured || 3;
  var artifacts = options.artifacts || [];
  var artifactById = {};
  artifacts.forEach(function (a) { artifactById[a.public_id] = a; });
  var M = cfg.messages;
  var page = document.getElementById('website-page');
  var csrfHash = cfg.csrf.hash;
  var dirty = false;
  var activeCode = null;
  var state;

  // ---------------------------------------------------------------- helpers

  /** An element with attributes ("text" sets textContent, "on*" listeners) and children; text is never parsed as HTML */
  function el(tag, attrs, children) {
    var node = document.createElement(tag);
    Object.keys(attrs || {}).forEach(function (key) {
      var value = attrs[key];
      if (value === null || value === undefined || value === false) { return; }
      if (key === 'text') { node.textContent = value; }
      else if (key.indexOf('on') === 0) { node.addEventListener(key.slice(2), value); }
      else { node.setAttribute(key, value === true ? '' : value); }
    });
    (children || []).forEach(function (child) { if (child) { node.appendChild(typeof child === 'string' ? document.createTextNode(child) : child); } });
    return node;
  }

  function notify(type, text) {
    if (window.toastr) { window.toastr[type](text); } else if (type === 'error') { window.alert(text); }
  }

  /** POST JSON (or a FormData, sent as multipart) and resolve with { ok, json } */
  function post(url, body) {
    var headers = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    headers[cfg.csrf.header] = csrfHash;
    var isForm = body instanceof FormData;
    if (!isForm) { headers['Content-Type'] = 'application/json'; }
    return fetch(url, { method: 'POST', headers: headers, credentials: 'same-origin', body: isForm ? body : JSON.stringify(body || {}) })
      .then(function (response) {
        return response.json().catch(function () { return {}; }).then(function (json) {
          if (json.csrf_hash) { csrfHash = json.csrf_hash; }
          return { ok: response.ok && !!json.success, json: json };
        });
      })
      .catch(function () { return { ok: false, json: {} }; });
  }

  function confirmAsk(text) {
    if (window.Swal) {
      return window.Swal.fire({ text: text, icon: 'warning', showCancelButton: true, confirmButtonText: M.confirmYes, cancelButtonText: M.confirmNo })
        .then(function (r) { return !!r.isConfirmed; });
    }
    return Promise.resolve(window.confirm(text));
  }

  function markDirty() {
    dirty = true;
    document.getElementById('website-dirty').hidden = false;
  }

  function fontsOf(code) {
    var list = typefaces[code];
    return list && !Array.isArray(list) ? list : {};
  }

  /** Load a Google Font once, so the samples and the preview show it */
  var loadedFonts = {};
  function loadFont(code, name) {
    var query = fontsOf(code)[name];
    if (!query || loadedFonts[name]) { return; }
    loadedFonts[name] = true;
    document.head.appendChild(el('link', { rel: 'stylesheet', href: 'https://fonts.googleapis.com/css2?' + query + '&display=swap' }));
  }

  function fontStack(name) { return name ? '"' + name.replace(/"/g, '') + '", system-ui, sans-serif' : 'system-ui, sans-serif'; }

  /** The fonts a newly added language starts with: familiar ones of its list when it has them */
  function startingFonts(code) {
    var names = Object.keys(fontsOf(code));
    function pick(wanted) {
      for (var i = 0; i < wanted.length; i++) { if (names.indexOf(wanted[i]) !== -1) { return wanted[i]; } }
      return names[0] || '';
    }
    var heading = pick(['Prompt', 'Montserrat', 'Noto Sans']);
    return { heading_font: heading, nav_font: heading, default_font: pick(['Sarabun', 'Open Sans', 'Noto Sans']) };
  }

  /** The state of the settings from the API's response; featured artifacts that are no longer active are dropped */
  function fromSettings(settings) {
    var inactive = [];
    var featured = {};
    ['best_sellers', 'trending'].forEach(function (section) {
      featured[section] = [];
      ((settings.featured || {})[section] || []).forEach(function (a) {
        if (artifactById[a.public_id]) { featured[section].push(a.public_id); } else { inactive.push(a.artifact_name); }
      });
    });
    var warning = document.getElementById('featured-inactive');
    warning.textContent = inactive.length ? M.inactive.replace('{0}', inactive.join(', ')) : '';
    warning.classList.toggle('d-none', !inactive.length);
    return {
      colors: Object.assign({}, settings.colors || {}),
      logo: settings.logo || null,
      languages: (settings.languages || []).map(function (l) { return Object.assign({}, l); }),
      featured: featured,
    };
  }

  // ---------------------------------------------------------------- errors

  function clearErrors() {
    Array.prototype.forEach.call(page.querySelectorAll('[data-error-for]'), function (node) { node.textContent = ''; });
    Array.prototype.forEach.call(page.querySelectorAll('.is-invalid'), function (node) { node.classList.remove('is-invalid'); });
    Array.prototype.forEach.call(page.querySelectorAll('[data-tab-error]'), function (node) { node.hidden = true; });
  }

  function showErrors(errors) {
    clearErrors();
    var firstTab = null;
    Object.keys(errors || {}).forEach(function (key) {
      var target = page.querySelector('[data-error-for="' + key + '"]');
      if (target) { target.textContent = errors[key]; }
      var input = page.querySelector('[data-input-for="' + key + '"]');
      if (input) { input.classList.add('is-invalid'); }
      var match = /^languages\.(\d+)\./.exec(key);
      if (match && state.languages[+match[1]]) {
        var code = state.languages[+match[1]].language_code;
        var badge = page.querySelector('[data-tab-error="' + code + '"]');
        if (badge) { badge.hidden = false; }
        if (firstTab === null) { firstTab = code; }
      }
    });
    if (firstTab !== null) { showTab(firstTab); }
    var first = page.querySelector('[data-error-for]:not(:empty)');
    if (first) { first.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
  }

  // ---------------------------------------------------------------- languages

  function drawLanguages() {
    var list = document.getElementById('language-list');
    list.textContent = '';
    state.languages.forEach(function (language, i) {
      var code = language.language_code;
      var last = i === state.languages.length - 1;
      list.appendChild(el('li', { class: 'list-group-item d-flex align-items-center gap-2' }, [
        el('span', { class: 'flex-grow-1' }, [
          el('span', { class: 'fw-semibold', text: languageNames[code] || code }),
          ' ',
          el('code', { class: 'small text-muted', text: code }),
          i === 0 ? el('span', { class: 'badge bg-primary ms-2', text: M.default }) : null,
        ]),
        i > 0 ? el('button', { type: 'button', class: 'btn btn-sm btn-link text-decoration-none', text: M.makeDefault, onclick: function () { move(i, 0); } }) : null,
        el('div', { class: 'btn-group btn-group-sm' }, [
          el('button', { type: 'button', class: 'btn btn-outline-secondary', title: M.moveUp, 'aria-label': M.moveUp, disabled: i === 0, onclick: function () { move(i, i - 1); } }, [el('i', { class: 'bi bi-arrow-up' })]),
          el('button', { type: 'button', class: 'btn btn-outline-secondary', title: M.moveDown, 'aria-label': M.moveDown, disabled: last, onclick: function () { move(i, i + 1); } }, [el('i', { class: 'bi bi-arrow-down' })]),
          el('button', { type: 'button', class: 'btn btn-outline-danger', title: M.remove, 'aria-label': M.remove, disabled: state.languages.length === 1, onclick: function () { removeLanguage(i); } }, [el('i', { class: 'bi bi-x-lg' })]),
        ]),
      ]));
    });

    var chosen = state.languages.map(function (l) { return l.language_code; });
    var select = document.getElementById('language-add');
    select.textContent = '';
    select.appendChild(el('option', { value: '', text: M.addPlaceholder }));
    var left = Object.keys(languageNames).filter(function (code) { return chosen.indexOf(code) === -1; });
    left.forEach(function (code) { select.appendChild(el('option', { value: code, text: languageNames[code] })); });
    document.getElementById('language-add-group').classList.toggle('d-none', !left.length);
    document.getElementById('language-all-added').classList.toggle('d-none', !!left.length);

    var preview = document.getElementById('preview-language');
    preview.textContent = '';
    state.languages.forEach(function (l) { preview.appendChild(el('option', { value: l.language_code, text: languageNames[l.language_code] || l.language_code })); });
    preview.disabled = !state.languages.length;
  }

  function move(from, to) {
    var item = state.languages.splice(from, 1)[0];
    state.languages.splice(to, 0, item);
    markDirty();
    clearErrors(); // the errors' numbers no longer match
    redrawLanguages();
  }

  function removeLanguage(i) {
    var code = state.languages[i].language_code;
    state.languages.splice(i, 1);
    if (activeCode === code) { activeCode = null; }
    markDirty();
    clearErrors();
    redrawLanguages();
  }

  function addLanguage() {
    var code = document.getElementById('language-add').value;
    if (!code) { return; }
    var language = Object.assign({ language_code: code }, startingFonts(code));
    cfg.textFields.forEach(function (f) { language[f] = null; });
    state.languages.push(language);
    activeCode = code;
    markDirty();
    redrawLanguages();
  }

  function redrawLanguages() {
    drawLanguages();
    drawContent();
    drawPreview();
  }

  // ---------------------------------------------------------------- what each language has

  function counter(input, limit) {
    var count = el('span', { class: 'small text-muted' });
    function update() {
      var n = input.value.length;
      count.textContent = n + ' / ' + limit;
      count.classList.toggle('text-danger', n > limit);
    }
    input.addEventListener('input', update);
    update();
    return count;
  }

  function fieldBlock(language, index, field, control, extra) {
    var key = 'languages.' + index + '.' + field;
    var id = 'lang-' + language.language_code + '-' + field;
    control.id = id;
    control.setAttribute('data-input-for', key);
    return el('div', { class: 'mb-3' }, [
      el('div', { class: 'd-flex justify-content-between align-items-end' }, [
        el('label', { class: 'form-label mb-1', for: id, text: cfg.labels[field].label }),
        extra || null,
      ]),
      control,
      cfg.labels[field].help ? el('div', { class: 'form-text', text: cfg.labels[field].help }) : null,
      el('div', { class: 'invalid-feedback d-block', 'data-error-for': key }),
    ]);
  }

  function textField(language, index, field, multiline) {
    var limit = limits[field] || 255;
    var input = el(multiline ? 'textarea' : 'input', multiline ? { class: 'form-control', rows: field === 'hero_paragraph' ? 3 : 2 } : { class: 'form-control', type: 'text' });
    input.value = language[field] || '';
    input.addEventListener('input', function () { language[field] = input.value; markDirty(); drawPreview(); });
    return fieldBlock(language, index, field, input, counter(input, limit));
  }

  function fontField(language, index, field) {
    var code = language.language_code;
    var select = el('select', { class: 'form-select' });
    var names = Object.keys(fontsOf(code));
    if (language[field] && names.indexOf(language[field]) === -1) { names.unshift(language[field]); } // kept so the API can say why
    names.forEach(function (name) { select.appendChild(el('option', { value: name, text: name })); });
    select.value = language[field] || '';
    var sample = el('div', { class: 'border rounded px-2 py-1 mt-1 text-truncate', text: M.fontSample });
    function update() {
      loadFont(code, select.value);
      sample.style.fontFamily = fontStack(select.value);
    }
    select.addEventListener('change', function () { language[field] = select.value; markDirty(); update(); drawPreview(); });
    update();
    var block = fieldBlock(language, index, field, select);
    block.insertBefore(sample, block.querySelector('.form-text'));
    return block;
  }

  function drawContent() {
    var tabs = document.getElementById('content-tabs');
    var panes = document.getElementById('content-panes');
    tabs.textContent = '';
    panes.textContent = '';
    document.getElementById('content-empty').hidden = !!state.languages.length;
    if (!state.languages.length) { return; }
    if (!activeCode || !state.languages.some(function (l) { return l.language_code === activeCode; })) {
      activeCode = state.languages[0].language_code;
    }
    state.languages.forEach(function (language, index) {
      var code = language.language_code;
      tabs.appendChild(el('li', { class: 'nav-item', role: 'presentation' }, [
        el('button', { type: 'button', class: 'nav-link', role: 'tab', 'data-tab': code, onclick: function () { showTab(code); } }, [
          languageNames[code] || code,
          el('i', { class: 'bi bi-exclamation-circle-fill text-danger ms-1', 'data-tab-error': code, hidden: true }),
        ]),
      ]));
      var fonts = el('div', { class: 'row' }, ['heading_font', 'nav_font', 'default_font'].map(function (field) {
        return el('div', { class: 'col-md-4' }, [fontField(language, index, field)]);
      }));
      panes.appendChild(el('div', { class: 'tab-pane', role: 'tabpanel', 'data-pane': code, hidden: true }, [
        el('h6', { class: 'fw-semibold mt-1', text: M.fonts }),
        fonts,
        el('h6', { class: 'fw-semibold mt-2 mb-0', text: M.hero }),
        el('p', { class: 'small text-muted', text: M.heroHelp }),
        textField(language, index, 'hero_badge'),
        textField(language, index, 'hero_headline'),
        textField(language, index, 'hero_paragraph', true),
        el('h6', { class: 'fw-semibold mt-2 mb-0', text: M.meta }),
        el('p', { class: 'small text-muted', text: M.metaHelp }),
        textField(language, index, 'meta_title'),
        textField(language, index, 'meta_description', true),
        textField(language, index, 'meta_keywords'),
      ]));
    });
    showTab(activeCode);
  }

  function showTab(code) {
    activeCode = code;
    Array.prototype.forEach.call(page.querySelectorAll('[data-tab]'), function (tab) {
      var on = tab.getAttribute('data-tab') === code;
      tab.classList.toggle('active', on);
      tab.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    Array.prototype.forEach.call(page.querySelectorAll('[data-pane]'), function (pane) { pane.hidden = pane.getAttribute('data-pane') !== code; });
    var preview = document.getElementById('preview-language');
    if (preview.value !== code) { preview.value = code; drawPreview(); }
  }

  // ---------------------------------------------------------------- colours

  function setColor(field, value) {
    state.colors[field] = value.toLowerCase();
    var swatch = page.querySelector('[data-color="' + field + '"]');
    var text = page.querySelector('[data-color-text="' + field + '"]');
    if (swatch.value !== state.colors[field]) { swatch.value = state.colors[field]; }
    if (document.activeElement !== text) { text.value = state.colors[field]; }
    text.classList.remove('is-invalid');
  }

  Array.prototype.forEach.call(page.querySelectorAll('[data-color]'), function (swatch) {
    var field = swatch.getAttribute('data-color');
    swatch.addEventListener('input', function () { setColor(field, swatch.value); markDirty(); drawPreview(); });
  });
  Array.prototype.forEach.call(page.querySelectorAll('[data-color-text]'), function (text) {
    var field = text.getAttribute('data-color-text');
    text.setAttribute('data-input-for', 'colors.' + field);
    text.addEventListener('input', function () {
      var value = text.value.trim();
      if (value && value.charAt(0) !== '#') { value = '#' + value; }
      var valid = /^#[0-9a-f]{6}$/i.test(value);
      text.classList.toggle('is-invalid', !valid);
      if (valid) { setColor(field, value); markDirty(); drawPreview(); }
    });
    text.addEventListener('blur', function () { text.value = state.colors[field]; text.classList.remove('is-invalid'); });
  });
  document.getElementById('colors-reset').addEventListener('click', function () {
    Object.keys(cfg.defaultColors).forEach(function (field) { if (field in state.colors) { setColor(field, cfg.defaultColors[field]); } });
    markDirty();
    drawPreview();
  });

  // ---------------------------------------------------------------- logo

  function drawLogo() {
    var logo = state.logo;
    var image = document.getElementById('logo-image');
    image.hidden = !logo;
    if (logo) { image.src = logo.url; } else { image.removeAttribute('src'); }
    document.getElementById('logo-none').hidden = !!logo;
    document.getElementById('logo-delete').hidden = !logo;
    document.getElementById('logo-shape').textContent = logo ? (M.shapes[logo.shape] || '') : '';
    drawPreview();
  }

  var logoFile = document.getElementById('logo-file');
  var logoUpload = document.getElementById('logo-upload');
  logoFile.addEventListener('change', function () { logoUpload.disabled = !logoFile.files.length; });
  logoUpload.addEventListener('click', function () {
    if (!logoFile.files.length) { notify('error', M.chooseLogo); return; }
    var body = new FormData();
    body.append('image', logoFile.files[0]);
    logoUpload.disabled = true;
    post(cfg.urls.logoUpload, body).then(function (result) {
      if (result.ok) {
        state.logo = result.json.logo;
        logoFile.value = '';
        drawLogo();
        notify('success', result.json.message);
      } else {
        logoUpload.disabled = false;
        notify('error', result.json.message || M.logoFailed);
      }
    });
  });
  document.getElementById('logo-delete').addEventListener('click', function () {
    confirmAsk(M.confirmRemoveLogo).then(function (yes) {
      if (!yes) { return; }
      post(cfg.urls.logoDelete, {}).then(function (result) {
        if (result.ok) {
          state.logo = null;
          drawLogo();
          notify('success', result.json.message);
        } else {
          notify('error', result.json.message || M.logoFailed);
        }
      });
    });
  });

  // ---------------------------------------------------------------- featured

  function drawFeatured() {
    document.getElementById('featured-no-artifacts').classList.toggle('d-none', !!artifacts.length);
    ['best_sellers', 'trending'].forEach(function (section) {
      var chosen = state.featured[section];
      Array.prototype.forEach.call(page.querySelectorAll('[data-featured="' + section + '"]'), function (select) {
        var place = +select.getAttribute('data-place');
        var value = chosen[place] || '';
        select.textContent = '';
        select.appendChild(el('option', { value: '', text: M.none }));
        artifacts.forEach(function (a) {
          var takenElsewhere = chosen.indexOf(a.public_id) !== -1 && chosen[place] !== a.public_id;
          select.appendChild(el('option', { value: a.public_id, disabled: takenElsewhere, text: a.artifact_name + ' · ' + (M.artifactTypes[a.artifact_type] || a.artifact_type) }));
        });
        select.value = value;
        select.disabled = !artifacts.length;
      });
    });
  }

  Array.prototype.forEach.call(page.querySelectorAll('[data-featured]'), function (select) {
    select.addEventListener('change', function () {
      var section = select.getAttribute('data-featured');
      // the places in order, the empty ones left out, so "1, 3" becomes "1, 2"
      var values = Array.prototype.map.call(page.querySelectorAll('[data-featured="' + section + '"]'), function (s) { return s.value; });
      state.featured[section] = values.filter(function (v) { return v; }).slice(0, maxFeatured);
      markDirty();
      drawFeatured();
    });
  });

  // ---------------------------------------------------------------- preview

  function drawPreview() {
    var c = state.colors;
    var root = document.getElementById('preview');
    var code = document.getElementById('preview-language').value;
    var language = state.languages.filter(function (l) { return l.language_code === code; })[0] || state.languages[0] || {};
    function part(name) { return root.querySelectorAll('[data-preview="' + name + '"]'); }
    function each(name, fn) { Array.prototype.forEach.call(part(name), fn); }
    [language.heading_font, language.nav_font, language.default_font].forEach(function (f) { if (f) { loadFont(language.language_code, f); } });

    root.style.background = c.background_color;
    root.style.color = c.default_color;
    root.style.fontFamily = fontStack(language.default_font);
    root.lang = language.language_code || '';
    each('nav', function (n) { n.style.fontFamily = fontStack(language.nav_font); n.style.borderBottom = '1px solid color-mix(in srgb, ' + c.default_color + ', transparent 85%)'; });
    each('brand', function (n) { n.style.color = c.heading_color; n.style.fontFamily = fontStack(language.heading_font); n.hidden = !!(state.logo && state.logo.shape === 'W'); });
    each('logo', function (n) { n.hidden = !state.logo; if (state.logo) { n.src = state.logo.url; } });
    function sample(node, value, fallback) {
      node.textContent = value || fallback;
      node.style.opacity = value ? '1' : '.45';
    }
    each('badge', function (n) {
      sample(n, language.hero_badge, M.sampleBadge);
      n.style.color = c.accent_color;
      n.style.background = 'color-mix(in srgb, ' + c.accent_color + ', transparent 88%)';
    });
    each('headline', function (n) { sample(n, language.hero_headline, M.sampleHeadline); n.style.color = c.heading_color; n.style.fontFamily = fontStack(language.heading_font); });
    each('paragraph', function (n) { sample(n, language.hero_paragraph, M.sampleParagraph); });
    each('button', function (n) { n.style.background = c.accent_color; n.style.color = c.contrast_color; n.style.fontFamily = fontStack(language.nav_font); });
    each('surface', function (n) { n.style.background = c.surface_color; n.style.border = '1px solid color-mix(in srgb, ' + c.default_color + ', transparent 85%)'; });
  }

  document.getElementById('preview-language').addEventListener('change', function () {
    var code = document.getElementById('preview-language').value;
    if (code) { showTab(code); }
    drawPreview();
  });

  // ---------------------------------------------------------------- save

  var saveButton = document.getElementById('website-save');
  saveButton.addEventListener('click', function () {
    saveButton.disabled = true;
    saveButton.lastChild.textContent = ' ' + M.saving;
    var body = {
      colors: state.colors,
      languages: state.languages.map(function (l) {
        var row = { language_code: l.language_code, heading_font: l.heading_font, nav_font: l.nav_font, default_font: l.default_font };
        cfg.textFields.forEach(function (f) { row[f] = l[f] || ''; });
        return row;
      }),
      featured: state.featured,
    };
    post(cfg.urls.save, body).then(function (result) {
      saveButton.disabled = false;
      saveButton.lastChild.textContent = ' ' + M.save;
      if (!result.ok) {
        showErrors(result.json.errors || {});
        notify('error', result.json.message || M.saveFailed);
        return;
      }
      var logo = state.logo;
      state = fromSettings(result.json.settings || {});
      state.logo = logo;
      dirty = false;
      document.getElementById('website-dirty').hidden = true;
      clearErrors();
      redrawLanguages();
      drawFeatured();
      notify('success', result.json.message || M.saved);
    });
  });

  window.addEventListener('beforeunload', function (event) {
    if (dirty) { event.preventDefault(); event.returnValue = M.leave; }
  });

  // ---------------------------------------------------------------- start

  state = fromSettings(cfg.settings);
  document.getElementById('language-add-button').addEventListener('click', addLanguage);
  drawLanguages();
  drawContent();
  drawFeatured();
  drawLogo();
})(<?= json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>);
