<?php
/**
 * The people active in the session business (Employees::publicProfiles()): a grid of their photos, the name under
 * each, each opening their public profile
 *
 * @var list<array{name: string, avatar: string, url: string}> $people
 * @var string|null $error        why the people could not be loaded
 * @var string      $businessName the session business
 */
$this->extend('_layout_admin');
?>
<?= $this->section('content') ?>
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0"><?= lang('Employees.public.title') ?></h5>
        <small class="text-muted"><?= esc(lang('Employees.public.subtitle', [$businessName])) ?></small>
    </div>
    <div class="card-body">
        <?php if (null !== $error): ?>
            <div class="alert alert-danger mb-0"><?= esc($error) ?></div>
        <?php else: ?>
            <div class="row row-cols-2 row-cols-sm-3 row-cols-md-4 row-cols-lg-6 g-4">
                <?php foreach ($people as $person): ?>
                    <div class="col">
                        <a href="<?= esc($person['url'], 'attr') ?>" class="d-block text-center text-decoration-none text-reset">
                            <img src="<?= esc($person['avatar'], 'attr') ?>" alt="<?= esc($person['name'], 'attr') ?>"
                                 class="rounded-circle mb-2" width="96" height="96" style="object-fit: cover">
                            <div class="fw-semibold text-break"><?= esc($person['name']) ?></div>
                        </a>
                    </div>
                <?php endforeach ?>
            </div>
        <?php endif ?>
    </div>
</div>
<?= $this->endSection() ?>