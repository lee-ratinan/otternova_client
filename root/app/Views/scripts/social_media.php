<?php
/**
 * The script of social_media/index.php. It keeps the links in one list, in the order shown, draws a row for each (its
 * address editable, moved up or down, removed), adds a platform not yet in the list with the row under it, and saves
 * the whole list with one button. Errors come back by the API's field names: links.<index> for a row, links for the
 * list.
 *
 * @var array $links     v1/client/social-links links
 * @var array $platforms { platform: name }
 * @var array $urls      SocialMedia::index() urls
 */
use App\Controllers\SocialMedia;

$config = [
    'links'     => array_map(static fn (array $l): array => ['platform' => (string) ($l['platform'] ?? ''), 'profile_url' => (string) ($l['profile_url'] ?? '')], $links),
    'platforms' => (object) $platforms,
    'examples'  => SocialMedia::EXAMPLES,
    'urls'      => $urls,
    'csrf'      => ['header' => csrf_header(), 'hash' => csrf_hash()],
    'messages'  => [
        'moveUp'      => lang('SocialMedia.move-up'),
        'moveDown'    => lang('SocialMedia.move-down'),
        'remove'      => lang('SocialMedia.remove'),
        'open'        => lang('SocialMedia.open'),
        'url'         => lang('SocialMedia.url'),
        'urlRequired' => lang('SocialMedia.url-required'),
        'saved'       => lang('SocialMedia.saved'),
        'saveFailed'  => lang('SocialMedia.save-failed'),
        'saving'      => lang('SocialMedia.saving'),
        'save'        => lang('SocialMedia.save'),
        'leave'       => lang('SocialMedia.unsaved-leave'),
    ],
];
?>
(function (cfg) {
  'use strict';

  var M = cfg.messages;
  var page = document.getElementById('social-media-page');
  var list = document.getElementById('social-list');
  var addPlatform = document.getElementById('social-add-platform');
  var addUrl = document.getElementById('social-add-url');
  var addError = document.getElementById('social-add-error');
  var saveButton = document.getElementById('social-save');
  var csrfHash = cfg.csrf.hash;
  var links = cfg.links.slice();
  var dirty = false;

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

  function markDirty() {
    dirty = true;
    document.getElementById('social-dirty').hidden = false;
  }

  function nameOf(platform) { return cfg.platforms[platform] || platform; }

  /** A link the browser may open: only http(s), so a typed javascript: address never becomes a link */
  function openable(url) { return /^https?:\/\//i.test(url) ? url : (url ? 'https://' + url : null); }

  // ---------------------------------------------------------------- errors

  function clearErrors() {
    Array.prototype.forEach.call(page.querySelectorAll('[data-error-for]'), function (node) { node.textContent = ''; });
    Array.prototype.forEach.call(page.querySelectorAll('.is-invalid'), function (node) { node.classList.remove('is-invalid'); });
  }

  function showErrors(errors) {
    clearErrors();
    Object.keys(errors || {}).forEach(function (key) {
      var target = page.querySelector('[data-error-for="' + key + '"]');
      if (target) { target.textContent = errors[key]; }
      var input = page.querySelector('[data-input-for="' + key + '"]');
      if (input) { input.classList.add('is-invalid'); }
    });
    var first = page.querySelector('[data-error-for]:not(:empty)');
    if (first) { first.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
  }

  // ---------------------------------------------------------------- the list

  function move(i, by) {
    var j = i + by;
    if (j < 0 || j >= links.length) { return; }
    var link = links[i];
    links[i] = links[j];
    links[j] = link;
    markDirty();
    draw();
  }

  function draw() {
    list.textContent = '';
    links.forEach(function (link, i) {
      var key = 'links.' + i;
      var input = el('input', { type: 'url', class: 'form-control', value: link.profile_url, maxlength: 255, spellcheck: 'false', autocomplete: 'off',
        inputmode: 'url', placeholder: cfg.examples[link.platform] || '', 'aria-label': nameOf(link.platform) + ' ' + M.url, 'data-input-for': key,
        oninput: function () { link.profile_url = input.value; markDirty(); } });
      var href = openable(link.profile_url);
      list.appendChild(el('li', { class: 'list-group-item' }, [
        el('div', { class: 'd-flex flex-wrap align-items-center gap-2' }, [
          el('div', { class: 'input-group flex-grow-1', style: 'min-width: 16rem' }, [
            el('span', { class: 'input-group-text sm-icon', title: nameOf(link.platform) }, [el('i', { class: 'bi bi-' + link.platform, 'aria-hidden': 'true' })]),
            el('span', { class: 'input-group-text d-none d-sm-flex', text: nameOf(link.platform) }),
            input,
            href ? el('a', { class: 'btn btn-outline-secondary', href: href, target: '_blank', rel: 'noopener noreferrer', title: M.open, 'aria-label': M.open },
              [el('i', { class: 'bi bi-box-arrow-up-right' })]) : null,
          ]),
          el('div', { class: 'btn-group btn-group-sm' }, [
            el('button', { type: 'button', class: 'btn btn-outline-secondary', title: M.moveUp, 'aria-label': M.moveUp, disabled: i === 0,
              onclick: function () { move(i, -1); } }, [el('i', { class: 'bi bi-arrow-up' })]),
            el('button', { type: 'button', class: 'btn btn-outline-secondary', title: M.moveDown, 'aria-label': M.moveDown, disabled: i === links.length - 1,
              onclick: function () { move(i, 1); } }, [el('i', { class: 'bi bi-arrow-down' })]),
            el('button', { type: 'button', class: 'btn btn-outline-danger', title: M.remove, 'aria-label': M.remove,
              onclick: function () { links.splice(i, 1); markDirty(); draw(); } }, [el('i', { class: 'bi bi-trash' })]),
          ]),
        ]),
        el('div', { class: 'invalid-feedback d-block', 'data-error-for': key }),
      ]));
    });
    document.getElementById('social-empty').classList.toggle('d-none', links.length > 0);
    drawAdd();
  }

  /** The add row offers only the platforms not in the list yet */
  function drawAdd() {
    var used = {};
    links.forEach(function (link) { used[link.platform] = true; });
    var chosen = addPlatform.value;
    addPlatform.textContent = '';
    Object.keys(cfg.platforms).forEach(function (platform) {
      if (!used[platform]) { addPlatform.appendChild(el('option', { value: platform, text: nameOf(platform), selected: platform === chosen })); }
    });
    var none = addPlatform.options.length === 0;
    document.getElementById('social-add-group').classList.toggle('d-none', none);
    document.getElementById('social-add-heading').classList.toggle('d-none', none);
    document.getElementById('social-all-added').classList.toggle('d-none', !none);
    addUrl.placeholder = cfg.examples[addPlatform.value] || '';
  }

  function add() {
    var url = addUrl.value.trim();
    if (!addPlatform.value) { return; }
    if (url === '') {
      addUrl.classList.add('is-invalid');
      addError.textContent = M.urlRequired;
      addUrl.focus();
      return;
    }
    addUrl.classList.remove('is-invalid');
    addError.textContent = '';
    links.push({ platform: addPlatform.value, profile_url: url });
    addUrl.value = '';
    markDirty();
    draw();
  }

  // ---------------------------------------------------------------- save

  function save() {
    saveButton.disabled = true;
    saveButton.lastChild.textContent = ' ' + M.saving;
    post(cfg.urls.save, { links: links }).then(function (result) {
      saveButton.disabled = false;
      saveButton.lastChild.textContent = ' ' + M.save;
      if (!result.ok) {
        showErrors(result.json.errors || {});
        notify('error', result.json.message || M.saveFailed);
        return;
      }
      // the API gives the addresses back cleaned up (https, no tracking parameters)
      links = (result.json.links || []).map(function (l) { return { platform: l.platform, profile_url: l.profile_url }; });
      dirty = false;
      document.getElementById('social-dirty').hidden = true;
      clearErrors();
      draw();
      notify('success', result.json.message || M.saved);
    });
  }

  window.addEventListener('beforeunload', function (event) {
    if (dirty) { event.preventDefault(); event.returnValue = M.leave; }
  });

  // ---------------------------------------------------------------- start

  addPlatform.addEventListener('change', function () { addUrl.placeholder = cfg.examples[addPlatform.value] || ''; });
  addUrl.addEventListener('keydown', function (event) { if (event.key === 'Enter') { event.preventDefault(); add(); } });
  document.getElementById('social-add-button').addEventListener('click', add);
  saveButton.addEventListener('click', save);
  draw();
})(<?= json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>);
