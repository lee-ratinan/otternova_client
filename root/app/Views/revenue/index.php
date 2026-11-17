<?php
/**
 * The revenue of the session business in a period (RevenueReport::index()): the range controls, then for each
 * currency its tiles, a chart by day (or month), how sales add up and their breakdowns, then the orders
 *
 * @var string      $period       day, week, month or custom
 * @var string      $from         'Y-m-d', the first day shown
 * @var string      $to           'Y-m-d', the last day shown
 * @var string      $previous     the link to the period before
 * @var string      $next         the link to the period after
 * @var string      $granularity  day or month
 * @var list<array> $currencies   v1/client/revenue's currencies
 * @var list<array> $orders       v1/client/revenue's orders
 * @var int         $orderCount   all the orders of the period
 * @var string|null $error        why the period could not be loaded
 * @var string      $businessName the session business
 * @var string      $locale
 */
$this->extend('_layout_admin');

$base     = base_url('admin/revenue-report');
$range    = $from === $to ? formatDate($from, $locale) : formatDate($from, $locale) . ' – ' . formatDate($to, $locale);
$kinds    = (array) lang('RevenueReport.kind');
$statuses = (array) lang('Sales.enum.financial_status');
$label    = static function (string $period, string $granularity, string $locale): string {
    if ('month' === $granularity) {
        $time = strtotime($period . '-01');
        return 'th' === $locale ? ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'][date('n', $time) - 1] . ' ' . (date('Y', $time) + 543)
            : date('M Y', $time);
    }
    return (string) formatDate($period, $locale);
};
$share    = static fn (string $amount, string $total): float => (float) $total > 0 ? max(0, min(100, (float) $amount / (float) $total * 100)) : 0;
?>
<?= $this->section('content') ?>
<div class="card mb-3" id="revenue-report">
    <div class="card-header">
        <h5 class="card-title mb-0"><?= esc(lang('RevenueReport.title')) ?> · <?= esc($range) ?></h5>
        <small class="text-muted"><?= esc(lang('RevenueReport.subtitle', [$businessName])) ?></small>
    </div>
    <div class="card-body">
        <div class="d-flex flex-wrap gap-2 align-items-end">
            <div class="btn-group" role="group">
                <a class="btn btn-outline-secondary" href="<?= esc($previous, 'attr') ?>" title="<?= esc(lang('RevenueReport.previous'), 'attr') ?>"
                   aria-label="<?= esc(lang('RevenueReport.previous'), 'attr') ?>"><i class="bi bi-chevron-left"></i></a>
                <?php foreach ((array) lang('RevenueReport.period') as $key => $name) : ?>
                    <a class="btn <?= $period === $key ? 'btn-primary' : 'btn-outline-secondary' ?>" href="<?= esc($base . '?period=' . $key, 'attr') ?>"><?= esc($name) ?></a>
                <?php endforeach; ?>
                <a class="btn btn-outline-secondary" href="<?= esc($next, 'attr') ?>" title="<?= esc(lang('RevenueReport.next'), 'attr') ?>"
                   aria-label="<?= esc(lang('RevenueReport.next'), 'attr') ?>"><i class="bi bi-chevron-right"></i></a>
            </div>
            <form method="get" action="<?= esc($base, 'attr') ?>" class="d-flex flex-wrap gap-2 align-items-end ms-md-auto">
                <input type="hidden" name="period" value="custom">
                <div>
                    <label for="revenue-from" class="form-label small mb-1"><?= esc(lang('RevenueReport.from')) ?></label>
                    <input type="date" class="form-control" id="revenue-from" name="from" value="<?= esc($from, 'attr') ?>" required>
                </div>
                <div>
                    <label for="revenue-to" class="form-label small mb-1"><?= esc(lang('RevenueReport.to')) ?></label>
                    <input type="date" class="form-control" id="revenue-to" name="to" value="<?= esc($to, 'attr') ?>" required>
                </div>
                <button type="submit" class="btn <?= 'custom' === $period ? 'btn-primary' : 'btn-outline-primary' ?>"><?= esc(lang('RevenueReport.go')) ?></button>
            </form>
        </div>
    </div>
</div>

<?php if (null !== $error) : ?>
    <div class="alert alert-danger"><?= esc($error) ?></div>
