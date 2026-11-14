<?php
/**
 * One order of the session business; see App\Controllers\Sales::order(). The status form and the payment buttons are
 * run by scripts/sales_order, which posts to admin/sales/order/* and reloads the page.
 * @var array  $order          v1/client/order's order
 * @var array  $paymentMethods [{public_id, method_name}] of the business, active
 * @var string $timezone       the business's clock
 * @var bool   $canRefund
 * @var array  $badges         Sales::BADGES
 */
$this->extend('_layout_admin');

$currency = (string) ($order['currency_code'] ?? '');
$money    = static fn ($amount): string => esc(formatMoney($amount, $currency));
$enum     = static fn (string $field, ?string $value): string => (string) (lang('Sales.enum.' . $field)[$value ?? ''] ?? $value ?? '');
$badge    = static fn (string $field, ?string $value): string => '<span class="badge ' . esc($badges[$field][$value ?? 'none'] ?? 'bg-secondary', 'attr') . '">'
    . esc($enum($field, $value ?? 'none')) . '</span>';
$totals   = (array) ($order['totals'] ?? []);
$sums     = (array) ($order['money'] ?? []);
$customer = (array) ($order['customer'] ?? []);
$shipping = $order['shipping'] ?? null;
$hasProducts = (bool) ($order['has_products'] ?? false);
$publicId = (string) $order['public_id'];
?>
<?= $this->section('content') ?>
<section class="section" id="sales-order" data-public-id="<?= esc($publicId, 'attr') ?>">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <a href="<?= base_url('admin/sales') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> <?= esc(lang('Sales.order.back')) ?></a>
        <span class="text-muted small ms-1"><?= esc(lang('Sales.order.placed')) ?> <?= localTimeTag($order['created_at'] ?? null) ?></span>
        <?php if (! empty($order['paid_at'])) : ?>
            <span class="text-muted small">· <?= esc(lang('Sales.order.paid-at')) ?> <?= localTimeTag($order['paid_at']) ?></span>
        <?php endif; ?>
        <?php if (! empty($order['cancelled_at'])) : ?>
            <span class="badge bg-danger"><?= esc(lang('Sales.order.cancelled')) ?></span>
        <?php endif; ?>
        <span class="ms-auto d-flex flex-wrap gap-1">
            <?= $badge('order_status', $order['order_status'] ?? null) ?>
            <?= $badge('financial_status', $order['financial_status'] ?? null) ?>
            <?= $badge('fulfillment_status', $order['fulfillment_status'] ?? null) ?>
        </span>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <!-- items -->
            <div class="card mb-4">
                <div class="card-header"><h5 class="card-title mb-0"><?= esc(lang('Sales.order.items')) ?></h5></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                            <tr>
                                <th><?= esc(lang('Sales.order.item')) ?></th>
                                <th class="text-end"><?= esc(lang('Sales.order.quantity')) ?></th>
                                <th class="text-end"><?= esc(lang('Sales.order.unit-price')) ?></th>
                                <th class="text-end"><?= esc(lang('Sales.order.discount')) ?></th>
                                <th class="text-end"><?= esc(lang('Sales.order.tax')) ?></th>
                                <th class="text-end"><?= esc(lang('Sales.order.line-total')) ?></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ((array) ($order['lines'] ?? []) as $line) : ?>
                                <?php $tax = (array) ($line['tax'] ?? []); ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= esc((string) $line['artifact_name']) ?></div>
                                        <div class="small text-muted">
                                            <span class="badge bg-light text-dark border"><?= esc($enum('line_kind', $line['kind'] ?? null)) ?></span>
                                            <?php if (! empty($line['variant_label'])) : ?><?= esc((string) $line['variant_label']) ?><?php endif; ?>
                                            <?php if (! empty($line['sku'])) : ?> · <?= esc(lang('Sales.order.sku')) ?> <?= esc((string) $line['sku']) ?><?php endif; ?>
                                        </div>
                                        <?php if (! empty($line['store'])) : ?>
                                            <div class="small text-muted"><i class="bi bi-shop"></i> <?= esc((string) $line['store']['store_name']) ?></div>
                                        <?php endif; ?>
                                        <?php if (! empty($line['starts_at'])) : ?>
                                            <div class="small"><i class="bi bi-calendar-event"></i> <?= localTimeTag($line['starts_at']) ?> – <?= localTimeTag($line['ends_at'] ?? null) ?></div>
                                        <?php endif; ?>
                                        <?php if (! empty($line['participants'])) : ?>
                                            <div class="small text-muted"><?= esc(lang('Sales.order.participants')) ?>: <?= esc(implode(', ', (array) $line['participants'])) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end"><?= (int) $line['quantity'] ?></td>
                                    <td class="text-end text-nowrap">
                                        <?= $money($line['unit_price']) ?>
                                        <?php if (! empty($line['compare_price'])) : ?><div class="small text-muted text-decoration-line-through"><?= $money($line['compare_price']) ?></div><?php endif; ?>
                                    </td>
                                    <td class="text-end text-nowrap"><?= '0.00' === ($line['discount_amount'] ?? '0.00') ? '–' : '-' . $money($line['discount_amount']) ?></td>
                                    <td class="text-end text-nowrap">
                                        <?php if (($tax['calculation'] ?? 'X') === 'X') : ?>–<?php else : ?>
                                            <?= $money($tax['amount'] ?? '0') ?>
                                            <div class="small text-muted"><?= esc((string) ($tax['percentage'] ?? '')) ?>%<?= ($tax['calculation'] ?? '') === 'I' ? ' ' . esc(lang('Sales.order.tax-included')) : '' ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end text-nowrap fw-semibold"><?= $money($line['line_total']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer">
                    <dl class="row mb-0 small justify-content-end text-end">
                        <dt class="col-8 col-md-9 fw-normal"><?= esc(lang('Sales.order.subtotal')) ?></dt>
                        <dd class="col-4 col-md-3 mb-1"><?= $money($totals['subtotal'] ?? '0') ?></dd>
                        <?php if (($totals['discount'] ?? '0.00') !== '0.00') : ?>
                            <dt class="col-8 col-md-9 fw-normal"><?= esc(lang('Sales.order.discount')) ?><?= ! empty($order['discount_code']) ? ' (' . esc((string) $order['discount_code']) . ')' : '' ?></dt>
                            <dd class="col-4 col-md-3 mb-1">-<?= $money($totals['discount']) ?></dd>
                        <?php endif; ?>
                        <?php if (null !== $shipping) : ?>
                            <dt class="col-8 col-md-9 fw-normal"><?= esc(lang('Sales.order.shipping')) ?></dt>
                            <dd class="col-4 col-md-3 mb-1"><?= $money($totals['shipping'] ?? '0') ?></dd>
                        <?php endif; ?>
                        <?php if (($totals['tax_added'] ?? '0.00') !== '0.00') : ?>
                            <dt class="col-8 col-md-9 fw-normal"><?= esc(lang('Sales.order.tax-added')) ?></dt>
                            <dd class="col-4 col-md-3 mb-1"><?= $money($totals['tax_added']) ?></dd>
                        <?php endif; ?>
                        <dt class="col-8 col-md-9 fs-6"><?= esc(lang('Sales.order.total')) ?></dt>
                        <dd class="col-4 col-md-3 mb-1 fs-6 fw-bold"><?= $money($totals['total'] ?? '0') ?></dd>
                        <?php if (($totals['tax_included'] ?? '0.00') !== '0.00') : ?>
                            <dt class="col-8 col-md-9 fw-normal text-muted"><?= esc(lang('Sales.order.tax-in-prices')) ?></dt>
                            <dd class="col-4 col-md-3 mb-0 text-muted"><?= $money($totals['tax_included']) ?></dd>
                        <?php endif; ?>
                    </dl>
                </div>
            </div>

            <!-- payments and refunds -->
            <div class="card">
                <div class="card-header d-flex flex-wrap align-items-center gap-2">
                    <h5 class="card-title mb-0 me-auto"><?= esc(lang('Sales.order.payments')) ?></h5>
                    <button type="button" class="btn btn-sm btn-primary" data-payment-form="payment"<?= (int) round(((float) ($sums['balance_due'] ?? 0)) * 100) <= 0 ? ' disabled' : '' ?>>
                        <i class="bi bi-plus-circle"></i> <?= esc(lang('Sales.order.record-payment')) ?>
                    </button>
                    <?php if ($canRefund) : ?>
                        <button type="button" class="btn btn-sm btn-outline-danger" data-payment-form="refund"<?= (int) round(((float) ($sums['refundable'] ?? 0)) * 100) <= 0 ? ' disabled' : '' ?>>
                            <i class="bi bi-arrow-counterclockwise"></i> <?= esc(lang('Sales.order.record-refund')) ?>
                        </button>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <div class="row text-center g-2 mb-3">
                        <?php foreach (['paid' => 'text-success', 'refunded' => 'text-danger', 'balance_due' => ''] as $key => $class) : ?>
                            <div class="col-4">
                                <div class="border rounded py-2">
                                    <div class="small text-muted"><?= esc(lang('Sales.order.' . str_replace('_', '-', $key))) ?></div>
                                    <div class="fw-semibold <?= $class ?>"><?= $money($sums[$key] ?? '0') ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (empty($order['payments'])) : ?>
                        <p class="text-muted mb-0"><?= esc(lang('Sales.order.no-payments')) ?></p>
                    <?php else : ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                <tr>
                                    <th><?= esc(lang('Sales.order.date')) ?></th>
                                    <th><?= esc(lang('Sales.order.type')) ?></th>
                                    <th><?= esc(lang('Sales.order.method')) ?></th>
                                    <th class="text-end"><?= esc(lang('Sales.order.amount')) ?></th>
                                    <th><?= esc(lang('Sales.order.status')) ?></th>
                                    <th><?= esc(lang('Sales.order.details')) ?></th>
                                    <th></th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ((array) $order['payments'] as $payment) : ?>
                                    <?php $refund = ($payment['transaction_type'] ?? '') === 'refund'; ?>
                                    <tr>
                                        <td class="text-nowrap small"><?= localTimeTag($payment['processed_at'] ?? $payment['created_at'] ?? null) ?></td>
                                        <td><?= esc($enum('transaction_type', $payment['transaction_type'] ?? null)) ?></td>
                                        <td><?= esc((string) ($payment['payment_method_name'] ?? '')) ?></td>
                                        <td class="text-end text-nowrap<?= $refund ? ' text-danger' : '' ?>"><?= $refund ? '-' : '' ?><?= $money($payment['amount']) ?></td>
                                        <td><?= $badge('transaction_status', $payment['transaction_status'] ?? null) ?></td>
                                        <td class="small">
                                            <?php if (! empty($payment['reference'])) : ?><div><i class="bi bi-hash"></i><?= esc((string) $payment['reference']) ?></div><?php endif; ?>
                                            <?php if (! empty($payment['failure_reason'])) : ?><div class="text-danger"><?= esc((string) $payment['failure_reason']) ?></div><?php endif; ?>
                                            <?php if (! empty($payment['note'])) : ?><div class="text-muted" style="white-space: pre-line;"><?= esc((string) $payment['note']) ?></div><?php endif; ?>
                                            <?php if (! empty($payment['recorded_by_name'])) : ?><div class="text-muted"><?= esc(lang('Sales.order.recorded-by', [(string) $payment['recorded_by_name']])) ?></div><?php endif; ?>
                                        </td>
                                        <td class="text-end text-nowrap">
                                            <?php if (($payment['transaction_status'] ?? '') === 'pending' && (! $refund || $canRefund)) : ?>
                                                <?php foreach (['succeeded' => ['btn-outline-success', 'bi-check-lg'], 'failed' => ['btn-outline-danger', 'bi-x-lg'], 'voided' => ['btn-outline-secondary', 'bi-slash-circle']] as $status => [$class, $icon]) : ?>
                                                    <button type="button" class="btn btn-sm <?= $class ?> ms-1" data-settle="<?= $status ?>" data-payment-id="<?= esc((string) $payment['public_id'], 'attr') ?>">
                                                        <i class="bi <?= $icon ?>"></i> <?= esc(lang('Sales.order.mark-' . $status)) ?>
                                                    </button>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- statuses -->
            <div class="card mb-4">
                <div class="card-header"><h5 class="card-title mb-0"><?= esc(lang('Sales.order.statuses')) ?></h5></div>
                <div class="card-body">
                    <form id="sales-status-form" novalidate>
                        <div class="mb-3">
                            <label class="form-label" for="order_status"><?= esc(lang('Sales.order.order-status')) ?></label>
                            <select class="form-select" id="order_status" name="order_status">
                                <?php foreach (\App\Controllers\Sales::ORDER_STATUSES as $status) : ?>
                                    <option value="<?= $status ?>"<?= $status === ($order['order_status'] ?? '') ? ' selected' : '' ?>><?= esc($enum('order_status', $status)) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback" data-error-for="order_status"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="fulfillment_status"><?= esc(lang('Sales.order.fulfillment-status')) ?></label>
                            <?php if ($hasProducts && null !== ($order['fulfillment_status'] ?? null)) : ?>
                                <select class="form-select" id="fulfillment_status" name="fulfillment_status">
                                    <?php foreach (\App\Controllers\Sales::FULFILLMENT_STATUSES as $status) : ?>
                                        <option value="<?= $status ?>"<?= $status === $order['fulfillment_status'] ? ' selected' : '' ?>><?= esc($enum('fulfillment_status', $status)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback" data-error-for="fulfillment_status"></div>
                            <?php else : ?>
                                <div class="form-text mt-0"><?= esc(lang('Sales.order.no-products')) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="mb-3">
                            <span class="form-label d-block"><?= esc(lang('Sales.order.financial-status')) ?></span>
                            <?= $badge('financial_status', $order['financial_status'] ?? null) ?>
                            <div class="form-text"><?= esc(lang('Sales.order.financial-help')) ?></div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-check2"></i> <?= esc(lang('Sales.order.save-status')) ?></button>
                    </form>
                </div>
            </div>

            <!-- customer -->
            <div class="card mb-4">
                <div class="card-header"><h5 class="card-title mb-0"><?= esc(lang('Sales.order.customer')) ?></h5></div>
                <div class="card-body">
                    <div class="fw-semibold"><?= esc(trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''))) ?></div>
                    <?php if (! empty($customer['email_address'])) : ?>
                        <div><i class="bi bi-envelope"></i> <a href="mailto:<?= esc((string) $customer['email_address'], 'attr') ?>"><?= esc((string) $customer['email_address']) ?></a></div>
                    <?php endif; ?>
                    <?php if (! empty($customer['telephone_number'])) : ?>
                        <div><i class="bi bi-telephone"></i> <a href="tel:<?= esc(preg_replace('/[^0-9+]/', '', (string) $customer['telephone_number']), 'attr') ?>"><?= esc((string) $customer['telephone_number']) ?></a></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- shipping -->
            <div class="card mb-4">
                <div class="card-header"><h5 class="card-title mb-0"><?= esc(lang('Sales.order.delivery')) ?></h5></div>
                <div class="card-body">
                    <?php if (null === $shipping) : ?>
                        <p class="text-muted mb-0"><?= esc(lang('Sales.order.no-shipping')) ?></p>
                    <?php else : ?>
                        <div class="fw-semibold"><?= esc((string) ($shipping['name'] ?? '')) ?></div>
                        <div class="small text-muted mb-2"><?= esc($enum('shipping_type', $shipping['type'] ?? null)) ?></div>
                        <?php if (! empty($shipping['address'])) : ?>
                            <?php $address = (array) $shipping['address']; ?>
                            <address class="mb-0">
                                <?= esc((string) ($address['line_1'] ?? '')) ?><br>
                                <?php if (! empty($address['line_2'])) : ?><?= esc((string) $address['line_2']) ?><br><?php endif; ?>
                                <?= esc(trim(($address['city'] ?? '') . ' ' . ($address['subdivision_code'] ?? '') . ' ' . ($address['postal_code'] ?? ''))) ?><br>
                                <?= esc((string) ($address['country_code'] ?? '')) ?>
                            </address>
                        <?php elseif (! empty($shipping['pickup_point'])) : ?>
                            <div class="small text-muted"><?= esc(lang('Sales.order.pickup-point')) ?></div>
                            <div style="white-space: pre-line;"><?= esc((string) $shipping['pickup_point']) ?></div>
                        <?php elseif (! empty($shipping['pickup_store'])) : ?>
                            <div class="small text-muted"><?= esc(lang('Sales.order.pickup-store')) ?></div>
                            <div><?= esc((string) $shipping['pickup_store']['store_name']) ?></div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- payment method -->
            <div class="card">
                <div class="card-header"><h5 class="card-title mb-0"><?= esc(lang('Sales.order.payment-method')) ?></h5></div>
                <div class="card-body">
                    <div><?= esc((string) ($order['payment_method']['name'] ?? '')) ?></div>
                    <?php if (! empty($order['discount_code'])) : ?>
                        <div class="small text-muted"><i class="bi bi-tag"></i> <?= esc(lang('Sales.order.discount-code', [(string) $order['discount_code']])) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- the form of a payment or a refund -->
