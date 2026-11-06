<?php
/**
 * The images card below the artifact form. scripts/artifact_form fills the grid and handles the upload, delete and
 * reorder; a new artifact has to be saved first.
 *
 * @var string|null $publicId artifact_master.public_id; null for a new one
 * @var array       $labels   variant public_id => label, for the "Shown For" dropdown
 */
?>
<div class="card mt-4" id="artifact-images-card">
    <div class="card-header">
        <h5 class="card-title"><?= esc(lang('Artifacts.images.title')) ?></h5>
        <p class="card-subtitle"><?= esc(lang('Artifacts.images.subtitle')) ?></p>
    </div>
    <div class="card-body">
        <?php if (null === $publicId): ?>
            <p class="text-muted mb-0"><?= esc(lang('Artifacts.images.save-first')) ?></p>
        <?php else: ?>
            <div class="row g-3 mb-3" data-images-grid></div>
            <p class="text-muted d-none" data-images-empty><?= esc(lang('Artifacts.images.empty')) ?></p>
            <form class="row g-2 align-items-end" data-images-upload novalidate>
                <div class="col-md-5">
                    <label class="form-label" for="artifact-image-file"><?= esc(lang('Artifacts.images.image')) ?></label>
                    <input type="file" class="form-control" id="artifact-image-file" name="image" accept="image/jpeg,image/png,image/webp">
                </div>
                <div class="col-md-5">
                    <label class="form-label" for="artifact-image-variant"><?= esc(lang('Artifacts.images.variant')) ?></label>
                    <select class="form-select" id="artifact-image-variant" name="variant_public_id">
                        <option value=""><?= esc(lang('Artifacts.images.whole')) ?></option>
                        <?php foreach ($labels as $variantId => $label): ?>
                            <option value="<?= esc((string) $variantId, 'attr') ?>"><?= esc($label) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="col-md-2 d-grid">
                    <button type="submit" class="btn btn-primary" data-images-submit>
                        <i class="bi bi-upload"></i> <?= esc(lang('Artifacts.images.upload')) ?>
                    </button>
                </div>
            </form>
            <div class="form-text" data-images-count></div>
        <?php endif ?>
    </div>
</div>
