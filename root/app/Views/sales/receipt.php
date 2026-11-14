<?php
/**
 * The receipt of a paid order, a page of its own made to be printed; see App\Controllers\Sales::receipt(). Its paper
 * is the store's receipt_format: a thermal roll 58mm or 80mm wide (the printer's driver sets the roll's length) or
 * an A4 sheet. Its words are in $language, which may not be the UI's; its times are the store's clock. It opens the
 * print dialog once loaded; the toolbar above it is not printed.
 * @var string     $language the receipt's language, one of listSupportedLanguages()
 * @var string     $publicId the order's
 * @var array|null $receipt  v1/client/order/receipt's data: {receipt, business, store, order}; null with $error
 * @var string|null $error   why it cannot be printed
 */
helper('list');
$t        = static fn (string $key, array $args = []): string => lang('Sales.receipt.' . $key, $args, $language);
$format   = (string) ($receipt['receipt']['format'] ?? '80mm');
$format   = in_array($format, ['58mm', '80mm', 'A4'], true) ? $format : '80mm';
$a4       = 'A4' === $format;
$business = (array) ($receipt['business'] ?? []);
$store    = (array) ($receipt['store'] ?? []);
$order    = (array) ($receipt['order'] ?? []);
$totals   = (array) ($order['totals'] ?? []);
$currency = (string) ($order['currency_code'] ?? '');
$timezone = (string) ($receipt['receipt']['timezone'] ?? 'UTC');
$money    = static fn ($amount): string => number_format((float) $amount, 2);
$nonZero  = static fn ($amount): bool => (int) round(((float) $amount) * 100) !== 0;
// a line's quantity times its price, before its discount and tax, as the subtotal adds them up
$gross    = static fn (array $line): string => number_format(round((int) $line['quantity'] * (float) $line['unit_price'], 2), 2);
$at       = static function (?string $utc, string $pattern = 'd/m/Y H:i') use ($timezone): string {
    if (null === $utc || '' === $utc) {
        return '';
    }
    try {
        return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone($timezone))->format($pattern);
    } catch (Throwable) {
        return $utc;
    }
};
$address = array_filter([
    (string) ($store['address_line_1'] ?? ''),
    (string) ($store['address_line_2'] ?? ''),
    trim(implode(' ', array_filter([
        (string) ($store['city'] ?? ''),
        (string) (listSupportedSubdivisions((string) ($business['country_code'] ?? ''))[(string) ($store['subdivision_code'] ?? '')] ?? ''),
        (string) ($store['postal_code'] ?? ''),
    ]))),
]);
$branch = (string) ($business['tax_branch_code'] ?? '');
?>
<!DOCTYPE html>
<html lang="<?= esc($language, 'attr') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($t('title') . (empty($order['order_number']) ? '' : ' ' . $order['order_number'])) ?></title>
    <style>
        /* the thermal printer's driver gives the roll's length; A4 is a sheet with a margin */
        @page { margin: <?= $a4 ? '15mm' : '0' ?>; <?= $a4 ? 'size: A4;' : '' ?> }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; background: #e9ecef; color: #000; }
        body { font-family: Tahoma, "Noto Sans Thai", "Leelawadee UI", "Thonburi", Arial, sans-serif; line-height: 1.35; }
        .toolbar { display: flex; gap: 8px; justify-content: center; padding: 12px; font-size: 14px; }
        .toolbar button { font: inherit; padding: 6px 16px; border: 1px solid #6c757d; border-radius: 4px; background: #fff; cursor: pointer; }
        .toolbar button.primary { background: #0d6efd; border-color: #0d6efd; color: #fff; }
        .paper { background: #fff; margin: 0 auto 24px; box-shadow: 0 1px 4px rgba(0, 0, 0, .2); }
        .fmt-58mm .paper { width: 58mm; padding: 3mm 5mm; font-size: 8.5pt; }
        .fmt-80mm .paper { width: 80mm; padding: 4mm 4mm; font-size: 9.5pt; }
        .fmt-A4 .paper { width: 210mm; max-width: 100%; min-height: 297mm; padding: 15mm; font-size: 10.5pt; }
        .center { text-align: center; }
        .right { text-align: right; }
        .muted { color: #333; }
        .bold { font-weight: bold; }
        .big { font-size: 1.25em; }
        .rule { border: 0; border-top: 1px dashed #000; margin: 2mm 0; }
        .fmt-A4 .rule { border-top-style: solid; border-color: #999; margin: 4mm 0; }
        .row { display: flex; justify-content: space-between; gap: 2mm; }
        .row > :last-child { text-align: right; white-space: nowrap; }
        .line { margin-bottom: 1.5mm; }
        .line .sub { padding-left: 3mm; }
        .footer { white-space: pre-line; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 1.5mm 2mm; vertical-align: top; }
        thead th { border-bottom: 1px solid #000; text-align: left; }
        tbody td { border-bottom: 1px solid #ddd; }
        .error { max-width: 420px; margin: 40px auto; padding: 20px; background: #fff; border-left: 4px solid #dc3545; font-size: 15px; }
        @media print {
            html, body { background: #fff; }
            .toolbar { display: none; }
            .paper { box-shadow: none; margin: 0; }
            .fmt-A4 .paper { width: auto; min-height: 0; padding: 0; }
        }
    </style>
</head>
<body class="fmt-<?= esc($format, 'attr') ?>">
<div class="toolbar">
    <?php if (null !== $receipt) : ?>
        <button type="button" class="primary" data-print><?= esc($t('print')) ?></button>
    <?php endif; ?>
    <button type="button" data-close><?= esc($t('close')) ?></button>
</div>

<?php if (null === $receipt) : ?>
    <div class="error" role="alert"><?= esc((string) $error) ?></div>
<?php else : ?>
<div class="paper">
    <!-- who it is from -->
    <div class="<?= $a4 ? '' : 'center' ?>">
        <div class="bold big"><?= esc((string) ($business['business_name'] ?? '')) ?></div>
        <?php if (! empty($business['legal_name']) && $business['legal_name'] !== ($business['business_name'] ?? '')) : ?>
            <div><?= esc((string) $business['legal_name']) ?></div>
        <?php endif; ?>
        <div><?= esc((string) ($store['store_name'] ?? '')) ?></div>
        <?php foreach ($address as $text) : ?><div class="muted"><?= esc($text) ?></div><?php endforeach; ?>
        <?php if (! empty($store['phone_number'])) : ?><div class="muted"><?= esc($t('phone', [(string) $store['phone_number']])) ?></div><?php endif; ?>
        <?php if (! empty($business['tax_id'])) : ?>
            <div><?= esc($t('tax-id', [(string) $business['tax_id']])) ?><?= '' === $branch ? '' : ' ' . esc($t(trim($branch, '0') === '' ? 'head-office' : 'branch', [$branch])) ?></div>
        <?php endif; ?>
    </div>
    <hr class="rule">
    <div class="center bold big"><?= esc($t('title')) ?></div>
    <hr class="rule">

    <!-- which order -->
    <div class="row"><span><?= esc($t('number')) ?></span><span class="bold"><?= esc((string) ($order['order_number'] ?? '')) ?></span></div>
    <div class="row"><span><?= esc($t('date')) ?></span><span><?= esc($at($order['paid_at'] ?? null)) ?></span></div>
    <?php if (! empty($receipt['receipt']['printed_by_name'])) : ?>
        <div class="row"><span><?= esc($t('cashier')) ?></span><span><?= esc((string) $receipt['receipt']['printed_by_name']) ?></span></div>
    <?php endif; ?>
    <?php if (! empty($order['customer_name'])) : ?>
        <div class="row"><span><?= esc($t('customer')) ?></span><span><?= esc((string) $order['customer_name']) ?></span></div>
    <?php endif; ?>
    <hr class="rule">

    <!-- what was bought -->
    <?php if ($a4) : ?>
        <table>
            <thead>
            <tr>
                <th><?= esc($t('item')) ?></th>
                <th class="right"><?= esc($t('quantity')) ?></th>
                <th class="right"><?= esc($t('unit-price')) ?></th>
                <th class="right"><?= esc($t('discount')) ?></th>
                <th class="right"><?= esc($t('amount', [$currency])) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ((array) ($order['lines'] ?? []) as $line) : ?>
                <tr>
                    <td>
                        <?= esc((string) $line['artifact_name']) ?><?= empty($line['variant_label']) ? '' : ' (' . esc((string) $line['variant_label']) . ')' ?>
                        <?php if (! empty($line['starts_at'])) : ?><div class="muted"><?= esc($at($line['starts_at']) . '–' . $at($line['ends_at'] ?? null, 'H:i')) ?></div><?php endif; ?>
                    </td>
                    <td class="right"><?= (int) $line['quantity'] ?></td>
                    <td class="right"><?= esc($money($line['unit_price'])) ?></td>
                    <td class="right"><?= $nonZero($line['discount_amount'] ?? 0) ? '-' . esc($money($line['discount_amount'])) : '' ?></td>
                    <td class="right"><?= esc($gross($line)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php else : ?>
        <?php foreach ((array) ($order['lines'] ?? []) as $line) : ?>
            <div class="line">
                <div><?= esc((string) $line['artifact_name']) ?><?= empty($line['variant_label']) ? '' : ' (' . esc((string) $line['variant_label']) . ')' ?></div>
                <?php if (! empty($line['starts_at'])) : ?><div class="sub muted"><?= esc($at($line['starts_at']) . '–' . $at($line['ends_at'] ?? null, 'H:i')) ?></div><?php endif; ?>
                <div class="row sub"><span><?= (int) $line['quantity'] ?> × <?= esc($money($line['unit_price'])) ?></span><span><?= esc($gross($line)) ?></span></div>
                <?php if ($nonZero($line['discount_amount'] ?? 0)) : ?>
                    <div class="row sub muted"><span><?= esc($t('discount')) ?></span><span>-<?= esc($money($line['discount_amount'])) ?></span></div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    <hr class="rule">

    <!-- what it came to -->
    <div style="<?= $a4 ? 'margin-left: auto; width: 45%;' : '' ?>">
        <div class="row"><span><?= esc($t('subtotal')) ?></span><span><?= esc($money($totals['subtotal'] ?? 0)) ?></span></div>
        <?php if ($nonZero($totals['discount'] ?? 0)) : ?>
            <div class="row"><span><?= esc(empty($order['discount_code']) ? $t('discount') : $t('discount-code', [(string) $order['discount_code']])) ?></span><span>-<?= esc($money($totals['discount'])) ?></span></div>
        <?php endif; ?>
        <?php if ($nonZero($totals['shipping'] ?? 0)) : ?>
            <div class="row"><span><?= esc((string) ($order['shipping_method_name'] ?? '') ?: $t('shipping')) ?></span><span><?= esc($money($totals['shipping'])) ?></span></div>
        <?php endif; ?>
        <?php if ($nonZero($totals['tax_added'] ?? 0)) : ?>
            <div class="row"><span><?= esc($t('tax-added')) ?></span><span><?= esc($money($totals['tax_added'])) ?></span></div>
        <?php endif; ?>
        <div class="row bold big"><span><?= esc($t('total', [$currency])) ?></span><span><?= esc($money($totals['total'] ?? 0)) ?></span></div>
        <?php if ($nonZero($totals['tax_included'] ?? 0)) : ?>
            <div class="row muted"><span><?= esc($t('tax-included')) ?></span><span><?= esc($money($totals['tax_included'])) ?></span></div>
        <?php endif; ?>
        <hr class="rule">
        <?php foreach ((array) ($order['payments'] ?? []) as $payment) : ?>
            <?php $refund = ($payment['transaction_type'] ?? '') === 'refund'; ?>
            <div class="row">
                <span><?= esc($refund ? $t('refund', [(string) $payment['payment_method_name']]) : (string) $payment['payment_method_name']) ?><?= empty($payment['reference']) ? '' : ' #' . esc((string) $payment['reference']) ?></span>
                <span><?= $refund ? '-' : '' ?><?= esc($money($payment['amount'])) ?></span>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if (! empty($receipt['receipt']['footer'])) : ?>
        <hr class="rule">
        <div class="footer center"><?= esc((string) $receipt['receipt']['footer']) ?></div>
    <?php endif; ?>
    <hr class="rule">
    <div class="center muted"><?= esc($t('printed-at', [$at(gmdate('Y-m-d H:i:s'))])) ?></div>
</div>
<?php endif; ?>

<script>
(function (autoPrint) {
  'use strict';
  var print = document.querySelector('[data-print]');
  if (print) { print.addEventListener('click', function () { window.print(); }); }
  document.querySelector('[data-close]').addEventListener('click', function () { window.close(); });
  // once the fonts are in, so the printed receipt has them
  if (autoPrint) {
    window.addEventListener('load', function () {
      var ready = document.fonts && document.fonts.ready ? document.fonts.ready : Promise.resolve();
      ready.then(function () { window.print(); });
    });
  }
})(<?= null === $receipt ? 'false' : 'true' ?>);
</script>
</body>
</html>
