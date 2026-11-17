<?php
/**
 * Dashboard widget: the marketplace traffic of the last days (Dashboard::traffic())
 *
 * @var string|null $error
 * @var int         $days
 * @var array       $kpis visits, visitors, orders (int|null), conversion_rate (float|null, a percentage)
 * @var string      $locale
 */
if (null !== $error) : ?>
    <div class="alert alert-warning mb-0"><?= esc($error) ?></div>
<?php return; endif; ?>
<div class="small text-muted mb-2"><?= esc(lang('Dashboard.traffic.period', [$days])) ?></div>
<div class="row g-2">
    <?php foreach ($kpis as $key => $value) : ?>
        <div class="col-6">
            <div class="border rounded p-2 h-100<?= 'conversion_rate' === $key ? ' border-primary' : '' ?>" data-traffic="<?= esc($key, 'attr') ?>">
                <div class="fs-5 fw-semibold">
                    <?php if (null === $value) : ?>—<?php elseif ('conversion_rate' === $key) : ?><?= number_format((float) $value, 2) ?>%<?php else : ?><?= number_format((int) $value) ?><?php endif; ?>
                </div>
                <div class="small text-muted"><?= esc(lang('Traffic.kpi.' . $key)) ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