<?php else : ?>
    <?php foreach ($currencies as $index => $sum) :
        $code    = (string) $sum['currency_code'];
        $money   = static fn ($amount): string => formatMoney($amount, $code);
        $periods = (array) ($sum['periods'] ?? []);
        ?>
        <div class="row g-3 mb-3">
            <?php foreach ([
                ['sales', $sum['sales'], lang('RevenueReport.tile.sales-note', [(int) $sum['orders']]), 'bi-receipt', 'text-primary'],
                ['collected', $sum['collected'], lang('RevenueReport.tile.collected-note'), 'bi-cash-stack', 'text-success'],
                ['outstanding', $sum['outstanding'], lang('RevenueReport.tile.outstanding-note'), 'bi-hourglass-split', 'text-warning'],
                ['average', $sum['average'], '', 'bi-calculator', 'text-info'],
            ] as [$key, $amount, $note, $icon, $tone]) : ?>
                <div class="col-6 col-xl">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="small text-muted"><i class="bi <?= $icon ?> <?= $tone ?>"></i> <?= esc(lang('RevenueReport.tile.' . $key)) ?></div>
                            <div class="fs-4 fw-semibold text-nowrap" data-revenue="<?= esc($key, 'attr') ?>"><?= esc($money($amount)) ?></div>
                            <?php if ('' !== $note) : ?><div class="small text-muted"><?= esc($note) ?></div><?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            <div class="col-6 col-xl">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="small text-muted"><i class="bi bi-x-circle text-danger"></i> <?= esc(lang('RevenueReport.tile.cancelled')) ?></div>
                        <div class="fs-4 fw-semibold"><?= (int) $sum['cancelled_orders'] ?></div>
                        <div class="small text-muted"><?= esc(lang('RevenueReport.tile.refunded', [$money($sum['refunded'])])) ?></div>
                        <?php if ((float) $sum['refund_due'] > 0) : ?>
                            <div class="small text-danger"><?= esc(lang('RevenueReport.tile.refund-due', [$money($sum['refund_due'])])) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if (count($periods) > 1) : ?>
            <div class="card mb-3">
                <div class="card-header"><h6 class="card-title mb-0"><?= esc(lang('RevenueReport.chart.title')) ?><?= count($currencies) > 1 ? ' · ' . esc($code) : '' ?></h6></div>
                <div class="card-body">
                    <div class="revenue-chart" style="min-height: 300px" data-currency="<?= esc($code, 'attr') ?>"
                         data-chart="<?= esc(json_encode([
                             'labels'    => array_map(static fn (array $p): string => $label((string) $p['period'], $granularity, $locale), $periods),
                             'sales'     => array_map(static fn (array $p): float => (float) $p['sales'], $periods),
                             'collected' => array_map(static fn (array $p): float => (float) $p['collected'], $periods),
                             'names'     => [lang('RevenueReport.chart.sales'), lang('RevenueReport.chart.collected')],
                         ], JSON_UNESCAPED_UNICODE), 'attr') ?>"></div>
                </div>
            </div>
        <?php endif; ?>

        <div class="row g-3 mb-3">
            <div class="col-lg-6 col-xxl-3">
                <div class="card h-100">
                    <div class="card-header"><h6 class="card-title mb-0"><?= esc(lang('RevenueReport.summary.title')) ?></h6></div>
                    <div class="card-body">
                        <table class="table table-sm mb-0">
                            <tbody>
                            <tr><td><?= esc(lang('RevenueReport.summary.subtotal')) ?></td><td class="text-end text-nowrap"><?= esc($money($sum['subtotal'])) ?></td></tr>
                            <tr><td><?= esc(lang('RevenueReport.summary.discount')) ?></td><td class="text-end text-nowrap">−<?= esc($money($sum['discount'])) ?></td></tr>
                            <tr><td><?= esc(lang('RevenueReport.summary.shipping')) ?></td><td class="text-end text-nowrap"><?= esc($money($sum['shipping'])) ?></td></tr>
                            <tr><td class="text-muted small"><?= esc(lang('RevenueReport.summary.tax')) ?></td><td class="text-end text-nowrap text-muted small"><?= esc($money($sum['tax'])) ?></td></tr>
                            <tr class="fw-semibold"><td><?= esc(lang('RevenueReport.summary.total')) ?></td><td class="text-end text-nowrap"><?= esc($money($sum['sales'])) ?></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php foreach ([
                'kind'   => array_map(static fn (array $k): array => [(string) ($kinds[$k['kind']] ?? $k['kind']), lang('RevenueReport.by.quantity', [(int) $k['quantity']]), (string) $k['amount']], (array) $sum['kinds']),
                'store'  => array_map(static fn (array $s): array => ['' === (string) $s['store_name'] ? lang('RevenueReport.by.no-store') : (string) $s['store_name'], '', (string) $s['amount']], (array) $sum['stores']),
                'method' => array_map(static fn (array $m): array => [(string) $m['payment_method_name'], lang('RevenueReport.by.orders', [(int) $m['orders']]), (string) $m['amount']], (array) $sum['methods']),
            ] as $by => $rows) :
                $whole = 'method' === $by ? (string) $sum['sales'] : (string) array_sum(array_map(static fn (array $r): float => (float) $r[2], $rows));
                ?>
                <div class="col-lg-6 col-xxl-3">
                    <div class="card h-100">
                        <div class="card-header">
                            <h6 class="card-title mb-0"><?= esc(lang('RevenueReport.by.' . $by)) ?></h6>
                            <?php if ('method' !== $by) : ?><small class="text-muted"><?= esc(lang('RevenueReport.by.note')) ?></small><?php endif; ?>
                        </div>
                        <div class="card-body">
                            <?php if ([] === $rows) : ?>
                                <p class="text-muted small mb-0">—</p>
                            <?php endif; ?>
                            <?php foreach ($rows as [$name, $note, $amount]) : ?>
                                <div class="mb-2">
                                    <div class="d-flex justify-content-between gap-2 small">
                                        <span><?= esc($name) ?> <?php if ('' !== $note) : ?><span class="text-muted"><?= esc($note) ?></span><?php endif; ?></span>
                                        <span class="fw-semibold text-nowrap"><?= esc($money($amount)) ?></span>
                                    </div>
                                    <div class="progress" style="height: 6px" role="presentation">
                                        <div class="progress-bar" style="width: <?= number_format($share($amount, $whole), 1, '.', '') ?>%"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>

    <div class="card">
        <div class="card-header"><h6 class="card-title mb-0"><?= esc(lang('RevenueReport.orders.title')) ?> (<?= $orderCount ?>)</h6></div>
        <div class="card-body">
            <?php if ([] === $orders) : ?>
                <div class="text-center text-muted py-5">
                    <i class="bi bi-receipt fs-1 d-block mb-2"></i>
                    <?= esc(lang('RevenueReport.empty')) ?>
                </div>
            <?php else : ?>
                <?php if ($orderCount > count($orders)) : ?>
                    <p class="small text-muted"><?= esc(lang('RevenueReport.orders.shown', [count($orders), $orderCount])) ?></p>
                <?php endif; ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                        <tr>
                            <th><?= esc(lang('RevenueReport.orders.number')) ?></th>
                            <th><?= esc(lang('RevenueReport.orders.placed')) ?></th>
                            <th><?= esc(lang('RevenueReport.orders.customer')) ?></th>
                            <th><?= esc(lang('RevenueReport.orders.method')) ?></th>
                            <th><?= esc(lang('RevenueReport.orders.status')) ?></th>
                            <th class="text-end"><?= esc(lang('RevenueReport.orders.total')) ?></th>
                            <th class="text-end"><?= esc(lang('RevenueReport.orders.paid')) ?></th>
                            <th class="text-end"><?= esc(lang('RevenueReport.orders.balance')) ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($orders as $order) :
                            $money     = static fn ($amount): string => formatMoney($amount, (string) $order['currency_code']);
                            $cancelled = ! empty($order['cancelled']);
                            $local     = (string) $order['local_created_at'];
                            ?>
                            <tr class="<?= $cancelled ? 'text-muted' : '' ?>">
                                <td class="text-nowrap">
                                    <a href="<?= esc(base_url('admin/sales/order/' . rawurlencode((string) $order['public_id'])), 'attr') ?>"><?= esc((string) ($order['order_number'] ?? $order['public_id'])) ?></a>
                                    <?php if ('counter' === ($order['order_source'] ?? '')) : ?>
                                        <span class="badge bg-light text-dark border"><?= esc(lang('RevenueReport.orders.counter')) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-nowrap"><?= esc(formatDate(substr($local, 0, 10), $locale) . ' ' . substr($local, 11, 5)) ?></td>
                                <td><?= esc((string) $order['customer_name']) ?></td>
                                <td><?= esc((string) $order['payment_method_name']) ?></td>
                                <td>
                                    <?php if ($cancelled) : ?>
                                        <span class="badge bg-danger"><?= esc(lang('RevenueReport.orders.cancelled')) ?></span>
                                    <?php endif; ?>
                                    <span class="badge bg-secondary"><?= esc((string) ($statuses[$order['financial_status']] ?? $order['financial_status'])) ?></span>
                                </td>
                                <td class="text-end text-nowrap<?= $cancelled ? ' text-decoration-line-through' : '' ?>"><?= esc($money($order['total_amount'])) ?></td>
                                <td class="text-end text-nowrap"><?= esc($money($order['net_paid'])) ?></td>
                                <td class="text-end text-nowrap"><?= (float) $order['balance_due'] > 0 ? esc($money($order['balance_due'])) : '—' ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        window.addEventListener('load', function () {
            if (typeof ApexCharts === 'undefined') {
                return;
            }
            document.querySelectorAll('.revenue-chart').forEach(function (el) {
                const data = JSON.parse(el.dataset.chart);
                const style = getComputedStyle(document.body);
                const money = new Intl.NumberFormat(undefined, {style: 'currency', currency: el.dataset.currency});
                new ApexCharts(el, {
                    chart: {type: 'bar', height: 300, toolbar: {show: false}, fontFamily: 'inherit', foreColor: style.color},
                    series: [{name: data.names[0], data: data.sales}, {name: data.names[1], data: data.collected}],
                    xaxis: {categories: data.labels, labels: {rotate: -45, hideOverlappingLabels: true}},
                    yaxis: {labels: {formatter: function (value) { return money.format(value); }}},
                    plotOptions: {bar: {columnWidth: '60%', borderRadius: 3}},
                    dataLabels: {enabled: false},
                    legend: {position: 'top', horizontalAlign: 'right'},
                    grid: {strokeDashArray: 4},
                    tooltip: {theme: document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light',
                        y: {formatter: function (value) { return money.format(value); }}}
                }).render();
            });
        });
    </script>
<?php endif; ?>
<?= $this->endSection() ?>
