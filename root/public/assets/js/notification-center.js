/**
 * The signed-in account's notifications: the header's dropdown on every page, and the notification centre page.
 * The same file is in the Admin and Client apps. Everything comes from the element with data-notification-center
 * (in the layout), whose JSON value gives:
 *   layout: 'admin' or 'client' (the dropdown items' markup differs between the two templates)
 *   urls:   latest, list, open (followed by /{public_id}), readAll, delete, deleteRead
 *   text:   unread ('{0} unread'), allCaughtUp, empty, loadFailed, delete, deleteConfirm
 *   poll:   seconds between refreshes of the dropdown while the tab is visible
 * Server times are UTC 'Y-m-d H:i:s'; they are shown relative to now in the page's language.
 */
(function () {
  'use strict';

  var root = document.querySelector('[data-notification-center]');
  if (!root) {
    return;
  }
  var cfg;
  try {
    cfg = JSON.parse(root.getAttribute('data-notification-center'));
  } catch (e) {
    return;
  }
  var locale = document.documentElement.lang || undefined;
  var badge = document.querySelector('[data-notification-badge]');
  var count = document.querySelector('[data-notification-count]');
  var list = document.querySelector('[data-notification-list]');

  function format(text, value) {
    return String(text || '').replace('{0}', value);
  }

  function notify(type, message) {
    if (!message) {
      return;
    }
    if (window.toastr) { window.toastr[type](message); } else if (type === 'error') { window.alert(message); }
  }

  function post(url, body) {
    return fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify(body || {})
    }).then(function (response) {
      return response.json().catch(function () { return {}; }).then(function (data) {
        if (!response.ok || !data.success) {
          var error = new Error(data.message || cfg.text.loadFailed);
          error.status = response.status;
          throw error;
        }
        return data;
      });
    });
  }

  function utc(value) {
    var date = new Date(String(value || '').replace(' ', 'T') + 'Z');
    return isNaN(date.getTime()) ? null : date;
  }

  function ago(value) {
    var date = utc(value);
    if (!date) {
      return '';
    }
    var seconds = Math.round((date.getTime() - Date.now()) / 1000);
    var units = [['year', 31536000], ['month', 2592000], ['week', 604800], ['day', 86400], ['hour', 3600], ['minute', 60]];
    var rtf = window.Intl && Intl.RelativeTimeFormat ? new Intl.RelativeTimeFormat(locale, { numeric: 'auto' }) : null;
    for (var i = 0; i < units.length; i++) {
      if (Math.abs(seconds) >= units[i][1]) {
        var amount = Math.round(seconds / units[i][1]);
        return rtf ? rtf.format(amount, units[i][0]) : date.toLocaleString(locale);
      }
    }
    return rtf ? rtf.format(0, 'second') : date.toLocaleString(locale);
  }

  function el(tag, className, text) {
    var node = document.createElement(tag);
    if (className) { node.className = className; }
    if (text !== undefined && text !== null) { node.textContent = text; }
    return node;
  }

  function icon(n) {
    var box = el('span', 'notification-icon ' + n.tone);
    box.appendChild(el('i', 'bi ' + n.icon));
    return box;
  }

  function time(n) {
    var node = el('span', 'notification-time', ago(n.created_at));
    var date = utc(n.created_at);
    if (date) { node.title = date.toLocaleString(locale); }
    return node;
  }

  function content(n, withBusiness) {
    var box = el('span', 'notification-content');
    box.appendChild(el('span', 'notification-title d-block', n.title));
    box.appendChild(el('span', 'notification-text d-block', n.message));
    if (withBusiness && n.business && n.business.business_name) {
      box.appendChild(el('span', 'notification-text d-block small fst-italic', n.business.business_name));
    }
    return box;
  }

  /** One notification as a link that opens it (marks it read and goes to its page) */
  function item(n, forPage) {
    var link = el('a', 'notification-item text-decoration-none' + (n.is_read ? '' : ' unread'));
    link.href = cfg.urls.open + '/' + encodeURIComponent(n.public_id);
    link.setAttribute('data-public-id', n.public_id);
    var withBusiness = cfg.layout === 'client';
    if (cfg.layout === 'client' && !forPage) {
      link.appendChild(icon(n));
      link.appendChild(content(n, withBusiness));
      var meta = el('span', 'notification-meta');
      meta.appendChild(time(n));
      if (!n.is_read) { meta.appendChild(el('span', 'notification-dot')); }
      link.appendChild(meta);
      return link;
    }
    if (!n.is_read) { link.appendChild(el('span', 'notification-dot')); }
    link.appendChild(icon(n));
    var body = content(n, withBusiness);
    body.appendChild(time(n));
    body.classList.add('flex-grow-1');
    link.appendChild(body);
    return link;
  }

  /* ---------- the header's dropdown ---------- */

  function showCount(unread) {
    if (badge) {
      badge.textContent = unread > 99 ? '99+' : String(unread);
      badge.classList.toggle('d-none', unread === 0);
    }
    if (count) {
      count.textContent = unread === 0 ? cfg.text.allCaughtUp : format(cfg.text.unread, unread);
    }
  }

  function renderDropdown(data) {
    showCount(data.unread_count || 0);
    if (!list) {
      return;
    }
    list.textContent = '';
    if (!data.rows || data.rows.length === 0) {
      list.appendChild(el('div', 'notification-item text-muted small justify-content-center', cfg.text.empty));
      return;
    }
    data.rows.forEach(function (n) { list.appendChild(item(n, false)); });
  }

  var stopped = false;
  var pending = null;
  var lastRefresh = 0;
  /** Ask once at a time; a refresh asked for while one is under way shares it */
  function refresh() {
    if (stopped) {
      return Promise.resolve();
    }
    if (pending) {
      return pending;
    }
    lastRefresh = Date.now();
    pending = post(cfg.urls.latest).then(function (response) {
      renderDropdown(response.data);
    }).catch(function (e) {
      // signed out or the session ended: stop asking until the page is loaded again
      if (e.status === 401 || e.status === 403) { stopped = true; }
    }).then(function () {
      pending = null;
    });
    return pending;
  }

  document.querySelectorAll('[data-notification-action="mark-all-read"]').forEach(function (button) {
    button.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      post(cfg.urls.readAll).then(function (response) {
        notify('success', response.message);
        refresh();
        if (page) { page.reload(); }
      }).catch(function (err) { notify('error', err.message); });
    });
  });

  refresh();
  setInterval(function () { if (!document.hidden) { refresh(); } }, Math.max(15, cfg.poll || 60) * 1000);
  // back to the tab: refresh at once, unless it was refreshed only moments ago
  document.addEventListener('visibilitychange', function () {
    if (!document.hidden && Date.now() - lastRefresh > 10000) { refresh(); }
  });

  /* ---------- the notification centre page ---------- */

  var page = (function () {
    var box = document.querySelector('[data-notification-page]');
    if (!box) {
      return null;
    }
    var rows = box.querySelector('[data-notification-rows]');
    var more = box.querySelector('[data-notification-more]');
    var summary = box.querySelector('[data-notification-summary]');
    var status = '';
    var start = 0;
    var length = 20;

    function row(n) {
      var wrap = el('div', 'd-flex align-items-start border-bottom');
      var link = item(n, true);
      link.classList.add('flex-grow-1', 'border-0');
      wrap.appendChild(link);
      var remove = el('button', 'btn btn-sm btn-link text-secondary mt-2 me-2');
      remove.type = 'button';
      remove.title = cfg.text.delete;
      remove.setAttribute('aria-label', cfg.text.delete);
      remove.appendChild(el('i', 'bi bi-x-lg'));
      remove.addEventListener('click', function () {
        if (!window.confirm(cfg.text.deleteConfirm)) {
          return;
        }
        remove.disabled = true;
        post(cfg.urls.delete, { public_id: n.public_id }).then(function (response) {
          wrap.remove();
          start = Math.max(0, start - 1);
          showCount(response.data.unread_count || 0);
          refresh();
          if (!rows.children.length) { load(true); }
        }).catch(function (err) { remove.disabled = false; notify('error', err.message); });
      });
      wrap.appendChild(remove);
      return wrap;
    }

    function load(reset) {
      if (reset) {
        start = 0;
        rows.textContent = '';
      }
      more.disabled = true;
      return post(cfg.urls.list, { start: start, length: length, status: status }).then(function (response) {
        var data = response.data;
        data.rows.forEach(function (n) { rows.appendChild(row(n)); });
        start += data.rows.length;
        if (!rows.children.length) {
          rows.appendChild(el('div', 'text-center text-muted py-5', cfg.text.empty));
        }
        more.classList.toggle('d-none', start >= data.records_filtered);
        more.disabled = false;
        if (summary) { summary.textContent = data.unread_count === 0 ? cfg.text.allCaughtUp : format(cfg.text.unread, data.unread_count); }
      }).catch(function (err) {
        more.disabled = false;
        notify('error', err.message);
      });
    }

    box.querySelectorAll('[data-notification-status]').forEach(function (tab) {
      tab.addEventListener('click', function () {
        box.querySelectorAll('[data-notification-status]').forEach(function (t) { t.classList.remove('active'); });
        tab.classList.add('active');
        status = tab.getAttribute('data-notification-status');
        load(true);
      });
    });
    more.addEventListener('click', function () { load(false); });
    var deleteRead = box.querySelector('[data-notification-action="delete-read"]');
    if (deleteRead) {
      deleteRead.addEventListener('click', function () {
        post(cfg.urls.deleteRead).then(function (response) {
          notify('success', response.message);
          load(true);
        }).catch(function (err) { notify('error', err.message); });
      });
    }
    load(true);

    return { reload: function () { load(true); } };
  })();

  window.OtternovaNotifications = { refresh: refresh };
})();
