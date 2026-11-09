<?php
/**
 * The notification centre: every notification of the signed-in client, newest first, from all their businesses;
 * see App\Controllers\Notifications. Filled by assets/js/notification-center.js, which the layout loads.
 */
$this->extend('_layout_admin');
?>
<?= $this->section('content') ?>
    <section class="section">
        <div class="card" data-notification-page>
            <div class="card-header d-flex flex-wrap align-items-center gap-2">
                <div class="me-auto">
                    <h5 class="card-title"><?= lang('Notifications.all-title') ?></h5>
                    <p class="card-subtitle" data-notification-summary>&nbsp;</p>
                </div>
                <div class="btn-group btn-group-sm" role="group" aria-label="<?= esc(lang('Notifications.show'), 'attr') ?>">
                    <button type="button" class="btn btn-outline-primary active" data-notification-status=""><?= lang('Notifications.all') ?></button>
                    <button type="button" class="btn btn-outline-primary" data-notification-status="unread"><?= lang('Notifications.unread-only') ?></button>
                    <button type="button" class="btn btn-outline-primary" data-notification-status="read"><?= lang('Notifications.read-only') ?></button>
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary" data-notification-action="mark-all-read">
                    <i class="bi bi-check2-all"></i> <?= lang('Notifications.mark-all-read') ?>
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-notification-action="delete-read">
                    <i class="bi bi-trash"></i> <?= lang('Notifications.delete-read') ?>
                </button>
            </div>
            <div class="card-body p-0">
                <div data-notification-rows></div>
                <div class="text-center p-3">
                    <button type="button" class="btn btn-sm btn-outline-primary d-none" data-notification-more><?= lang('Notifications.load-more') ?></button>
                </div>
            </div>
            <div class="card-footer small text-secondary">
                <?= lang('Notifications.auto-delete') ?>
            </div>
        </div>
    </section>
<?php $this->endSection() ?>
