<?php $this->extend('_layout_public') ?>
<?= $this->section('content') ?>
    <div class="auth-card">
        <div class="auth-card-header">
            <h1 class="auth-title"><?= lang('Login.reset-password.title') ?></h1>
            <?php if ($valid): ?>
                <p class="auth-subtitle"><?= lang('Login.reset-password.subtitle') ?></p>
            <?php endif; ?>
        </div>
        <div id="reset-alert" class="alert <?= $valid ? 'd-none' : 'alert-danger' ?>" role="alert"><?= $valid ? '' : esc($error) ?></div>
        <?php if ($valid): ?>
            <form id="reset-form" class="auth-form" action="<?= base_url('reset-password') ?>" method="post" novalidate>
                <div class="auth-field-group">
                    <label for="new_password" class="form-label"><?= lang('Login.password-change.new-password') ?></label>
                    <div class="auth-field">
                        <i class="ph ph-lock auth-field-icon"></i>
                        <input type="password" class="form-control" id="new_password" name="new_password"
                               placeholder="<?= lang('Login.password-change.new-password-placeholder') ?>" minlength="8"
                               autocomplete="new-password" required>
                    </div>
                    <div class="invalid-feedback" data-feedback="new_password"><?= lang('Login.error.password-too-short', [8]) ?></div>
                    <div class="auth-strength-text"><?= lang('Login.create-account.password-hint') ?></div>
                </div>
                <div class="auth-field-group">
                    <label for="confirm_password" class="form-label"><?= lang('Login.password-change.confirm-password') ?></label>
                    <div class="auth-field">
                        <i class="ph ph-lock-key auth-field-icon"></i>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                               placeholder="<?= lang('Login.password-change.confirm-password-placeholder') ?>" minlength="8"
                               autocomplete="new-password" required>
                    </div>
                    <div class="invalid-feedback" data-feedback="confirm_password"><?= lang('Login.error.password-mismatch') ?></div>
                </div>
                <button type="submit" class="auth-submit"><?= lang('Login.reset-password.submit-btn') ?> <i
                        class="ph ph-arrow-right"></i></button>
            </form>
        <?php endif; ?>
        <p id="reset-request-new" class="auth-footer-text <?= $valid ? 'd-none' : '' ?>">
            <a href="<?= base_url('forgot-password') ?>" class="auth-link"><?= lang('Login.reset-password.request-new') ?></a>
        </p>
        <a href="<?= base_url() ?>" class="auth-back"><i class="ph ph-arrow-left"></i> <?= lang('Login.forgot-password.back') ?></a>
    </div>
<?php $this->endSection() ?>
<?php if ($valid): ?>
<?= $this->section('script') ?>
<script>
(function () {
  'use strict';

  var form = document.getElementById('reset-form');
  var alertBox = document.getElementById('reset-alert');
  var messages = <?= json_encode([
      'network'  => lang('Login.error.network'),
      'mismatch' => lang('Login.error.password-mismatch'),
      'failed'   => lang('Login.reset-password.failed'),
  ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;

  function showAlert(type, message) {
    alertBox.className = 'alert alert-' + type;
    alertBox.textContent = message;
  }

  // the default message of each field's .invalid-feedback, put back once the server's own message is cleared
  var defaults = {};
  Array.prototype.forEach.call(form.querySelectorAll('[data-feedback]'), function (el) {
    defaults[el.getAttribute('data-feedback')] = el.textContent;
  });

  function setFieldError(name, message) {
    var feedback = form.querySelector('[data-feedback="' + name + '"]');
    if (!form.elements[name] || !feedback) {
      return;
    }
    feedback.textContent = message || defaults[name];
    form.elements[name].classList.toggle('is-invalid', message !== null);
  }

  Array.prototype.forEach.call(form.querySelectorAll('input'), function (input) {
    input.addEventListener('input', function () {
      input.setCustomValidity('');
      setFieldError(input.name, null);
    });
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var confirm = form.confirm_password;
    confirm.setCustomValidity(confirm.value === form.new_password.value ? '' : messages.mismatch);
    var valid = true;
    Array.prototype.forEach.call(form.querySelectorAll('input[required]'), function (input) {
      var ok = input.checkValidity();
      setFieldError(input.name, ok ? null : '');
      valid = valid && ok;
    });
    if (!valid) {
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
        return res.json().catch(function () { return {}; }).then(function (body) {
          return { code: res.status, body: body };
        });
      })
      .catch(function () {
        return { code: 0, body: { message: messages.network } };
      })
      .then(function (res) {
        button.disabled = false;
        if (res.body.status === 'success' && res.body.redirect) {
          window.location.href = res.body.redirect;
          return;
        }
        // 410: the link is used up or expired, so offer a new one instead of the form
        if (res.code === 410) {
          form.classList.add('d-none');
          document.getElementById('reset-request-new').classList.remove('d-none');
        }
        Object.keys(res.body.errors || {}).forEach(function (name) {
          setFieldError(name, res.body.errors[name]);
        });
        showAlert('danger', res.body.message || messages.failed);
      });
  });
})();
</script>
<?php $this->endSection() ?>
<?php endif; ?>
