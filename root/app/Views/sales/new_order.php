<?php
/**
 * A new order taken at the counter (a walk-in, a call-in); see App\Controllers\Sales::newOrder(). Everything on it is
 * run by scripts/sales_new_order through admin/sales/counter/*: the counter's store, the catalog, the times and
 * sessions of a booking, the price (the API's quote, again after each change) and the order.
 */
$this->extend('_layout_admin');
?>
<?= $this->section('content') ?>
<section class="section" id="counter-order">
    <p class="text-muted"><?= esc(lang('Sales.new.subtitle')) ?></p>
    <div class="alert alert-danger d-none" role="alert" data-co-errors></div>

    <div class="row g-4">
        <div class="col-xl-8">
            <!-- the counter, and what it sells -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label" for="co-store"><?= esc(lang('Sales.new.store')) ?></label>
                            <select class="form-select" id="co-store" data-co-store></select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label" for="co-search"><?= esc(lang('Sales.new.search')) ?></label>
                            <input type="search" class="form-control" id="co-search" data-co-search autocomplete="off" placeholder="<?= esc(lang('Sales.new.search-placeholder'), 'attr') ?>">
                        </div>
                    </div>
                    <div class="list-group list-group-flush mt-3" data-co-results></div>
                </div>
            </div>

            <!-- the order's lines -->
            <div class="card">
                <div class="card-header"><h5 class="card-title mb-0"><?= esc(lang('Sales.new.items')) ?></h5></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <tbody data-co-lines></tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer">
                    <dl class="row mb-0 text-end small" data-co-totals></dl>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <form id="co-form" novalidate>
                <!-- the customer -->
                <div class="card mb-4">
                    <div class="card-header"><h5 class="card-title mb-0"><?= esc(lang('Sales.new.customer')) ?></h5></div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label" for="co-first"><?= esc(lang('Sales.new.first-name')) ?> <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="co-first" name="first_name" maxlength="100" autocomplete="off" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label" for="co-last"><?= esc(lang('Sales.new.last-name')) ?></label>
                                <input type="text" class="form-control" id="co-last" name="last_name" maxlength="100" autocomplete="off">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="co-email"><?= esc(lang('Sales.new.email')) ?></label>
                                <input type="email" class="form-control" id="co-email" name="email" maxlength="64" autocomplete="off">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="co-tel"><?= esc(lang('Sales.new.telephone')) ?></label>
                                <input type="tel" class="form-control" id="co-tel" name="telephone" maxlength="24" autocomplete="off">
                                <div class="form-text"><?= esc(lang('Sales.new.contact-help')) ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- the products: handed over or shipped -->
                <div class="card mb-4 d-none" data-co-delivery>
                    <div class="card-header"><h5 class="card-title mb-0"><?= esc(lang('Sales.new.delivery')) ?></h5></div>
                    <div class="card-body">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="co-handed" name="handed_over" checked>
                            <label class="form-check-label" for="co-handed"><?= esc(lang('Sales.new.handed-over')) ?></label>
                            <div class="form-text"><?= esc(lang('Sales.new.handed-help')) ?></div>
                        </div>
                        <div class="d-none" data-co-shipping>
                            <label class="form-label" for="co-ship"><?= esc(lang('Sales.new.shipping')) ?></label>
                            <select class="form-select mb-2" id="co-ship" name="shipping"></select>
                            <div class="d-none" data-co-pickup-store>
                                <label class="form-label" for="co-pickup-store"><?= esc(lang('Sales.new.pickup-store')) ?></label>
                                <select class="form-select mb-2" id="co-pickup-store" name="pickup_store"></select>
                            </div>
                            <div class="d-none" data-co-pickup-point>
                                <label class="form-label" for="co-pickup-point"><?= esc(lang('Sales.new.pickup-point')) ?></label>
                                <textarea class="form-control mb-2" id="co-pickup-point" name="pickup_point" rows="2" maxlength="500"></textarea>
                            </div>
                            <div class="row g-2 d-none" data-co-address>
                                <div class="col-12"><input type="text" class="form-control" name="line_1" maxlength="255" placeholder="<?= esc(lang('Sales.new.line-1'), 'attr') ?>" aria-label="<?= esc(lang('Sales.new.line-1'), 'attr') ?>"></div>
                                <div class="col-12"><input type="text" class="form-control" name="line_2" maxlength="255" placeholder="<?= esc(lang('Sales.new.line-2'), 'attr') ?>" aria-label="<?= esc(lang('Sales.new.line-2'), 'attr') ?>"></div>
                                <div class="col-7"><input type="text" class="form-control" name="city" maxlength="128" placeholder="<?= esc(lang('Sales.new.city'), 'attr') ?>" aria-label="<?= esc(lang('Sales.new.city'), 'attr') ?>"></div>
                                <div class="col-5"><input type="text" class="form-control" name="postal_code" maxlength="16" placeholder="<?= esc(lang('Sales.new.postal-code'), 'attr') ?>" aria-label="<?= esc(lang('Sales.new.postal-code'), 'attr') ?>"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- payment -->
                <div class="card mb-4">
                    <div class="card-header"><h5 class="card-title mb-0"><?= esc(lang('Sales.new.payment')) ?></h5></div>
                    <div class="card-body">
                        <label class="form-label" for="co-discount"><?= esc(lang('Sales.new.discount')) ?></label>
                        <input type="text" class="form-control mb-1" id="co-discount" name="discount" maxlength="30" autocomplete="off">
                        <div class="small text-danger mb-2" data-co-discount-error></div>
                        <label class="form-label" for="co-payment"><?= esc(lang('Sales.new.payment-method')) ?> <span class="text-danger">*</span></label>
                        <select class="form-select mb-2" id="co-payment" name="payment"></select>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="co-paid" name="paid_now">
                            <label class="form-check-label" for="co-paid"><?= esc(lang('Sales.new.paid-now')) ?></label>
                            <div class="form-text"><?= esc(lang('Sales.new.paid-help')) ?></div>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-lg w-100" data-co-create disabled><i class="bi bi-bag-check"></i> <?= esc(lang('Sales.new.create')) ?></button>
            </form>
        </div>
    </div>
</section>

<!-- booking an appointment or a group session -->
<div class="modal fade" id="co-book-modal" tabindex="-1" aria-labelledby="co-book-title" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form class="modal-content" id="co-book-form" novalidate>
            <div class="modal-header">
                <h5 class="modal-title" id="co-book-title"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= esc(lang('Sales.new.cancel'), 'attr') ?>"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-3 d-none" data-co-book-date>
                    <div class="col-sm-5">
                        <label class="form-label" for="co-book-day"><?= esc(lang('Sales.new.date')) ?></label>
                        <input type="date" class="form-control" id="co-book-day">
                    </div>
                </div>
                <div class="fw-semibold mb-2" data-co-book-heading></div>
                <div class="d-flex flex-wrap gap-2 mb-3" data-co-book-options></div>
                <div class="mb-3 d-none" data-co-book-seats>
                    <label class="form-label" for="co-book-seat-count"><?= esc(lang('Sales.new.seats')) ?></label>
                    <input type="number" class="form-control" id="co-book-seat-count" min="1" value="1" style="max-width: 8rem;">
                </div>
                <div data-co-book-names></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= esc(lang('Sales.new.cancel')) ?></button>
                <button type="submit" class="btn btn-primary" data-co-book-add disabled><?= esc(lang('Sales.new.add-booking')) ?></button>
            </div>
        </form>
    </div>
</div>

<script>
<?= view('scripts/sales_new_order') ?>
</script>
<?= $this->endSection() ?>
