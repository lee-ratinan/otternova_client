<?php
/**
 * The session business's social media accounts (SocialMedia::index()). The list is drawn and kept by
 * scripts/social_media from $links; a platform is added with the row under it, and one button saves the list.
 *
 * @var array $links     v1/client/social-links links: [{public_id, platform, profile_url, sort_order}], in order
 * @var array $platforms the platforms the business may add: { platform: name }
 * @var array $urls      save
 */
$this->extend('_layout_admin');
?>
<?= $this->section('content') ?>
<style>
    #social-media-page .sm-icon { width: 2.5rem; justify-content: center; font-size: 1.1rem; }
    #social-media-page [data-error-for]:empty { display: none !important; }
</style>

<section class="section" id="social-media-page">
    <div class="row g-4">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title"><?= lang('SocialMedia.title') ?></h5>
                    <p class="card-subtitle"><?= lang('SocialMedia.subtitle', [esc((string) (currentBusinessName() ?? ''))]) ?></p>
                </div>
                <div class="card-body">
                    <p class="text-muted" id="social-empty"><?= lang('SocialMedia.none-yet') ?></p>
                    <ul class="list-group mb-3" id="social-list"></ul>
                    <div class="invalid-feedback d-block mb-2" data-error-for="links"></div>

                    <h6 class="fw-semibold mt-4" id="social-add-heading"><?= lang('SocialMedia.add-title') ?></h6>
                    <div class="row g-2 align-items-start" id="social-add-group">
                        <div class="col-md-4">
                            <label class="visually-hidden" for="social-add-platform"><?= lang('SocialMedia.platform') ?></label>
                            <select class="form-select" id="social-add-platform"></select>
                        </div>
                        <div class="col-md-6">
                            <label class="visually-hidden" for="social-add-url"><?= lang('SocialMedia.url') ?></label>
                            <input type="url" class="form-control" id="social-add-url" maxlength="255" autocomplete="off" spellcheck="false" inputmode="url">
                            <div class="invalid-feedback" id="social-add-error"></div>
                        </div>
                        <div class="col-md-2 d-grid">
                            <button type="button" class="btn btn-outline-primary" id="social-add-button"><i class="bi bi-plus-lg"></i> <?= lang('SocialMedia.add') ?></button>
                        </div>
                    </div>
                    <p class="form-text mb-0"><?= lang('SocialMedia.url-help') ?></p>
                    <p class="form-text mb-0 d-none" id="social-all-added"><?= lang('SocialMedia.all-added') ?></p>
                </div>
                <div class="card-footer d-flex align-items-center gap-3">
                    <button type="button" class="btn btn-primary" id="social-save"><i class="bi bi-check-lg"></i> <?= lang('SocialMedia.save') ?></button>
                    <span class="small text-muted" id="social-dirty" hidden><i class="bi bi-dot"></i><?= lang('SocialMedia.unsaved') ?></span>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
<?= view('scripts/social_media', ['links' => $links, 'platforms' => $platforms, 'urls' => $urls]) ?>
</script>
<?= $this->endSection() ?>
