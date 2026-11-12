<?php
/**
 * The slots editor of the session form (create and edit panes): scripts/session_form builds the rows into it, and
 * keeps the hidden field $field filled with the slots as JSON, the only one of its inputs the form submits.
 *
 * @var string $field the hidden field's name
 */
?>
<div class="row mb-3">
    <div class="col-12" data-slots-editor>
        <p class="form-text mt-0"><?= esc(lang('ServiceSessions.slots.help')) ?></p>
        <p class="small mb-2" data-slots-needs></p>
        <p class="small mb-2" data-slots-total></p>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-2" data-slots-table></table>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <button type="button" class="btn btn-sm btn-outline-primary" data-slots-add>
                <i class="bi bi-plus-circle"></i> <?= esc(lang('ServiceSessions.slots.add')) ?>
            </button>
            <span class="text-muted small"><?= esc(lang('ServiceSessions.slots.or')) ?></span>
            <div class="input-group input-group-sm" style="width: auto;">
                <button type="button" class="btn btn-outline-primary" data-slots-repeat>
                    <i class="bi bi-calendar-week"></i> <?= esc(lang('ServiceSessions.slots.repeat')) ?>
                </button>
                <input type="number" class="form-control" style="width: 5rem;" min="1" max="52" value="9" data-slots-repeat-count
                       aria-label="<?= esc(lang('ServiceSessions.slots.repeat-count'), 'attr') ?>">
                <span class="input-group-text"><?= esc(lang('ServiceSessions.slots.weeks')) ?></span>
            </div>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-slots-copy>
                <i class="bi bi-files"></i> <?= esc(lang('ServiceSessions.slots.copy')) ?>
            </button>
        </div>
        <input type="hidden" name="<?= esc($field, 'attr') ?>" value="[]">
    </div>
</div>
