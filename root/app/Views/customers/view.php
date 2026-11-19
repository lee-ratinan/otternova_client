<?php
/**
 * One customer of the session business; see App\Controllers\Customers::customer(). Their details and orders are read
 * only; the notes form and the Delete buttons are run by scripts/customer_notes, which posts to admin/customers/note/*
 * and reloads the page.
 * @var array $customer v1/client/customer's customer
 * @var array $orders   their latest orders with the business, newest first
 * @var array $notes    [{public_id, note_text, created_by_name, created_at, can_delete}], newest first
 * @var array $badges   Sales::BADGES
 */
$this->extend('_layout_admin');

$currency = (string) ($customer['currency_code'] ?? '');
$enum     = static fn (string $field, ?string $value): string => (string) (lang('Sales.enum.' . $field)[$value ?? ''] ?? $value ?? '');
$badge    = static fn (string $field, ?string $value): string => '<span class="badge ' . esc($badges[$field][$value ?? ''] ?? 'bg-secondary', 'attr') . '">'
    . esc($enum($field, $value)) . '</span>';
$publicId = (string) ($customer['public_id'] ?? '');
$maxNote  = 2000; // BusinessCustomerNoteModel::MAX_LENGTH of the API
?>
<?= $this->section('content') ?>
<section class="section" id="customer-view" data-public-id="<?= esc($publicId, 'attr') ?>">
    <div class="mb-3">
        <a href="<?= base_url('admin/customers') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> <?= esc(lang('Customers.view.back')) ?></a>
    </div>
    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-body">
                    <h5 class="card-title mb-1"><?= esc(lang('Customers.view.details')) ?></h5>
                    <p class="small text-muted"><?= esc(lang('Customers.view.read-only')) ?></p>
                    <dl class="row mb-0">
                        <dt class="col-sm-5"><?= esc(lang('Customers.view.email')) ?></dt>
                        <dd class="col-sm-7 text-break"><?= esc((string) ($customer['email_address'] ?? '')) ?></dd>
                        <dt class="col-sm-5"><?= esc(lang('Customers.view.telephone')) ?></dt>
                        <dd class="col-sm-7"><?= esc((string) ($customer['telephone_number'] ?? '')) ?></dd>
                        <dt class="col-sm-5"><?= esc(lang('Customers.view.order-count')) ?></dt>
                        <dd class="col-sm-7"><?= (int) ($customer['order_count'] ?? 0) ?></dd>
                        <dt class="col-sm-5"><?= esc(lang('Customers.view.first-order')) ?></dt>
                        <dd class="col-sm-7"><?= localTimeTag($customer['first_order_at'] ?? null) ?></dd>
                        <dt class="col-sm-5"><?= esc(lang('Customers.view.last-order')) ?></dt>
                        <dd class="col-sm-7"><?= localTimeTag($customer['last_order_at'] ?? null) ?></dd>
                        <dt class="col-sm-5"><?= esc(lang('Customers.view.net-paid')) ?></dt>
                        <dd class="col-sm-7"><?= esc(formatMoney($customer['net_paid'] ?? null, $currency)) ?></dd>
                        <dt class="col-sm-5"><?= esc(lang('Customers.view.no-shows')) ?></dt>
                        <dd class="col-sm-7 mb-0">
                            <?php $noShows = (int) ($customer['no_show_count'] ?? 0); ?>
                            <?= $noShows > 0 ? '<span class="badge bg-dark">' . $noShows . '</span>' : '0' ?>
                        </dd>
                    </dl>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h5 class="card-title mb-1"><?= esc(lang('Customers.notes.title')) ?></h5>
                    <p class="small text-muted"><?= esc(lang('Customers.notes.subtitle')) ?></p>
                    <form id="customer-note-form" class="mb-3" novalidate>
                        <textarea class="form-control" name="note_text" rows="3" maxlength="<?= $maxNote ?>" required
                                  placeholder="<?= esc(lang('Customers.notes.placeholder'), 'attr') ?>"></textarea>
                        <div class="invalid-feedback" data-error-for="note_text"></div>
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <span class="form-text"><?= esc(lang('Customers.notes.max', [$maxNote])) ?></span>
                            <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> <?= esc(lang('Customers.notes.add')) ?></button>
                        </div>
                    </form>
                    <?php if ($notes === []) : ?>
                        <p class="text-muted small mb-0"><?= esc(lang('Customers.notes.none')) ?></p>
                    <?php else : ?>
                        <ul class="list-group list-group-flush" id="customer-notes">
                            <?php foreach ($notes as $note) : ?>
                                <li class="list-group-item px-0">
                                    <div class="d-flex justify-content-between align-items-start gap-2">
                                        <div class="small text-muted">
                                            <strong><?= esc((string) ($note['created_by_name'] ?? '') ?: lang('Customers.notes.unknown')) ?></strong>
                                            · <?= localTimeTag($note['created_at'] ?? null) ?>
                                        </div>
                                        <?php if (! empty($note['can_delete'])) : ?>
                                            <button type="button" class="btn btn-sm btn-link text-danger p-0" data-delete-note="<?= esc((string) $note['public_id'], 'attr') ?>">
                                                <i class="bi bi-trash"></i> <?= esc(lang('Customers.notes.delete')) ?>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                    <div class="mt-1" style="white-space: pre-line;"><?= esc((string) ($note['note_text'] ?? '')) ?></div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title mb-1"><?= esc(lang('Customers.view.orders')) ?></h5>
                    <p class="small text-muted"><?= esc(lang('Customers.view.orders-latest', [count($orders)])) ?></p>
                    <?php if ($orders === []) : ?>
                        <p class="text-muted small mb-0"><?= esc(lang('Customers.view.no-orders')) ?></p>
                    <?php else : ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                <tr>
                                    <th><?= esc(lang('Customers.view.order-number')) ?></th>
                                    <th><?= esc(lang('Customers.view.placed')) ?></th>
                                    <th class="text-end"><?= esc(lang('Customers.view.total')) ?></th>
                                    <th><?= esc(lang('Customers.view.order-status')) ?></th>
                                    <th><?= esc(lang('Customers.view.payment-status')) ?></th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($orders as $order) :
                                    $status = ! empty($order['cancelled_at']) ? 'cancelled' : (! empty($order['no_show_at']) ? 'no_show' : (string) ($order['order_status'] ?? '')); ?>
                                    <tr>
                                        <td class="text-nowrap">
                                            <a href="<?= base_url('admin/sales/order/' . rawurlencode((string) ($order['public_id'] ?? ''))) ?>"><?= esc((string) ($order['order_number'] ?? '')) ?></a>
                                        </td>
                                        <td class="text-nowrap"><?= localTimeTag($order['created_at'] ?? null) ?></td>
                                        <td class="text-end text-nowrap"><?= esc(formatMoney($order['total_amount'] ?? null, (string) ($order['currency_code'] ?? $currency))) ?></td>
                                        <td><?= $badge('order_status', $status) ?></td>
                                        <td><?= $badge('financial_status', $order['financial_status'] ?? null) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
<?= view('scripts/customer_notes', [
    'customerPublicId' => $publicId,
    'urls'             => ['add' => base_url('admin/customers/note/add'), 'delete' => base_url('admin/customers/note/delete')],
]) ?>
</script>
<?= $this->endSection() ?>
