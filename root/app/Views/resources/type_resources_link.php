<?php
/**
 * On a resource type's page (Resources::typeForm()): the way to the resources of that type
 *
 * @var string $url   the resources page, narrowed to the type
 * @var int    $count how many resources the type has
 */
?>
<div class="card mt-4">
    <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <h5 class="card-title mb-1"><i class="bi bi-door-open"></i> <?= esc(lang('Resources.type-link.title')) ?></h5>
            <p class="text-muted mb-0"><?= esc(lang('Resources.type-link.text', [$count])) ?></p>
        </div>
        <a class="btn btn-outline-primary" href="<?= esc($url, 'attr') ?>"><?= esc(lang('Resources.type-link.open')) ?></a>
    </div>
</div>
