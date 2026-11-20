<?php
/**
 * The pages the session business designs itself (Webpages::index()): the list, drawn and kept by scripts/webpages,
 * with creating, publishing, previewing, reordering and deleting. The first page made is always the home page.
 *
 * @var list<array> $pages   v1/client/webpages pages: public_id, page_slug, path, page_status, is_home, publishable,
 *                           sort_order, names, image_count
 * @var array       $options v1/client/webpages options: plan, max_pages, languages, home_names, name_limits, website_ready
 * @var array       $urls    Webpages::urls()
 */
$this->extend('_layout_admin');

$maxPages = (int) ($options['max_pages'] ?? 0);
$ready    = (bool) ($options['website_ready'] ?? false);
helper('list');
$languageNames = listLanguages();
?>
<?= $this->section('content') ?>
<style>
    #webpages-page [data-error-for]:empty { display: none !important; }
    #webpages-page .wpg-order { width: 4.5rem; }
</style>

<section class="section" id="webpages-page">
    <?php if (! $ready): ?>
        <div class="alert alert-info d-flex align-items-center justify-content-between gap-3">
            <span><i class="bi bi-info-circle"></i> <?= lang('Webpages.website-first') ?></span>
            <a class="btn btn-sm btn-primary" href="<?= esc($urls['website'], 'attr') ?>"><?= lang('System.page.website') ?></a>
        </div>
    <?php elseif ($maxPages === 0): ?>
        <div class="alert alert-info d-flex align-items-center justify-content-between gap-3">
            <span><i class="bi bi-stars"></i> <?= lang('Webpages.plan-none', [ucfirst((string) ($options['plan'] ?? ''))]) ?></span>
            <?php if (isBusinessOwner()): ?>
                <a class="btn btn-sm btn-primary" href="<?= esc($urls['plans'], 'attr') ?>"><?= lang('Webpages.upgrade') ?></a>
            <?php endif ?>
        </div>
    <?php endif ?>

    <div class="card">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                <h5 class="card-title mb-0"><?= lang('Webpages.list.title') ?></h5>
                <p class="card-subtitle mb-0"><?= lang('Webpages.list.subtitle') ?></p>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="small text-muted" id="page-count"></span>
                <button type="button" class="btn btn-primary" id="page-create" aria-describedby="page-create-reason-text"<?= $ready && $maxPages > 0 ? '' : ' disabled' ?>>
                    <i class="bi bi-plus-lg"></i> <span id="page-create-label"></span>
                </button>
            </div>
        </div>
        <div class="alert alert-warning d-flex align-items-center gap-2 mx-3 mt-3 mb-0" id="page-create-reason" role="status" hidden>
            <i class="bi bi-exclamation-triangle"></i> <span id="page-create-reason-text"></span>
        </div>
        <div class="card-body">
            <p class="text-muted mb-0" id="pages-empty" hidden><?= lang('Webpages.list.empty') ?></p>
            <div class="table-responsive">
                <table class="table align-middle mb-0" id="pages-table">
                    <thead>
                    <tr>
                        <th class="wpg-order"><span class="visually-hidden"><?= lang('Webpages.list.order') ?></span></th>
                        <th><?= lang('Webpages.list.name') ?></th>
                        <th><?= lang('Webpages.list.address') ?></th>
                        <th><?= lang('Webpages.list.status') ?></th>
                        <th class="text-end"><?= lang('Webpages.list.actions') ?></th>
                    </tr>
                    </thead>
                    <tbody id="pages-body"></tbody>
                </table>
            </div>
            <p class="small text-muted mt-3 mb-0"><i class="bi bi-info-circle"></i> <?= lang('Webpages.list.help') ?></p>
        </div>
    </div>
</section>

<?php // ---- a new page: its name in each language, and its address ---- ?>
<div class="modal fade" id="create-modal" tabindex="-1" aria-labelledby="create-modal-title" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" id="create-form" novalidate>
            <div class="modal-header">
                <h5 class="modal-title" id="create-modal-title"><?= lang('Webpages.create.title') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= esc(lang('Webpages.close'), 'attr') ?>"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted"><?= lang('Webpages.create.help') ?></p>
                <?php foreach ((array) ($options['languages'] ?? []) as $i => $code): ?>
                    <div class="mb-3">
                        <label class="form-label" for="create-name-<?= esc($code, 'attr') ?>">
                            <?= lang('Webpages.create.name', [esc($languageNames[$code] ?? $code)]) ?><?= $i === 0 ? ' <span class="text-danger">*</span>' : '' ?>
                        </label>
                        <input type="text" class="form-control" id="create-name-<?= esc($code, 'attr') ?>" data-name-language="<?= esc($code, 'attr') ?>"
                               maxlength="<?= (int) ($options['name_limits']['page_name'] ?? 64) ?>" data-input-for="names.<?= $i ?>.page_name">
                        <div class="invalid-feedback d-block" data-error-for="names.<?= $i ?>.page_name"></div>
                    </div>
                <?php endforeach ?>
                <div class="invalid-feedback d-block mb-2" data-error-for="names"></div>
                <label class="form-label" for="create-slug"><?= lang('Webpages.create.slug') ?></label>
                <div class="input-group">
                    <span class="input-group-text small">/pages/</span>
                    <input type="text" class="form-control font-monospace" id="create-slug" maxlength="64" spellcheck="false" data-input-for="page_slug"
                           placeholder="<?= esc(lang('Webpages.create.slug-placeholder'), 'attr') ?>">
                </div>
                <div class="form-text"><?= lang('Webpages.create.slug-help') ?></div>
                <div class="invalid-feedback d-block" data-error-for="page_slug"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= lang('Webpages.cancel') ?></button>
                <button type="submit" class="btn btn-primary" id="create-submit"><?= lang('Webpages.create.submit') ?></button>
            </div>
        </form>
    </div>
</div>

<script>
<?= view('scripts/webpages', ['pages' => $pages, 'options' => $options, 'urls' => $urls]) ?>
</script>
<?= $this->endSection() ?>
