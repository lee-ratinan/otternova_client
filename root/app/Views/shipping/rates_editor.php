<?php
/**
 * The fee brackets editor of the shipping method form (create and edit panes): scripts/shipping_method_form builds
 * the rows into it and keeps the hidden field $field filled with the brackets as JSON, the only one of its inputs
 * the form submits.
 *
 * @var string $field the hidden field's name
 */
?>
<div class="row mb-3">
    <div class="col-12" data-rates-editor>
        <p class="form-text mt-0" data-rates-help></p>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-2" data-rates-table></table>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <button type="button" class="btn btn-sm btn-outline-primary" data-rates-add>
                <i class="bi bi-plus-circle"></i> <?= esc(lang('ShippingMethods.rates.add')) ?>
            </button>
            <span class="form-text m-0" data-rates-count></span>
        </div>
        <input type="hidden" name="<?= esc($field, 'attr') ?>" value="[]">
    </div>
</div>
