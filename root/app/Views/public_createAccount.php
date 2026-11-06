<?php $this->extend('_layout_public') ?>
<?= $this->section('content') ?>
    <div class="auth-card">
        <div class="auth-card-header">
            <h1 class="auth-title"><?= lang('Login.create-account.title') ?></h1>
            <p class="auth-subtitle"><?= lang('Login.create-account.subtitle') ?></p>
        </div>
        <?php if (isset($invitation)): ?>
            <div class="alert alert-info" role="status">
                <?= esc(lang('Login.create-account.invited', [$invitation['business_name'] ?? '', lang('BusinessClientLink.enum.client_role.' . ($invitation['client_role'] ?? 'E'))])) ?>
            </div>
        <?php elseif (isset($invitationError)): ?>
            <div class="alert alert-warning" role="alert"><?= esc($invitationError) ?></div>
        <?php endif ?>
        <div id="create-alert" class="alert d-none" role="alert"></div>
        <form id="create-form" class="auth-form" action="<?= base_url('create-account') ?>" method="post" novalidate>
            <?php if (isset($invitation)): ?>
                <input type="hidden" name="invitation" value="<?= esc($invitation['token'], 'attr') ?>">
            <?php endif ?>
            <div class="auth-field-group">
                <label for="full_name" class="form-label"><?= lang('Login.create-account.full-name') ?></label>
                <div class="auth-field">
                    <i class="bi bi-person auth-field-icon"></i>
                    <input type="text" class="form-control" id="full_name" name="full_name"
                           placeholder="<?= lang('Login.create-account.full-name-placeholder') ?>" maxlength="255"
                           autocomplete="name" required>
                </div>
                <div class="invalid-feedback" data-feedback="full_name"><?= lang('Login.create-account.full-name-invalid-feedback') ?></div>
            </div>

            <div class="auth-field-group">
                <label for="email" class="form-label"><?= lang('Login.main.email') ?></label>
                <div class="auth-field">
                    <i class="bi bi-envelope auth-field-icon"></i>
                    <input type="email" class="form-control" id="email" name="email"
                           placeholder="<?= lang('Login.main.email-placeholder') ?>" maxlength="128"
                           autocomplete="email" required<?php if (isset($invitation)): ?> value="<?= esc($invitation['email_address'] ?? '', 'attr') ?>" readonly<?php endif ?>>
                </div>
                <div class="invalid-feedback" data-feedback="email"><?= lang('Login.main.email-invalid-feedback') ?></div>
            </div>

            <div class="auth-field-group">
                <label for="password" class="form-label"><?= lang('Login.main.password') ?></label>
                <div class="auth-field">
                    <i class="bi bi-lock auth-field-icon"></i>
                    <input type="password" class="form-control" id="password" name="password"
                           placeholder="<?= lang('Login.create-account.password-placeholder') ?>" minlength="8"
                           autocomplete="new-password" required>
                    <button class="auth-field-toggle password-toggle" type="button" tabindex="-1">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
                <div class="invalid-feedback" data-feedback="password"><?= lang('Login.error.password-too-short', [8]) ?></div>
                <div class="auth-strength" data-strength>
                    <span></span><span></span><span></span><span></span>
                </div>
                <div class="auth-strength-text" data-strength-text><?= lang('Login.create-account.password-hint') ?></div>
            </div>

            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="terms" name="terms" value="1" required>
                <label class="form-check-label" for="terms">
                    <?= lang('Login.create-account.terms', [
                        '<a href="' . getenv('LINK_WWW') . 'terms-of-service" class="auth-link" target="_blank">' . lang('Login.create-account.terms-of-service') . '</a>',
                        '<a href="' . getenv('LINK_WWW') . 'privacy-policy" class="auth-link" target="_blank">' . lang('Login.create-account.privacy-policy') . '</a>',
                    ]) ?>
                </label>
                <div class="invalid-feedback" data-feedback="terms"><?= lang('Login.create-account.terms-invalid-feedback') ?></div>
            </div>

            <button type="submit" class="auth-submit"><?= lang('Login.create-account.submit-btn') ?> <i class="bi bi-arrow-right"></i></button>

        </form>

        <p class="auth-footer-text">
            <?= lang('Login.create-account.have-account') ?> <a href="<?= base_url() ?>" class="auth-link"><?= lang('Login.create-account.sign-in') ?></a>
        </p>
    </div>
<?php $this->endSection() ?>
<?= $this->section('script') ?>
<script>
(function () {
  'use strict';

  var form = document.getElementById('create-form');
  var alertBox = document.getElementById('create-alert');
  var strength = form.querySelector('[data-strength]');
  var messages = <?= json_encode([
      'network' => lang('Login.error.network'),
      'failed'  => lang('Login.create-account.failed'),
  ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;

  function showAlert(type, message) {
    alertBox.className = 'alert alert-' + type;
    alertBox.textContent = message;
  }

  function hideAlert() {
    alertBox.className = 'alert d-none';
    alertBox.textContent = '';
  }

  // the default message of each field's .invalid-feedback, put back once the server's own message is cleared
  var defaults = {};
  Array.prototype.forEach.call(form.querySelectorAll('[data-feedback]'), function (el) {
    defaults[el.getAttribute('data-feedback')] = el.textContent;
  });

  function setFieldError(name, message) {
    var input = form.elements[name];
    var feedback = form.querySelector('[data-feedback="' + name + '"]');
    if (!input || !feedback) {
      return;
    }
    feedback.textContent = message || defaults[name];
    input.classList.toggle('is-invalid', message !== null);
  }

  // Bootstrap validation styling: show .invalid-feedback for fields the browser rejects
  function validate() {
    var valid = true;
    Array.prototype.forEach.call(form.querySelectorAll('input[required]'), function (input) {
      if (input.type !== 'checkbox') {
        input.value = input.type === 'password' ? input.value : input.value.trim();
      }
      var ok = input.checkValidity();
      setFieldError(input.name, ok ? null : '');
      valid = valid && ok;
    });
    return valid;
  }

  // 0-4 bars: length, then letters and digits, then mixed case or symbols
  function score(password) {
    if (password.length === 0) {
      return 0;
    }
    var points = 1;
    if (password.length >= 8 && /\p{L}/u.test(password) && /\d/.test(password)) {
      points++;
      if (/[a-z]/.test(password) && /[A-Z]/.test(password)) {
        points++;
      }
      if (/[^\p{L}\d]/u.test(password)) {
        points++;
      }
    }
    return Math.min(points, 4);
  }

  form.password.addEventListener('input', function () {
    strength.className = 'auth-strength ' + ['', 'weak', 'fair', 'good', 'strong'][score(this.value)];
  });

  Array.prototype.forEach.call(form.querySelectorAll('input'), function (input) {
    input.addEventListener(input.type === 'checkbox' ? 'change' : 'input', function () {
      setFieldError(input.name, null);
    });
  });

  form.querySelector('.password-toggle').addEventListener('click', function () {
    var show = form.password.type === 'password';
    form.password.type = show ? 'text' : 'password';
    this.querySelector('i').className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    hideAlert();
    if (!validate()) {
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
        return res.json().catch(function () { return {}; }).then(function (body) {
          return { code: res.status, body: body };
        });
      })
      .catch(function () {
        return { code: 0, body: { message: messages.network } };
      })
      .then(function (res) {
        button.disabled = false;
        if (res.body.status === 'success') {
          // nothing more to do here: the next step is the link in the email
          form.reset();
          form.classList.add('d-none');
          showAlert('success', res.body.message);
          return;
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
