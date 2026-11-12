<?php
/**
 * The restrictions form of a person's profile page, view mode: what customers are told before booking the person
 *
 * @var list<string> $notices from v1/client/staff/profile, in the UI's language
 */
?>
<div class="row mb-3">
    <div class="col-12">
        <h6 class="mt-2"><i class="bi bi-chat-square-text"></i> <?= esc(lang('StaffProfiles.restrictions.notices-title')) ?></h6>
        <?php if ([] === $notices): ?>
            <p class="text-muted mb-0"><?= esc(lang('StaffProfiles.restrictions.notices-none')) ?></p>
        <?php else: ?>
            <div class="alert alert-warning mb-0">
                <?php foreach ($notices as $notice): ?>
                    <div><i class="bi bi-exclamation-triangle"></i> <?= esc($notice) ?></div>
                <?php endforeach ?>
            </div>
            <p class="form-text"><?= esc(lang('StaffProfiles.restrictions.notices-help')) ?></p>
        <?php endif ?>
    </div>
</div>
