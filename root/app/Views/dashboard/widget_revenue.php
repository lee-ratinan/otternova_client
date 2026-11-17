<?php
/**
 * Dashboard widget: today's and this month's revenue, each currency apart (Dashboard::revenue())
 *
 * @var string|null $error
 * @var list<array> $currencies code, today, todayOrders, month, orders, collected, outstanding, refundDue, spark
 * @var string      $locale
 */
if (null !== $error) : ?>
    <div class="alert alert-warning mb-0"><?= esc($error) ?></div>
<?php return; endif;

if ([] === $currencies) : ?>
    <div class="text-center text-muted py-3"><?= esc(lang('Dashboard.revenue.empty')) ?></div>
<?php return; endif; ?>
<?php foreach ($currencies as $index => $sum) :
    $money = static fn ($amount): string => formatMoney($amount, $sum['code']);
    ?>
    <div class="row g-3 align-items-stretch<?= $index > 0 ? ' mt-1 pt-3 border-top' : '' ?>" data-revenue-currency="<?= esc($sum['code'], 'attr') ?>">
        <?php foreach ([
            ['today', $sum['today'], lang('Dashboard.revenue.orders', [$sum['todayOrders']]), 'text-primary'],
            ['month', $sum['month'], lang('Dashboard.revenue.orders', [$sum['orders']]), 'text-primary'],
            ['collected', $sum['collected'], '', 'text-success'],
            ['outstanding', $sum['outstanding'], (float) $sum['refundDue'] > 0 ? lang('RevenueReport.tile.refund-due', [$money($sum['refundDue'])]) : '', 'text-warning'],
        ] as [$key, $amount, $note, $tone]) : ?>
            <div class="col-6 col-lg-3">
                <div class="small text-muted"><?= esc(lang('Dashboard.revenue.' . $key)) ?></div>
                <div class="fs-4 fw-semibold text-nowrap <?= $tone ?>" data-revenue="<?= esc($key, 'attr') ?>"><?= esc($money($amount)) ?></div>
                <?php if ('' !== $note) : ?><div class="small <?= 'outstanding' === $key ? 'text-danger' : 'text-muted' ?>"><?= esc($note) ?></div><?php endif; ?>
                <?php if ('month' === $key) : ?>
                    <div data-sparkline="<?= esc(json_encode($sum['spark']), 'attr') ?>"></div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endforeach; ?>
