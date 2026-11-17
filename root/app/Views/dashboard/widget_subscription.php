<?php
/**
 * Dashboard widget: the business's subscription and the invoice waiting for payment (Dashboard::subscription())
 *
 * @var string|null $error
 * @var array|null  $subscription v1/client/subscription/current's subscription; null on the free plan
 * @var string|null $planName
 * @var array|null  $pending      the invoice waiting for payment
 * @var string      $locale
 */
if (null !== $error) : ?>
    <div class="alert alert-warning mb-0"><?= esc($error) ?></div>
<?php return; endif;

if (null === $subscription) : ?>
    <p class="mb-2"><?= esc(lang('Dashboard.subscription.free')) ?></p>
    <a href="<?= base_url('admin/plans') ?>" class="btn btn-sm btn-primary"><?= esc(lang('Dashboard.subscription.upgrade')) ?></a>
<?php return; endif;

$status = (string) ($subscription['subscription_status'] ?? '');
$label  = ['A' => ['active', 'bg-success'], 'P' => ['pending', 'bg-warning text-dark'], 'S' => ['suspended', 'bg-danger']][$status] ?? null;
$end    = (string) ($subscription['current_period_end'] ?? '');
?>
<div class="d-flex align-items-center gap-2 mb-2">
    <span class="fs-5 fw-semibold"><?= esc((string) $planName) ?></span>
    <?php if (null !== $label) : ?>
        <span class="badge <?= $label[1] ?>" data-subscription-status><?= esc(lang('Dashboard.subscription.' . $label[0])) ?></span>
    <?php endif; ?>
</div>
<?php if ('' !== $end) : ?>
    <div class="small text-muted mb-2">
        <?= esc(lang('Y' === ($subscription['cancel_at_period_end'] ?? 'N') ? 'Dashboard.subscription.ends' : 'Dashboard.subscription.renews', [formatDate(substr($end, 0, 10), $locale)])) ?>
    </div>
<?php endif; ?>
<?php if (null !== $pending) : ?>
    <div class="alert alert-warning py-2 mb-0 d-flex align-items-center gap-2" data-subscription-pending>
        <i class="bi bi-exclamation-triangle"></i>
        <span class="me-auto"><?= esc(lang('Dashboard.subscription.invoice-due', [
            (string) ($pending['invoice_no'] ?? ''),
            formatMoney($pending['amount_payable'] ?? $pending['amount_due'] ?? null, (string) ($pending['currency_code'] ?? '')),
        ])) ?></span>
        <a href="<?= base_url('admin/plans') ?>" class="btn btn-sm btn-warning"><?= esc(lang('Dashboard.subscription.pay')) ?></a>
    </div>
<?php endif; ?>
