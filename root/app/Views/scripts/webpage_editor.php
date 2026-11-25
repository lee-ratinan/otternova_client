<?php
/**
 * The script of webpages/edit.php. It keeps the page as the API last answered it, and draws from it:
 * - the details card: the names per language and the address (the home page has neither, only its fixed name)
 * - one card per section the page has, in the API's order, then the one being added: a picker of the type's designs,
 *   each with its thumbnail ({LINK_ASSETS}img/sections/{design_code}.webp, an icon while there is none) and a preview
 *   on the storefront in a modal; the design's text fields in a tab per language; and its picture slots (for a section
 *   not saved yet, the chosen files are uploaded right after it is added)
 * - below them, a chooser of the next section type: the page starts empty and the user adds one section at a time
 * - the status and preview buttons
 * - the AI refinement card: once every text slot of every saved section is filled in, in the default language (as
 *   the editor has them, unsaved ones too), a button asks the API to rewrite them all in each language; the answers
 *   go into the sections' cards, for the user to review and save. Until then it tells what is still empty. The
 *   allowance (page.text_refinement) is kept here, as the page the API sends back after a save does not have it.
 * Changing a section's design keeps the texts of the slots both designs have; the API deletes the pictures that do
 * not fit the new design, which the page asks about first. Errors come back by the API's field names.
 *
 * @var array $page
 * @var array $options
 * @var array $urls
 */