<div class="modal fade" id="sales-payment-modal" tabindex="-1" aria-labelledby="sales-payment-title" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" id="sales-payment-form" novalidate>
            <div class="modal-header">
                <h5 class="modal-title" id="sales-payment-title"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= esc(lang('Sales.order.form.cancel'), 'attr') ?>"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="transaction_type" value="payment">
                <div class="mb-3">
                    <label class="form-label" for="payment-amount"><?= esc(lang('Sales.order.form.amount', [$currency])) ?></label>
                    <input type="number" class="form-control" id="payment-amount" name="amount" min="0.01" step="0.01" inputmode="decimal" required>
                    <div class="invalid-feedback" data-error-for="amount"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="payment-method"><?= esc(lang('Sales.order.form.method')) ?></label>
                    <select class="form-select" id="payment-method" name="payment_method_public_id">
                        <option value=""><?= esc(lang('Sales.order.form.order-method', [(string) ($order['payment_method']['name'] ?? '')])) ?></option>
                        <?php foreach ($paymentMethods as $method) : ?>
                            <option value="<?= esc((string) $method['public_id'], 'attr') ?>"><?= esc((string) $method['method_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback" data-error-for="payment_method_public_id"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="payment-status"><?= esc(lang('Sales.order.form.status')) ?></label>
                    <select class="form-select" id="payment-status" name="transaction_status">
                        <?php foreach (['succeeded', 'pending', 'failed'] as $status) : ?>
                            <option value="<?= $status ?>"><?= esc($enum('transaction_status', $status)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback" data-error-for="transaction_status"></div>
                </div>
                <div class="mb-3 d-none" data-failure-only>
                    <label class="form-label" for="payment-failure"><?= esc(lang('Sales.order.form.failure_reason')) ?></label>
                    <input type="text" class="form-control" id="payment-failure" name="failure_reason" maxlength="255">
                    <div class="invalid-feedback" data-error-for="failure_reason"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="payment-reference"><?= esc(lang('Sales.order.form.reference')) ?></label>
                    <input type="text" class="form-control" id="payment-reference" name="reference" maxlength="100">
                    <div class="form-text"><?= esc(lang('Sales.order.form.reference-help')) ?></div>
                    <div class="invalid-feedback" data-error-for="reference"></div>
                </div>
                <div class="mb-0">
                    <label class="form-label" for="payment-note"><?= esc(lang('Sales.order.form.note')) ?></label>
                    <textarea class="form-control" id="payment-note" name="note" rows="2" maxlength="1000"></textarea>
                    <div class="invalid-feedback" data-error-for="note"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= esc(lang('Sales.order.form.cancel')) ?></button>
                <button type="submit" class="btn btn-primary"><?= esc(lang('Sales.order.form.save')) ?></button>
            </div>
        </form>
    </div>
</div>

<script>
<?= view('scripts/sales_order', [
    'publicId' => $publicId,
    'amounts'  => ['payment' => (string) ($sums['balance_due'] ?? ''), 'refund' => (string) ($sums['refundable'] ?? '')],
]) ?>
</script>
<?= $this->endSection() ?>
