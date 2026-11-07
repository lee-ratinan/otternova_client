<?php
/**
 * Below a saved store's form: the way to its opening hours page (StoreHours::index())
 *
 * @var string $url
 */
?>
<div class="card mt-4">
    <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <h5 class="card-title mb-1"><i class="bi bi-clock"></i> <?= esc(lang('Stores.hours-link.title')) ?></h5>
            <p class="text-muted mb-0"><?= esc(lang('Stores.hours-link.text')) ?></p>
        </div>
        <a class="btn btn-outline-primary" href="<?= esc($url, 'attr') ?>"><?= esc(lang('Stores.hours-link.open')) ?></a>
    </div>
</div>
