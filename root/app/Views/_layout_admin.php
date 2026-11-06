<!DOCTYPE html>
<html lang="<?= $locale ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= lang('System.page.' . $slug) ?> - <?= lang('System.site-title') ?></title>
    <meta name="description" content="<?= META_DESCRIPTION ?>">
    <meta name="keywords" content="<?= META_KEYWORDS ?>">
    <!-- Favicons -->
    <link href="<?= base_url('assets/img/logo.webp') ?>" rel="icon">
    <link href="<?= base_url('assets/img/logo.webp') ?>" rel="apple-touch-icon">
    <!-- Google Fonts - Plus Jakarta Sans (body) + Outfit (headings) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <?php if ('en-US' == $locale): ?>
        <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@200..700&family=Raleway:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <?php elseif ('th' == $locale): ?>
        <link href="https://fonts.googleapis.com/css2?family=Noto+Serif+Thai:wght@100..900&family=Prompt:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <?php endif; ?>
    <!-- Vendor CSS Files -->
    <link href="<?= base_url('assets/vendor/bootstrap/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link href="<?= base_url('assets/vendor/bootstrap-icons/bootstrap-icons.css') ?>" rel="stylesheet">
    <link href="<?= base_url('assets/vendor/fontawesome-free/css/all.min.css') ?>" rel="stylesheet">
    <link href="<?= base_url('assets/vendor/quill/quill.snow.css') ?>" rel="stylesheet">
    <link href="<?= base_url('assets/vendor/quill/quill.bubble.css') ?>" rel="stylesheet">
    <link href="<?= base_url('assets/vendor/choices.js/choices.min.css') ?>" rel="stylesheet">
    <link href="<?= base_url('assets/vendor/flatpickr/flatpickr.min.css') ?>" rel="stylesheet">
    <link href="<?= base_url('assets/vendor/toastr/toastr.min.css') ?>" rel="stylesheet">
    <link href="https://cdn.datatables.net/2.1.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <!-- Template Main CSS File -->
    <link href="<?= base_url('assets/css/main.css') ?>" rel="stylesheet">
    <!-- =======================================================
      * Template Name: TrendyAdmin - Bootstrap Admin Template
      * Template URL: https://bootstrapmade.com/trendy-admin-bootstrap-dashboard-template/
      * Updated: Jul 29 2026 with Bootstrap v5.3.8
      * Author: BootstrapMade.com
      * License: https://bootstrapmade.com/license/
      ======================================================== -->
