<?php
/**
 * The variants editor of the artifact form (create and edit panes): scripts/artifact_form builds the options and the
 * rows into it, and keeps the hidden field $field filled with the variants as JSON, the only one of its inputs the
 * form submits.
 *
 * @var string $field the hidden field's name
 */
?>
<div class="row mb-3">
    <div class="col-12" data-variants-editor>
        <p class="form-text mt-0"><?= esc(lang('Artifacts.variants.help')) ?></p>
        <div class="mb-3" data-variants-options></div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-2" data-variants-table></table>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <button type="button" class="btn btn-sm btn-outline-primary" data-variants-add>
                <i class="bi bi-plus-circle"></i> <?= esc(lang('Artifacts.variants.add')) ?>
            </button>
            <span class="form-text m-0" data-variants-count></span>
        </div>
        <input type="hidden" name="<?= esc($field, 'attr') ?>" value="[]">
    </div>
</div>
