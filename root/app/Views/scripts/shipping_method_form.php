<?php
/**
 * customScript of the shipping method form (see generic_form.php): the fee brackets editor (shipping/rates_editor),
 * one row per bracket (from, up to, fee, and optionally an extra fee for every step above "from"), kept as JSON in
 * the hidden field the form submits. The brackets are kept in order of "from" once the focus leaves the table, and numbered as
 * they are sent (empty rows are left out and not numbered), so the API's "bracket 2" is the row numbered 2.
 * The units follow "Fee based on": the business's currency for the order total, grams for the weight; changing it
 * clears the brackets, as they were in the other unit.
 * No brackets at all means free, which suits store pickup.
 *
 * @var string $formId     the shipping method form's DOM id
 * @var string $ratesField the hidden field of the brackets
 * @var array  $rates      the saved brackets, as v1/client/shipping-methods sends them
 * @var string $currency   the business's currency code
 * @var int    $max        the most brackets the API takes
 */
$config = [
    'formId'     => $formId,
    'ratesField' => $ratesField,
    'rates'      => array_values($rates),
    'currency'   => $currency,
    'max'        => $max,
    'labels'     => [
        'columns'    => lang('ShippingMethods.rates.columns'),
        'noLimit'    => lang('ShippingMethods.rates.no-limit'),
        'remove'     => lang('ShippingMethods.rates.remove'),
        'count'      => lang('ShippingMethods.rates.count'),
        'free'       => lang('ShippingMethods.rates.free'),
        'helpP'      => lang('ShippingMethods.rates.help-subtotal'),
        'helpW'      => lang('ShippingMethods.rates.help-weight'),
        'grams'      => 'g',
    ],
];
?>
(function (cfg) {
  'use strict';
  var L = cfg.labels;
  var FIELDS = ['range_from', 'range_to', 'fee_amount', 'step_size', 'step_fee'];
  var form = document.getElementById(cfg.formId);
  var root = form && form.querySelector('[data-rates-editor]');
  if (!root) { return; }
  var hidden = root.querySelector('input[name="' + cfg.ratesField + '"]');
  var basis = form.elements.fee_basis;
  var rows;

  function esc(s) {
    return String(s === null || s === undefined ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function fmt(text, args) {
    return String(text).replace(/\{(\d+)\}/g, function (m, i) { return args[i] !== undefined ? args[i] : m; });
  }
  function byWeight() { return basis && basis.value === 'W'; }
  // a saved amount as the input shows it: grams without their ".00"
  function shown(value, isRange) {
    if (value === null || value === undefined || value === '') { return ''; }
    return isRange && byWeight() ? String(parseInt(value, 10)) : String(value);
  }
  function blank() { var r = {}; FIELDS.forEach(function (f) { r[f] = ''; }); return r; }
  function isBlank(r) { return FIELDS.every(function (f) { return String(r[f]).trim() === ''; }); }
  function start() {
    rows = cfg.rates.map(function (rate) {
      var r = {};
      FIELDS.forEach(function (f) { r[f] = shown(rate[f], f === 'range_from' || f === 'range_to' || f === 'step_size'); });
      return r;
    });
  }
  function sortRows() {
    rows.sort(function (a, b) {
      if (isBlank(a) !== isBlank(b)) { return isBlank(a) ? 1 : -1; }
      return (parseFloat(a.range_from) || 0) - (parseFloat(b.range_from) || 0);
    });
  }

  function render() {
    var unit = byWeight() ? L.grams : cfg.currency;
    var c = L.columns;
    var rangeStep = byWeight() ? '1' : '0.01';
    var head = '<thead><tr><th>#</th>'
      + '<th>' + esc(c.range_from) + ' <span class="text-muted">(' + esc(unit) + ')</span></th>'
      + '<th>' + esc(c.range_to) + ' <span class="text-muted">(' + esc(unit) + ')</span></th>'
      + '<th>' + esc(c.fee_amount) + ' <span class="text-muted">(' + esc(cfg.currency) + ')</span></th>'
      + '<th>' + esc(c.step_size) + ' <span class="text-muted">(' + esc(unit) + ')</span></th>'
      + '<th>' + esc(c.step_fee) + ' <span class="text-muted">(' + esc(cfg.currency) + ')</span></th>'
      + '<th></th></tr></thead>';
    var n = 0;
    var body = rows.map(function (r, i) {
      var number = isBlank(r) ? '&ndash;' : String(++n);
      function input(field, step, placeholder) {
        return '<td><input type="number" min="0" step="' + step + '" class="form-control form-control-sm" style="min-width: 6.5rem;" data-row="' + i
          + '" data-field="' + field + '" value="' + esc(r[field]) + '" placeholder="' + esc(placeholder || '') + '" aria-label="' + esc(c[field]) + '"></td>';
      }
      return '<tr><td class="text-muted">' + number + '</td>'
        + input('range_from', rangeStep, '0')
        + input('range_to', rangeStep, L.noLimit)
        + input('fee_amount', '0.01', '0.00')
        + input('step_size', rangeStep, '')
        + input('step_fee', '0.01', '')
        + '<td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger" data-remove="' + i + '" title="' + esc(L.remove) + '" aria-label="' + esc(L.remove) + '"><i class="bi bi-x-lg"></i></button></td></tr>';
    }).join('');
    root.querySelector('[data-rates-table]').innerHTML = rows.length ? head + '<tbody>' + body + '</tbody>' : '';
    root.querySelector('[data-rates-help]').textContent = fmt(byWeight() ? L.helpW : L.helpP, [cfg.currency]);
    root.querySelector('[data-rates-add]').disabled = rows.length >= cfg.max;
    root.querySelector('[data-rates-count]').textContent = n ? fmt(L.count, [n]) : L.free;
    write();
  }

  // the brackets as the API takes them, in the rows' order, empty rows left out
  function write() {
    hidden.value = JSON.stringify(rows.filter(function (r) { return !isBlank(r); }).map(function (r) {
      var out = {};
      FIELDS.forEach(function (f) { var v = String(r[f]).trim(); out[f] = v === '' ? null : v; });
      return out;
    }));
  }

  root.addEventListener('input', function (e) {
    var r = rows[Number(e.target.getAttribute('data-row'))];
    if (!r || !e.target.hasAttribute('data-field')) { return; }
    r[e.target.getAttribute('data-field')] = e.target.value;
    write();
  });
  // on leaving the table, the brackets go back in order of "from", and the rows no longer empty get their numbers
  // (not while moving between its inputs, as a render would lose the focus)
  root.addEventListener('focusout', function (e) {
    if (e.relatedTarget && root.contains(e.relatedTarget)) { return; }
    sortRows();
    render();
  });
  root.addEventListener('click', function (e) {
    var remove = e.target.closest('[data-remove]');
    if (remove) {
      rows.splice(Number(remove.getAttribute('data-remove')), 1);
      render();
    } else if (e.target.closest('[data-rates-add]') && rows.length < cfg.max) {
      // a new bracket starts where the last one ends
      var last = rows.filter(function (r) { return !isBlank(r); }).pop();
      var r = blank();
      if (last && String(last.range_to).trim() !== '') { r.range_from = last.range_to; }
      rows.push(r);
      render();
      var inputs = root.querySelectorAll('[data-rates-table] tbody tr:last-child input');
      if (inputs[r.range_from === '' ? 0 : 1]) { inputs[r.range_from === '' ? 0 : 1].focus(); }
    }
  });
  // the brackets are in the old unit (money or grams): they are cleared, to be entered again, rather than read anew
  if (basis) { basis.addEventListener('change', function () { rows = []; render(); }); }
  // Cancel resets the form's inputs: the brackets go back to the saved ones too
  form.addEventListener('reset', function () { setTimeout(function () { start(); render(); }, 0); });

  start();
  render();
})(<?= json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>);
