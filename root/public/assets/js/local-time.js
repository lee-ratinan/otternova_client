/**
 * Show UTC datetimes from the API in the viewer's local timezone.
 * Markup (see localTimeTag() in user_helper.php): <time datetime="2026-11-01T10:30:00Z" data-local-time="datetime">
 */
(function () {
  'use strict';

  var locale = document.documentElement.lang || undefined;
  var formats = {
    date: { dateStyle: 'medium' },
    datetime: { dateStyle: 'medium', timeStyle: 'short' }
  };

  function formatAll(root) {
    Array.prototype.forEach.call((root || document).querySelectorAll('time[data-local-time]'), function (el) {
      var date = new Date(el.getAttribute('datetime'));
      if (isNaN(date.getTime())) {
        return;
      }
      var options = formats[el.dataset.localTime] || formats.datetime;
      el.textContent = new Intl.DateTimeFormat(locale, options).format(date);
      el.title = date.toISOString();
    });
  }

  window.formatLocalTimes = formatAll;
  document.addEventListener('DOMContentLoaded', function () { formatAll(); });
})();