</head>
<body class="lang-<?= $locale ?>">
<header class="header">
    <div class="header-inner">
        <div class="header-left">
            <button class="sidebar-toggle" title="Toggle Sidebar">
                <i class="bi bi-layout-sidebar-inset"></i>
            </button>
            <a href="<?= base_url('admin/dashboard') ?>" class="header-logo">
                <span class="header-logo-mark"><img src="<?= base_url('assets/img/logo.webp') ?>" alt="<?= lang('System.site-title') ?>" /></span>
                <span class="header-logo-text">
                    <?= esc(currentBusinessName() ?? lang('System.site-title')) ?>
                </span>
            </a>
        </div>
        <div class="header-search">
            <form class="search-form" action="<?= base_url('admin/search') ?>" method="GET">
                <label for="any-search" class="d-none"><?= lang('System.header.search-anything') ?></label>
                <button type="submit"><i class="bi bi-search"></i></button>
                <input type="search" id="any-search" name="q" placeholder="<?= lang('System.header.search-anything') ?>" autocomplete="off">
            </form>
        </div>
        <div class="header-right">
            <div class="header-actions-desktop">
                <button class="header-action theme-toggle" title="<?= lang('System.header.toggle-theme') ?>">
                    <i class="bi bi-moon icon-dark"></i>
                    <i class="bi bi-sun icon-light"></i>
                </button>
                <button class="header-action fullscreen-toggle" onclick="toggleFullscreen()" title="<?= lang('System.header.toggle-fullscreen') ?>">
                    <i class="bi bi-arrows-angle-expand icon-enter"></i>
                    <i class="bi bi-arrows-angle-contract icon-exit"></i>
                </button>
                <a class="header-action fullscreen-toggle" href="<?= base_url('admin/messages') ?>" title="<?= lang('System.page.messages') ?>">
                    <i class="bi bi-chat-left-dots"></i>
                    <span class="badge">##</span>
                </a>
                <div class="header-action dropdown notification-dropdown">
                    <button class="dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" title="<?= lang('System.page.notifications') ?>">
                        <i class="bi bi-bell"></i>
                        <span class="badge">##</span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end">
                        <div class="notification-header">
                            <h6><?= lang('System.header.you-have-notifications', [3]) ?></h6>
                            <a href="#" class="notification-mark-read" data-notification-action="mark-all-read"
                               title="<?= lang('System.header.mark-all-read') ?>">
                                <i class="bi bi-check2-all"></i>
                            </a>
                        </div>
                        <div class="notification-list">
                            <div class="notification-item unread">
                                <span class="notification-icon success"><i class="bi bi-check-circle"></i></span>
                                <span class="notification-content">
                                    <span class="notification-title">#######</span>
                                    <span class="notification-text">#########</span>
                                </span>
                                <span class="notification-meta">
                                    <span class="notification-time">###</span>
                                    <span class="notification-dot"></span>
                                </span>
                            </div>
                        </div>
                        <div class="notification-footer">
                            <a href="<?= base_url('admin/notifications') ?>"><?= lang('System.header.notifications-view-all') ?></a>
                        </div>
                    </div>
                </div>
                <div class="header-divider"></div>
                <div class="header-action dropdown user-dropdown">
                    <button class="dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                        <img src="<?= esc(userAvatarUrl($user['avatar_path'] ?? null), 'attr') ?>" alt="<?= esc(userDisplayName($user), 'attr') ?>" class="avatar">
                        <span class="user-name"><?= esc(userDisplayName($user)) ?></span>
                        <i class="ph ph-caret-down user-caret"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end">
                        <div class="user-menu-card">
                            <span class="user-menu-avatar">
                                <img src="<?= esc(userAvatarUrl($user['avatar_path'] ?? null), 'attr') ?>" alt="<?= esc(userDisplayName($user), 'attr') ?>">
                            </span>
                            <span class="user-menu-name"><?= esc(userDisplayName($user)) ?></span>
                            <span class="user-menu-mail"><?= esc($user['email_address'] ?? '') ?></span>
                            <span class="user-menu-role"><?= esc(currentBusinessName()) ?? '<i class="bi bi-stars"></i>' ?></span>
                        </div>
                        <div class="user-menu-body">
                            <a class="dropdown-item" href="<?= base_url('admin/profile') ?>">
                                <span class="user-menu-item-icon"><i class="bi bi-person"></i></span>
                                <?= lang('System.page.profile') ?>
                                <i class="bi bi-caret-right user-menu-item-caret"></i>
                            </a>
                        </div>
                        <div class="user-menu-body">
                            <a class="dropdown-item" href="<?= base_url('admin/my-businesses') ?>">
                                <span class="user-menu-item-icon"><i class="bi bi-shop"></i></span>
                                <?= lang('System.page.my-businesses') ?>
                                <i class="bi bi-caret-right user-menu-item-caret"></i>
                            </a>
                        </div>
                        <div class="user-menu-footer">
                            <a href="<?= base_url('logout') ?>" class="user-menu-signout">
                                <i class="bi bi-box-arrow-right"></i> <?= lang('System.page.sign-out') ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Mobile Actions (visible only on mobile) -->
            <div class="header-actions-mobile">
                <button class="header-action search-toggle" title="Search">
                    <i class="bi bi-search"></i>
                </button>
                <button class="header-action mobile-menu-toggle" title="More">
                    <i class="bi bi-three-dots-vertical"></i>
                </button>
            </div>
        </div>
    </div>
</header>
<!-- Mobile Search -->
<div class="mobile-search">
    <form class="search-form" action="<?= base_url('admin/search') ?>" method="GET">
        <label for="any-search-mobile" class="d-none"><?= lang('System.header.search-anything') ?></label>
        <input type="search" name="q" id="any-search-mobile" placeholder="<?= lang('System.header.search-anything') ?>" autocomplete="off">
        <button type="submit"><i class="bi bi-search"></i></button>
    </form>
