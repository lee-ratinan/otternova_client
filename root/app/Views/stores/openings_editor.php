<?php
/**
 * An editor of openings (pairs of opening and closing times) in a form of the store hours page: "weekly" has a row
 * of openings for each day of the week, "special" a single row, for one date. scripts/store_hours builds the rows
 * into it and keeps the hidden field $field filled with the openings as JSON, the only one of its inputs the form
 * submits.
 *
 * @var string      $kind  weekly or special
 * @var string      $field the hidden field's name
 * @var string|null $help  the text above it, in place of the store hours one (a person's working hours page)
 */
?>
<div class="row mb-3"<?= 'special' === $kind ? ' data-special-openings' : '' ?>>
    <div class="<?= 'special' === $kind ? 'col-sm-8 offset-sm-4' : 'col-12' ?>" data-openings-editor="<?= esc($kind, 'attr') ?>">
        <p class="form-text mt-0"><?= esc($help ?? lang('StoreHours.editor.help-' . $kind)) ?></p>
        <div data-openings-days></div>
        <input type="hidden" name="<?= esc($field, 'attr') ?>" value="[]">
    </div>
</div>
