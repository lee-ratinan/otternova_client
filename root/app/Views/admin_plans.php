<?php
/**
 * The session business's plan and subscription (Plans::index()).
 *
 * @var string     $businessName
 * @var string     $planCode     the plan the business is on now (the free one unless its subscription is active)
 * @var array|null $subscription Plans::subscriptionView(), null on the free plan
 * @var array      $plans        Plans::planView() rows, cheapest first
 * @var array|null $pending      Plans::invoiceView() of the invoice waiting for payment
 * @var array      $methods      the ways to pay it: method_code, currency_code, payee
 * @var array|null $history      Plans::invoiceView() rows, newest first; null when they could not be loaded
 * @var array      $urls
 */
$this->extend('_layout_admin');

$cycles       = lang('BusinessPlans.cycle');
$statuses     = lang('BusinessPlans.status');
$statusClass  = ['P' => 'bg-warning text-dark', 'A' => 'bg-success', 'S' => 'bg-danger', 'C' => 'bg-secondary', 'X' => 'bg-secondary'];
$invoiceState = lang('BusinessPlans.invoice-status');
$invoiceClass = ['P' => 'bg-warning text-dark', 'D' => 'bg-success', 'V' => 'bg-secondary'];
$methodName   = static function (?string $code): string {
    $key  = 'BusinessPlans.methods.' . $code;
    $name = lang($key);
    return $name === $key ? (string) $code : $name;
};
$paidPlans = array_values(array_filter($plans, static fn (array $plan) => ! $plan['is_free'] && [] !== $plan['prices']));
$freePlan  = null;
foreach ($plans as $plan) {
    if ($plan['is_free']) {
        $freePlan = $plan;
        break;
    }
}
// the plan cards: the checkout on the free plan, the plan change on an active subscription not being cancelled
$mode = null === $subscription ? 'subscribe' : ($subscription['is_active'] && ! $subscription['is_cancelling'] ? 'change' : null);
// the cycle the cards open on: the subscription's, else monthly
$openCycle = (string) ($subscription['billing_cycle'] ?? 'M');
$hasYearly = [] !== array_filter($paidPlans, static fn (array $plan) => isset($plan['prices']['Y']));
$hasMonthly = [] !== array_filter($paidPlans, static fn (array $plan) => isset($plan['prices']['M']));
if (! $hasMonthly && $hasYearly) {
    $openCycle = 'Y';
}
?>
<?= $this->section('content') ?>
<section class="section" id="plans-page">
    <div class="row g-4">

        <?php // ---- the plan the business is on ---- ?>
        <div class="col-12">
            <div class="card" id="current-plan-card">
                <div class="card-header">
                    <h5 class="card-title"><?= lang('BusinessPlans.current.title') ?></h5>
                    <p class="card-subtitle"><?= esc(lang('BusinessPlans.current.subtitle', [$businessName])) ?></p>
                </div>
                <div class="card-body">
                    <?php if (null === $subscription): ?>
                        <div class="d-flex align-items-center gap-3 flex-wrap">
                            <span class="fs-4 fw-semibold"><?= esc($freePlan['name'] ?? ucfirst($planCode)) ?></span>
                            <span class="badge bg-secondary"><?= lang('BusinessPlans.current.free-badge') ?></span>
                        </div>
                        <p class="text-muted mt-2 mb-0"><?= lang('BusinessPlans.current.free-text') ?></p>
                    <?php else: ?>
                        <?php if ($subscription['is_pending']): ?>
                            <div class="alert alert-warning"><i class="bi bi-hourglass-split"></i> <?= lang('BusinessPlans.current.pending-text') ?></div>
                        <?php elseif ($subscription['is_suspended']): ?>
                            <div class="alert alert-danger"><i class="bi bi-exclamation-octagon"></i> <?= lang('BusinessPlans.current.suspended-text') ?></div>
                        <?php elseif ($subscription['is_cancelling']): ?>
                            <div class="alert alert-info"><i class="bi bi-calendar-x"></i>
                                <?= lang('BusinessPlans.current.cancelling-text', [localTimeTag($subscription['current_period_end'], 'date')]) ?></div>
                        <?php endif ?>

                        <div class="d-flex align-items-center gap-3 flex-wrap mb-3">
                            <span class="fs-4 fw-semibold"><?= esc($subscription['name']) ?></span>
                            <span class="badge <?= $statusClass[$subscription['subscription_status']] ?? 'bg-secondary' ?>"><?= esc($statuses[$subscription['subscription_status']] ?? $subscription['subscription_status']) ?></span>
                        </div>

                        <dl class="row mb-0" id="subscription-details">
                            <dt class="col-sm-4"><?= lang('BusinessPlans.current.price') ?></dt>
                            <dd class="col-sm-8"><?= esc($subscription['price_text']) ?> / <?= esc($cycles[$subscription['billing_cycle']] ?? $subscription['billing_cycle']) ?></dd>

                            <dt class="col-sm-4"><?= lang('BusinessPlans.current.subscribed-on') ?></dt>
                            <dd class="col-sm-8"><?= localTimeTag($subscription['activated_at'] ?? $subscription['created_at'] ?? null, 'date') ?: '&mdash;' ?></dd>

                            <?php if (! empty($subscription['current_period_start'])): ?>
                                <dt class="col-sm-4"><?= lang('BusinessPlans.current.period') ?></dt>
                                <dd class="col-sm-8"><?= localTimeTag($subscription['current_period_start'], 'date') ?> &ndash; <?= localTimeTag($subscription['current_period_end'], 'date') ?></dd>

                                <dt class="col-sm-4"><?= lang($subscription['is_active'] && ! $subscription['is_cancelling'] ? 'BusinessPlans.current.renews-on' : 'BusinessPlans.current.expires-on') ?></dt>
                                <dd class="col-sm-8"><?= localTimeTag($subscription['current_period_end'], 'date') ?></dd>
                            <?php endif ?>

                            <dt class="col-sm-4"><?= lang('BusinessPlans.current.last-payment') ?></dt>
                            <dd class="col-sm-8">
                                <?php if (null !== $subscription['last_payment']): $paid = $subscription['last_payment']; ?>
                                    <?= esc($paid['amount_due_text']) ?>
                                    <?= lang('BusinessPlans.current.paid-on', [localTimeTag($paid['paid_at'] ?? null, 'date')]) ?>
                                    <?php if (null !== $paid['paid_by']): ?>
                                        <span class="text-muted">(<?= esc($methodName($paid['paid_by']['method_code'] ?? null)) ?>)</span>
                                    <?php endif ?>
                                    <span class="text-muted small d-block"><?= esc($paid['invoice_no'] ?? '') ?></span>
                                <?php else: ?>
                                    <span class="text-muted"><?= lang('BusinessPlans.current.no-payment') ?></span>
                                <?php endif ?>
                            </dd>

                            <?php if (null !== $subscription['next_name']): ?>
                                <dt class="col-sm-4"><?= lang('BusinessPlans.current.next-plan') ?></dt>
                                <dd class="col-sm-8" id="scheduled-change">
                                    <?= esc(lang('BusinessPlans.current.next-plan-text', [$subscription['next_name'], $cycles[$subscription['next_billing_cycle']] ?? $subscription['next_billing_cycle']])) ?>
                                    <?= lang('BusinessPlans.current.next-plan-from', [localTimeTag($subscription['current_period_end'], 'date')]) ?>
                                    <?php if ($subscription['is_active'] && ! $subscription['is_cancelling']): ?>
                                        <button type="button" class="btn btn-sm btn-outline-secondary ms-2" data-plan-action="change"
                                                data-plan-code="<?= esc($subscription['plan_code'], 'attr') ?>" data-billing-cycle="<?= esc($subscription['billing_cycle'], 'attr') ?>"
                                                data-confirm="<?= esc(lang('BusinessPlans.confirm.keep-text', [esc($subscription['name'])]), 'attr') ?>">
                                            <i class="bi bi-arrow-counterclockwise"></i> <?= lang('BusinessPlans.current.keep-plan') ?>
                                        </button>
                                    <?php endif ?>
                                </dd>
                            <?php endif ?>
                        </dl>

                        <div class="mt-4 d-flex gap-2 flex-wrap">
                            <?php if ($subscription['is_active'] && $subscription['is_cancelling']): ?>
                                <button type="button" class="btn btn-primary" data-plan-action="resume" data-confirm="<?= esc(lang('BusinessPlans.confirm.resume-text'), 'attr') ?>">
                                    <i class="bi bi-arrow-repeat"></i> <?= lang('BusinessPlans.current.resume') ?>
                                </button>
                            <?php elseif ($subscription['is_active']): ?>
                                <button type="button" class="btn btn-outline-danger" data-plan-action="cancel" data-danger="1"
                                        data-confirm="<?= esc(lang('BusinessPlans.confirm.cancel-end-text', [localTimeTag($subscription['current_period_end'], 'date')]), 'attr') ?>">
                                    <i class="bi bi-x-circle"></i> <?= lang('BusinessPlans.current.cancel-at-end') ?>
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn btn-outline-danger" data-plan-action="cancel" data-danger="1"
                                        data-confirm="<?= esc(lang('BusinessPlans.confirm.cancel-now-text'), 'attr') ?>">
                                    <i class="bi bi-x-circle"></i> <?= lang('BusinessPlans.current.cancel-now') ?>
                                </button>
                            <?php endif ?>
                        </div>
                    <?php endif ?>
                </div>
            </div>
        </div>

        <?php // ---- the invoice waiting for payment ---- ?>
        <?php if (null !== $pending): ?>
        <div class="col-12">
            <div class="card border-warning" id="payment-card">
                <div class="card-header">
                    <h5 class="card-title"><?= lang('BusinessPlans.pay.title') ?></h5>
                    <p class="card-subtitle"><?= esc(lang('BusinessPlans.pay.subtitle', [$pending['invoice_no'] ?? ''])) ?></p>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-lg-5">
                            <table class="table table-sm mb-0">
                                <tbody>
                                <tr><th scope="row"><?= lang('BusinessPlans.pay.plan') ?></th>
                                    <td class="text-end"><?= esc($pending['plan_name']) ?> / <?= esc($cycles[$pending['billing_cycle'] ?? ''] ?? '') ?></td></tr>
                                <tr><th scope="row"><?= lang('BusinessPlans.pay.list-price') ?></th>
                                    <td class="text-end"><?= esc($pending['list_price_text']) ?></td></tr>
                                <?php if (null !== $pending['discount_amount_text']): ?>
                                    <tr><th scope="row"><?= lang('BusinessPlans.pay.discount') ?> <span class="badge bg-info"><?= esc($pending['discount_code'] ?? '') ?></span></th>
                                        <td class="text-end">&minus;<?= esc($pending['discount_amount_text']) ?></td></tr>
                                <?php endif ?>
                                <tr class="fw-bold"><th scope="row"><?= lang('BusinessPlans.pay.amount-due') ?></th>
                                    <td class="text-end fs-5" id="amount-due"><?= esc($pending['amount_due_text']) ?></td></tr>
                                <tr><th scope="row"><?= lang('BusinessPlans.pay.due-at') ?></th>
                                    <td class="text-end"><?= localTimeTag($pending['due_at'] ?? null) ?></td></tr>
                                </tbody>
                            </table>

                            <?php if (! $pending['under_review']): ?>
                            <form class="mt-3" id="apply-discount-form" novalidate>
                                <input type="hidden" name="invoice_public_id" value="<?= esc($pending['public_id'], 'attr') ?>">
                                <label class="form-label" for="apply-discount-code"><?= lang('BusinessPlans.pay.discount-label') ?></label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="apply-discount-code" name="discount_code" maxlength="40" autocomplete="off">
                                    <button type="submit" class="btn btn-outline-primary"><?= lang('BusinessPlans.pay.apply') ?></button>
                                </div>
                                <div class="invalid-feedback d-block" data-error-for="discount_code"></div>
                            </form>
                            <?php endif ?>
                        </div>

                        <div class="col-lg-7">
                            <?php if ($pending['under_review']): ?>
                                <div class="alert alert-info mb-0" id="payment-under-review">
                                    <i class="bi bi-hourglass-split"></i> <?= lang('BusinessPlans.pay.under-review') ?>
                                </div>
                            <?php elseif ([] === $methods): ?>
                                <div class="alert alert-secondary mb-0"><?= lang('BusinessPlans.pay.no-methods') ?></div>
                            <?php else: ?>
                                <?php $last = $pending['last_payment']; if (null !== $last && 'F' === ($last['txn_status'] ?? '')): ?>
                                    <div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i>
                                        <?= esc(lang('BusinessPlans.pay.rejected', [$last['failure_reason'] ?? ''])) ?></div>
                                <?php endif ?>
                                <form id="pay-form" enctype="multipart/form-data" novalidate>
                                    <input type="hidden" name="invoice_public_id" value="<?= esc($pending['public_id'], 'attr') ?>">
                                    <p class="fw-semibold mb-2"><?= lang('BusinessPlans.pay.method') ?></p>
                                    <div class="list-group mb-3">
                                        <?php foreach ($methods as $i => $method): $code = (string) ($method['method_code'] ?? ''); $payee = (array) ($method['payee'] ?? []); ?>
                                            <label class="list-group-item d-flex gap-3">
                                                <input class="form-check-input flex-shrink-0 mt-1" type="radio" name="method_code" value="<?= esc($code, 'attr') ?>"<?= 0 === $i ? ' checked' : '' ?>>
                                                <span>
                                                    <span class="fw-semibold d-block"><?= esc($methodName($code)) ?></span>
                                                    <?php if (isset($payee['promptpay_id'])): ?>
                                                        <span class="small d-block"><?= lang('BusinessPlans.pay.promptpay-id') ?>: <span class="font-monospace"><?= esc($payee['promptpay_id']) ?></span></span>
                                                    <?php endif ?>
                                                    <?php if (isset($payee['account_number'])): ?>
                                                        <span class="small d-block"><?= lang('BusinessPlans.pay.bank') ?>: <?= esc($payee['bank_name'] ?? '') ?></span>
                                                        <span class="small d-block"><?= lang('BusinessPlans.pay.account-name') ?>: <?= esc($payee['account_name'] ?? '') ?></span>
                                                        <span class="small d-block"><?= lang('BusinessPlans.pay.account-number') ?>: <span class="font-monospace"><?= esc($payee['account_number']) ?></span></span>
                                                    <?php endif ?>
                                                </span>
                                            </label>
                                        <?php endforeach ?>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label" for="pay-slip"><?= lang('BusinessPlans.pay.slip') ?> <span class="text-danger">*</span></label>
                                        <input type="file" class="form-control" id="pay-slip" name="slip" accept="image/jpeg,image/png,image/webp,application/pdf" required>
                                        <div class="form-text"><?= lang('BusinessPlans.pay.slip-help') ?></div>
                                        <div class="invalid-feedback d-block" data-error-for="slip"></div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label" for="pay-reference"><?= lang('BusinessPlans.pay.reference') ?></label>
                                        <input type="text" class="form-control" id="pay-reference" name="provider_ref" maxlength="100" autocomplete="off">
                                        <div class="invalid-feedback d-block" data-error-for="provider_ref"></div>
                                    </div>
                                    <button type="submit" class="btn btn-primary"><i class="bi bi-send"></i> <?= lang('BusinessPlans.pay.submit') ?></button>
                                </form>
                            <?php endif ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif ?>

        <?php // ---- the plans: to subscribe, or to change to ---- ?>
        <?php if (null !== $mode): ?>
        <div class="col-12">
            <div class="card" id="plans-card" data-mode="<?= $mode ?>">
                <div class="card-header d-flex justify-content-between align-items-start flex-wrap gap-2">
                    <div>
                        <h5 class="card-title"><?= lang('subscribe' === $mode ? 'BusinessPlans.plans.choose-title' : 'BusinessPlans.plans.change-title') ?></h5>
                        <p class="card-subtitle"><?= lang('subscribe' === $mode ? 'BusinessPlans.plans.choose-subtitle' : 'BusinessPlans.plans.change-subtitle') ?></p>
                    </div>
                    <?php if ($hasMonthly && $hasYearly): ?>
                        <div class="btn-group" role="group" aria-label="<?= esc(lang('BusinessPlans.plans.cycle-label'), 'attr') ?>">
                            <?php foreach (['M', 'Y'] as $cycle): ?>
                                <input type="radio" class="btn-check" name="plans-cycle" id="plans-cycle-<?= $cycle ?>" value="<?= $cycle ?>"<?= $cycle === $openCycle ? ' checked' : '' ?>>
                                <label class="btn btn-outline-primary" for="plans-cycle-<?= $cycle ?>"><?= esc(lang('BusinessPlans.cycle-adjective.' . $cycle)) ?></label>
                            <?php endforeach ?>
                        </div>
                    <?php endif ?>
                </div>
                <div class="card-body">
                    <?php if ([] === $paidPlans): ?>
                        <div class="alert alert-secondary mb-0"><?= lang('BusinessPlans.plans.none') ?></div>
                    <?php else: ?>
                    <div class="row g-3">
                        <?php foreach (array_merge(null === $freePlan ? [] : [$freePlan], $paidPlans) as $plan): ?>
                            <div class="col-md-6 col-xl-3" data-plan-card="<?= esc($plan['plan_code'], 'attr') ?>">
                                <div class="card h-100 mb-0 border<?= $plan['plan_code'] === ($subscription['plan_code'] ?? $planCode) ? ' border-primary' : '' ?>">
                                    <div class="card-body d-flex flex-column">
                                        <h5 class="card-title mb-1"><?= esc($plan['name']) ?></h5>
                                        <?php foreach (['M', 'Y'] as $cycle): $price = $plan['prices'][$cycle] ?? null; ?>
                                            <div data-cycle="<?= $cycle ?>"<?= $cycle === $openCycle ? '' : ' hidden' ?>>
                                                <?php if ($plan['is_free']): ?>
                                                    <p class="fs-4 fw-bold mb-1"><?= lang('BusinessPlans.plans.free-price') ?></p>
                                                <?php elseif (null !== $price): ?>
                                                    <p class="fs-4 fw-bold mb-1"><?= esc($price['price_text']) ?>
                                                        <small class="fs-6 fw-normal text-muted text-nowrap">/&nbsp;<?= esc($cycles[$cycle]) ?></small></p>
                                                    <?php if (null !== $price['next_price_text']): ?>
                                                        <p class="small text-muted mb-1"><?= lang('BusinessPlans.plans.next-price', [esc($price['next_price_text']), localTimeTag($price['next_price_from'], 'date')]) ?></p>
                                                    <?php endif ?>
                                                <?php else: ?>
                                                    <p class="text-muted mb-1"><?= lang('BusinessPlans.plans.not-offered.' . $cycle) ?></p>
                                                <?php endif ?>
                                            </div>
                                        <?php endforeach ?>
                                        <?php if ('' !== $plan['plan_description']): ?>
                                            <p class="text-muted small flex-grow-1" style="white-space: pre-line"><?= esc($plan['plan_description']) ?></p>
                                        <?php else: ?>
                                            <div class="flex-grow-1"></div>
                                        <?php endif ?>

                                        <?php foreach (['M', 'Y'] as $cycle): $price = $plan['prices'][$cycle] ?? null; ?>
                                            <div class="mt-2" data-cycle="<?= $cycle ?>"<?= $cycle === $openCycle ? '' : ' hidden' ?>>
                                            <?php if ('subscribe' === $mode): ?>
                                                <?php if ($plan['is_free']): ?>
                                                    <span class="badge bg-primary"><?= lang('BusinessPlans.plans.current-badge') ?></span>
                                                <?php elseif (null !== $price): ?>
                                                    <button type="button" class="btn btn-primary w-100" data-plan-checkout
                                                            data-plan-code="<?= esc($plan['plan_code'], 'attr') ?>" data-billing-cycle="<?= $cycle ?>"
                                                            data-plan-name="<?= esc($plan['name'], 'attr') ?>" data-cycle-name="<?= esc($cycles[$cycle], 'attr') ?>"
                                                            data-price-text="<?= esc($price['price_text'], 'attr') ?>">
                                                        <?= lang('BusinessPlans.plans.choose') ?>
                                                    </button>
                                                <?php endif ?>
                                            <?php else: // change ?>
                                                <?php
                                                $isCurrent   = $plan['plan_code'] === $subscription['plan_code'] && $cycle === $subscription['billing_cycle'];
                                                $isScheduled = $plan['plan_code'] === ($subscription['next_plan_code'] ?? null) && $cycle === ($subscription['next_billing_cycle'] ?? null);
                                                ?>
                                                <?php if ($isCurrent): ?>
                                                    <span class="badge bg-primary"><?= lang('BusinessPlans.plans.current-badge') ?></span>
                                                <?php elseif ($isScheduled): ?>
                                                    <span class="badge bg-info"><?= lang('BusinessPlans.plans.scheduled-badge', [localTimeTag($subscription['current_period_end'], 'date')]) ?></span>
                                                <?php elseif ($plan['is_free']): ?>
                                                    <span class="small text-muted"><?= lang('BusinessPlans.plans.free-by-cancelling') ?></span>
                                                <?php elseif (null !== $price):
                                                    $kind = $plan['plan_tier'] > $subscription['plan_tier'] ? 'upgrade' : ($plan['plan_tier'] < $subscription['plan_tier'] ? 'downgrade' : 'switch');
                                                    $label = 'switch' === $kind ? lang('BusinessPlans.plans.switch-cycle.' . $cycle) : lang('BusinessPlans.plans.' . $kind);
                                                    ?>
                                                    <button type="button" class="btn <?= 'downgrade' === $kind ? 'btn-outline-primary' : 'btn-primary' ?> w-100" data-plan-action="change"
                                                            data-plan-code="<?= esc($plan['plan_code'], 'attr') ?>" data-billing-cycle="<?= $cycle ?>"
                                                            data-confirm="<?= esc(lang('BusinessPlans.confirm.change-text', [esc($plan['name']), esc($cycles[$cycle]), esc($price['price_text']),
                                                                localTimeTag($subscription['current_period_end'], 'date')]), 'attr') ?>">
                                                        <i class="bi <?= 'upgrade' === $kind ? 'bi-arrow-up-circle' : ('downgrade' === $kind ? 'bi-arrow-down-circle' : 'bi-arrow-left-right') ?>"></i>
                                                        <?= esc($label) ?>
                                                    </button>
                                                <?php endif ?>
                                            <?php endif ?>
                                            </div>
                                        <?php endforeach ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach ?>
                    </div>
                    <?php if ('change' === $mode): ?>
                        <p class="text-muted small mt-3 mb-0"><i class="bi bi-info-circle"></i> <?= lang('BusinessPlans.plans.change-note', [localTimeTag($subscription['current_period_end'], 'date')]) ?></p>
                    <?php endif ?>
                    <?php endif ?>
                </div>
            </div>
        </div>
        <?php elseif (null !== $subscription && $subscription['is_cancelling']): ?>
        <div class="col-12">
            <div class="alert alert-secondary mb-0"><i class="bi bi-info-circle"></i> <?= lang('BusinessPlans.plans.resume-to-change') ?></div>
        </div>
        <?php endif ?>

        <?php // ---- every invoice the business has had ---- ?>
        <?php if (! empty($history)): ?>
        <div class="col-12">
            <div class="card" id="billing-history-card">
                <div class="card-header">
                    <h5 class="card-title"><?= lang('BusinessPlans.history.title') ?></h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                            <tr>
                                <th><?= lang('BusinessPlans.history.invoice') ?></th>
                                <th><?= lang('BusinessPlans.history.plan') ?></th>
                                <th><?= lang('BusinessPlans.history.period') ?></th>
                                <th class="text-end"><?= lang('BusinessPlans.history.amount') ?></th>
                                <th><?= lang('BusinessPlans.history.status') ?></th>
                                <th><?= lang('BusinessPlans.history.paid') ?></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($history as $invoice): $state = (string) ($invoice['invoice_status'] ?? ''); ?>
                                <tr>
                                    <td class="text-nowrap"><?= esc($invoice['invoice_no'] ?? '') ?><span class="d-block small text-muted"><?= localTimeTag($invoice['created_at'] ?? null, 'date') ?></span></td>
                                    <td><?= esc($invoice['plan_name']) ?> / <?= esc($cycles[$invoice['billing_cycle'] ?? ''] ?? '') ?></td>
                                    <td class="text-nowrap"><?php if (! empty($invoice['period_start'])): ?><?= localTimeTag($invoice['period_start'], 'date') ?> &ndash; <?= localTimeTag($invoice['period_end'], 'date') ?><?php else: ?>&mdash;<?php endif ?></td>
                                    <td class="text-end text-nowrap"><?= esc($invoice['amount_due_text']) ?>
                                        <?php if (null !== $invoice['discount_amount_text']): ?><span class="d-block small text-muted"><?= esc(lang('BusinessPlans.history.discounted', [$invoice['discount_code'] ?? ''])) ?></span><?php endif ?></td>
                                    <td><span class="badge <?= $invoiceClass[$state] ?? 'bg-secondary' ?>"><?= esc($invoiceState[$state] ?? $state) ?></span>
                                        <?php if ($invoice['under_review']): ?><span class="d-block small text-muted"><?= lang('BusinessPlans.history.under-review') ?></span><?php endif ?></td>
                                    <td class="text-nowrap"><?php if (! empty($invoice['paid_at'])): ?><?= localTimeTag($invoice['paid_at'], 'date') ?>
                                        <?php if (null !== $invoice['paid_by']): ?><span class="d-block small text-muted"><?= esc($methodName($invoice['paid_by']['method_code'] ?? null)) ?></span><?php endif ?>
                                        <?php else: ?>&mdash;<?php endif ?></td>
                                </tr>
                            <?php endforeach ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php endif ?>
    </div>

    <?php // ---- the checkout: the plan picked, a discount code, then subscribe ---- ?>
    <?php if ('subscribe' === $mode): ?>
    <div class="modal fade" id="checkout-modal" tabindex="-1" aria-labelledby="checkout-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" id="checkout-form" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="checkout-title"><?= lang('BusinessPlans.checkout.title') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= esc(lang('BusinessPlans.checkout.close'), 'attr') ?>"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="plan_code">
                    <input type="hidden" name="billing_cycle">
                    <div class="d-flex justify-content-between align-items-baseline mb-3">
                        <span><span class="fw-semibold" data-checkout="plan"></span> / <span data-checkout="cycle"></span></span>
                        <span class="fs-4 fw-bold" data-checkout="price"></span>
                    </div>
                    <div class="mb-2" data-checkout="discounts" hidden>
                        <p class="small text-muted mb-1"><?= lang('BusinessPlans.checkout.available-discounts') ?></p>
                        <div class="d-flex flex-wrap gap-2" data-checkout="discount-list"></div>
                    </div>
                    <label class="form-label" for="checkout-discount"><?= lang('BusinessPlans.checkout.discount-label') ?></label>
                    <input type="text" class="form-control" id="checkout-discount" name="discount_code" maxlength="40" autocomplete="off">
                    <div class="invalid-feedback d-block" data-error-for="discount_code"></div>
                    <div class="invalid-feedback d-block" data-error-for="billing_cycle"></div>
                    <div class="invalid-feedback d-block" data-error-for="plan_code"></div>
                    <p class="small text-muted mt-3 mb-0"><i class="bi bi-info-circle"></i> <?= lang('BusinessPlans.checkout.note') ?></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= lang('BusinessPlans.checkout.cancel') ?></button>
                    <button type="submit" class="btn btn-primary"><?= lang('BusinessPlans.checkout.submit') ?></button>
                </div>
            </form>
        </div>
    </div>
    <?php endif ?>
</section>

<script>
<?= view('scripts/plans', ['urls' => $urls]) ?>
</script>
<?= $this->endSection() ?>
