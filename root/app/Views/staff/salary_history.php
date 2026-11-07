<?php
/**
 * The salary history of a person, the latest first, inside the change-salary form of their page (Staff::form())
 *
 * @var list<array> $salaries salary_amount, salary_period, effective_date, end_date
 * @var string      $currency
 * @var string      $locale
 */
?>
<div class="row mt-4">
    <div class="col-12">
        <h6 class="text-uppercase text-muted mb-2"><?= esc(lang('Staff.salary.history')) ?></h6>
        <?php if ([] === $salaries): ?>
            <p class="text-muted mb-0"><?= esc(lang('Staff.salary.none')) ?></p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                    <tr>
                        <th><?= esc(lang('Staff.salary.effective-date')) ?></th>
                        <th><?= esc(lang('Staff.salary.end-date')) ?></th>
                        <th class="text-end"><?= esc(lang('Staff.salary.amount', [$currency])) ?></th>
                        <th><?= esc(lang('Staff.salary.period')) ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($salaries as $salary): ?>
                        <tr>
                            <td class="text-nowrap"><?= esc((string) formatDate($salary['effective_date'], $locale)) ?></td>
                            <td class="text-nowrap">
                                <?php if (null === $salary['end_date']): ?>
                                    <span class="badge bg-success"><?= esc(lang('Staff.salary.current')) ?></span>
                                <?php else: ?>
                                    <?= esc((string) formatDate($salary['end_date'], $locale)) ?>
                                <?php endif ?>
                            </td>
                            <td class="text-end text-nowrap"><?= esc(formatMoney($salary['salary_amount'], $currency)) ?></td>
                            <td><?= esc(lang('Staff.period.' . $salary['salary_period'])) ?></td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        <?php endif ?>
    </div>
</div>
