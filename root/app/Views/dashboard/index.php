<?php
/**
 * The dashboard (Dashboard::index()): a greeting with the quick actions of the client's role, then one card per
 * widget, each filled in by the script below from admin/dashboard/widget/{name}, so each loads (or fails) apart
 *
 * @var string|null  $role         O, M or E in the session business; null with no business
 * @var list<string> $widgets      the widgets the role sees, in order
 * @var string       $businessName the session business
 * @var string       $widgetUrl    admin/dashboard/widget/, the widget's name appended
 * @var array        $user         the session user
 * @var string       $locale
 */
$this->extend('_layout_admin');

$hour     = (int) date('G');
$greeting = lang('Dashboard.greeting.' . ($hour < 12 ? 'morning' : ($hour < 18 ? 'afternoon' : 'evening')), [userDisplayName($user)]);
$roles    = (array) lang('Dashboard.role');

// the quick actions of each role: [url, icon, label key, least role]
$actions = [
    ['admin/sales/new', 'bi-bag-plus', 'new-order', 'E'],
    ['admin/calendar?mine=1', 'bi-calendar-check', 'my-calendar', 'E'],
    ['admin/sessions/create', 'bi-people', 'new-session', 'M'],
    ['admin/staff', 'bi-person-badge', 'staff', 'M'],
    ['admin/revenue-report', 'bi-graph-up-arrow', 'revenue', 'O'],
    ['admin/salary-and-commission', 'bi-wallet2', 'payroll', 'O'],
];
$rank = ['E' => 1, 'M' => 2, 'O' => 3];

// the card each widget sits in: [title key, icon, link to its page, columns]
$cards = [
    'revenue'      => ['revenue', 'bi-cash-coin', 'admin/revenue-report?period=month', 'col-12'],
    'today'        => ['today', 'bi-calendar-day', 'admin/calendar', 'col-xl-7'],
    'orders'       => ['orders', 'bi-receipt', 'admin/sales', 'col-xl-5'],
    'traffic'      => ['traffic', 'bi-bar-chart-line', 'admin/traffic', 'col-lg-6'],
    'reviews'      => ['reviews', 'bi-star', 'admin/reviews', 'col-lg-6'],
    'subscription' => ['subscription', 'bi-gem', 'admin/plans', 'col-lg-6'],
];
// the order of the cards on the page: the owner's money first, then the day
$order = ['revenue', 'today', 'orders', 'traffic', 'reviews', 'subscription'];
?>
<?= $this->section('content') ?>
<div class="page-dashboard" id="dashboard" data-role="<?= esc((string) $role, 'attr') ?>">
    <div class="card mb-3">
        <div class="card-body d-flex flex-wrap align-items-center gap-3">
            <div class="me-auto">
                <h4 class="mb-1"><?= esc($greeting) ?></h4>
                <?php if (null === $role) : ?>
                    <p class="text-muted mb-0"><?= esc(lang('Dashboard.no-business')) ?></p>
                <?php else : ?>
                    <p class="text-muted mb-0">
                        <?= esc(lang('Dashboard.subtitle', [$businessName])) ?>
                        <span class="badge bg-light text-dark border ms-1" data-dashboard-role><?= esc((string) ($roles[$role] ?? $role)) ?></span>
                    </p>
                <?php endif; ?>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <?php if (null === $role) : ?>
                    <a href="<?= base_url('admin/create-business') ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> <?= esc(lang('Dashboard.action.create-business')) ?></a>
                <?php else : ?>
                    <?php foreach ($actions as [$url, $icon, $key, $least]) : ?>
                        <?php if ($rank[$role] >= $rank[$least]) : ?>
                            <a href="<?= esc(base_url($url), 'attr') ?>" class="btn <?= 'new-order' === $key ? 'btn-primary' : 'btn-outline-secondary' ?>" data-dashboard-action="<?= esc($key, 'attr') ?>">
                                <i class="bi <?= $icon ?>"></i> <?= esc(lang('Dashboard.action.' . $key)) ?>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <?php foreach ($order as $name) :
            if (! in_array($name, $widgets, true)) {
                continue;
            }
            [$title, $icon, $link, $columns] = $cards[$name];
            ?>
            <div class="<?= $columns ?>">
                <div class="card h-100" data-widget="<?= esc($name, 'attr') ?>">
                    <div class="card-header d-flex align-items-center">
                        <h6 class="card-title mb-0 me-auto"><i class="bi <?= $icon ?> text-primary"></i> <?= esc(lang('Dashboard.widget.' . $title)) ?></h6>
                        <a href="<?= esc(base_url($link), 'attr') ?>" class="small text-decoration-none"><?= esc(lang('Dashboard.view-all')) ?> <i class="bi bi-arrow-right"></i></a>
                    </div>
                    <div class="card-body" data-widget-body>
                        <div class="placeholder-glow" aria-hidden="true">
                            <span class="placeholder col-7 mb-2"></span>
                            <span class="placeholder col-4 mb-2"></span>
                            <span class="placeholder col-9"></span>
                        </div>
                        <span class="visually-hidden"><?= esc(lang('Dashboard.loading')) ?></span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php if ([] !== $widgets) : ?>
