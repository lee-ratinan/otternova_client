<?php
/**
 * One person's pay in a pay period (SalaryAndCommission::person()): their totals, then how the salary (pro rata by
 * day) and the commission (line by line) are worked out
 *
 * @var string $cycle      M, S or W
 * @var string $from       'Y-m-d', the period's first day
 * @var string $to         'Y-m-d', its last day
 * @var string $currency   the business's currency
 * @var array  $commission {status, period, threshold}
 * @var array  $person     v1/client/payroll/person's person
 * @var string $back       the list of the period
 * @var string $locale
 */
$this->extend('_layout_admin');

$money       = static fn ($amount): string => formatMoney($amount, $currency);
$roles       = (array) lang('SalaryAndCommission.role');
$kinds       = (array) lang('SalaryAndCommission.person.kind');
$salaryLines = (array) ($person['salary_lines'] ?? []);
$lines       = (array) ($person['commission_lines'] ?? []);
$range       = formatDate($from, $locale) . ' – ' . formatDate($to, $locale);
?>
<?= $this->section('content') ?>
<section class="section" id="payroll-person">
    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <img src="<?= esc(avatarUrl($person['avatar_path'] ?? null), 'attr') ?>" alt="" class="rounded-circle" width="56" height="56" style="object-fit: cover">
                        <div>
                            <a href="<?= esc(base_url('admin/public-profile/' . rawurlencode((string) $person['uid'])), 'attr') ?>" class="fw-semibold fs-5 text-decoration-none"><?= esc((string) $person['name']) ?></a>
                            <div class="small text-muted"><?= esc((string) ($roles[$person['client_role']] ?? '')) ?></div>
                        </div>
                    </div>
                    <div class="small text-muted mb-1"><?= esc(lang('SalaryAndCommission.person.summary', [$range])) ?> · <?= esc(lang('SalaryAndCommission.cycle.' . $cycle)) ?></div>
                    <table class="table table-sm mb-3">
                        <tbody>
                        <tr><td><?= esc(lang('SalaryAndCommission.person.salary')) ?></td><td class="text-end text-nowrap"><?= esc($money($person['salary'])) ?></td></tr>
                        <tr><td><?= esc(lang('SalaryAndCommission.person.commission')) ?></td><td class="text-end text-nowrap"><?= esc($money($person['commission'])) ?></td></tr>
                        <tr class="fw-semibold fs-5"><td><?= esc(lang('SalaryAndCommission.person.total')) ?></td><td class="text-end text-nowrap" data-payroll="total"><?= esc($money($person['total'])) ?></td></tr>
                        </tbody>
                    </table>
                    <h6 class="small text-uppercase text-muted"><?= esc(lang('SalaryAndCommission.person.employment')) ?></h6>
                    <dl class="row small mb-0">
                        <dt class="col-5"><?= esc(lang('SalaryAndCommission.person.started')) ?></dt>
                        <dd class="col-7"><?= esc(formatDate((string) $person['start_date'], $locale)) ?></dd>
                        <?php if (! empty($person['termination_date'])) : ?>
                            <dt class="col-5"><?= esc(lang('SalaryAndCommission.person.left')) ?></dt>
                            <dd class="col-7"><?= esc(formatDate((string) $person['termination_date'], $locale)) ?></dd>
                        <?php endif; ?>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0"><i class="bi bi-wallet2"></i> <?= esc(lang('SalaryAndCommission.person.salary')) ?></h5>
                    <span class="fw-semibold"><?= esc($money($person['salary'])) ?></span>
                </div>
                <div class="card-body">
                    <?php if ([] === $salaryLines) : ?>
                        <p class="text-muted mb-0"><?= esc(lang('SalaryAndCommission.person.salary-none')) ?></p>
                    <?php else : ?>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                <tr>
                                    <th><?= esc(lang('SalaryAndCommission.person.rate')) ?></th>
                                    <th><?= esc(lang('SalaryAndCommission.person.when')) ?></th>
                                    <th><?= esc(lang('SalaryAndCommission.person.days')) ?></th>
                                    <th class="text-end"><?= esc(lang('SalaryAndCommission.person.amount')) ?></th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($salaryLines as $line) : ?>
                                    <tr>
                                        <td class="text-nowrap"><?= esc($money($line['salary_amount'])) ?></td>
                                        <td class="text-nowrap"><?= esc(formatDate((string) $line['from'], $locale) . ' – ' . formatDate((string) $line['to'], $locale)) ?></td>
                                        <td class="text-nowrap">
                                            <?= esc(lang('SalaryAndCommission.person.days-of', [(int) $line['days'], (int) $line['period_days']])) ?>
                                            <?php if ((int) $line['days'] < (int) $line['period_days']) : ?>
                                                <div class="small text-muted"><?= esc(lang('SalaryAndCommission.person.salary-formula', [$money($line['salary_amount']), (int) $line['days'], (int) $line['period_days']])) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end text-nowrap fw-semibold"><?= esc($money($line['amount'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0"><i class="bi bi-percent"></i> <?= esc(lang('SalaryAndCommission.person.commission')) ?></h5>
                    <span class="fw-semibold"><?= esc($money($person['commission'])) ?></span>
                </div>
                <div class="card-body">
                    <?php if ('A' !== ($commission['status'] ?? '')) : ?>
                        <p class="text-muted mb-0"><?= esc(lang('SalaryAndCommission.person.commission-off')) ?></p>
                    <?php elseif ([] === $lines) : ?>
                        <p class="text-muted mb-0"><?= esc(lang('SalaryAndCommission.person.commission-none')) ?></p>
                    <?php else : ?>
                        <?php if (! empty($person['below_threshold'])) : ?>
                            <div class="alert alert-warning small"><?= esc(lang('SalaryAndCommission.person.below', [$money($person['threshold'])])) ?></div>
                        <?php endif; ?>
                        <p class="small text-muted"><?= esc(lang('SalaryAndCommission.person.explain')) ?></p>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                <tr>
                                    <th><?= esc(lang('SalaryAndCommission.person.when')) ?></th>
                                    <th><?= esc(lang('SalaryAndCommission.person.order')) ?></th>
                                    <th><?= esc(lang('SalaryAndCommission.person.service')) ?></th>
                                    <th><?= esc(lang('SalaryAndCommission.person.customer')) ?></th>
                                    <th class="text-end"><?= esc(lang('SalaryAndCommission.person.line')) ?></th>
                                    <th class="text-end"><?= esc(lang('SalaryAndCommission.person.share')) ?></th>
                                    <th class="text-end"><?= esc(lang('SalaryAndCommission.person.rate-col')) ?></th>
                                    <th class="text-end"><?= esc(lang('SalaryAndCommission.person.earned')) ?></th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($lines as $line) :
                                    $local = (string) $line['local_starts_at'];
                                    ?>
                                    <tr>
                                        <td class="text-nowrap"><?= esc(formatDate(substr($local, 0, 10), $locale) . ' ' . substr($local, 11, 5)) ?></td>
                                        <td class="text-nowrap">
                                            <a href="<?= esc(base_url('admin/sales/order/' . rawurlencode((string) $line['order_public_id'])), 'attr') ?>"><?= esc((string) ($line['order_number'] ?? $line['order_public_id'])) ?></a>
                                        </td>
                                        <td>
                                            <?= esc((string) $line['service_name']) ?>
                                            <?php if (! empty($line['variant_label'])) : ?><span class="small text-muted"><?= esc((string) $line['variant_label']) ?></span><?php endif; ?>
                                            <div><span class="badge bg-light text-dark border"><?= esc((string) ($kinds[$line['kind']] ?? $line['kind'])) ?></span></div>
                                        </td>
                                        <td><?= esc((string) $line['customer_name']) ?></td>
                                        <td class="text-end text-nowrap"><?= esc($money($line['line_amount'])) ?>
                                            <?php if ((int) $line['quantity'] > 1) : ?><div class="small text-muted">× <?= (int) $line['quantity'] ?></div><?php endif; ?>
                                        </td>
                                        <td class="text-end text-nowrap">
                                            <?= esc($money($line['share'])) ?>
                                            <?php if ((int) $line['slot_count'] > 1 || (int) $line['staff_count'] > 1) : ?>
                                                <div class="small text-muted"><?= esc(lang('SalaryAndCommission.person.split', [(int) $line['slot_number'], (int) $line['slot_count'], (int) $line['staff_count']])) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end text-nowrap">
                                            <?php if (null === $line['commission_value']) : ?>
                                                <span class="text-muted small"><?= esc(lang('SalaryAndCommission.person.no-rate')) ?></span>
                                            <?php elseif ('F' === $line['commission_unit']) : ?>
                                                <?= esc(lang('SalaryAndCommission.person.fixed', [$money($line['commission_value'])])) ?>
                                            <?php else : ?>
                                                <?= esc(rtrim(rtrim((string) $line['commission_value'], '0'), '.')) ?>%
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end text-nowrap fw-semibold<?= ! empty($person['below_threshold']) ? ' text-decoration-line-through text-muted' : '' ?>"><?= esc($money($line['commission'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                <tr class="small text-muted">
                                    <td colspan="5"><?= esc(lang('SalaryAndCommission.person.sales')) ?>
                                        <?php if ((float) $person['threshold'] > 0) : ?>· <?= esc(lang('SalaryAndCommission.person.threshold')) ?> <?= esc($money($person['threshold'])) ?><?php endif; ?>
                                    </td>
                                    <td class="text-end text-nowrap"><?= esc($money($person['sales'])) ?></td>
                                    <td></td>
                                    <td class="text-end text-nowrap fw-semibold text-body"><?= esc($money($person['commission'])) ?></td>
                                </tr>
                                </tfoot>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>
<?= $this->endSection() ?>
