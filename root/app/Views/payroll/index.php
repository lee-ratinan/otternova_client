<?php
/**
 * What the people of the session business earn in a pay period (SalaryAndCommission::index()): the cycle and period
 * controls, then one row per person with their salary, commission and total, each opening their breakdown
 *
 * @var string      $cycle        M, S or W
 * @var string      $from         'Y-m-d', the period's first day
 * @var string      $to           'Y-m-d', its last day
 * @var string      $previous     the link to the period before
 * @var string      $next         the link to the period after
 * @var string      $currency     the business's currency
 * @var array       $commission   {status, period, threshold}
 * @var list<array> $people       v1/client/payroll's people
 * @var string|null $error        why the period could not be loaded
 * @var string      $businessName the session business
 * @var string      $locale
 */

use App\Controllers\SalaryAndCommission;

$this->extend('_layout_admin');

$money = static fn ($amount): string => formatMoney($amount, $currency);
$roles = (array) lang('SalaryAndCommission.role');
$sum   = static fn (string $key): string => number_format(array_sum(array_map(static fn (array $p): float => (float) $p[$key], $people)), 2, '.', '');
?>
<?= $this->section('content') ?>
<div class="card" id="salary-and-commission">
    <div class="card-header">
        <h5 class="card-title mb-0"><?= esc(lang('SalaryAndCommission.title')) ?> · <?= esc(formatDate($from, $locale) . ' – ' . formatDate($to, $locale)) ?></h5>
        <small class="text-muted"><?= esc(lang('SalaryAndCommission.subtitle', [$businessName])) ?></small>
    </div>
    <div class="card-body">
        <div class="d-md-none mb-3">
            <button type="button" class="btn btn-outline-secondary w-100" data-bs-toggle="collapse" data-bs-target="#payroll-filters"
                    aria-expanded="false" aria-controls="payroll-filters"><i class="bi bi-funnel"></i> <?= esc(lang('System.filters')) ?></button>
        </div>
        <div class="collapse d-md-block" id="payroll-filters">
        <div class="d-flex flex-wrap gap-2 align-items-end mb-3">
            <div class="btn-group col-12 col-md-auto" role="group">
                <?php foreach ((array) lang('SalaryAndCommission.cycle') as $key => $name) : ?>
                    <a class="btn px-2 px-sm-3 text-nowrap <?= $cycle === $key ? 'btn-primary' : 'btn-outline-secondary' ?>" href="<?= esc(base_url('admin/salary-and-commission') . '?cycle=' . $key, 'attr') ?>"><?= esc($name) ?></a>
                <?php endforeach; ?>
            </div>
            <div class="btn-group col-12 col-md-auto" role="group">
                <a class="btn btn-outline-secondary flex-grow-0" href="<?= esc($previous, 'attr') ?>" title="<?= esc(lang('SalaryAndCommission.previous'), 'attr') ?>"
                   aria-label="<?= esc(lang('SalaryAndCommission.previous'), 'attr') ?>"><i class="bi bi-chevron-left"></i></a>
                <a class="btn btn-outline-secondary text-nowrap" href="<?= esc(base_url('admin/salary-and-commission') . '?cycle=' . $cycle, 'attr') ?>"><?= esc(lang('SalaryAndCommission.this.' . $cycle)) ?></a>
                <a class="btn btn-outline-secondary flex-grow-0" href="<?= esc($next, 'attr') ?>" title="<?= esc(lang('SalaryAndCommission.next'), 'attr') ?>"
                   aria-label="<?= esc(lang('SalaryAndCommission.next'), 'attr') ?>"><i class="bi bi-chevron-right"></i></a>
            </div>
            <form method="get" action="<?= esc(base_url('admin/salary-and-commission'), 'attr') ?>" class="d-flex gap-2 align-items-end col-12 col-md-auto ms-md-auto">
                <input type="hidden" name="cycle" value="<?= esc($cycle, 'attr') ?>">
                <div class="flex-fill">
                    <label for="payroll-date" class="form-label small mb-1"><?= esc(lang('SalaryAndCommission.date')) ?></label>
                    <input type="date" class="form-control" id="payroll-date" name="date" value="<?= esc($from, 'attr') ?>" required onchange="this.form.submit()">
                </div>
                <button type="submit" class="btn btn-outline-primary"><?= esc(lang('SalaryAndCommission.go')) ?></button>
            </form>
            <?php if (null === $error) : ?>
                <a class="btn btn-outline-secondary col-12 col-md-auto text-nowrap" target="_blank" rel="noopener" id="payroll-export-link"
                   href="<?= esc(base_url('admin/salary-and-commission/export') . '?' . http_build_query(['cycle' => $cycle, 'date' => $from]), 'attr') ?>"><i class="bi bi-printer"></i> <?= esc(lang('System.print.export')) ?></a>
            <?php endif; ?>
        </div>
        </div>

        <?php if ([] !== $commission) : ?>
            <p class="small text-muted mb-3">
                <i class="bi bi-info-circle"></i>
                <?php if ('A' === ($commission['status'] ?? '')) : ?>
                    <?= esc((float) $commission['threshold'] > 0
                        ? lang('SalaryAndCommission.policy.active', [lang('SalaryAndCommission.policy.period.' . $commission['period']), $money($commission['threshold'])])
                        : lang('SalaryAndCommission.policy.no-threshold', [lang('SalaryAndCommission.policy.period.' . $commission['period'])])) ?>
                <?php else : ?>
                    <?= esc(lang('SalaryAndCommission.policy.inactive')) ?>
                <?php endif; ?>
                <?= esc(lang('SalaryAndCommission.note')) ?>
            </p>
        <?php endif; ?>

        <?php if (null !== $error) : ?>
            <div class="alert alert-danger mb-0"><?= esc($error) ?></div>
        <?php elseif ([] === $people) : ?>
            <div class="text-center text-muted py-5">
                <i class="bi bi-cash-coin fs-1 d-block mb-2"></i>
                <?= esc(lang('SalaryAndCommission.empty')) ?>
            </div>
        <?php else : ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                    <tr>
                        <th><?= esc(lang('SalaryAndCommission.column.person')) ?></th>
                        <th class="text-end"><?= esc(lang('SalaryAndCommission.column.salary')) ?></th>
                        <th class="text-end"><?= esc(lang('SalaryAndCommission.column.sales')) ?></th>
                        <th class="text-end"><?= esc(lang('SalaryAndCommission.column.commission')) ?></th>
                        <th class="text-end"><?= esc(lang('SalaryAndCommission.column.total')) ?></th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($people as $person) :
                        $detail = SalaryAndCommission::link($cycle, $from, (string) $person['uid']);
                        ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <img src="<?= esc(avatarUrl($person['avatar_path'] ?? null), 'attr') ?>" alt="" class="rounded-circle" width="32" height="32" style="object-fit: cover">
                                    <div>
                                        <a href="<?= esc($detail, 'attr') ?>" class="fw-semibold text-decoration-none"><?= esc((string) $person['name']) ?></a>
                                        <div class="d-flex flex-wrap gap-1">
                                            <span class="small text-muted"><?= esc((string) ($roles[$person['client_role']] ?? '')) ?></span>
                                            <?php if (($person['start_date'] ?? '') > $from) : ?>
                                                <span class="badge bg-info text-dark"><?= esc(lang('SalaryAndCommission.joined', [formatDate((string) $person['start_date'], $locale)])) ?></span>
                                            <?php endif; ?>
                                            <?php if (! empty($person['termination_date']) && $person['termination_date'] <= $to) : ?>
                                                <span class="badge bg-secondary"><?= esc(lang('SalaryAndCommission.terminated', [formatDate((string) $person['termination_date'], $locale)])) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="text-end text-nowrap">
                                <?= esc($money($person['salary'])) ?>
                                <?php if (! empty($person['prorated'])) : ?>
                                    <div><span class="badge bg-warning text-dark"><?= esc(lang('SalaryAndCommission.prorated')) ?></span></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-end text-nowrap text-muted"><?= esc($money($person['sales'])) ?></td>
                            <td class="text-end text-nowrap">
                                <?= esc($money($person['commission'])) ?>
                                <?php if (! empty($person['below_threshold'])) : ?>
                                    <div><span class="badge bg-light text-dark border"><?= esc(lang('SalaryAndCommission.below')) ?></span></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-end text-nowrap fw-semibold"><?= esc($money($person['total'])) ?></td>
                            <td class="text-end">
                                <a href="<?= esc($detail, 'attr') ?>" class="btn btn-sm btn-outline-primary text-nowrap"><i class="bi bi-list-columns-reverse"></i> <?= esc(lang('SalaryAndCommission.breakdown')) ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                    <tr class="fw-semibold">
                        <td><?= esc(lang('SalaryAndCommission.column.total-row')) ?></td>
                        <td class="text-end text-nowrap"><?= esc($money($sum('salary'))) ?></td>
                        <td class="text-end text-nowrap text-muted"><?= esc($money($sum('sales'))) ?></td>
                        <td class="text-end text-nowrap"><?= esc($money($sum('commission'))) ?></td>
                        <td class="text-end text-nowrap"><?= esc($money($sum('total'))) ?></td>
                        <td></td>
                    </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
