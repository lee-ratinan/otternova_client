<?php
/**
 * How the session business's web pages look (Website::index()). The cards are filled and kept in step by
 * scripts/website from $settings; one button saves the languages, colours and featured products together, while the
 * logo is uploaded and removed on its own. The preview beside them shows the main page's top as it will look.
 *
 * @var array $settings v1/client/website: colors, logo, languages, featured, options
 * @var array $urls     save, logoUpload, logoDelete
 */
$this->extend('_layout_admin');

$colorFields = ['background_color', 'default_color', 'heading_color', 'accent_color', 'surface_color', 'contrast_color'];
$colors      = (array) ($settings['colors'] ?? []);
$logo        = $settings['logo'] ?? null;
$maxFeatured = (int) ($settings['options']['max_featured'] ?? 3);
?>
<?= $this->section('content') ?>
<style>
    #website-page .wp-swatch { width: 3rem; height: 2.375rem; padding: .25rem; }
    #website-page .wp-checker { background: repeating-conic-gradient(#e9ecef 0% 25%, #fff 0% 50%) 50% / 16px 16px; }
    #website-page .wp-logo-box { min-height: 9rem; }
    #website-page .wp-logo-box img { max-height: 9rem; max-width: 100%; }
    #website-page .wp-preview { border-radius: .5rem; overflow: hidden; border: 1px solid var(--bs-border-color); }
    #website-page .wp-preview-nav { display: flex; align-items: center; gap: 1rem; padding: .75rem 1rem; font-size: .85rem; }
    #website-page .wp-preview-nav img { height: 1.75rem; width: auto; }
    #website-page .wp-preview-nav .wp-links { margin-left: auto; display: flex; gap: .875rem; }
    #website-page .wp-preview-hero { padding: 1.75rem 1rem 1.5rem; }
    #website-page .wp-preview-badge { display: inline-block; font-size: .7rem; letter-spacing: .04em; text-transform: uppercase; padding: .25rem .625rem; border-radius: 2rem; margin-bottom: .75rem; }
    #website-page .wp-preview-hero h2 { font-size: 1.5rem; line-height: 1.2; margin: 0 0 .5rem; overflow-wrap: anywhere; }
    #website-page .wp-preview-hero p { font-size: .875rem; margin: 0 0 1rem; overflow-wrap: anywhere; }
    #website-page .wp-preview-button { display: inline-block; padding: .4rem 1rem; border-radius: .375rem; font-size: .8rem; }
    #website-page .wp-preview-cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: .5rem; padding: 0 1rem 1.25rem; }
    #website-page .wp-preview-cards div { height: 3rem; border-radius: .375rem; }
    #website-page .wp-sticky { position: sticky; top: 5rem; }
    #website-page .wp-lang-handle { width: 2rem; }
    #website-page [data-error-for]:empty { display: none !important; }
</style>

