<?php $this->extend('_layout_public') ?>
<?= $this->section('content') ?>
    <div class="auth-card">
        <div class="auth-card-header">
            <h1 class="auth-title"><?= lang('Login.main.welcome') ?></h1>
            <p class="auth-subtitle"><?= lang('Login.main.signin-to-continue') ?></p>
        </div>
        <?php $authAlert = session()->getFlashdata('auth_alert'); // [type, message], e.g. from activate-account ?>
        <?php if (is_array($authAlert) && count($authAlert) === 2): ?>
            <div id="login-alert" class="alert alert-<?= esc($authAlert[0], 'attr') ?>" role="alert"><?= esc($authAlert[1]) ?></div>
        <?php else: ?>
            <div id="login-alert" class="alert d-none" role="alert"></div>
        <?php endif; ?>
        <form id="login-form" class="auth-form" action="<?= base_url('login') ?>" method="post" novalidate>
            <div class="auth-field-group">
                <label for="email" class="form-label"><?= lang('Login.main.email') ?></label>
                <div class="auth-field">
                    <i class="ph ph-envelope auth-field-icon"></i>
                    <input type="email" class="form-control" id="email" name="email"
                           placeholder="<?= lang('Login.main.email-placeholder') ?>" autocomplete="username" required>
                </div>
                <div class="invalid-feedback"><?= lang('Login.main.email-invalid-feedback') ?></div>
            </div>
            <div class="auth-field-group">
                <label for="password" class="form-label"><?= lang('Login.main.password') ?></label>
                <div class="auth-field">
                    <i class="ph ph-lock auth-field-icon"></i>
                    <input type="password" class="form-control" id="password" name="password"
                           placeholder="<?= lang('Login.main.password-placeholder') ?>" autocomplete="current-password" required>
                </div>
                <div class="invalid-feedback"><?= lang('Login.main.password-invalid-feedback') ?></div>
            </div>
            <button type="submit" class="auth-submit"><?= lang('Login.main.sign-in-btn') ?> <i
                    class="ph ph-arrow-right"></i></button>
            <div class="auth-divider d-none">
                <span>or continue with</span>
            </div>
            <div class="auth-social d-none">
                <button type="button" class="auth-social-btn">
                    <i class="ph ph-google-logo"></i> Google
                </button>
                <button type="button" class="auth-social-btn">
                    <i class="ph ph-github-logo"></i> GitHub
                </button>
            </div>
        </form>
        <form id="otp-form" class="auth-form d-none" action="<?= base_url('verify-otp') ?>" method="post" novalidate>
            <div class="auth-field-group">
                <label for="otp" class="form-label"><?= lang('Login.otp.label') ?></label>
                <div class="auth-field">
                    <i class="ph ph-shield-check auth-field-icon"></i>
                    <input type="text" class="form-control" id="otp" name="otp"
                           placeholder="<?= lang('Login.otp.placeholder') ?>" inputmode="numeric" pattern="[0-9]{6}"
                           maxlength="6" autocomplete="one-time-code" required>
                </div>
                <div class="invalid-feedback"><?= lang('Login.error.otp-format') ?></div>
            </div>
            <button type="submit" class="auth-submit"><?= lang('Login.otp.verify-btn') ?> <i
                    class="ph ph-arrow-right"></i></button>
            <a href="#" id="otp-back" class="auth-back"><i class="ph ph-arrow-left"></i> <?= lang('Login.otp.back') ?></a>
        </form>
        <form id="password-form" class="auth-form d-none" action="<?= base_url('change-password') ?>" method="post" novalidate>
            <div class="auth-field-group">
                <label for="new_password" class="form-label"><?= lang('Login.password-change.new-password') ?></label>
                <div class="auth-field">
                    <i class="ph ph-lock auth-field-icon"></i>
                    <input type="password" class="form-control" id="new_password" name="new_password"
                           placeholder="<?= lang('Login.password-change.new-password-placeholder') ?>" minlength="8"
                           autocomplete="new-password" required>
                </div>
                <div class="invalid-feedback"><?= lang('Login.error.password-too-short', [8]) ?></div>
            </div>
            <div class="auth-field-group">
                <label for="confirm_password" class="form-label"><?= lang('Login.password-change.confirm-password') ?></label>
                <div class="auth-field">
                    <i class="ph ph-lock-key auth-field-icon"></i>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                           placeholder="<?= lang('Login.password-change.confirm-password-placeholder') ?>" minlength="8"
                           autocomplete="new-password" required>
                </div>
                <div class="invalid-feedback"><?= lang('Login.error.password-mismatch') ?></div>
            </div>
            <button type="submit" class="auth-submit"><?= lang('Login.password-change.submit-btn') ?> <i
                    class="ph ph-arrow-right"></i></button>
            <a href="#" id="password-back" class="auth-back"><i class="ph ph-arrow-left"></i> <?= lang('Login.otp.back') ?></a>
        </form>
        <p class="auth-footer-text">
            <?= lang('Login.main.dont-have-account') ?>
            <a href="<?= base_url('create-account') ?>"><?= lang('Login.main.create-new') ?></a>
            <br/>
            <?= lang('Login.main.or') ?>
            <a href="<?= base_url('forgot-password') ?>"><?= lang('Login.forget-password.title') ?></a>
        </p>
    </div>
