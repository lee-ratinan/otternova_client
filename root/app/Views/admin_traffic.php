<?php
/**
 * The session business's traffic on the marketplace; see App\Controllers\Traffic. Every card is filled by
 * assets/js/traffic.js from admin/traffic/data/*, so the page shows at once and each card loads (or fails) apart.
 * @var string $locale
 * @var string $from YYYY-MM-DD or ''
 * @var string $to   YYYY-MM-DD or ''
 */
$this->extend('_layout_admin');

// the script's words, in the page's language
$text = [];
foreach (['load-failed', 'compared', 'new', 'pts', 'measure', 'this-period', 'period-before', 'vs', 'empty', 'funnel', 'column',
             'source', 'device', 'stage', 'unknown', 'returning', 'bounced', 'more', 'page-count', 'one-page', 'detail',
             'times-in', 'range', 'visits-count'] as $key) {
    $text[$key] = lang('Traffic.' . $key);
}
$kpis = ['visits', 'visitors', 'pageviews', 'avg_visit_s', 'orders', 'revenue', 'conversion_rate', 'bounce_rate'];
$asset = static fn (string $path): string => base_url($path) . '?v=' . @filemtime(FCPATH . $path);
?>
<?= $this->section('content') ?>
<link href="<?= $asset('assets/css/traffic.css') ?>" rel="stylesheet">
<div class="page-traffic" id="traffic"
     data-from="<?= esc($from, 'attr') ?>"
     data-to="<?= esc($to, 'attr') ?>"
     data-locale="<?= esc($locale, 'attr') ?>"
     data-url="<?= esc(base_url('admin/traffic/data/'), 'attr') ?>">
    <script type="application/json" id="tr-text"><?= json_encode($text, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>

    <!-- filters -->
    <div class="tr-toolbar mb-3">
        <p class="tr-note mb-0"><?= esc(lang('Traffic.subtitle', [currentBusinessName() ?? ''])) ?></p>
        <div class="tr-filters">
            <div class="btn-group" role="group" aria-label="<?= esc(lang('Traffic.period'), 'attr') ?>">
                <?php foreach ([7, 30, 90] as $days) : ?>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-tr-days="<?= $days ?>"><?= esc(lang('Traffic.days', [$days])) ?></button>
                <?php endforeach; ?>
            </div>
            <div class="tr-range">
                <i class="bi bi-calendar3"></i>
                <input type="text" id="tr-range" class="form-control form-control-sm" placeholder="<?= esc(lang('Traffic.choose-dates'), 'attr') ?>" aria-label="<?= esc(lang('Traffic.period'), 'attr') ?>" readonly>
            </div>
        </div>
    </div>

    <!-- headline numbers: the traffic, then what it made -->
    <div class="card mb-3">
        <div class="card-body">
            <div class="tr-kpis" id="tr-kpis">
                <?php foreach ($kpis as $key) : ?>
                    <?php $help = ['conversion_rate' => lang('Traffic.conversion-help'), 'bounce_rate' => lang('Traffic.bounce-help')][$key] ?? ''; ?>
                    <div class="tr-kpi<?= 'conversion_rate' === $key ? ' tr-kpi-hero' : '' ?>" data-kpi="<?= $key ?>"<?= $help ? ' title="' . esc($help, 'attr') . '"' : '' ?>>
                        <span class="tr-kpi-label"><?= esc(lang('Traffic.kpi.' . $key)) ?><?= $help ? ' <i class="bi bi-info-circle"></i>' : '' ?></span>
                        <span class="tr-kpi-value"><span class="tr-skeleton"></span></span>
                        <span class="tr-kpi-trend">&nbsp;</span>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="tr-note mt-2" id="tr-period-note">&nbsp;</div>
        </div>
    </div>

    <!-- day by day -->
    <div class="card mb-3">
        <div class="card-header">
            <h5 class="card-title mb-0"><?= esc(lang('Traffic.card.day-by-day')) ?></h5>
            <div class="card-actions">
                <div class="btn-group flex-wrap" role="group" id="tr-measures">
                    <?php foreach (['visits', 'visitors', 'pageviews', 'orders', 'revenue', 'conversion'] as $i => $measure) : ?>
                        <button type="button" class="btn btn-sm btn-outline-secondary<?= 0 === $i ? ' active' : '' ?>" data-tr-measure="<?= $measure ?>"><?= esc(lang('Traffic.measure.' . $measure)) ?></button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div id="tr-chart" class="tr-chart"></div>
        </div>
    </div>

    <!-- shopping funnel -->
    <div class="card mb-3">
        <div class="card-header">
            <h5 class="card-title mb-0"><?= esc(lang('Traffic.card.funnel')) ?></h5>
        </div>
        <div class="card-body" id="tr-funnel"></div>
    </div>

    <!-- breakdowns -->
    <div class="row g-3 mb-3">
        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0"><?= esc(lang('Traffic.card.pages')) ?></h5>
                </div>
                <div class="card-body p-0" id="tr-pages"></div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0"><?= esc(lang('Traffic.card.sources')) ?></h5>
                    <div class="card-actions">
                        <div class="btn-group" role="group" id="tr-source-mode">
                            <button type="button" class="btn btn-sm btn-outline-secondary active" data-tr-source="source"><?= esc(lang('Traffic.sources-mode')) ?></button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-tr-source="referrer"><?= esc(lang('Traffic.sites-mode')) ?></button>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0" id="tr-sources"></div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0"><?= esc(lang('Traffic.card.countries')) ?></h5>
                </div>
                <div class="card-body p-0" id="tr-countries"></div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0"><?= esc(lang('Traffic.card.devices')) ?></h5>
                </div>
                <div class="card-body" id="tr-devices"></div>
            </div>
        </div>
    </div>

    <!-- visits -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0"><?= esc(lang('Traffic.card.visits')) ?></h5>
            <div class="card-actions tr-note" id="tr-visits-note"></div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 tr-visits">
                    <thead>
                    <tr>
                        <th><?= esc(lang('Traffic.column.when')) ?></th>
                        <th><?= esc(lang('Traffic.column.came-from')) ?></th>
                        <th><?= esc(lang('Traffic.column.pages')) ?></th>
                        <th><?= esc(lang('Traffic.column.where')) ?></th>
                        <th class="text-end"><?= esc(lang('Traffic.column.time')) ?></th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody id="tr-visits"></tbody>
                </table>
            </div>
        </div>
        <div class="card-footer tr-pager">
            <span class="tr-note" id="tr-visits-count"></span>
            <div class="btn-group">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="tr-visits-prev"><i class="bi bi-chevron-left"></i> <?= esc(lang('Traffic.newer')) ?></button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="tr-visits-next"><?= esc(lang('Traffic.older')) ?> <i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>
</div>
<script src="<?= $asset('assets/js/traffic.js') ?>" defer></script>
<?= $this->endSection() ?>
