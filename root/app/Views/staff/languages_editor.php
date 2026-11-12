<?php
/**
 * The languages editor of a person's profile page (StaffProfiles::form()): scripts/staff_profile builds the rows into
 * it and keeps the hidden field $field filled with them as JSON, the only one of its inputs the form submits.
 *
 * @var string $field the hidden field's name
 */
?>
<div class="row mb-3">
    <div class="col-12" data-languages-editor>
        <p class="form-text mt-0"><?= esc(lang('StaffProfiles.languages.help')) ?></p>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-2" data-languages-table></table>
        </div>
        <button type="button" class="btn btn-sm btn-outline-primary" data-languages-add>
            <i class="bi bi-plus-circle"></i> <?= esc(lang('StaffProfiles.languages.add')) ?>
        </button>
        <input type="hidden" name="<?= esc($field, 'attr') ?>" value="[]">
    </div>
</div>