<?php $this->endSection() ?>
<?= $this->section('script') ?>
<script>
(function () {
  'use strict';

  var loginForm = document.getElementById('login-form');
  var otpForm = document.getElementById('otp-form');
  var passwordForm = document.getElementById('password-form');
  var alertBox = document.getElementById('login-alert');
  var messages = <?= json_encode([
      'network'     => lang('Login.error.network'),
      'signInError' => lang('Login.error.sign-in-failed'),
      'otpError'    => lang('Login.error.otp-incorrect'),
      'mismatch'    => lang('Login.error.password-mismatch'),
      'changeError' => lang('Login.error.password-change-failed'),
  ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;

  function showAlert(type, message) {
    alertBox.className = 'alert alert-' + type;
    alertBox.textContent = message;
  }

  function hideAlert() {
    alertBox.className = 'alert d-none';
    alertBox.textContent = '';
  }

  function showStep(step) {
    loginForm.classList.toggle('d-none', step !== 'login');
    otpForm.classList.toggle('d-none', step !== 'otp');
    passwordForm.classList.toggle('d-none', step !== 'password');
    ({ login: loginForm.email, otp: otpForm.otp, password: passwordForm.new_password })[step].focus();
  }

  // Bootstrap validation styling: show .invalid-feedback for fields the browser rejects
  function validate(form) {
    var valid = true;
    Array.prototype.forEach.call(form.querySelectorAll('input[required]'), function (input) {
      var ok = input.checkValidity();
      input.classList.toggle('is-invalid', !ok);
      valid = valid && ok;
    });
    return valid;
  }

  function resetPasswordForm() {
    passwordForm.reset();
    Array.prototype.forEach.call(passwordForm.querySelectorAll('input'), function (input) {
      input.setCustomValidity('');
      input.classList.remove('is-invalid');
    });
  }

  function post(form) {
    var button = form.querySelector('button[type="submit"]');
    button.disabled = true;
    hideAlert();
    return fetch(form.action, {
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
      .finally(function () {
        button.disabled = false;
      });
  }

  function handleSuccess(body) {
    if (body.status === 'success' && body.redirect) {
      window.location.href = body.redirect;
      return true;
    }
    // the password has expired: no session until a new one is set
    if (body.status === 'password_change_required') {
      loginForm.password.value = '';
      otpForm.otp.value = '';
      resetPasswordForm();
      showStep('password');
      showAlert('warning', body.message);
      return true;
    }
    return false;
  }

  loginForm.addEventListener('submit', function (e) {
    e.preventDefault();
    if (!validate(loginForm)) {
      return;
    }
    post(loginForm).then(function (res) {
      if (handleSuccess(res.body)) {
        return;
      }
      if (res.body.status === 'otp_required') {
        loginForm.password.value = '';
        otpForm.otp.value = '';
        showStep('otp');
        showAlert('info', res.body.message);
        return;
      }
      showAlert('danger', res.body.message || messages.signInError);
    });
  });

  otpForm.addEventListener('submit', function (e) {
    e.preventDefault();
    otpForm.otp.value = otpForm.otp.value.replace(/\D/g, '');
    if (!validate(otpForm)) {
      return;
    }
    post(otpForm).then(function (res) {
      if (handleSuccess(res.body)) {
        return;
      }
      otpForm.otp.value = '';
      // 410: the verification has expired, so sign in again
      if (res.code === 410) {
        showStep('login');
      }
      showAlert('danger', res.body.message || messages.otpError);
    });
  });

  passwordForm.addEventListener('submit', function (e) {
    e.preventDefault();
    var confirm = passwordForm.confirm_password;
    confirm.setCustomValidity(confirm.value === passwordForm.new_password.value ? '' : messages.mismatch);
    if (!validate(passwordForm)) {
      return;
    }
    post(passwordForm).then(function (res) {
      if (handleSuccess(res.body)) {
        return;
      }
      // 410: the password change has expired, so sign in again
      if (res.code === 410) {
        resetPasswordForm();
        showStep('login');
      }
      showAlert('danger', res.body.message || messages.changeError);
    });
  });

  passwordForm.confirm_password.addEventListener('input', function () {
    this.setCustomValidity('');
  });

  ['otp-back', 'password-back'].forEach(function (id) {
    document.getElementById(id).addEventListener('click', function (e) {
      e.preventDefault();
      resetPasswordForm();
      hideAlert();
      showStep('login');
    });
  });
})();
</script>
<?php $this->endSection() ?>