</div>
<!-- Mobile Header Menu -->
<div class="mobile-header-menu">
    <div class="mobile-header-menu-content">
        <button class="mobile-menu-item theme-toggle" title="<?= lang('System.header.toggle-theme') ?>">
            <i class="bi bi-moon icon-dark"></i>
            <i class="bi bi-sun icon-light"></i>
            <span class="mobile-menu-label"><?= lang('System.header.theme') ?></span>
        </button>
        <button class="mobile-menu-item fullscreen-toggle" onclick="toggleFullscreen()" title="<?= lang('System.header.toggle-fullscreen') ?>">
            <i class="bi bi-arrows-angle-expand icon-enter"></i>
            <i class="bi bi-arrows-angle-contract icon-exit"></i>
            <span class="mobile-menu-label"><?= lang('System.header.fullscreen') ?></span>
        </button>
        <a href="<?= base_url('admin/messages') ?>" class="mobile-menu-item">
            <i class="bi bi-chat-left-dots"></i>
            <span class="badge">##</span>
            <span class="mobile-menu-label"><?= lang('System.page.messages') ?></span>
        </a>
        <a href="<?= base_url('admin/notifications') ?>" class="mobile-menu-item">
            <i class="bi bi-bell"></i>
            <span class="badge">##</span>
            <span class="mobile-menu-label"><?= lang('System.page.notifications') ?></span>
        </a>
        <a href="<?= base_url('admin/profile') ?>" class="mobile-menu-item">
            <i class="bi bi-person"></i>
            <span class="mobile-menu-label"><?= lang('System.page.profile') ?></span>
        </a>
        <a href="<?= base_url('logout') ?>" class="mobile-menu-item mobile-menu-item-danger">
            <i class="bi bi-box-arrow-right"></i>
            <span class="mobile-menu-label"><?= lang('System.page.sign-out') ?></span>
        </a>
    </div>
</div>
<!-- Sidebar -->
<aside class="sidebar">
    <div class="sidebar-header">
        <a href="<?= base_url('admin/dashboard') ?>" class="sidebar-logo">
            <span class="sidebar-logo-mark"><img src="<?= base_url('assets/img/logo.webp') ?>" alt="<?= lang('System.site-title') ?>" /></span>
            <span class="sidebar-logo-text">
                <?= esc(currentBusinessName() ?? lang('System.site-title')) ?>
            </span>
        </a>
        <button class="sidebar-close">
            <i class="bi bi-x"></i>
        </button>
    </div>
    <!-- Sidebar Navigation -->
    <nav class="sidebar-nav">
        <ul class="nav-menu">
            <!-- SHOW NAV MENU IF THERE IS BUSINESS -->
            <li class="nav-item">
                <a class="nav-link<?= ('dashboard' == $slug ? ' active' : '') ?>" href="<?= base_url('admin/dashboard') ?>">
                    <span class="nav-icon"><i class="bi bi-speedometer2"></i></span>
                    <span class="nav-text"><?= lang('System.page.dashboard') ?></span>
                </a>
            </li>
            <!-- ALL STAFF CAN SEE -->
            <li class="nav-heading"><span><?= lang('System.page.sales') ?></span></li>
            <li class="nav-item">
                <a class="nav-link<?= ('sales' == $slug ? ' active' : '') ?>" href="<?= base_url('admin/sales') ?>">
                    <span class="nav-icon"><i class="bi bi-receipt-cutoff"></i></span>
                    <span class="nav-text"><?= lang('System.page.sales') ?></span>
                </a>
            </li>
            <!-- ONLY BUSINESS OWNER and MANAGER -->
            <?php if (canManageBusiness()): ?>
            <li class="nav-heading"><span><?= lang('System.page.business') ?></span></li>
            <li class="nav-item has-submenu ">
                <a class="nav-link" href="#" aria-expanded="false">
                    <span class="nav-icon"><i class="bi bi-shop"></i></span>
                    <span class="nav-text"><?= lang('System.page.business') ?></span>
                    <i class="ph ph-caret-right nav-arrow"></i>
                </a>
                <ul class="nav-submenu ">
                    <li><a class="nav-link<?= ('business' == $slug ? ' active' : '') ?>" href="<?= base_url('admin/business') ?>"><?= lang('System.page.business') ?></a></li>
                    <li><a class="nav-link<?= ('stores' == $slug ? ' active' : '') ?>" href="<?= base_url('admin/business/stores') ?>"><?= lang('System.page.stores') ?></a></li>
                    <?php if (isBusinessOwner()): ?>
                    <li><a class="nav-link<?= ('plans' == $slug ? ' active' : '') ?>" href="<?= base_url('admin/plans') ?>"><?= lang('System.page.plans') ?></a></li>
                    <?php endif ?>
                </ul>
            </li>
            <li class="nav-item">
                <a class="nav-link<?= ('employees' == $slug ? ' active' : '') ?>" href="<?= base_url('admin/employees') ?>">
                    <span class="nav-icon"><i class="bi bi-people"></i></span>
                    <span class="nav-text"><?= lang('System.page.employees') ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link<?= ('artifacts' == $slug ? ' active' : '') ?>" href="<?= base_url('admin/artifacts') ?>">
                    <span class="nav-icon"><i class="bi bi-box"></i></span>
                    <span class="nav-text"><?= lang('System.page.products-and-services') ?></span>
                </a>
            </li>
            <?php endif ?>
            <li class="nav-item">
                <a class="nav-link<?= ('reviews' == $slug ? ' active' : '') ?>" href="<?= base_url('admin/reviews') ?>">
                    <span class="nav-icon"><i class="bi bi-star"></i></span>
                    <span class="nav-text"><?= lang('System.page.reviews') ?></span>
                </a>
            </li>
        </ul>
    </nav>
    <!-- Sidebar Footer - User Card -->
    <div class="sidebar-footer">
        <div class="sidebar-user">
            <a href="<?= base_url('admin/profile') ?>" class="sidebar-user-link">
                <span class="sidebar-user-avatar">
                    <img src="<?= esc(userAvatarUrl($user['avatar_path'] ?? null), 'attr') ?>" alt="<?= esc(userDisplayName($user), 'attr') ?>" />
                    <span class="sidebar-user-status"></span>
                </span>
                <span class="sidebar-user-info">
                    <span class="sidebar-user-name"><?= esc(userDisplayName($user)) ?></span>
                    <span class="sidebar-user-role"><?= esc(currentBusinessName() ?? '') ?></span>
                </span>
            </a>
            <div class="sidebar-user-actions">
                <a href="<?= base_url('logout') ?>" class="sidebar-user-action sidebar-user-logout" title="<?= lang('System.page.sign-out') ?>">
                    <i class="bi bi-box-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>
