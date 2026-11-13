<?php
/**
 * The order number preview of the order number settings (OrderNumbers::index()): the number the next order gets as
 * the settings are saved, and as the form is being changed
 * @var string $next
 */
?>
<div class="row mb-3">
    <div class="col-md-4 col-form-label fw-semibold"><?= lang('OrderNumbers.next') ?></div>
    <div class="col-md-8">
        <div class="fs-5 font-monospace" id="order-number-preview"><?= esc($next) ?></div>
        <div class="form-text" id="order-number-preview-note"><?= lang('OrderNumbers.next-help') ?></div>
        <div class="text-danger small mt-1" id="order-number-rule" hidden><?= lang('OrderNumbers.rule') ?></div>
    </div>
</div>
