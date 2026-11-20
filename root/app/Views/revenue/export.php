<?php
/**
 * The revenue of the session business in a period, to print on A4 (RevenueReport::export()): every order placed in
 * it, oldest first, one row each, then each currency's totals. Black and white; see _layout_print.
 *
 * @var string      $period       day, week, month or custom
 * @var string      $from         'Y-m-d', the first day
 * @var string      $to           'Y-m-d', the last day
 * @var list<array> $currencies   v1/client/revenue's currencies
 * @var list<array> $orders       v1/client/revenue's orders, oldest first
 * @var int         $orderCount   all the orders of the period
 * @var string|null $error        why the period could not be loaded
 * @var string      $businessName the session business
 * @var string      $back         the report on screen
 * @var string      $locale
 */
$this->extend('_layout_print');

$t        = static fn (string $key, array $args = []): string => lang('RevenueReport.' . $key, $args);
$statuses = (array) lang('Sales.enum.financial_status');
$amount   = static fn ($value): string => number_format((float) $value, 2);
// one currency: its code is said once, in the headings; more than one: each row says its own
$codes    = array_values(array_unique(array_map(static fn (array $c): string => (string) $c['currency_code'], $currencies)));
$single   = count($codes) <= 1;
$code     = $codes[0] ?? '';

$this->setData([
    'title'    => $t('export.title'),
    'subtitle' => $from === $to ? (string) formatDate($from, $locale) : formatDate($from, $locale) . ' – ' . formatDate($to, $locale),
]);
?>
<?= $this->section('content') ?>
<?php if (null !== $error) : ?>
    <div class="error" role="alert"><?= esc($error) ?></div>
<?php else : ?>
    <h2><?= esc($t('export.orders')) ?> (<?= $orderCount ?>)</h2>
    <?php if ($orderCount > count($orders)) : ?>
        <p class="small"><?= esc($t('export.shown', [count($orders), $orderCount])) ?></p>
    <?php endif; ?>
    <?php if ([] === $orders) : ?>
        <div class="empty"><?= esc($t('empty')) ?></div>
    <?php else : ?>
        <table id="revenue-export">
            <thead>
            <tr>
                <th class="num"><?= esc($t('export.no')) ?></th>
                <th><?= esc($t('orders.number')) ?></th>
                <th><?= esc($t('orders.placed')) ?></th>
                <th><?= esc($t('orders.customer')) ?></th>
                <th><?= esc($t('orders.method')) ?></th>
                <th><?= esc($t('orders.status')) ?></th>
                <?php if (! $single) : ?><th><?= esc($t('export.currency')) ?></th><?php endif; ?>
                <th class="num"><?= esc($t('orders.total')) ?><?= $single && '' !== $code ? ' (' . esc($code) . ')' : '' ?></th>
                <th class="num"><?= esc($t('orders.paid')) ?></th>
                <th class="num"><?= esc($t('orders.balance')) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($orders as $index => $order) :
                $cancelled = ! empty($order['cancelled']);
                $local     = (string) $order['local_created_at'];
                ?>
                <tr>
                    <td class="num"><?= $index + 1 ?></td>
                    <td class="nowrap"><?= esc((string) ($order['order_number'] ?? $order['public_id'])) ?><?= 'counter' === ($order['order_source'] ?? '') ? ' · ' . esc($t('orders.counter')) : '' ?></td>
                    <td class="nowrap"><?= esc(formatDate(substr($local, 0, 10), $locale) . ' ' . substr($local, 11, 5)) ?></td>
                    <td><?= esc((string) $order['customer_name']) ?></td>
                    <td><?= esc((string) $order['payment_method_name']) ?></td>
                    <td><?= esc((string) ($statuses[$order['financial_status']] ?? $order['financial_status'])) ?><?= $cancelled ? ' (C)' : '' ?></td>
                    <?php if (! $single) : ?><td><?= esc((string) $order['currency_code']) ?></td><?php endif; ?>
                    <td class="num<?= $cancelled ? ' struck' : '' ?>"><?= esc($amount($order['total_amount'])) ?></td>
                    <td class="num"><?= esc($amount($order['net_paid'])) ?></td>
                    <td class="num"><?= (float) $order['balance_due'] > 0 ? esc($amount($order['balance_due'])) : '–' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <?php if ([] !== $currencies) : ?>
        <h2><?= esc($t('export.summary')) ?></h2>
        <table id="revenue-export-summary">
            <thead>
            <tr>
                <th><?= esc($t('export.currency')) ?></th>
                <th class="num"><?= esc($t('export.count')) ?></th>
                <th class="num"><?= esc($t('summary.subtotal')) ?></th>
                <th class="num"><?= esc($t('summary.discount')) ?></th>
                <th class="num"><?= esc($t('summary.shipping')) ?></th>
                <th class="num"><?= esc($t('tile.sales')) ?></th>
                <th class="num"><?= esc($t('tile.collected')) ?></th>
                <th class="num"><?= esc($t('tile.outstanding')) ?></th>
                <th class="num"><?= esc($t('export.cancelled')) ?></th>
                <th class="num"><?= esc($t('export.refunded')) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($currencies as $sum) : ?>
                <tr>
                    <td><?= esc((string) $sum['currency_code']) ?></td>
                    <td class="num"><?= (int) $sum['orders'] ?></td>
                    <td class="num"><?= esc($amount($sum['subtotal'])) ?></td>
                    <td class="num"><?= (float) $sum['discount'] > 0 ? '−' . esc($amount($sum['discount'])) : esc($amount(0)) ?></td>
                    <td class="num"><?= esc($amount($sum['shipping'])) ?></td>
                    <td class="num"><strong><?= esc($amount($sum['sales'])) ?></strong></td>
                    <td class="num"><?= esc($amount($sum['collected'])) ?></td>
                    <td class="num"><?= esc($amount($sum['outstanding'])) ?></td>
                    <td class="num"><?= (int) $sum['cancelled_orders'] ?></td>
                    <td class="num"><?= esc($amount($sum['refunded'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p class="small"><?= esc($t('export.note')) ?></p>
    <?php endif; ?>
<?php endif; ?>
<?= $this->endSection() ?>
