<?php
/**
 * The read-only public profile of someone active in the session business (Employees::publicProfile())
 * @var array  $person       name, full_name, avatar, status_emoji, status_message, bio, role, start_date, email_address,
 *                           telephone, telephone_e164, gender, date_of_birth, nationality, language, is_self
 * @var string $businessName the session business
 */
$this->extend('_layout_admin');
$notSet  = lang('Employees.public.not-set');
$details = [
    ['icon' => 'bi-person', 'label' => lang('AuthMaster.fields.full_name'), 'value' => $person['full_name']],
    ['icon' => 'bi-briefcase', 'label' => lang('BusinessClientLink.fields.client_role'), 'value' => $person['role']],
    ['icon' => 'bi-calendar3', 'label' => lang('Employees.public.column.start_date'), 'value' => $person['start_date']],
    ['icon' => 'bi-gender-ambiguous', 'label' => lang('ClientMaster.fields.gender'), 'value' => $person['gender']],
    ['icon' => 'bi-cake2', 'label' => lang('ClientMaster.fields.date_of_birth'), 'value' => $person['date_of_birth']],
    ['icon' => 'bi-flag', 'label' => lang('ClientMaster.fields.nationality_code'), 'value' => $person['nationality']],
    ['icon' => 'bi-translate', 'label' => lang('ClientMaster.fields.preferred_language'), 'value' => $person['language']],
];
$status = trim($person['status_emoji'] . ' ' . $person['status_message']);
?>
<?= $this->section('content') ?>
<div class="page-profile">
    <div class="pf-hero">
        <div class="pf-hero-main">
            <span class="pf-avatar">
                <img src="<?= esc($person['avatar'], 'attr') ?>" alt="<?= esc($person['name'], 'attr') ?>">
            </span>
            <div class="pf-identity">
                <h1 class="pf-name"><?= esc($person['name']) ?></h1>
                <div class="pf-role"><?= esc($person['role']) ?> · <span><?= esc($businessName) ?></span></div>
                <div class="pf-meta">
                    <?php if ('' !== $status): ?>
                        <span><?= esc($status) ?></span>
                    <?php endif ?>
                    <?php if ('' !== $person['start_date']): ?>
                        <span><i class="bi bi-calendar3"></i> <?= esc(lang('Employees.public.member-since', [$person['start_date']])) ?></span>
                    <?php endif ?>
                </div>
            </div>
            <?php if ($person['is_self']): ?>
                <div class="pf-hero-actions">
                    <a class="pf-btn-light" href="<?= base_url('admin/profile') ?>"><i class="bi bi-pencil"></i> <?= lang('Employees.public.edit-own') ?></a>
                </div>
            <?php endif ?>
        </div>
    </div>
    <div class="pf-layout">
        <div class="pf-side">
            <div class="card">
                <div class="dash-card-header">
                    <div>
                        <h5 class="dash-card-title"><?= lang('Employees.public.contact') ?></h5>
                    </div>
                </div>
                <div class="card-body">
                    <div class="pf-links">
                        <a href="mailto:<?= esc($person['email_address'], 'attr') ?>" class="pf-link">
                            <span class="pf-link-icon"><i class="bi bi-envelope"></i></span>
                            <span class="pf-link-text"><?= esc($person['email_address']) ?></span>
                            <i class="bi bi-arrow-up-right pf-link-arrow"></i>
                        </a>
                        <?php if ('' !== $person['telephone']): ?>
                            <a href="tel:<?= esc($person['telephone_e164'], 'attr') ?>" class="pf-link">
                                <span class="pf-link-icon"><i class="bi bi-telephone"></i></span>
                                <span class="pf-link-text"><?= esc($person['telephone']) ?></span>
                                <i class="bi bi-arrow-up-right pf-link-arrow"></i>
                            </a>
                        <?php endif ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="card pf-main-card">
            <div class="card-body">
                <h6 class="pf-section-title"><?= lang('Employees.public.about') ?></h6>
                <p class="pf-about"><?= '' === trim($person['bio']) ? esc(lang('Employees.public.no-bio')) : nl2br(esc($person['bio'])) ?></p>

                <h6 class="pf-section-title"><?= lang('Employees.public.details') ?></h6>
                <div class="pf-detail-list">
                    <?php foreach ($details as $detail): ?>
                        <div class="pf-detail">
                            <span class="pf-detail-label"><i class="bi <?= $detail['icon'] ?>"></i> <?= esc($detail['label']) ?></span>
                            <span class="pf-detail-value"><?= esc('' === $detail['value'] ? $notSet : $detail['value']) ?></span>
                        </div>
                    <?php endforeach ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
