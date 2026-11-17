<?php
/**
 * Dashboard widget: the orders that need something done (Dashboard::orders()), and the latest ones
 *
 * @var string|null $error
 * @var array       $tiles  key => ['count' => int|null, 'url' => string]; refund_due for owners and managers only
 * @var list<array> $orders v1/client/orders rows, newest first
 * @var int         $total  all the orders
 * @var string      $locale
 */
if (null !== $error) : ?>
    <div class="alert alert-warning mb-0"><?= esc($error) ?></div>
<?php return; endif;

$tones    = ['open' => 'text-primary', 'awaiting_payment' => 'text-warning', 'to_fulfil' => 'text-info', 'refund_due' => 'text-danger'];
$statuses = (array) lang('Sales.enum.financial_status');
$badges   = App\Controllers\Sales::BADGES['financial_status'];
?>
<div class="row g-2 mb-3">
    <?php foreach ($tiles as $key => $tile) : ?>
        <div class="col-6">
            <a href="<?= esc($tile['url'], 'attr') ?>" class="d-block border rounded p-2 text-decoration-none text-reset h-100" data-orders-tile="<?= esc($key, 'attr') ?>">
                <div class="fs-5 fw-semibold <?= $tones[$key] ?? '' ?>"><?= null === $tile['count'] ? '—' : (int) $tile['count'] ?></div>
                <div class="small text-muted"><?= esc(lang('Dashboard.orders.' . $key)) ?></div>
            </a>
        </div>
    <?php endforeach; ?>
</div>
<?php if ([] === $orders) : ?>
    <div class="text-center text-muted py-3"><?= esc(lang('Dashboard.orders.empty')) ?></div>
<?php else : ?>
    <div class="small text-muted mb-1"><?= esc(lang('Dashboard.orders.latest')) ?></div>
    <ul class="list-group list-group-flush">
        <?php foreach ($orders as $order) :
            $status = (string) ($order['financial_status'] ?? '');
            ?>
            <li class="list-group-item px-0 d-flex justify-content-between gap-2 align-items-center">
                <div class="text-truncate">
                    <a href="<?= esc(base_url('admin/sales/order/' . rawurlencode((string) ($order['public_id'] ?? ''))), 'attr') ?>" class="fw-semibold text-decoration-none">
                        <?= esc((string) ($order['order_number'] ?? '')) ?></a>
                    <span class="small text-muted"><?= esc((string) ($order['customer_name'] ?? '')) ?></span>
                    <?php if (! empty($order['cancelled_at'])) : ?>
                        <span class="badge bg-danger"><?= esc(lang('Sales.enum.order_status.cancelled')) ?></span>
                    <?php endif; ?>
                </div>
                <div class="text-nowrap">
                    <span class="badge <?= $badges[$status] ?? 'bg-secondary' ?>"><?= esc((string) ($statuses[$status] ?? $status)) ?></span>
                    <span class="fw-semibold ms-1"><?= esc(formatMoney($order['total_amount'] ?? null, (string) ($order['currency_code'] ?? ''))) ?></span>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
