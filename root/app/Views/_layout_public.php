<!DOCTYPE html>
<html lang="<?= $locale ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= lang('System.page.' . $slug) ?> - <?= SITE_TITLE ?></title>
    <meta name="description" content="<?= META_DESCRIPTION ?>">
    <meta name="keywords" content="<?= META_KEYWORDS ?>">
    <!-- Favicons -->
    <link href="<?= assets_url('img/logo.webp') ?>" rel="icon">
    <link href="<?= assets_url('img/logo.webp') ?>" rel="apple-touch-icon">
    <!-- Google Fonts - Plus Jakarta Sans (body) + Outfit (headings) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <?php if ('en-US' == $locale): ?>
        <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@200..700&family=Raleway:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <?php elseif ('th' == $locale): ?>
        <link href="https://fonts.googleapis.com/css2?family=Noto+Serif+Thai:wght@100..900&family=Prompt:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <?php endif; ?>
    <!-- Vendor CSS Files -->
    <link href="<?= assets_url('vendor/bootstrap5.3.8/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link href="<?= assets_url('vendor/bootstrap-icons1.13.1/bootstrap-icons.css') ?>" rel="stylesheet">
    <!-- Template Main CSS File -->
    <link href="<?= assets_url('themes/client/css/main.css') ?>" rel="stylesheet">
    <!-- =======================================================
      * Template Name: TrendyAdmin - Bootstrap Admin Template
      * Template URL: https://bootstrapmade.com/trendy-admin-bootstrap-dashboard-template/
      * Updated: Jul 29 2026 with Bootstrap v5.3.8
      * Author: BootstrapMade.com
      * License: https://bootstrapmade.com/license/
      ======================================================== -->
</head>
<body class="index-page lang-<?= $locale ?>">
<div class="auth-layout auth-split">
    <!-- Brand Panel (desktop only) -->
    <aside class="auth-panel">
        <span class="auth-panel-logo">
            <span class="auth-logo-mark">
                <img src="<?= assets_url('img/logo.webp') ?>" alt="<?= SITE_TITLE ?>">
            </span>
            <span><?= SITE_TITLE ?></span>
        </span>
        <div class="auth-panel-body">
            <h2 class="auth-panel-title"><?= lang('Login.auth-panel.title') ?></h2>
            <p class="auth-panel-text"><?= lang('Login.auth-panel.paragraph') ?></p>
            <ul class="auth-panel-list">
                <li><i class="bi bi-check-circle"></i> <?= lang('Login.auth-panel.item-1') ?></li>
                <li><i class="bi bi-check-circle"></i> <?= lang('Login.auth-panel.item-2') ?></li>
                <li><i class="bi bi-check-circle"></i> <?= lang('Login.auth-panel.item-3') ?></li>
            </ul>
        </div>
        <div class="auth-panel-footer">&nbsp;</div>
    </aside>
    <!-- Content Side -->
    <div class="auth-content">
        <a href="<?= base_url() ?>" class="auth-logo">
            <span class="auth-logo-mark">
                <img src="<?= assets_url('img/logo.webp') ?>" alt="<?= SITE_TITLE ?>">
            </span>
            <span><?= SITE_TITLE ?></span>
        </a>
        <div class="auth-content-inner">
            <?= $this->renderSection('content') ?>
        </div>
        <!-- Footer -->
        <footer class="auth-footer">
            <div class="footer-copyright">
                &copy; 2025-<?= date('Y') ?> <a href="<?= getenv('LINK_WWW') ?>"><?= SITE_TITLE ?></a> <?= lang('System.footer.copyright') ?>
            </div>
            <div class="footer-links">
                <a href="<?= getenv('LINK_WWW') ?>/privacy-policy"><?= lang('System.footer.privacy-policy') ?></a>
                <a href="<?= getenv('LINK_WWW') ?>/terms-of-service"><?= lang('System.footer.terms') ?></a>
                <a href="<?= getenv('LINK_WWW') ?>/contact"><?= lang('System.footer.help') ?></a>
            </div>
        </footer>
    </div>
</div>
<!-- Ribbon -->
<?php $env = getenv('CI_ENVIRONMENT'); ?>
<?php if (in_array($env, ['beta', 'development'])): ?>
    <?php if ('development' == $env) { $env = 'dev'; } ?>
    <div class="corner-ribbon">
        <span><?= esc($env) ?></span>
    </div>
<?php endif; ?>
<!-- Vendor JS Files -->
<script src="<?= assets_url('vendor/bootstrap5.3.8/js/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= assets_url('themes/client/js/theme.js') ?>"></script>
<script src="<?= assets_url('themes/client/js/main.js') ?>"></script>
<script src="<?= assets_url('themes/client/js/local-time.js') ?>"></script>
<?= $this->renderSection('script') ?>
</body>
</html>