<?php
/**
 * What the people of the session business earn in a pay period, to print on A4 (SalaryAndCommission::export()): one
 * row per person with their salary, services performed, commission and total, then the totals. Black and white; see
 * _layout_print.
 *
 * @var string      $cycle        M, S or W
 * @var string      $from         'Y-m-d', the period's first day
 * @var string      $to           'Y-m-d', its last day
 * @var string      $currency     the business's currency
 * @var array       $commission   {status, period, threshold}
 * @var list<array> $people       v1/client/payroll's people
 * @var string|null $error        why the period could not be loaded
 * @var string      $businessName the session business
 * @var string      $back         the list on screen
 * @var string      $locale
 */
$this->extend('_layout_print');

$t      = static fn (string $key, array $args = []): string => lang('SalaryAndCommission.' . $key, $args);
$amount = static fn ($value): string => number_format((float) $value, 2);
$roles  = (array) lang('SalaryAndCommission.role');
$sum    = static fn (string $key): float => array_sum(array_map(static fn (array $p): float => (float) $p[$key], $people));
$date   = static fn (?string $day): string => null === $day || '' === $day ? '–' : (string) formatDate($day, $locale);
$unit   = '' === $currency ? '' : ' (' . $currency . ')';

$this->setData([
    'title'    => $t('export.title'),
    'subtitle' => formatDate($from, $locale) . ' – ' . formatDate($to, $locale) . ' · ' . lang('SalaryAndCommission.cycle.' . $cycle),
]);
?>
<?= $this->section('content') ?>
<?php if ([] !== $commission) : ?>
    <p class="small">
        <?php if ('A' === ($commission['status'] ?? '')) : ?>
            <?= esc((float) $commission['threshold'] > 0
                ? $t('policy.active', [$t('policy.period.' . $commission['period']), formatMoney($commission['threshold'], $currency)])
                : $t('policy.no-threshold', [$t('policy.period.' . $commission['period'])])) ?>
        <?php else : ?>
            <?= esc($t('policy.inactive')) ?>
        <?php endif; ?>
    </p>
<?php endif; ?>

<?php if (null !== $error) : ?>
    <div class="error" role="alert"><?= esc($error) ?></div>
<?php elseif ([] === $people) : ?>
    <div class="empty"><?= esc($t('empty')) ?></div>
<?php else : ?>
    <table id="payroll-export">
        <thead>
        <tr>
            <th class="num"><?= esc($t('export.no')) ?></th>
            <th><?= esc($t('column.person')) ?></th>
            <th><?= esc($t('export.role')) ?></th>
            <th><?= esc($t('export.started')) ?></th>
            <th><?= esc($t('export.left')) ?></th>
            <th class="num"><?= esc($t('column.salary') . $unit) ?></th>
            <th class="num"><?= esc($t('column.sales') . $unit) ?></th>
            <th class="num"><?= esc($t('column.commission') . $unit) ?></th>
            <th class="num"><?= esc($t('column.total') . $unit) ?></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($people as $index => $person) :
            $full = trim((string) ($person['full_name'] ?? ''));
            ?>
            <tr>
                <td class="num"><?= $index + 1 ?></td>
                <td>
                    <?= esc((string) $person['name']) ?>
                    <?php if ('' !== $full && $full !== (string) $person['name']) : ?><div class="small"><?= esc($full) ?></div><?php endif; ?>
                </td>
                <td><?= esc((string) ($roles[$person['client_role']] ?? '')) ?></td>
                <td class="nowrap"><?= esc($date($person['start_date'] ?? null)) ?></td>
                <td class="nowrap"><?= esc($date($person['termination_date'] ?? null)) ?></td>
                <td class="num"><?= esc($amount($person['salary'])) ?><?= ! empty($person['prorated']) ? ' *' : '' ?></td>
                <td class="num"><?= esc($amount($person['sales'])) ?></td>
                <td class="num"><?= esc($amount($person['commission'])) ?><?= ! empty($person['below_threshold']) ? ' †' : '' ?></td>
                <td class="num"><strong><?= esc($amount($person['total'])) ?></strong></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
        <tr>
            <td colspan="5"><?= esc($t('column.total-row')) ?> (<?= count($people) ?>)</td>
            <td class="num"><?= esc($amount($sum('salary'))) ?></td>
            <td class="num"><?= esc($amount($sum('sales'))) ?></td>
            <td class="num"><?= esc($amount($sum('commission'))) ?></td>
            <td class="num"><?= esc($amount($sum('total'))) ?></td>
        </tr>
        </tfoot>
    </table>
    <p class="small"><?= esc($t('export.legend')) ?></p>
    <p class="small"><?= esc($t('note')) ?></p>
<?php endif; ?>
<?= $this->endSection() ?>
