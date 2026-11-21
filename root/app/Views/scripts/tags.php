<?php
/**
 * The script of tags/index.php. It keeps the tags in one list and draws them as chips, adds one typed in the box (on
 * Enter, a comma, or the Add button; several at once when pasted with commas), suggests the tags other businesses use
 * as one is typed (so the same words are used, and a search finds them all), and saves the list with one button.
 * Tags told apart only by case or spaces count once, as the API keeps them. Errors come back by the API's field
 * names: tags.<index> for a tag, tags for the list.
 *
 * @var list<string> $tags
 * @var array        $options max_tags, max_length
 * @var array        $urls    Tags::index() urls
 */
$config = [
    'tags'      => $tags,
    'maxTags'   => (int) ($options['max_tags'] ?? 20),
    'maxLength' => (int) ($options['max_length'] ?? 50),
    'urls'      => $urls,
    'csrf'      => ['header' => csrf_header(), 'hash' => csrf_hash()],
    'messages'  => [
        'remove'      => lang('Tags.remove'),
        'count'       => lang('Tags.count'),
        'tooMany'     => lang('Tags.too-many'),
        'already'     => lang('Tags.already'),
        'invalid'     => lang('Tags.invalid'),
        'usedBy'      => lang('Tags.used-by'),
        'saved'       => lang('Tags.saved'),
        'saveFailed'  => lang('Tags.save-failed'),
        'saving'      => lang('Tags.saving'),
        'save'        => lang('Tags.save'),
        'leave'       => lang('Tags.unsaved-leave'),
    ],
];
?>
(function (cfg) {
  'use strict';

  var M = cfg.messages;
  var page = document.getElementById('tags-page');
  var listNode = document.getElementById('tags-list');
  var input = document.getElementById('tags-input');
  var addError = document.getElementById('tags-add-error');
  var suggestNode = document.getElementById('tags-suggest');
  var saveButton = document.getElementById('tags-save');
  var csrfHash = cfg.csrf.hash;
  var tags = cfg.tags.slice();
  var dirty = false;
  var suggestions = [];
  var active = -1;
  var suggestTimer = null;
  var suggestSeq = 0;
  // the API's rules (TagMasterModel::clean()): letters with their marks, digits, and - ' & . or spaces between them
  var PATTERN = /^[\p{L}\p{N}][\p{L}\p{M}\p{N} '&.\-]*$/u;

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
    document.getElementById('tags-dirty').hidden = false;
  }

  /** A tag tidied as the API does: a leading # dropped, spaces made single */
  function tidy(text) { return String(text).normalize('NFC').replace(/\s+/g, ' ').trim().replace(/^#+/, '').trim(); }

  /** The key a tag is told apart by: case aside */
  function keyOf(text) { return tidy(text).toLocaleLowerCase(); }

  function has(text) {
    var key = keyOf(text);
    return tags.some(function (t) { return keyOf(t) === key; });
  }

  function showAddError(text) {
    addError.textContent = text || '';
    input.classList.toggle('is-invalid', !!text);
  }

  // ---------------------------------------------------------------- errors

  function clearErrors() {
    Array.prototype.forEach.call(page.querySelectorAll('[data-error-for]'), function (node) { node.textContent = ''; });
    Array.prototype.forEach.call(page.querySelectorAll('.tg-chip.is-invalid'), function (node) { node.classList.remove('is-invalid'); });
  }

  function showErrors(errors) {
    clearErrors();
    var messages = [];
    Object.keys(errors || {}).forEach(function (key) {
      var match = /^tags\.(\d+)$/.exec(key);
      if (match) {
        var chip = listNode.querySelector('[data-index="' + match[1] + '"]');
        if (chip) { chip.classList.add('is-invalid'); }
        messages.push((tags[+match[1]] || '') + ': ' + errors[key]);
      } else {
        messages.push(errors[key]);
      }
    });
    page.querySelector('[data-error-for="tags"]').textContent = messages.join(' ');
  }

  // ---------------------------------------------------------------- the list

  function draw() {
    listNode.textContent = '';
    tags.forEach(function (tag, i) {
      listNode.appendChild(el('span', { class: 'tg-chip', 'data-index': i }, [
        el('i', { class: 'bi bi-tag', 'aria-hidden': 'true' }),
        el('span', { text: tag }),
        el('button', { type: 'button', title: M.remove, 'aria-label': M.remove + ' ' + tag,
          onclick: function () { tags.splice(i, 1); markDirty(); clearErrors(); draw(); input.focus(); } }, [el('i', { class: 'bi bi-x-lg' })]),
      ]));
    });
    document.getElementById('tags-empty').classList.toggle('d-none', tags.length > 0);
    document.getElementById('tags-count').textContent = M.count.replace('{0}', tags.length).replace('{1}', cfg.maxTags);
    var full = tags.length >= cfg.maxTags;
    input.disabled = full;
    document.getElementById('tags-add-button').disabled = full;
    if (full) { hideSuggestions(); }
  }

  /** Add the tags of the text (split at commas); false when one could not be added, which stays in the box */
  function add(text) {
    var left = [];
    var error = '';
    String(text).split(/[,，、]/).forEach(function (part) {
      var tag = tidy(part);
      if (tag === '') { return; }
      if (has(tag)) { error = error || M.already.replace('{0}', tag); return; }
      if (tags.length >= cfg.maxTags) { left.push(tag); error = error || M.tooMany.replace('{0}', cfg.maxTags); return; }
      if ([...tag].length > cfg.maxLength || !PATTERN.test(tag)) { left.push(tag); error = error || M.invalid.replace('{0}', cfg.maxLength); return; }
      tags.push(tag);
      markDirty();
    });
    input.value = left.join(', ');
    showAddError(error);
    hideSuggestions();
    draw();
    return left.length === 0;
  }

  // ---------------------------------------------------------------- suggestions

  function hideSuggestions() {
    suggestions = [];
    active = -1;
    suggestNode.textContent = '';
    suggestNode.hidden = true;
    input.setAttribute('aria-expanded', 'false');
    input.removeAttribute('aria-activedescendant');
  }

  function drawSuggestions() {
    suggestNode.textContent = '';
    suggestions.forEach(function (s, i) {
      suggestNode.appendChild(el('button', { type: 'button', role: 'option', id: 'tags-suggest-' + i,
        class: 'list-group-item list-group-item-action d-flex justify-content-between align-items-center' + (i === active ? ' active' : ''),
        'aria-selected': i === active ? 'true' : 'false',
        onmousedown: function (event) { event.preventDefault(); }, // keep the focus in the box
        onclick: function () { add(s.tag_name); input.focus(); } }, [
        el('span', { text: s.tag_name }),
        el('small', { class: i === active ? '' : 'text-muted', text: M.usedBy.replace('{0}', s.business_count) }),
      ]));
    });
    suggestNode.hidden = suggestions.length === 0;
    input.setAttribute('aria-expanded', suggestions.length ? 'true' : 'false');
    if (active >= 0) { input.setAttribute('aria-activedescendant', 'tags-suggest-' + active); } else { input.removeAttribute('aria-activedescendant'); }
  }

  function fetchSuggestions() {
    var q = tidy(input.value.split(/[,，、]/).pop());
    if (q === '') { hideSuggestions(); return; }
    var seq = ++suggestSeq;
    post(cfg.urls.suggest, { q: q }).then(function (result) {
      if (seq !== suggestSeq || document.activeElement !== input) { return; } // an older answer, or the box was left
      suggestions = (result.ok ? result.json.suggestions || [] : []).filter(function (s) { return !has(s.tag_name); });
      active = -1;
      drawSuggestions();
    });
  }

  // ---------------------------------------------------------------- save

  function save() {
    if (tidy(input.value) !== '' && !add(input.value)) { return; } // what is typed counts, if it can be added
    saveButton.disabled = true;
    saveButton.lastChild.textContent = ' ' + M.saving;
    post(cfg.urls.save, { tags: tags }).then(function (result) {
      saveButton.disabled = false;
      saveButton.lastChild.textContent = ' ' + M.save;
      if (!result.ok) {
        showErrors(result.json.errors || {});
        notify('error', result.json.message || M.saveFailed);
        return;
      }
      // the API gives back the tags as they are kept: one that existed as it was first written
      tags = (result.json.tags || []).slice();
      dirty = false;
      document.getElementById('tags-dirty').hidden = true;
      clearErrors();
      draw();
      notify('success', result.json.message || M.saved);
    });
  }

  window.addEventListener('beforeunload', function (event) {
    if (dirty) { event.preventDefault(); event.returnValue = M.leave; }
  });

  // ---------------------------------------------------------------- start

  input.addEventListener('keydown', function (event) {
    if (event.isComposing) { return; } // an IME (Thai, Japanese, Chinese) is still choosing the characters
    if (event.key === 'ArrowDown' && suggestions.length) {
      event.preventDefault(); active = (active + 1) % suggestions.length; drawSuggestions();
    } else if (event.key === 'ArrowUp' && suggestions.length) {
      event.preventDefault(); active = (active - 1 + suggestions.length) % suggestions.length; drawSuggestions();
    } else if (event.key === 'Enter') {
      event.preventDefault();
      add(active >= 0 ? suggestions[active].tag_name : input.value);
    } else if (event.key === 'Escape') {
      hideSuggestions();
    } else if (event.key === 'Backspace' && input.value === '' && tags.length) {
      tags.pop(); markDirty(); draw();
    }
  });
  input.addEventListener('input', function () {
    showAddError('');
    if (/[,，、]/.test(input.value)) { add(input.value); return; }
    clearTimeout(suggestTimer);
    suggestTimer = setTimeout(fetchSuggestions, 250);
  });
  input.addEventListener('blur', function () { setTimeout(hideSuggestions, 100); });
  document.getElementById('tags-add-button').addEventListener('click', function () { add(input.value); input.focus(); });
  saveButton.addEventListener('click', save);
  draw();
})(<?= json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>);
