<?php
/**
 * The tags of the session business (Tags::index()). The tags are drawn and kept by scripts/tags from $tags; one is
 * added by typing it (with suggestions of the tags other businesses use), and one button saves them.
 *
 * @var list<string> $tags    the business's tag names
 * @var array        $options v1/client/tags options: max_tags, max_length
 * @var array        $urls    save, suggest
 */
$this->extend('_layout_admin');
?>
<?= $this->section('content') ?>
<style>
    #tags-page .tg-chip { display: inline-flex; align-items: center; gap: .375rem; padding: .375rem .5rem .375rem .75rem; border-radius: 2rem;
        background: var(--bs-secondary-bg); border: 1px solid var(--bs-border-color); font-size: .9rem; }
    #tags-page .tg-chip.is-invalid { border-color: var(--bs-danger); }
    #tags-page .tg-chip button { border: 0; background: none; padding: 0 .125rem; line-height: 1; color: var(--bs-secondary-color); }
    #tags-page .tg-chip button:hover { color: var(--bs-danger); }
    #tags-page .tg-suggest { position: absolute; z-index: 10; left: 0; right: 0; top: 100%; max-height: 18rem; overflow-y: auto; }
    #tags-page [data-error-for]:empty { display: none !important; }
</style>

<section class="section" id="tags-page">
    <div class="row g-4">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title"><?= lang('Tags.title') ?></h5>
                    <p class="card-subtitle"><?= lang('Tags.subtitle', [esc((string) (currentBusinessName() ?? ''))]) ?></p>
                </div>
                <div class="card-body">
                    <p class="text-muted" id="tags-empty"><?= lang('Tags.none-yet') ?></p>
                    <div class="d-flex flex-wrap gap-2 mb-2" id="tags-list" aria-live="polite"></div>
                    <div class="invalid-feedback d-block mb-2" data-error-for="tags"></div>

                    <label class="form-label fw-semibold mt-3" for="tags-input"><?= lang('Tags.add-title') ?></label>
                    <div class="position-relative" id="tags-add-group">
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-tag"></i></span>
                            <input type="text" class="form-control" id="tags-input" maxlength="<?= (int) ($options['max_length'] ?? 50) ?>" autocomplete="off"
                                   placeholder="<?= esc(lang('Tags.placeholder'), 'attr') ?>" role="combobox" aria-expanded="false" aria-controls="tags-suggest" aria-autocomplete="list">
                            <button type="button" class="btn btn-outline-primary" id="tags-add-button"><i class="bi bi-plus-lg"></i> <?= lang('Tags.add') ?></button>
                        </div>
                        <div class="list-group shadow-sm tg-suggest" id="tags-suggest" role="listbox" hidden></div>
                    </div>
                    <div class="invalid-feedback d-block" id="tags-add-error"></div>
                    <p class="form-text mb-0"><?= lang('Tags.help') ?></p>
                    <p class="form-text mb-0" id="tags-count"></p>
                </div>
                <div class="card-footer d-flex align-items-center gap-3">
                    <button type="button" class="btn btn-primary" id="tags-save"><i class="bi bi-check-lg"></i> <?= lang('Tags.save') ?></button>
                    <span class="small text-muted" id="tags-dirty" hidden><i class="bi bi-dot"></i><?= lang('Tags.unsaved') ?></span>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card">
                <div class="card-body">
                    <h6 class="fw-semibold"><i class="bi bi-lightbulb"></i> <?= lang('Tags.tips-title') ?></h6>
                    <p class="small text-muted mb-0"><?= lang('Tags.tips') ?></p>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
<?= view('scripts/tags', ['tags' => $tags, 'options' => $options, 'urls' => $urls]) ?>
</script>
<?= $this->endSection() ?>
