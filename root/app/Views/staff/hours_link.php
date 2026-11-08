<?php
/**
 * On a person's page (Staff::form()): the way to their working hours page (Staff::hours())
 *
 * @var string $url
 */
?>
<div class="card mt-4">
    <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <h5 class="card-title mb-1"><i class="bi bi-calendar-week"></i> <?= esc(lang('StaffHours.link.title')) ?></h5>
            <p class="text-muted mb-0"><?= esc(lang('StaffHours.link.text')) ?></p>
        </div>
        <a class="btn btn-outline-primary" href="<?= esc($url, 'attr') ?>"><?= esc(lang('StaffHours.link.open')) ?></a>
    </div>
</div>