</aside>
<!-- Sidebar Overlay (Mobile) -->
<div class="sidebar-overlay"></div>
<!-- Main Content -->
<main class="main">
    <div class="main-content page-blank">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title"><?= lang('System.page.' . $slug) ?></h1>
            <nav class="breadcrumb">
                <a href="<?= base_url('admin/dashboard') ?>" class="breadcrumb-item">Home</a>
                <?php if ('dashboard' != $slug): ?>
                    <!-- if there are other pages in between, add them here: -->
                    <!-- the $breadcrumbs structure must be the URL => page title -->
                    <?php if (isset($breadcrumbs)) : ?>
                        <?php foreach ($breadcrumbs as $u => $t) : ?>
                            <span class="breadcrumb-item"><a href="<?= $u ?>"><?= esc($t) ?></a></span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <!-- if not dashboard, at least it needs its own page here: -->
                    <span class="breadcrumb-item active"><?= lang('System.page.' . $slug) ?></span>
                <?php endif; ?>
            </nav>
        </div>
        <!-- PAGE CONTENTS GO HERE -->
        <?= $this->renderSection('content') ?>
    </div>
    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-brand">
                <span class="footer-copyright">&copy; 2025-<?= date('Y') ?> <?= lang('System.site-title') ?></span>
            </div>
        </div>
    </footer>
</main>
<!-- Back to Top -->
<a href="#" class="back-to-top">
    <i class="bi bi-arrow-up"></i>
</a>
<!-- Vendor JS Files -->
<script src="<?= base_url('assets/vendor/jquery/jquery-4.0.0.min.js') ?>"></script>
<script src="<?= base_url('assets/vendor/bootstrap/js/bootstrap.bundle.min.js') ?>"></script>
<!-- DataTables (jQuery), for the Table Builder of generic_list.php -->
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.min.js"></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.bootstrap5.min.js"></script>
<script src="<?= base_url('assets/vendor/apexcharts/apexcharts.min.js') ?>"></script>
<script src="<?= base_url('assets/vendor/chart.js/chart.umd.js') ?>"></script>
<script src="<?= base_url('assets/vendor/echarts/echarts.min.js') ?>"></script>
<script src="<?= base_url('assets/vendor/simple-datatables/simple-datatables.js') ?>"></script>
<script src="<?= base_url('assets/vendor/quill/quill.js') ?>"></script>
<script src="<?= base_url('assets/vendor/tinymce/tinymce.min.js') ?>"></script>
<script src="<?= base_url('assets/vendor/choices.js/choices.min.js') ?>"></script>
<script src="<?= base_url('assets/vendor/flatpickr/flatpickr.min.js') ?>"></script>
<script src="<?= base_url('assets/vendor/php-email-form/validate.js') ?>"></script>
<script src="<?= base_url('assets/vendor/toastr/toastr.min.js') ?>"></script>
<script src="<?= base_url('assets/vendor/sweetalert/sweetalert2.min.js') ?>"></script>
<!-- Template Main JS Files -->
<script src="<?= base_url('assets/js/theme.js') ?>"></script>
<script src="<?= base_url('assets/js/main.js') ?>"></script>
<script src="<?= base_url('assets/js/local-time.js') ?>"></script>
<!-- App Sidebar Toggle (for app pages with sidebars) -->
<script src="<?= base_url('assets/js/apps-sidebar-toggle.js') ?>"></script>
</body>
</html>