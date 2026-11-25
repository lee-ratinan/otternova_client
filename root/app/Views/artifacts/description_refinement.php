<?php
/**
 * The AI refinement of the description, under "Name and Description in Other Languages" of the artifact form (edit
 * pane, a saved artifact only): which description to start from, the button, and how many refinements are left this
 * window (60 days, rolling). scripts/artifact_form wires it up (cfg.refinement).
 *
 * @var array $cap      { max, count, can_add, window_days } of v1/client/artifact's caps.description_refinement
 * @var string $planName
 */
$max   = (int) ($cap['max'] ?? 0);
$count = (int) ($cap['count'] ?? 0);
$days  = (int) ($cap['window_days'] ?? 60);
?>
<div class="row mb-3" id="artifact-description-refinement">
    <div class="col-12">
        <div class="border rounded-2 p-3">
            <div class="d-flex flex-wrap align-items-end gap-2">
                <div class="flex-grow-1" style="min-width: 12rem;">
                    <label class="form-label small mb-1" for="artifact-refine-source"><?= esc(lang('Artifacts.refinement.source')) ?></label>
                    <select class="form-select form-select-sm" id="artifact-refine-source" data-refine-source></select>
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary" data-refine-button disabled>
                    <span class="spinner-border spinner-border-sm me-1 d-none" role="status" aria-hidden="true" data-refine-spinner></span>
                    <i class="bi bi-stars me-1" data-refine-icon></i><?= esc(lang('Artifacts.refinement.button')) ?>
                </button>
            </div>
            <div class="form-text" data-refine-help><?= esc(lang('Artifacts.refinement.help')) ?></div>
            <div class="form-text<?= $count >= $max ? ' text-warning' : '' ?>" data-refine-count><?= esc(0 === $max
                ? lang('Artifacts.refinement.not-included', [$planName])
                : ($count >= $max ? lang('Artifacts.refinement.limit-reached', [$max, $planName, $days]) : lang('Artifacts.refinement.count', [$count, $max, $planName, $days]))) ?></div>
        </div>
    </div>
</div>
