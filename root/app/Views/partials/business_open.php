<?php
/**
 * The owner's switch for whether the session business shows on OtterNovaMart (Admin::business(), owners only), under
 * the business form. It saves as soon as it is flipped (Admin::businessOpenPost()), asking first before closing an
 * open shop, and goes back if the save fails.
 *
 * @var bool   $open    is_business_open Y
 * @var bool   $paid    on a paid plan: closing the shop does not pause the subscription
 * @var string $shopUrl the business's pages on OtterNovaMart
 * @var string $url     admin/business/open
 */
$config = [
    'url'      => $url,
    'csrf'     => ['header' => csrf_header(), 'hash' => csrf_hash()],
    'messages' => [
        'opened'       => lang('EditBusiness.open.opened'),
        'closed'       => lang('EditBusiness.open.closed'),
        'failed'       => lang('EditBusiness.open.failed'),
        'confirmClose' => lang('EditBusiness.open.confirm-close'),
        'confirmYes'   => lang('EditBusiness.open.confirm-yes'),
        'confirmNo'    => lang('EditBusiness.open.confirm-no'),
        'stateOpen'    => lang('EditBusiness.open.state-open'),
        'stateClosed'  => lang('EditBusiness.open.state-closed'),
    ],
];
?>
<div class="card mt-4" id="business-open-card">
    <div class="card-header">
        <h5 class="card-title"><i class="bi bi-shop"></i> <?= lang('EditBusiness.open.title') ?></h5>
        <p class="card-subtitle"><?= lang('EditBusiness.open.subtitle') ?></p>
    </div>
    <div class="card-body">
        <div class="form-check form-switch fs-5 mb-2">
            <input class="form-check-input" type="checkbox" role="switch" id="business-open-switch"<?= $open ? ' checked' : '' ?> aria-describedby="business-open-help">
            <label class="form-check-label fw-semibold" for="business-open-switch" id="business-open-state"><?= lang($open ? 'EditBusiness.open.state-open' : 'EditBusiness.open.state-closed') ?></label>
        </div>
        <p class="form-text mb-2" id="business-open-help"><?= lang('EditBusiness.open.help') ?></p>
        <?php if (! empty($paid)): ?>
        <div class="alert alert-warning small py-2 mb-2" role="alert" id="business-open-paid"><i class="bi bi-exclamation-triangle"></i> <?= lang('EditBusiness.open.paid-warning') ?></div>
        <?php endif ?>
        <a href="<?= esc($shopUrl, 'attr') ?>" target="_blank" rel="noopener" class="small"><i class="bi bi-box-arrow-up-right"></i> <?= lang('EditBusiness.open.view-shop') ?></a>
    </div>
</div>
<script>
(function (cfg) {
  'use strict';
  var M = cfg.messages;
  var input = document.getElementById('business-open-switch');
  var state = document.getElementById('business-open-state');
  var csrfHash = cfg.csrf.hash;

  function notify(type, text) { if (window.toastr) { window.toastr[type](text); } else if (type === 'error') { window.alert(text); } }

  function confirmClose() {
    if (window.Swal) {
      return window.Swal.fire({ text: M.confirmClose, icon: 'warning', showCancelButton: true, confirmButtonText: M.confirmYes, cancelButtonText: M.confirmNo })
        .then(function (r) { return !!r.isConfirmed; });
    }
    return Promise.resolve(window.confirm(M.confirmClose));
  }

  function show(open) {
    input.checked = open;
    state.textContent = open ? M.stateOpen : M.stateClosed;
  }

  function save(open) {
    var headers = { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    headers[cfg.csrf.header] = csrfHash;
    input.disabled = true;
    fetch(cfg.url, { method: 'POST', headers: headers, credentials: 'same-origin', body: JSON.stringify({ is_business_open: open ? 'Y' : 'N' }) })
      .then(function (response) {
        return response.json().catch(function () { return {}; }).then(function (json) { return { ok: response.ok && !!json.success, json: json }; });
      })
      .catch(function () { return { ok: false, json: {} }; })
      .then(function (result) {
        input.disabled = false;
        if (result.json.csrf_hash) { csrfHash = result.json.csrf_hash; }
        if (!result.ok) {
          show(!open); // as it was
          notify('error', result.json.message || M.failed);
          return;
        }
        show(result.json.is_business_open === 'Y');
        notify('success', result.json.message || (open ? M.opened : M.closed));
      });
  }

  input.addEventListener('change', function () {
    var open = input.checked;
    if (open) { save(true); return; }
    confirmClose().then(function (yes) { if (yes) { save(false); } else { show(true); } });
  });
})(<?= json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>);
</script>
