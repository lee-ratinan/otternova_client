<?php
/**
 * The details editor of a person's profile page (StaffProfiles::form()): one block per attribute of the business, in
 * which scripts/staff_profile builds the person's values; it keeps the hidden field $field filled with them as JSON,
 * the only one of its inputs the form submits. Without attributes, the way to define them instead.
 *
 * @var string $field         the hidden field's name
 * @var bool   $hasAttributes whether the business records anything about its people yet
 * @var string $attributesUrl the staff attributes list
 */
?>
<div class="row mb-3">
    <div class="col-12" data-details-editor>
        <?php if ($hasAttributes): ?>
            <p class="form-text mt-0">
                <?= esc(lang('StaffProfiles.details.help')) ?>
                <a href="<?= esc($attributesUrl, 'attr') ?>"><?= esc(lang('StaffProfiles.details.manage')) ?></a>
            </p>
            <div data-details-blocks></div>
            <input type="hidden" name="<?= esc($field, 'attr') ?>" value="[]">
        <?php else: ?>
            <p class="text-muted mb-2"><?= esc(lang('StaffProfiles.details.none')) ?></p>
            <a class="btn btn-sm btn-outline-primary" href="<?= esc($attributesUrl, 'attr') ?>">
                <i class="bi bi-plus-circle"></i> <?= esc(lang('StaffProfiles.details.define')) ?>
            </a>
        <?php endif ?>
    </div>
</div>
