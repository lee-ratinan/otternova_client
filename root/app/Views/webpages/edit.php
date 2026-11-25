<?php
/**
 * The editor of one page the session business designs itself (Webpages::edit()): its names and address (not the
 * home page's), its sections, each a design of the API's options.designs with its texts per language and its
 * pictures per slot, and its status. Drawn and kept by scripts/webpage_editor. The designs are previewed on the
 * storefront (/{business}/design-preview/{design_code}) in a modal; the page itself through a preview link. The AI
 * refinement card rewrites every text of the page once they are all filled in (page.text_refinement, the allowance).
 *
 * @var array $page    v1/client/webpage: the page with its sections
 * @var array $options v1/client/webpages options
 * @var array $urls    Webpages::urls()
 */
$this->extend('_layout_admin');
?>
<?= $this->section('content') ?>
<style>
    #webpage-editor [data-error-for]:empty { display: none !important; }
    #webpage-editor .wpe-sticky { position: sticky; top: 5rem; }
    #webpage-editor .wpe-design { cursor: pointer; transition: border-color .15s, box-shadow .15s; }
    #webpage-editor .wpe-design.selected { border-color: var(--bs-primary); box-shadow: 0 0 0 .2rem rgba(var(--bs-primary-rgb), .2); }
    #webpage-editor .wpe-design.disabled { cursor: not-allowed; opacity: .55; }
    #webpage-editor .wpe-thumb { aspect-ratio: 16 / 9; background: rgba(var(--bs-body-color-rgb), .08); display: flex; align-items: center; justify-content: center; overflow: hidden; border-radius: .375rem .375rem 0 0; }
    #webpage-editor .wpe-thumb img { width: 100%; height: 100%; object-fit: cover; }
    #webpage-editor .wpe-image { position: relative; width: 10rem; }
    #webpage-editor .wpe-image img { width: 100%; height: auto; border-radius: .375rem; border: 1px solid var(--bs-border-color); }
    #webpage-editor .wpe-image button { position: absolute; top: .25rem; right: .25rem; }
    #design-preview-modal iframe { width: 100%; height: 75vh; border: 0; }
</style>

<section class="section" id="webpage-editor">
    <div class="row g-4">
        <div class="col-xl-8">
            <?php // ---- the page's names and address; the home page has neither ---- ?>
            <div class="card mb-4" id="details-card">
                <div class="card-header">
                    <h5 class="card-title mb-0" id="details-title"></h5>
                    <p class="card-subtitle mb-0" id="details-subtitle"></p>
                </div>
                <div class="card-body" id="details-body"></div>
            </div>

            <div id="sections"></div>
        </div>

        <?php // ---- status, preview ---- ?>
        <div class="col-xl-4">
            <div class="wpe-sticky">
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <span class="fw-semibold"><?= lang('Webpages.list.status') ?></span>
                            <span class="badge" id="status-badge"></span>
                        </div>
                        <div class="small text-warning mb-3" id="status-over-plan" hidden><i class="bi bi-exclamation-triangle"></i> <?= lang('Webpages.list.over-plan') ?></div>
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-success" id="status-toggle"></button>
                            <button type="button" class="btn btn-outline-primary" id="page-preview"><i class="bi bi-eye"></i> <?= lang('Webpages.editor.preview') ?></button>
                            <a class="btn btn-link" id="page-live" target="_blank" rel="noopener" hidden><i class="bi bi-box-arrow-up-right"></i> <?= lang('Webpages.list.view') ?></a>
                            <a class="btn btn-link" href="<?= esc($urls['list'], 'attr') ?>"><i class="bi bi-arrow-left"></i> <?= lang('Webpages.editor.back') ?></a>
                        </div>
                        <p class="small text-muted mt-3 mb-0"><?= lang('Webpages.editor.live-note') ?></p>
                    </div>
                </div>
                <?php // ---- the AI refinement of every text of the page, drawn by the script ---- ?>
                <div class="card mb-3" id="refine-card">
                    <div class="card-body">
                        <div class="fw-semibold mb-1"><i class="bi bi-stars"></i> <?= lang('Webpages.refinement.title') ?></div>
                        <p class="small text-muted"><?= lang('Webpages.refinement.help') ?></p>
                        <div id="refine-body"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="modal fade" id="design-preview-modal" tabindex="-1" aria-labelledby="design-preview-title" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="design-preview-title"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= esc(lang('Webpages.close'), 'attr') ?>"></button>
            </div>
            <div class="modal-body p-0"><iframe id="design-preview-frame" title="<?= esc(lang('Webpages.editor.design-preview'), 'attr') ?>"></iframe></div>
        </div>
    </div>
</div>

<script>
<?= view('scripts/webpage_editor', ['page' => $page, 'options' => $options, 'urls' => $urls]) ?>
</script>
<?= $this->endSection() ?>
