<?php $this->extend('_layout_public') ?>; ?>
<?= $this->section('content') ?>
    <div class="auth-card">
        <div class="auth-card-header">
            <h1 class="auth-title"><?= lang('Login.main.welcome') ?></h1>
            <p class="auth-subtitle"><?= lang('Login.main.signin-to-continue') ?></p>
        </div>
        <form class="auth-form" novalidate>
            <div class="auth-field-group">
                <label for="email" class="form-label"><?= lang('Login.main.email') ?></label>
                <div class="auth-field">
                    <i class="ph ph-envelope auth-field-icon"></i>
                    <input type="email" class="form-control" id="email" name="email"
                           placeholder="<?= lang('Login.main.email-placeholder') ?>" required>
                </div>
                <div class="invalid-feedback"><?= lang('Login.main.email-invalid-feedback') ?></div>
            </div>
            <div class="auth-field-group">
                <label for="password" class="form-label"><?= lang('Login.main.password') ?></label>
                <div class="auth-field">
                    <i class="ph ph-lock auth-field-icon"></i>
                    <input type="password" class="form-control" id="password" name="password"
                           placeholder="<?= lang('Login.main.password-placeholder') ?>" required>
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
        <p class="auth-footer-text">
            <?= lang('Login.main.dont-have-account') ?>
            <a href="<?= base_url('create-account') ?>"><?= lang('Login.main.create-new') ?></a>
            <br/>
            <?= lang('Login.main.or') ?>
            <a href="<?= base_url('forgot-password') ?>"><?= lang('Login.forget-password.title') ?></a>
        </p>
    </div>
<?php $this->endSection() ?>