<?php $this->extend('_layout_public') ?>
<?= $this->section('content') ?>
    <div class="auth-card">
        <div class="auth-card-header">
            <h1 class="auth-title"><?= lang('Login.dashboard.title') ?></h1>
            <p class="auth-subtitle"><?= esc(lang('Login.dashboard.greeting', [$user['display_name'] ?? $user['email_address'] ?? ''])) ?></p>
        </div>
        <a href="<?= base_url('logout') ?>" class="auth-submit"><?= lang('Login.dashboard.sign-out') ?> <i class="ph ph-sign-out"></i></a>
    </div>
<?php $this->endSection() ?>