helper(['list', 'html']);
// the section types of the API's designs (Config\WebpageDesigns::SECTION_TYPES), named in Webpages.sections
$sectionTypes = ['hero', 'about', 'services', 'portfolio', 'testimonials', 'why_us'];
$config       = [
    'page'          => $page,
    'options'       => $options,
    'urls'          => $urls,
    'languageNames' => listLanguages(),
    'planName'      => planName((string) ($options['plan'] ?? '')),
    'csrf'          => ['header' => csrf_header(), 'hash' => csrf_hash()],
    'messages'      => [
        'homeTitle'        => lang('Webpages.editor.home-title'),
        'homeSubtitle'     => lang('Webpages.editor.home-subtitle'),
        'homeNames'        => lang('Webpages.editor.home-names'),
        'pageTitle'        => lang('Webpages.editor.page-title'),
        'pageSubtitle'     => lang('Webpages.editor.page-subtitle'),
        'name'             => lang('Webpages.editor.name'),
        'metaTitle'        => lang('Webpages.editor.meta-title'),
        'metaDescription'  => lang('Webpages.editor.meta-description'),
        'metaHelp'         => lang('Webpages.editor.meta-help'),
        'slug'             => lang('Webpages.create.slug'),
        'slugWarning'      => lang('Webpages.editor.slug-warning'),
        'confirmSlug'      => lang('Webpages.editor.confirm-slug'),
        'saveDetails'      => lang('Webpages.editor.save-details'),
        'sectionTypes'     => array_combine($sectionTypes, array_map(static fn (string $type): string => lang('Webpages.sections.' . $type), $sectionTypes)),
        'sectionHelp'      => array_combine($sectionTypes, array_map(static fn (string $type): string => lang('Webpages.sections.' . $type . '-help'), $sectionTypes)),
        'chooseDesign'     => lang('Webpages.sections.choose-design'),
        'previewDesign'    => lang('Webpages.sections.preview-design'),
        'notOnPlan'        => lang('Webpages.sections.not-on-plan'),
        'current'          => lang('Webpages.sections.current'),
        'texts'            => lang('Webpages.sections.texts'),
        'textsHelp'        => lang('Webpages.sections.texts-help'),
        'required'         => lang('Webpages.sections.required'),
        'saveSection'      => lang('Webpages.sections.save'),
        'addSection'       => lang('Webpages.sections.add'),
        'deleteSection'    => lang('Webpages.sections.delete'),
        'confirmDeleteSection' => lang('Webpages.sections.confirm-delete'),
        'confirmDesign'    => lang('Webpages.sections.confirm-design'),
        'notYet'           => lang('Webpages.sections.not-yet'),
        'pickFirst'        => lang('Webpages.sections.pick-first'),
        'pickFirstHelp'    => lang('Webpages.sections.pick-first-help'),
        'pickNext'         => lang('Webpages.sections.pick-next'),
        'pickNextHelp'     => lang('Webpages.sections.pick-next-help'),
        'allAdded'         => lang('Webpages.sections.all-added'),
        'finishFirst'      => lang('Webpages.sections.finish-first'),
        'discard'          => lang('Webpages.sections.discard'),
        'newSection'       => lang('Webpages.sections.new'),
        'uploadOnSave'     => lang('Webpages.images.upload-on-save'),
        'pictures'         => lang('Webpages.images.title'),
        'picturesFirst'    => lang('Webpages.images.save-first'),
        'slotHelp'         => lang('Webpages.images.slot-help'),
        'slotHelpAny'      => lang('Webpages.images.slot-help-any'),
        'pageImages'       => lang('Webpages.images.page-count'),
        'upload'           => lang('Webpages.images.upload'),
        'deleteImage'      => lang('Webpages.images.delete'),
        'confirmDeleteImage' => lang('Webpages.images.confirm-delete'),
        'full'             => lang('Webpages.images.full'),
        'uploading'        => lang('Webpages.images.uploading'),
        'slots'            => lang('Webpages.slots'),
        'slotItem'         => lang('Webpages.slot-item'),
        'slotItems'        => lang('Webpages.slot-items'),
        'slotFields'       => lang('Webpages.slot-fields'),
        'designs'          => lang('Webpages.designs'),
        'draft'            => lang('Webpages.status.D'),
        'published'        => lang('Webpages.status.P'),
        'publish'          => lang('Webpages.list.publish'),
        'unpublish'        => lang('Webpages.list.unpublish'),
        'confirmUnpublishHome' => lang('Webpages.list.confirm-unpublish-home'),
        'confirmYes'       => lang('Webpages.yes'),
        'confirmNo'        => lang('Webpages.cancel'),
        'failed'           => lang('Webpages.save-failed'),
        'previewFailed'    => lang('Webpages.preview-failed'),
        'popupBlocked'     => lang('Webpages.popup-blocked'),
        'saving'           => lang('Webpages.saving'),
        'unsavedLeave'     => lang('Webpages.unsaved-leave'),
        'imageFailed'      => lang('Webpages.images.failed'),
        'refine'           => lang('Webpages.refinement'),
    ],
];
?>
(function (cfg) {
  'use strict';

  var M = cfg.messages;
  var options = cfg.options || {};
  var designs = options.designs || {};
  var languages = options.languages || [];
  var defaultLanguage = languages[0] || null;
  var maxImages = options.max_images_per_page || 0;
  var limits = options.name_limits || {};
  var page = cfg.page;
  var csrfHash = cfg.csrf.hash;
  var root = document.getElementById('webpage-editor');
  // what is being edited in each section type's card and not saved yet: { design, texts, files: { slot: [File] } }
  var drafts = {};
  // the section type the user picked to add and has not saved yet
  var adding = null;
  var dirty = false;
  // the page's AI refinements of its texts against the plan's allowance, and whether one is running
  var refineCap = page.text_refinement || { max: 0, count: 0, can_add: false, window_days: 60 };
  var refining = false;

  // ---------------------------------------------------------------- helpers

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

  function showErrors(scope, errors) {
    Array.prototype.forEach.call(scope.querySelectorAll('[data-error-for]'), function (node) { node.textContent = ''; });
    Array.prototype.forEach.call(scope.querySelectorAll('.is-invalid'), function (node) { node.classList.remove('is-invalid'); });
    Object.keys(errors || {}).forEach(function (key) {
      var target = scope.querySelector('[data-error-for="' + key + '"]');
      if (target) { target.textContent = errors[key]; }
      var input = scope.querySelector('[data-input-for="' + key + '"]');
      if (input) { input.classList.add('is-invalid'); }
    });
    var first = scope.querySelector('[data-error-for]:not(:empty)');
    if (first) {
      var pane = first.closest('.tab-pane');
      if (pane && window.bootstrap) {
        var tab = scope.querySelector('[data-bs-target="#' + pane.id + '"]');
        if (tab) { window.bootstrap.Tab.getOrCreateInstance(tab).show(); }
      }
      first.scrollIntoView({ block: 'center', behavior: 'smooth' });
    }
  }

  function humanize(code) { return code.replace(/_/g, ' ').replace(/^./, function (c) { return c.toUpperCase(); }); }
  /** A slot's name: its own, or for a numbered one, {item}_{n}_{field}, the item's name with n and the field's */
  function slotName(code) {
    if (M.slots[code]) { return M.slots[code]; }
    var parts = /^([a-z]+)_(\d+)_([a-z_]+)$/.exec(code);
    if (parts && M.slotItems[parts[1]]) {
      return fill(M.slotItem, [M.slotItems[parts[1]], parts[2], M.slotFields[parts[3]] || humanize(parts[3])]);
    }
    return humanize(code);
  }
  function designName(code) { return M.designs[code] || humanize(code); }
  function languageName(code) { return cfg.languageNames[code] || code; }
  function fill(text, values) { return text.replace(/\{(\d+)\}/g, function (m, i) { return values[+i] !== undefined ? values[+i] : m; }); }

  function busy(button, on) {
    if (on) { button.dataset.label = button.innerHTML; button.disabled = true; button.textContent = M.saving; }
    else { button.disabled = false; if (button.dataset.label) { button.innerHTML = button.dataset.label; } }
  }

  function markDirty() { dirty = true; drawRefine(); }
  window.addEventListener('beforeunload', function (event) {
    if (dirty) { event.preventDefault(); event.returnValue = M.unsavedLeave; }
  });

  /** The tabs of one field set per language: build(code) makes a pane's fields */
  function languageTabs(idPrefix, build) {
    var tabs = el('ul', { class: 'nav nav-tabs mb-3', role: 'tablist' });
    var panes = el('div', { class: 'tab-content' });
    languages.forEach(function (code, i) {
      var paneId = idPrefix + '-' + code.replace(/[^A-Za-z0-9]/g, '');
      tabs.appendChild(el('li', { class: 'nav-item', role: 'presentation' }, [
        el('button', { type: 'button', class: 'nav-link' + (i === 0 ? ' active' : ''), 'data-bs-toggle': 'tab', 'data-bs-target': '#' + paneId, role: 'tab',
          text: languageName(code) + (i === 0 ? ' ★' : '') }),
      ]));
      panes.appendChild(el('div', { class: 'tab-pane fade' + (i === 0 ? ' show active' : ''), id: paneId, role: 'tabpanel' }, build(code, i)));
    });
    return el('div', {}, [tabs, panes]);
  }

  // ---------------------------------------------------------------- the page's names and address

  function drawDetails() {
    var body = document.getElementById('details-body');
    body.textContent = '';
    if (page.is_home) {
      document.getElementById('details-title').textContent = M.homeTitle;
      document.getElementById('details-subtitle').textContent = M.homeSubtitle;
      var list = el('ul', { class: 'list-unstyled mb-0' });
      languages.forEach(function (code) {
        list.appendChild(el('li', {}, [el('span', { class: 'text-muted', text: languageName(code) + ': ' }), (options.home_names || {})[code] || '']));
      });
      body.appendChild(el('p', { class: 'small text-muted', text: M.homeNames }));
      body.appendChild(list);
      return;
    }
    var names = page.names || {};
    document.getElementById('details-title').textContent = M.pageTitle;
    document.getElementById('details-subtitle').textContent = M.pageSubtitle;
    body.appendChild(languageTabs('details', function (code, i) {
      var name = names[code] || {};
      function field(key, label, max, help, textarea) {
        var id = 'detail-' + key + '-' + code.replace(/[^A-Za-z0-9]/g, '');
        var input = el(textarea ? 'textarea' : 'input', { class: 'form-control', id: id, maxlength: max, 'data-language': code, 'data-field': key, rows: textarea ? 2 : null,
          'data-input-for': 'details.' + code + '.' + key,
          type: textarea ? null : 'text', oninput: markDirty });
        input.value = name[key] || '';
        return el('div', { class: 'mb-3' }, [
          el('label', { class: 'form-label', for: id }, [label, key === 'page_name' && i === 0 ? el('span', { class: 'text-danger', text: ' *' }) : null]),
          input,
          help ? el('div', { class: 'form-text', text: help }) : null,
          el('div', { class: 'invalid-feedback d-block', 'data-error-for': 'details.' + code + '.' + key }),
        ]);
      }
      return [
        field('page_name', M.name, limits.page_name || 64),
        field('meta_title', M.metaTitle, limits.meta_title || 70, M.metaHelp),
        field('meta_description', M.metaDescription, limits.meta_description || 160, null, true),
      ];
    }));
    var slug = el('input', { type: 'text', class: 'form-control font-monospace', id: 'detail-slug', maxlength: 64, spellcheck: 'false', 'data-input-for': 'page_slug', oninput: markDirty });
    slug.value = page.page_slug;
    body.appendChild(el('div', { class: 'mb-3' }, [
      el('label', { class: 'form-label', for: 'detail-slug', text: M.slug }),
      el('div', { class: 'input-group' }, [el('span', { class: 'input-group-text small', text: '/pages/' }), slug]),
      el('div', { class: 'form-text text-warning' }, [el('i', { class: 'bi bi-exclamation-triangle' }), ' ' + M.slugWarning]),
      el('div', { class: 'invalid-feedback d-block', 'data-error-for': 'page_slug' }),
    ]));
    body.appendChild(el('div', { class: 'invalid-feedback d-block mb-2', 'data-error-for': 'names' }));
    body.appendChild(el('button', { type: 'button', class: 'btn btn-primary', onclick: saveDetails }, [el('i', { class: 'bi bi-check-lg' }), ' ' + M.saveDetails]));
  }

  function saveDetails() {
    var card = document.getElementById('details-card');
    var button = this;
    var names = [];
    languages.forEach(function (code) {
      var entry = { language_code: code };
      Array.prototype.forEach.call(card.querySelectorAll('[data-language="' + code + '"]'), function (input) { entry[input.getAttribute('data-field')] = input.value; });
      if ((entry.page_name || '').trim() !== '' || (entry.meta_title || '').trim() !== '' || (entry.meta_description || '').trim() !== '') { names.push(entry); }
    });
    var body = { page_public_id: page.public_id, names: names };
    var slug = document.getElementById('detail-slug').value.trim();
    var slugChanged = slug !== page.page_slug;
    if (slugChanged) { body.page_slug = slug; }
    (slugChanged ? confirmAsk(fill(M.confirmSlug, ['/pages/' + page.page_slug])) : Promise.resolve(true)).then(function (yes) {
      if (!yes) { return; }
      busy(button, true);
      post(cfg.urls.save, body).then(function (r) {
        busy(button, false);
        if (!r.ok) {
          // names.{place}.{field} by the place in "names": point them at the language's field
          var errors = {};
          Object.keys(r.json.errors || {}).forEach(function (key) {
            var m = /^names\.(\d+)\.(.+)$/.exec(key);
            errors[m && names[+m[1]] ? 'details.' + names[+m[1]].language_code + '.' + m[2] : key] = r.json.errors[key];
          });
          showErrors(card, errors);
          if (!r.json.errors) { notify('error', r.json.message || M.failed); }
          return;
        }
        dirty = false;
        page = r.json.data;
        notify('success', r.json.message);
        drawDetails();
        drawStatus();
      });
    });
  }

  // ---------------------------------------------------------------- sections

  function sectionOf(type) {
    return (page.sections || []).filter(function (s) { return s.section_type === type; })[0] || null;
  }

  function designsOf(type) {
    return Object.keys(designs).filter(function (code) { return designs[code].section_type === type; });
  }

  /** The section type's card state: what is saved, or the first design that fits the plan */
  function draftOf(type) {
    if (!drafts[type]) {
      var section = sectionOf(type);
      var first = designsOf(type).filter(function (code) { return designs[code].fits_plan; })[0] || null;
      drafts[type] = { design: section ? section.design_code : first, texts: JSON.parse(JSON.stringify(section ? section.texts : {})), files: {} };
    }
    return drafts[type];
  }

  function previewDesign(code) {
    document.getElementById('design-preview-title').textContent = designName(code);
    document.getElementById('design-preview-frame').src = cfg.urls.designPreview + encodeURIComponent(code);
    if (window.bootstrap) { window.bootstrap.Modal.getOrCreateInstance(document.getElementById('design-preview-modal')).show(); }
  }
  document.getElementById('design-preview-modal').addEventListener('hidden.bs.modal', function () {
    document.getElementById('design-preview-frame').src = 'about:blank';
  });

  function designPicker(type, draft, section) {
    var grid = el('div', { class: 'row g-3 mb-4' });
    designsOf(type).forEach(function (code) {
      var design = designs[code];
      var thumb = el('img', { src: cfg.urls.thumbnails + encodeURIComponent(code) + '.webp', alt: '', loading: 'lazy' });
      var thumbBox = el('div', { class: 'wpe-thumb' }, [thumb]);
      thumb.addEventListener('error', function () { thumbBox.textContent = ''; thumbBox.appendChild(el('i', { class: 'bi bi-image fs-1 text-muted' })); });
      var selected = draft.design === code;
      var card = el('div', { class: 'card h-100 wpe-design' + (selected ? ' selected' : '') + (design.fits_plan ? '' : ' disabled'), role: 'radio', 'aria-checked': selected ? 'true' : 'false',
        tabindex: design.fits_plan ? '0' : null }, [
        thumbBox,
        el('div', { class: 'card-body p-2' }, [
          el('div', { class: 'd-flex align-items-center justify-content-between gap-2' }, [
            el('span', { class: 'fw-semibold small', text: designName(code) }),
            section && section.design_code === code ? el('span', { class: 'badge text-bg-light', text: M.current }) : null,
          ]),
          design.fits_plan ? null : el('div', { class: 'small text-muted', text: M.notOnPlan }),
          el('button', { type: 'button', class: 'btn btn-link btn-sm px-0', onclick: function (event) { event.stopPropagation(); previewDesign(code); } },
            [el('i', { class: 'bi bi-eye' }), ' ' + M.previewDesign]),
        ]),
      ]);
      function choose() {
        if (!design.fits_plan || draft.design === code) { return; }
        draft.design = code;
        markDirty();
        drawSections();
      }
      card.addEventListener('click', choose);
      card.addEventListener('keydown', function (event) { if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); choose(); } });
      grid.appendChild(el('div', { class: 'col-sm-6 col-lg-4' }, [card]));
    });
    return el('div', { role: 'radiogroup', 'aria-label': M.chooseDesign }, [el('h6', { class: 'fw-semibold', text: M.chooseDesign }), grid]);
  }

  function textFields(type, draft) {
    var design = designs[draft.design];
    return el('div', { class: 'mb-3' }, [
      el('h6', { class: 'fw-semibold', text: M.texts }),
      el('p', { class: 'small text-muted', text: M.textsHelp }),
      languageTabs('texts-' + type, function (code, i) {
        return Object.keys(design.texts).map(function (slot) {
          var rule = design.texts[slot];
          var id = 'text-' + type + '-' + slot + '-' + code.replace(/[^A-Za-z0-9]/g, '');
          var key = 'texts.' + code + '.' + slot;
          var input = el(rule.multiline ? 'textarea' : 'input', { class: 'form-control', id: id, maxlength: rule.max, rows: rule.multiline ? 4 : null,
            type: rule.multiline ? null : 'text', 'data-input-for': key });
          input.value = ((draft.texts[code] || {})[slot]) || '';
          var counter = el('span', { class: 'small text-muted' });
          function count() { counter.textContent = input.value.length + ' / ' + rule.max; }
          input.addEventListener('input', function () {
            draft.texts[code] = draft.texts[code] || {};
            draft.texts[code][slot] = input.value;
            count();
            markDirty();
          });
          count();
          return el('div', { class: 'mb-3' }, [
            el('div', { class: 'd-flex justify-content-between' }, [
              el('label', { class: 'form-label', for: id }, [slotName(slot), rule.required && i === 0 ? el('span', { class: 'text-danger', title: M.required, text: ' *' }) : null]),
              counter,
            ]),
            input,
            el('div', { class: 'invalid-feedback d-block', 'data-error-for': key }),
          ]);
        });
      }),
    ]);
  }

  function imageSlots(section) {
    var design = designs[section.design_code];
    var total = (page.sections || []).reduce(function (n, s) { return n + (s.images || []).length; }, 0);
    var box = el('div', { class: 'border-top pt-3 mt-3' }, [
      el('div', { class: 'd-flex justify-content-between align-items-center' }, [
        el('h6', { class: 'fw-semibold mb-0', text: M.pictures }),
        el('span', { class: 'small text-muted', text: fill(M.pageImages, [total, maxImages]) }),
      ]),
    ]);
    Object.keys(design.images).forEach(function (slot) {
      var rule = design.images[slot];
      var images = (section.images || []).filter(function (image) { return image.slot_code === slot; });
      var max = rule.max === null || rule.max === undefined ? null : rule.max;
      var full = (max !== null && images.length >= max) || total >= maxImages;
      var list = el('div', { class: 'd-flex flex-wrap gap-2 mb-2' }, images.map(function (image) {
        return el('div', { class: 'wpe-image' }, [
          el('img', { src: image.url, alt: slotName(slot), loading: 'lazy' }),
          el('button', { type: 'button', class: 'btn btn-sm btn-danger', title: M.deleteImage, 'aria-label': M.deleteImage, onclick: function () { deleteImage(section, image); } },
            [el('i', { class: 'bi bi-trash' })]),
        ]);
      }));
      var file = el('input', { type: 'file', class: 'form-control', accept: 'image/jpeg,image/png,image/webp', disabled: full, 'aria-label': M.upload + ': ' + slotName(slot) });
      var upload = el('button', { type: 'button', class: 'btn btn-outline-primary', disabled: true }, [el('i', { class: 'bi bi-upload' }), ' ' + M.upload]);
      file.addEventListener('change', function () { upload.disabled = !file.files.length; });
      upload.addEventListener('click', function () { uploadImage(section, slot, file, upload); });
      box.appendChild(el('div', { class: 'mt-3' }, [
        el('div', { class: 'fw-semibold small', text: slotName(slot) }),
        el('div', { class: 'form-text mt-0 mb-2', text: max === null ? fill(M.slotHelpAny, [rule.width, rule.height]) : fill(M.slotHelp, [rule.width, rule.height, max]) }),
        list,
        full ? el('div', { class: 'small text-muted', text: M.full }) : el('div', { class: 'input-group' }, [file, upload]),
      ]));
    });
    return box;
  }

  /** The picture slots of a section not saved yet: the files chosen are kept and uploaded once it is added */
  function pendingSlots(draft) {
    var design = designs[draft.design];
    var total = (page.sections || []).reduce(function (n, s) { return n + (s.images || []).length; }, 0);
    var box = el('div', { class: 'border-top pt-3 mt-3' }, [
      el('div', { class: 'd-flex justify-content-between align-items-center' }, [
        el('h6', { class: 'fw-semibold mb-0', text: M.pictures }),
        el('span', { class: 'small text-muted', text: fill(M.pageImages, [total, maxImages]) }),
      ]),
      el('p', { class: 'small text-muted mb-0' }, [el('i', { class: 'bi bi-info-circle' }), ' ' + M.uploadOnSave]),
    ]);
    Object.keys(design.images).forEach(function (slot) {
      var rule = design.images[slot];
      var max = rule.max === null || rule.max === undefined ? null : rule.max;
      var file = el('input', { type: 'file', class: 'form-control', accept: 'image/jpeg,image/png,image/webp', multiple: max !== 1,
        disabled: total >= maxImages, 'aria-label': M.upload + ': ' + slotName(slot) });
      file.addEventListener('change', function () {
        var files = Array.prototype.slice.call(file.files);
        draft.files[slot] = max === null ? files : files.slice(0, max);
        markDirty();
      });
      box.appendChild(el('div', { class: 'mt-3' }, [
        el('div', { class: 'fw-semibold small', text: slotName(slot) }),
        el('div', { class: 'form-text mt-0 mb-2', text: max === null ? fill(M.slotHelpAny, [rule.width, rule.height]) : fill(M.slotHelp, [rule.width, rule.height, max]) }),
        file,
      ]));
    });
    return box;
  }

  /** Upload the files picked before the section existed, one after another; resolves once all are tried */
  function uploadPending(section, files) {
    var queue = [];
    Object.keys(files || {}).forEach(function (slot) {
      if (!(designs[section.design_code].images || {})[slot]) { return; }
      (files[slot] || []).forEach(function (f) { queue.push({ slot: slot, file: f }); });
    });
    var failed = 0;
    return queue.reduce(function (chain, item) {
      return chain.then(function () {
        var form = new FormData();
        form.append('page_public_id', page.public_id);
        form.append('section_public_id', section.public_id);
        form.append('slot_code', item.slot);
        form.append('image', item.file);
        return post(cfg.urls.imageUpload, form).then(function (r) {
          if (!r.ok) { failed++; notify('error', (r.json.message || M.imageFailed) + ' (' + item.file.name + ')'); return; }
          section.images = (section.images || []).concat([r.json.data]);
          page.image_count = (page.image_count || 0) + 1;
        });
      });
    }, Promise.resolve()).then(function () { return failed; });
  }

  function uploadImage(section, slot, file, button) {
    var form = new FormData();
    form.append('page_public_id', page.public_id);
    form.append('section_public_id', section.public_id);
    form.append('slot_code', slot);
    form.append('image', file.files[0]);
    button.disabled = true;
    button.textContent = M.uploading;
    post(cfg.urls.imageUpload, form).then(function (r) {
      if (!r.ok) {
        notify('error', r.json.message || M.imageFailed);
        drawSections();
        return;
      }
      section.images = (section.images || []).concat([r.json.data]);
      page.image_count = (page.image_count || 0) + 1;
      notify('success', r.json.message);
      drawSections();
    });
  }

  function deleteImage(section, image) {
    confirmAsk(M.confirmDeleteImage).then(function (yes) {
      if (!yes) { return; }
      post(cfg.urls.imageDelete, { public_id: image.public_id }).then(function (r) {
        if (!r.ok) { notify('error', r.json.message || M.imageFailed); return; }
        section.images = section.images.filter(function (i) { return i.public_id !== image.public_id; });
        notify('success', r.json.message);
        drawSections();
      });
    });
  }

  function saveSection(type, button) {
    var draft = draftOf(type);
    var section = sectionOf(type);
    var card = button.closest('.card');
    // only the slots of the chosen design, the empty ones left out
    var texts = {};
    var slots = designs[draft.design].texts;
    Object.keys(draft.texts).forEach(function (code) {
      Object.keys(draft.texts[code] || {}).forEach(function (slot) {
        var text = draft.texts[code][slot];
        if (slots[slot] && (text || '').trim() !== '') { texts[code] = texts[code] || {}; texts[code][slot] = text; }
      });
    });
    var losing = section && section.design_code !== draft.design && (section.images || []).length > 0;
    (losing ? confirmAsk(M.confirmDesign) : Promise.resolve(true)).then(function (yes) {
      if (!yes) { return; }
      var body = { page_public_id: page.public_id, design_code: draft.design, texts: texts };
      if (section) { body.section_public_id = section.public_id; }
      busy(button, true);
      post(cfg.urls.sectionSave, body).then(function (r) {
        busy(button, false);
        if (!r.ok) {
          showErrors(card, r.json.errors);
          if (!r.json.errors) { notify('error', r.json.message || M.failed); }
          return;
        }
        page = r.json.data;
        var files = draft.files;
        delete drafts[type];
        if (adding === type) { adding = null; }
        dirty = false;
        notify('success', r.json.message);
        var saved = sectionOf(type);
        if (!section && saved && Object.keys(files || {}).length) {
          busy(button, true);
          uploadPending(saved, files).then(function () { drawSections(); });
          return;
        }
        drawSections();
      });
    });
  }

  function deleteSection(type) {
    var section = sectionOf(type);
    confirmAsk(M.confirmDeleteSection).then(function (yes) {
      if (!yes) { return; }
      post(cfg.urls.sectionDelete, { page_public_id: page.public_id, section_public_id: section.public_id }).then(function (r) {
        if (!r.ok) { notify('error', r.json.message || M.failed); return; }
        page = r.json.data;
        delete drafts[type];
        notify('success', r.json.message);
        drawSections();
      });
    });
  }

  /** The section types of the API's designs, in its order (the hero first) */
  function allTypes() {
    var types = [];
    Object.keys(designs).forEach(function (code) { if (types.indexOf(designs[code].section_type) === -1) { types.push(designs[code].section_type); } });
    return types;
  }

  function sectionCard(type) {
    var section = sectionOf(type);
    var draft = draftOf(type);
    var body = el('div', { class: 'card-body' });
    if (!section) { body.appendChild(el('p', { class: 'small text-muted', text: M.notYet })); }
    body.appendChild(designPicker(type, draft, section));
    if (draft.design) {
      body.appendChild(textFields(type, draft));
      body.appendChild(el('div', { class: 'invalid-feedback d-block mb-2', 'data-error-for': 'design_code' }));
      body.appendChild(el('div', { class: 'invalid-feedback d-block mb-2', 'data-error-for': 'texts' }));
    }
    // a design without pictures (services_01) has no picture slots to show
    var hasPictures = !!draft.design && Object.keys(designs[draft.design].images || {}).length > 0;
    if (hasPictures && section && section.design_code === draft.design) {
      body.appendChild(imageSlots(section));
    } else if (hasPictures && section) {
      body.appendChild(el('p', { class: 'small text-muted border-top pt-3 mt-3 mb-0' }, [el('i', { class: 'bi bi-image' }), ' ' + M.picturesFirst]));
    } else if (hasPictures) {
      body.appendChild(pendingSlots(draft));
    }
    if (draft.design) {
      var save = el('button', { type: 'button', class: 'btn btn-primary' }, [el('i', { class: 'bi bi-check-lg' }), ' ' + (section ? M.saveSection : M.addSection)]);
      save.addEventListener('click', function () { saveSection(type, save); });
      body.appendChild(el('div', { class: 'd-flex justify-content-between border-top pt-3 mt-3' }, [
        save,
        section
          ? el('button', { type: 'button', class: 'btn btn-outline-danger', onclick: function () { deleteSection(type); } }, [el('i', { class: 'bi bi-trash' }), ' ' + M.deleteSection])
          : el('button', { type: 'button', class: 'btn btn-outline-secondary', onclick: function () { adding = null; delete drafts[type]; drawSections(); } },
            [el('i', { class: 'bi bi-x-lg' }), ' ' + M.discard]),
      ]));
    }
    return el('div', { class: 'card mb-4', 'data-section-type': type }, [
      el('div', { class: 'card-header' }, [
        el('div', { class: 'd-flex align-items-center gap-2' }, [
          el('h5', { class: 'card-title mb-0', text: M.sectionTypes[type] || humanize(type) }),
          section ? null : el('span', { class: 'badge text-bg-primary', text: M.newSection }),
        ]),
        M.sectionHelp[type] ? el('p', { class: 'card-subtitle mb-0', text: M.sectionHelp[type] }) : null,
      ]),
      body,
    ]);
  }

  /** The chooser of the next section: the types the page does not have yet */
  function typeChooser(first) {
    var used = (page.sections || []).map(function (s) { return s.section_type; });
    var left = allTypes().filter(function (type) { return used.indexOf(type) === -1; });
    var body = el('div', { class: 'card-body' });
    if (!left.length) {
      body.appendChild(el('p', { class: 'small text-muted mb-0', text: M.allAdded }));
    } else if (adding) {
      body.appendChild(el('p', { class: 'small text-muted mb-0', text: M.finishFirst }));
    } else {
      var grid = el('div', { class: 'row g-3' });
      left.forEach(function (type) {
        var card = el('button', { type: 'button', class: 'card h-100 w-100 text-start wpe-design', onclick: function () {
          adding = type;
          drawSections();
          var added = document.querySelector('#sections [data-section-type="' + type + '"]');
          if (added) { added.scrollIntoView({ block: 'start', behavior: 'smooth' }); }
        } }, [
          el('div', { class: 'card-body p-3' }, [
            el('div', { class: 'fw-semibold', text: M.sectionTypes[type] || humanize(type) }),
            M.sectionHelp[type] ? el('div', { class: 'small text-muted', text: M.sectionHelp[type] }) : null,
          ]),
        ]);
        grid.appendChild(el('div', { class: 'col-sm-6 col-lg-4' }, [card]));
      });
      body.appendChild(grid);
    }
    return el('div', { class: 'card mb-4' + (first ? ' border-primary' : '') }, [
      el('div', { class: 'card-header' }, [
        el('h5', { class: 'card-title mb-0' }, [el('i', { class: 'bi bi-plus-circle' }), ' ' + (first ? M.pickFirst : M.pickNext)]),
        el('p', { class: 'card-subtitle mb-0', text: first ? M.pickFirstHelp : M.pickNextHelp }),
      ]),
      body,
    ]);
  }

  function drawSections() {
    var holder = document.getElementById('sections');
    holder.textContent = '';
    (page.sections || []).forEach(function (section) { holder.appendChild(sectionCard(section.section_type)); });
    if (adding && !sectionOf(adding)) { holder.appendChild(sectionCard(adding)); } else { adding = null; }
    holder.appendChild(typeChooser(!(page.sections || []).length && !adding));
    drawRefine();
  }

  // ---------------------------------------------------------------- the AI refinement of the page's texts

  /** The texts of the saved sections as the editor has them, in the default language, and the slots still empty */
  function refineSources() {
    var sections = [];
    var missing = [];
    (page.sections || []).forEach(function (section) {
      var draft = draftOf(section.section_type);
      var design = designs[draft.design];
      if (!design) { return; }
      var texts = {};
      Object.keys(design.texts).forEach(function (slot) {
        var text = ((draft.texts[defaultLanguage] || {})[slot]) || '';
        if (text.trim() === '') { missing.push((M.sectionTypes[section.section_type] || humanize(section.section_type)) + ' · ' + slotName(slot)); }
        texts[slot] = text;
      });
      sections.push({ section_public_id: section.public_id, design_code: draft.design, texts: texts });
    });
    return { sections: sections, missing: missing };
  }

  function drawRefine() {
    var R = M.refine;
    var body = document.getElementById('refine-body');
    if (!body) { return; }
    body.textContent = '';
    var sources = refineSources();
    var left = refineCap.count < refineCap.max;
    var countText = refineCap.max === 0 ? fill(R['not-included'], [cfg.planName])
      : (left ? fill(R.count, [refineCap.count, refineCap.max, cfg.planName, refineCap.window_days])
        : fill(R['limit-reached'], [refineCap.max, cfg.planName, refineCap.window_days]));
    if (!sources.sections.length) {
      body.appendChild(el('p', { class: 'small mb-0' }, [el('i', { class: 'bi bi-info-circle' }), ' ' + R['no-sections']]));
    } else if (sources.missing.length) {
      // the instruction, with the texts still empty
      var shown = sources.missing.slice(0, 6);
      body.appendChild(el('p', { class: 'small mb-1' }, [el('i', { class: 'bi bi-info-circle' }), ' ' + fill(R.incomplete, [languageName(defaultLanguage)])]));
      body.appendChild(el('ul', { class: 'small text-muted mb-0 ps-3', 'data-refine-missing': true },
        shown.map(function (item) { return el('li', { text: item }); })
          .concat(sources.missing.length > shown.length ? [el('li', { text: fill(R.more, [sources.missing.length - shown.length]) })] : [])));
    } else {
      var button = el('button', { type: 'button', class: 'btn btn-outline-primary w-100', disabled: refining || !left }, refining
        ? [el('span', { class: 'spinner-border spinner-border-sm me-1', role: 'status', 'aria-hidden': 'true' }), R.working]
        : [el('i', { class: 'bi bi-stars' }), ' ' + R.button]);
      button.addEventListener('click', function () { refineTexts(sources.sections); });
      body.appendChild(el('div', { class: 'd-grid' }, [button]));
    }
    body.appendChild(el('div', { class: 'form-text' + (left ? '' : ' text-warning'), text: countText }));
  }

  function refineTexts(sections) {
    if (refining || refineCap.count >= refineCap.max) { return; }
    refining = true;
    drawRefine();
    post(cfg.urls.refine, { page_public_id: page.public_id, sections: sections }).then(function (r) {
      refining = false;
      if (!r.ok) { notify('error', r.json.message || M.refine.failed); drawRefine(); return; }
      var refined = (r.json.data || {}).sections || {};
      (page.sections || []).forEach(function (section) {
        var texts = refined[section.public_id];
        if (!texts) { return; }
        var draft = draftOf(section.section_type);
        Object.keys(texts).forEach(function (code) {
          draft.texts[code] = Object.assign({}, draft.texts[code] || {}, texts[code]);
        });
      });
      if ((r.json.data || {}).text_refinement) { refineCap = r.json.data.text_refinement; } else { refineCap.count++; }
      dirty = true;
      notify('success', r.json.message || M.refine.done);
      drawSections();
    });
  }

  // ---------------------------------------------------------------- status and preview

  function drawStatus() {
    var published = page.page_status === 'P';
    var badge = document.getElementById('status-badge');
    badge.className = 'badge ' + (published ? 'text-bg-success' : 'text-bg-secondary');
    badge.textContent = published ? M.published : M.draft;
    var toggle = document.getElementById('status-toggle');
    toggle.className = 'btn ' + (published ? 'btn-outline-secondary' : 'btn-success');
    toggle.textContent = published ? M.unpublish : M.publish;
    toggle.disabled = !published && !page.publishable;
    document.getElementById('status-over-plan').hidden = !!page.publishable;
    var live = document.getElementById('page-live');
    live.hidden = !(published && page.publishable);
    live.href = cfg.urls.mart + page.path;
  }

  document.getElementById('status-toggle').addEventListener('click', function () {
    var status = page.page_status === 'P' ? 'D' : 'P';
    var button = this;
    (status === 'D' && page.is_home ? confirmAsk(M.confirmUnpublishHome) : Promise.resolve(true)).then(function (yes) {
      if (!yes) { return; }
      busy(button, true);
      post(cfg.urls.save, { page_public_id: page.public_id, page_status: status }).then(function (r) {
        busy(button, false);
        if (!r.ok) { notify('error', r.json.message || M.failed); drawStatus(); return; }
        page.page_status = r.json.data.page_status;
        notify('success', r.json.message);
        drawStatus();
      });
    });
  });

  document.getElementById('page-preview').addEventListener('click', function () {
    // open the window now, while the click still counts as the user's, then send it to the link
    var win = window.open('', '_blank');
    post(cfg.urls.preview, { page_public_id: page.public_id }).then(function (r) {
      if (!r.ok) {
        if (win) { win.close(); }
        notify('error', r.json.message || M.previewFailed);
        return;
      }
      if (win) { win.opener = null; win.location.href = r.json.data.url; } else { notify('warning', M.popupBlocked); }
    });
  });

  drawDetails();
  drawSections();
  drawStatus();
})(<?= json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>);