<script>
(function (cfg) {
    'use strict';
    var csrfHash = cfg.csrf.hash;

    function failed(body, message) {
        body.innerHTML = '';
        var alert = document.createElement('div');
        alert.className = 'alert alert-warning mb-0 d-flex align-items-center gap-2';
        alert.textContent = message || cfg.failed;
        var retry = document.createElement('button');
        retry.type = 'button';
        retry.className = 'btn btn-sm btn-outline-secondary ms-auto';
        retry.textContent = cfg.retry;
        retry.addEventListener('click', function () { load(body.closest('[data-widget]')); });
        alert.appendChild(retry);
        body.appendChild(alert);
    }

    // the sparklines a widget draws: [data-sparkline] with its values in JSON
    function sparklines(root) {
        if (typeof ApexCharts === 'undefined') { return; }
        root.querySelectorAll('[data-sparkline]').forEach(function (el) {
            var values = JSON.parse(el.getAttribute('data-sparkline') || '[]');
            if (values.length < 2) { return; }
            new ApexCharts(el, {
                chart: {type: 'area', height: 48, sparkline: {enabled: true}},
                series: [{data: values}],
                stroke: {width: 2, curve: 'smooth'},
                fill: {opacity: 0.2},
                tooltip: {enabled: false}
            }).render();
        });
    }

    function load(card) {
        var body = card.querySelector('[data-widget-body]');
        var headers = {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'};
        headers[cfg.csrf.header] = csrfHash;
        return fetch(cfg.url + encodeURIComponent(card.getAttribute('data-widget')), {method: 'POST', headers: headers, credentials: 'same-origin'})
            .then(function (response) {
                return response.json().catch(function () { return {}; }).then(function (json) { return {ok: response.ok, json: json}; });
            })
            .then(function (result) {
                var json = result.json || {};
                if (json.csrf_hash) { csrfHash = json.csrf_hash; }
                if (json.redirect && json.status === 'error') { window.location.href = json.redirect; return; } // session expired
                if (!result.ok || !json.success) { failed(body, json.message); return; }
                body.innerHTML = json.html;
                sparklines(body);
            })
            .catch(function () { failed(body); });
    }

    // one at a time, as the CSRF token may change with each answer
    Array.prototype.reduce.call(document.querySelectorAll('#dashboard [data-widget]'), function (chain, card) {
        return chain.then(function () { return load(card); });
    }, Promise.resolve());
})(<?= json_encode([
    'url'    => $widgetUrl,
    'failed' => lang('Dashboard.load-failed'),
    'retry'  => lang('Dashboard.retry'),
    'csrf'   => ['header' => csrf_header(), 'hash' => csrf_hash()],
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>);
</script>
<?php endif; ?>
<?= $this->endSection() ?>
