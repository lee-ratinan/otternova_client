<?php
/**
 * On a person's page (Staff::form()): the way to their profile page (StaffProfiles::form())
 *
 * @var string $url
 */
?>
<div class="card mt-4">
    <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <h5 class="card-title mb-1"><i class="bi bi-person-vcard"></i> <?= esc(lang('StaffProfiles.link.title')) ?></h5>
            <p class="text-muted mb-0"><?= esc(lang('StaffProfiles.link.text')) ?></p>
        </div>
        <a class="btn btn-outline-primary" href="<?= esc($url, 'attr') ?>"><?= esc(lang('StaffProfiles.link.open')) ?></a>
    </div>
</div>
