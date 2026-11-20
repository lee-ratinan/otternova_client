<?php
/**
 * The script of webpages/index.php: draws the list of pages from the API's answer and keeps it in step after each
 * action. Creating the first page makes the home page at once; after it, the modal asks for the names and address.
 * Errors come back by the API's field names, which the fields carry in data-error-for.
 *
 * @var list<array> $pages
 * @var array       $options
 * @var array       $urls
 */
$config = [
    'pages'    => $pages,
    'options'  => $options,
    'urls'     => $urls,
    'csrf'     => ['header' => csrf_header(), 'hash' => csrf_hash()],
    'messages' => [
        'createHome'      => lang('Webpages.create.home'),
        'createPage'      => lang('Webpages.create.page'),
        'count'           => lang('Webpages.list.count'),
        'limitReached'    => lang('Webpages.list.limit-reached'),
        'noWebsite'       => lang('Webpages.list.create-no-website'),
        'noPlan'          => lang('Webpages.list.create-no-plan'),
        'home'            => lang('Webpages.list.home'),
        'draft'           => lang('Webpages.status.D'),
        'published'       => lang('Webpages.status.P'),
        'overPlan'        => lang('Webpages.list.over-plan'),
        'edit'            => lang('Webpages.list.edit'),
        'preview'         => lang('Webpages.list.preview'),
        'publish'         => lang('Webpages.list.publish'),
        'unpublish'       => lang('Webpages.list.unpublish'),
        'delete'          => lang('Webpages.list.delete'),
        'moveUp'          => lang('Webpages.list.move-up'),
        'moveDown'        => lang('Webpages.list.move-down'),
        'view'            => lang('Webpages.list.view'),
        'confirmDelete'   => lang('Webpages.list.confirm-delete'),
        'confirmUnpublishHome' => lang('Webpages.list.confirm-unpublish-home'),
        'confirmYes'      => lang('Webpages.yes'),
        'confirmNo'       => lang('Webpages.cancel'),
        'failed'          => lang('Webpages.save-failed'),
        'previewFailed'   => lang('Webpages.preview-failed'),
        'popupBlocked'    => lang('Webpages.popup-blocked'),
        'images'          => lang('Webpages.list.images'),
    ],
];
?>
(function (cfg) {
  'use strict';

  var M = cfg.messages;
  var options = cfg.options || {};
  var languages = options.languages || [];
  var defaultLanguage = languages[0] || null;
  var maxPages = options.max_pages || 0;
  var pages = cfg.pages || [];
  var csrfHash = cfg.csrf.hash;
  var root = document.getElementById('webpages-page');
  var modalNode = document.getElementById('create-modal');
  // this script runs before the layout loads Bootstrap: take the modal when it is first opened
  function modal() { return window.bootstrap ? window.bootstrap.Modal.getOrCreateInstance(modalNode) : null; }

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

  /** POST JSON and resolve with { ok, json } */
  function post(url, body) {
    var headers = { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    headers[cfg.csrf.header] = csrfHash;
    return fetch(url, { method: 'POST', headers: headers, credentials: 'same-origin', body: JSON.stringify(body || {}) })
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

  function clearErrors(scope) {
    Array.prototype.forEach.call(scope.querySelectorAll('[data-error-for]'), function (node) { node.textContent = ''; });
    Array.prototype.forEach.call(scope.querySelectorAll('.is-invalid'), function (node) { node.classList.remove('is-invalid'); });
  }

  function showErrors(scope, errors) {
    clearErrors(scope);
    Object.keys(errors || {}).forEach(function (key) {
      var target = scope.querySelector('[data-error-for="' + key + '"]');
      if (target) { target.textContent = errors[key]; }
      var input = scope.querySelector('[data-input-for="' + key + '"]');
      if (input) { input.classList.add('is-invalid'); }
    });
  }

  /** A page's name to show: the home page's word, else its name in the default language, else any */
  function nameOf(page) {
    if (page.is_home) { return (options.home_names || {})[defaultLanguage] || M.home; }
    var names = page.names || {};
    var name = names[defaultLanguage] || names[Object.keys(names)[0]] || {};
    return name.page_name || page.page_slug;
  }

  function openPreview(page) {
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
  }

  function setStatus(page, status) {
    var ask = status === 'D' && page.is_home ? confirmAsk(M.confirmUnpublishHome) : Promise.resolve(true);
    ask.then(function (yes) {
      if (!yes) { return; }
      post(cfg.urls.save, { page_public_id: page.public_id, page_status: status }).then(function (r) {
        if (!r.ok) { notify('error', r.json.message || M.failed); return; }
        Object.assign(page, { page_status: r.json.data.page_status });
        notify('success', r.json.message);
        draw();
      });
    });
  }

  function remove(page) {
    confirmAsk(M.confirmDelete.replace('{0}', nameOf(page))).then(function (yes) {
      if (!yes) { return; }
      post(cfg.urls.delete, { page_public_id: page.public_id }).then(function (r) {
        if (!r.ok) { notify('error', r.json.message || M.failed); return; }
        pages = pages.filter(function (p) { return p.public_id !== page.public_id; });
        notify('success', r.json.message);
        draw();
      });
    });
  }

  function move(index, by) {
    var order = pages.map(function (p) { return p.public_id; });
    var target = index + by;
    if (target < 1 || target >= order.length) { return; } // the home page stays first
    order.splice(target, 0, order.splice(index, 1)[0]);
    post(cfg.urls.reorder, { order: order }).then(function (r) {
      if (!r.ok) { notify('error', r.json.message || M.failed); return; }
      pages = r.json.data.pages;
      draw();
    });
  }

  function iconButton(icon, label, onclick, variant) {
    return el('button', { type: 'button', class: 'btn btn-sm ' + (variant || 'btn-outline-secondary'), title: label, 'aria-label': label, onclick: onclick }, [el('i', { class: 'bi ' + icon })]);
  }

  function draw() {
    var body = document.getElementById('pages-body');
    body.textContent = '';
    pages.forEach(function (page, index) {
      var published = page.page_status === 'P';
      var status = el('span', { class: 'badge ' + (published ? 'text-bg-success' : 'text-bg-secondary'), text: published ? M.published : M.draft });
      var over = page.publishable ? null : el('div', { class: 'small text-warning mt-1' }, [el('i', { class: 'bi bi-exclamation-triangle' }), ' ' + M.overPlan]);
      var address = cfg.urls.mart + page.path;
      var actions = el('div', { class: 'd-flex justify-content-end flex-wrap gap-1' }, [
        el('a', { class: 'btn btn-sm btn-primary', href: cfg.urls.edit + encodeURIComponent(page.public_id) }, [el('i', { class: 'bi bi-pencil' }), ' ' + M.edit]),
        iconButton('bi-eye', M.preview, function () { openPreview(page); }),
        published
          ? el('button', { type: 'button', class: 'btn btn-sm btn-outline-secondary', onclick: function () { setStatus(page, 'D'); }, text: M.unpublish })
          : el('button', { type: 'button', class: 'btn btn-sm btn-outline-success', disabled: !page.publishable, onclick: function () { setStatus(page, 'P'); }, text: M.publish }),
        page.is_home ? null : iconButton('bi-trash', M.delete, function () { remove(page); }, 'btn-outline-danger'),
      ]);
      var order = page.is_home ? el('span', { class: 'text-muted' }, [el('i', { class: 'bi bi-house-door' })]) : el('div', { class: 'btn-group btn-group-sm' }, [
        index > 1 ? iconButton('bi-arrow-up', M.moveUp, function () { move(index, -1); }) : null,
        index < pages.length - 1 ? iconButton('bi-arrow-down', M.moveDown, function () { move(index, 1); }) : null,
      ]);
      body.appendChild(el('tr', {}, [
        el('td', {}, [order]),
        el('td', {}, [
          el('div', { class: 'fw-semibold', text: nameOf(page) }),
          el('div', { class: 'small text-muted', text: M.images.replace('{0}', page.image_count || 0) }),
        ]),
        el('td', {}, [published && page.publishable
          ? el('a', { href: address, target: '_blank', rel: 'noopener', class: 'small font-monospace', text: '/' + page.path })
          : el('span', { class: 'small font-monospace text-muted', text: '/' + page.path })]),
        el('td', {}, [status, over]),
        el('td', {}, [actions]),
      ]));
    });
    document.getElementById('pages-empty').hidden = pages.length > 0;
    document.getElementById('pages-table').hidden = pages.length === 0;
    document.getElementById('page-count').textContent = M.count.replace('{0}', pages.length).replace('{1}', maxPages);
    document.getElementById('page-create-label').textContent = pages.length === 0 ? M.createHome : M.createPage;
    var create = document.getElementById('page-create');
    var reason = createBlocked();
    create.disabled = !!reason;
    create.title = reason || '';
    document.getElementById('page-create-reason-text').textContent = reason || '';
    document.getElementById('page-create-reason').classList.toggle('d-none', !reason);
  }

  /** Why no page can be added now, or null when one can */
  function createBlocked() {
    if (!options.website_ready) { return M.noWebsite; }
    if (maxPages <= 0) { return M.noPlan; }
    if (pages.length >= maxPages) { return M.limitReached.replace('{0}', maxPages); }
    return null;
  }

  function created(r) {
    if (!r.ok) { return false; }
    window.location.href = cfg.urls.edit + encodeURIComponent(r.json.data.public_id);
    return true;
  }

  document.getElementById('page-create').addEventListener('click', function () {
    if (createBlocked()) { draw(); return; }
    if (pages.length === 0) {
      // the first page is always the home page: no name, no address to ask for
      this.disabled = true;
      var button = this;
      post(cfg.urls.create, {}).then(function (r) {
        if (!created(r)) { button.disabled = false; notify('error', r.json.message || M.failed); }
      });
      return;
    }
    clearErrors(modalNode);
    document.getElementById('create-form').reset();
    var dialog = modal();
    if (dialog) { dialog.show(); }
  });

  document.getElementById('create-form').addEventListener('submit', function (event) {
    event.preventDefault();
    var names = [];
    Array.prototype.forEach.call(modalNode.querySelectorAll('[data-name-language]'), function (input) {
      if (input.value.trim() !== '') { names.push({ language_code: input.getAttribute('data-name-language'), page_name: input.value }); }
    });
    // the error keys are by the place in "names", which leaves the empty ones out: point them at their fields
    Array.prototype.forEach.call(modalNode.querySelectorAll('[data-name-language]'), function (input) {
      var place = names.findIndex(function (n) { return n.language_code === input.getAttribute('data-name-language'); });
      input.setAttribute('data-input-for', 'names.' + place + '.page_name');
      input.nextElementSibling.setAttribute('data-error-for', 'names.' + place + '.page_name');
    });
    var body = { names: names };
    var slug = document.getElementById('create-slug').value.trim();
    if (slug !== '') { body.page_slug = slug; }
    var submit = document.getElementById('create-submit');
    submit.disabled = true;
    post(cfg.urls.create, body).then(function (r) {
      submit.disabled = false;
      if (created(r)) { return; }
      showErrors(modalNode, r.json.errors);
      if (!r.json.errors) { notify('error', r.json.message || M.failed); }
    });
  });

  draw();
})(<?= json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>);
