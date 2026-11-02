<?php $this->extend('_layout_public') ?>
<?= $this->section('content') ?>
    <div class="auth-card">
        <div class="auth-card-header">
            <h1 class="auth-title"><?= lang('Login.forgot-password.title') ?></h1>
            <p class="auth-subtitle"><?= lang('Login.forgot-password.subtitle') ?></p>
        </div>
        <div id="forgot-alert" class="alert d-none" role="alert"></div>
        <form id="forgot-form" class="auth-form" action="<?= base_url('forgot-password') ?>" method="post" novalidate>
            <div class="auth-field-group">
                <label for="email" class="form-label"><?= lang('Login.main.email') ?></label>
                <div class="auth-field">
                    <i class="ph ph-envelope auth-field-icon"></i>
                    <input type="email" class="form-control" id="email" name="email"
                           placeholder="<?= lang('Login.main.email-placeholder') ?>" maxlength="128"
                           autocomplete="email" required>
                </div>
                <div class="invalid-feedback"><?= lang('Login.main.email-invalid-feedback') ?></div>
            </div>
            <button type="submit" class="auth-submit"><?= lang('Login.forgot-password.submit-btn') ?> <i
                    class="ph ph-arrow-right"></i></button>
        </form>
        <a href="<?= base_url() ?>" class="auth-back"><i class="ph ph-arrow-left"></i> <?= lang('Login.forgot-password.back') ?></a>
    </div>
<?php $this->endSection() ?>
<?= $this->section('script') ?>
<script>
(function () {
  'use strict';

  var form = document.getElementById('forgot-form');
  var alertBox = document.getElementById('forgot-alert');
  var messages = <?= json_encode([
      'network' => lang('Login.error.network'),
      'failed'  => lang('Login.forgot-password.failed'),
  ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;

  function showAlert(type, message) {
    alertBox.className = 'alert alert-' + type;
    alertBox.textContent = message;
  }

  form.email.addEventListener('input', function () {
    this.classList.remove('is-invalid');
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    form.email.value = form.email.value.trim();
    var ok = form.email.checkValidity();
    form.email.classList.toggle('is-invalid', !ok);
    if (!ok) {
      return;
    }
    var button = form.querySelector('button[type="submit"]');
    button.disabled = true;
    alertBox.className = 'alert d-none';
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
        return { message: messages.network };
      })
      .then(function (body) {
        button.disabled = false;
        if (body.status === 'success') {
          // same message whether or not the address has an account; the next step is the link in the email
          form.classList.add('d-none');
          showAlert('success', body.message);
          return;
        }
        showAlert('danger', body.message || messages.failed);
      });
  });
})();
</script>
<?php $this->endSection() ?>