<section class="section" id="website-page">
    <div class="row g-4">
        <div class="col-xl-8">

            <?php // ---- 1. languages ---- ?>
            <div class="card mb-4" id="languages-card">
                <div class="card-header">
                    <h5 class="card-title"><?= lang('Website.languages.title') ?></h5>
                    <p class="card-subtitle"><?= lang('Website.languages.subtitle') ?></p>
                </div>
                <div class="card-body">
                    <ul class="list-group mb-3" id="language-list"></ul>
                    <div class="invalid-feedback d-block mb-2" data-error-for="languages"></div>
                    <div class="input-group" id="language-add-group">
                        <label class="input-group-text" for="language-add"><i class="bi bi-translate"></i></label>
                        <select class="form-select" id="language-add" aria-label="<?= esc(lang('Website.languages.add-placeholder'), 'attr') ?>"></select>
                        <button type="button" class="btn btn-outline-primary" id="language-add-button"><i class="bi bi-plus-lg"></i> <?= lang('Website.languages.add') ?></button>
                    </div>
                    <p class="form-text mb-0 d-none" id="language-all-added"><?= lang('Website.languages.all-added') ?></p>
                </div>
            </div>

            <?php // ---- 2, 3, 5. what each language has ---- ?>
            <div class="card mb-4" id="content-card">
                <div class="card-header">
                    <h5 class="card-title"><?= lang('Website.content.title') ?></h5>
                    <p class="card-subtitle"><?= lang('Website.content.subtitle') ?></p>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-0" id="content-empty"><?= lang('Website.content.none-yet') ?></p>
                    <ul class="nav nav-tabs mb-3" id="content-tabs" role="tablist"></ul>
                    <div id="content-panes"></div>
                </div>
            </div>

            <?php // ---- 4. colours ---- ?>
            <div class="card mb-4" id="colors-card">
                <div class="card-header">
                    <h5 class="card-title"><?= lang('Website.colors.title') ?></h5>
                    <p class="card-subtitle"><?= lang('Website.colors.subtitle') ?></p>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <?php foreach ($colorFields as $field): ?>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold mb-1" for="color-<?= $field ?>-text"><?= lang('Website.colors.' . $field) ?></label>
                                <div class="input-group">
                                    <input type="color" class="form-control form-control-color wp-swatch" data-color="<?= $field ?>" value="<?= esc((string) ($colors[$field] ?? '#000000'), 'attr') ?>"
                                           aria-label="<?= esc(lang('Website.colors.' . $field), 'attr') ?>">
                                    <input type="text" class="form-control font-monospace" id="color-<?= $field ?>-text" data-color-text="<?= $field ?>" maxlength="7" spellcheck="false"
                                           value="<?= esc((string) ($colors[$field] ?? ''), 'attr') ?>" pattern="#[0-9a-fA-F]{6}">
                                </div>
                                <div class="form-text"><?= lang('Website.colors.' . $field . '-help') ?></div>
                                <div class="invalid-feedback d-block" data-error-for="colors.<?= $field ?>"></div>
                            </div>
                        <?php endforeach ?>
                    </div>
                    <button type="button" class="btn btn-link px-0 mt-3" id="colors-reset"><i class="bi bi-arrow-counterclockwise"></i> <?= lang('Website.colors.reset') ?></button>
                </div>
            </div>

            <?php // ---- 6. logo ---- ?>
            <div class="card mb-4" id="logo-card">
                <div class="card-header">
                    <h5 class="card-title"><?= lang('Website.logo.title') ?></h5>
                    <p class="card-subtitle"><?= lang('Website.logo.subtitle') ?></p>
                </div>
                <div class="card-body">
                    <div class="row g-3 align-items-center">
                        <div class="col-md-6">
                            <div class="wp-checker wp-logo-box rounded border d-flex align-items-center justify-content-center p-2">
                                <img id="logo-image" alt="<?= esc(lang('Website.logo.title'), 'attr') ?>" src="<?= esc((string) ($logo['url'] ?? ''), 'attr') ?>"<?= null === $logo ? ' hidden' : '' ?>>
                                <span class="text-muted small" id="logo-none"<?= null === $logo ? '' : ' hidden' ?>><?= lang('Website.logo.none') ?></span>
                            </div>
                            <p class="small text-muted mt-1 mb-0" id="logo-shape"></p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="logo-file"><?= lang('Website.logo.choose') ?></label>
                            <input class="form-control" type="file" id="logo-file" accept="image/png,image/jpeg,image/webp">
                            <div class="form-text"><?= lang('Website.logo.help') ?></div>
                            <div class="d-flex gap-2 mt-3">
                                <button type="button" class="btn btn-primary" id="logo-upload" disabled><i class="bi bi-upload"></i> <?= lang('Website.logo.upload') ?></button>
                                <button type="button" class="btn btn-outline-danger" id="logo-delete"<?= null === $logo ? ' hidden' : '' ?>><i class="bi bi-trash"></i> <?= lang('Website.logo.remove') ?></button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php // ---- 7. featured products and services ---- ?>
            <div class="card mb-4" id="featured-card">
                <div class="card-header">
                    <h5 class="card-title"><?= lang('Website.featured.title') ?></h5>
                    <p class="card-subtitle"><?= lang('Website.featured.subtitle', [$maxFeatured]) ?></p>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning small d-none" id="featured-inactive"></div>
                    <p class="text-muted d-none" id="featured-no-artifacts"><?= lang('Website.featured.no-artifacts') ?></p>
                    <div class="row g-4">
                        <?php foreach (['best_sellers' => 'bi-fire', 'trending' => 'bi-graph-up-arrow'] as $section => $icon): ?>
                            <div class="col-md-6">
                                <h6 class="fw-semibold"><i class="bi <?= $icon ?>"></i> <?= lang('Website.featured.' . $section) ?></h6>
                                <?php for ($i = 0; $i < $maxFeatured; $i++): ?>
                                    <div class="input-group input-group-sm mb-2">
                                        <span class="input-group-text"><?= $i + 1 ?></span>
                                        <select class="form-select" data-featured="<?= $section ?>" data-place="<?= $i ?>"
                                                aria-label="<?= esc(lang('Website.featured.' . $section) . ' ' . ($i + 1), 'attr') ?>"></select>
                                    </div>
                                <?php endfor ?>
                                <div class="invalid-feedback d-block" data-error-for="featured.<?= $section ?>"></div>
                            </div>
                        <?php endforeach ?>
                    </div>
                    <p class="small text-muted mt-3 mb-0"><i class="bi bi-star-half"></i> <?= lang('Website.featured.highest-rated-note') ?></p>
                </div>
            </div>
        </div>

        <?php // ---- preview and save ---- ?>
        <div class="col-xl-4">
            <div class="wp-sticky">
                <div class="card mb-3">
                    <div class="card-header d-flex align-items-center justify-content-between gap-2">
                        <h5 class="card-title mb-0"><?= lang('Website.preview.title') ?></h5>
                        <select class="form-select form-select-sm w-auto" id="preview-language" aria-label="<?= esc(lang('Website.preview.language'), 'attr') ?>"></select>
                    </div>
                    <div class="card-body">
                        <div class="wp-preview" id="preview">
                            <div class="wp-preview-nav" data-preview="nav">
                                <img alt="" data-preview="logo" hidden>
                                <strong data-preview="brand"><?= esc((string) (currentBusinessName() ?? '')) ?></strong>
                                <span class="wp-links"><span><?= lang('Website.preview.nav-home') ?></span><span><?= lang('Website.preview.nav-shop') ?></span><span><?= lang('Website.preview.nav-contact') ?></span></span>
                            </div>
                            <div class="wp-preview-hero">
                                <span class="wp-preview-badge" data-preview="badge"></span>
                                <h2 data-preview="headline"></h2>
                                <p data-preview="paragraph"></p>
                                <span class="wp-preview-button" data-preview="button"><?= lang('Website.preview.button') ?></span>
                            </div>
                            <div class="wp-preview-cards"><div data-preview="surface"></div><div data-preview="surface"></div><div data-preview="surface"></div></div>
                        </div>
                        <p class="small text-muted mt-2 mb-0"><?= lang('Website.preview.help') ?></p>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body d-flex align-items-center gap-3">
                        <button type="button" class="btn btn-primary" id="website-save"><i class="bi bi-check-lg"></i> <?= lang('Website.save') ?></button>
                        <span class="small text-muted" id="website-dirty" hidden><i class="bi bi-dot"></i><?= lang('Website.unsaved') ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
<?= view('scripts/website', ['settings' => $settings, 'urls' => $urls]) ?>
</script>
<?= $this->endSection() ?>
