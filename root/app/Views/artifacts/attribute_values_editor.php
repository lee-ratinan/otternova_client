<?php
/**
 * The choices editor of the option form (create and edit panes): scripts/attribute_form builds the rows into it and
 * keeps the hidden field $field filled with the choices as JSON, the only one of its inputs the form submits.
 *
 * @var string $field the hidden field's name
 */
?>
<div class="row mb-3">
    <div class="col-12" data-values-editor>
        <p class="form-text mt-0"><?= esc(lang('ArtifactAttributes.values.help')) ?></p>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-2" data-values-table></table>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <button type="button" class="btn btn-sm btn-outline-primary" data-values-add>
                <i class="bi bi-plus-circle"></i> <?= esc(lang('ArtifactAttributes.values.add')) ?>
            </button>
            <span class="form-text m-0" data-values-count></span>
        </div>
        <input type="hidden" name="<?= esc($field, 'attr') ?>" value="[]">
    </div>
</div>
