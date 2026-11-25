<?php
/**
 * The Terms of Service and Privacy Policy versions the signed-in client has still to accept; see App\Controllers\Legal.
 * @var list<array> $documents each pending version, with body (its text) when it could be fetched
 */
$this->extend('_layout_public');
$pages = ['T' => 'terms-of-service', 'P' => 'privacy-policy'];
?>
<?= $this->section('content') ?>
    <div class="auth-card">
        <div class="auth-card-header">
            <h1 class="auth-title"><?= lang('Legal.accept.title') ?></h1>
            <p class="auth-subtitle"><?= lang('Legal.accept.subtitle') ?></p>
        </div>
        <div id="legal-alert" class="alert d-none" role="alert"></div>
        <form id="legal-form" class="auth-form" action="<?= base_url('admin/legal/accept') ?>" method="post" novalidate>
            <?php foreach ($documents as $document): ?>
                <?php $type = $document['document_type'] ?? 'T'; ?>
                <div class="mb-3" data-legal-document="<?= esc($document['public_id'] ?? '', 'attr') ?>">
                    <h2 class="h6 mb-1">
                        <?= lang('Legal.document.' . $type) ?>
                        <span class="text-muted small fw-normal"><?= lang('Legal.accept.version', [esc($document['version_label'] ?? '')]) ?></span>
                    </h2>
                    <?php if (! empty($document['change_summary'])): ?>
                        <p class="small mb-1"><?= nl2br(esc($document['change_summary'])) ?></p>
                    <?php endif ?>
                    <?php if (! empty($document['body'])): ?>
                        <div class="border rounded p-2 small bg-body-tertiary" style="max-height: 14rem; overflow-y: auto; white-space: pre-wrap;"><?= esc($document['body']) ?></div>
                    <?php endif ?>
                    <a href="<?= esc(rtrim((string) getenv('LINK_WWW'), '/') . '/' . ($pages[$type] ?? $pages['T']), 'attr') ?>" class="auth-link small" target="_blank" rel="noopener">
                        <?= lang('Legal.accept.read-full') ?>
                    </a>
                </div>
            <?php endforeach ?>

            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="agree" name="agree" value="1" required>
                <label class="form-check-label" for="agree"><?= lang('Legal.accept.agree') ?></label>
                <div class="invalid-feedback"><?= lang('Legal.accept.agree-required') ?></div>
            </div>

            <button type="submit" class="auth-submit"><?= lang('Legal.accept.submit') ?> <i class="bi bi-arrow-right"></i></button>
        </form>

        <p class="auth-footer-text">
            <?= lang('Legal.accept.decline') ?> <a href="<?= base_url('logout') ?>" class="auth-link"><?= lang('Legal.accept.sign-out') ?></a>
        </p>
    </div>
<?php $this->endSection() ?>
<?= $this->section('script') ?>
<script>
(function () {
  'use strict';

  var form = document.getElementById('legal-form');
  var alertBox = document.getElementById('legal-alert');
  var network = <?= json_encode(lang('Login.error.network'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;

  function showAlert(type, message) {
    alertBox.className = 'alert alert-' + type;
    alertBox.textContent = message;
  }

  form.agree.addEventListener('change', function () {
    form.agree.classList.remove('is-invalid');
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    alertBox.className = 'alert d-none';
    if (!form.agree.checked) {
      form.agree.classList.add('is-invalid');
      return;
    }
    var button = form.querySelector('button[type="submit"]');
    button.disabled = true;
    fetch(form.action, {
      method: 'POST',
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: new FormData(form),
      credentials: 'same-origin'
    })
      .then(function (res) {
        return res.json().catch(function () { return {}; });
      })
      .catch(function () {
        return { message: network };
      })
      .then(function (body) {
        if (body.status === 'success' && body.redirect) {
          window.location.href = body.redirect;
          return;
        }
        button.disabled = false;
        showAlert(body.reload ? 'warning' : 'danger', body.message || network);
        if (body.reload) {
          // a newer version took effect: show it after the message has been read
          window.setTimeout(function () { window.location.reload(); }, 2500);
        }
      });
  });
})();
</script>
<?php $this->endSection() ?>
