<?php
/**
 * One group session or private appointment of the session business (Calendar::session()): what it is, its times
 * (each scheduled slot with its resources and people), the colleagues who work it, and who booked it
 *
 * @var array  $session v1/client/calendar/session's session
 * @var string $back    the calendar day to go back to
 * @var string $locale
 */
$this->extend('_layout_admin');

$isGroup  = 'G' === ($session['kind'] ?? '');
$kinds    = (array) lang('Calendar.kind');
$statuses = (array) lang('ServiceSessions.enum.status');
$status   = (string) ($session['session_status'] ?? '');
$slots    = (array) ($session['slots'] ?? []);
$bookings = (array) ($session['bookings'] ?? []);
$staff    = (array) ($session['staff'] ?? []);
$dateOf   = static fn (?string $local): string => '' === (string) $local ? '' : (string) formatDate(substr((string) $local, 0, 10), $locale);
$timeOf   = static fn (?string $local): string => substr((string) $local, 11, 5);
$person   = static function (array $person): string {
    return '<span class="text-nowrap"><a href="' . esc(base_url('admin/public-profile/' . rawurlencode((string) $person['uid'])), 'attr') . '" class="text-decoration-none text-reset">'
        . '<img src="' . esc(avatarUrl($person['avatar_path'] ?? null), 'attr') . '" alt="" class="rounded-circle me-1" width="24" height="24" style="object-fit: cover">'
        . esc((string) $person['name']) . '</a>'
        . (! empty($person['is_me']) ? ' <span class="badge bg-primary">' . esc(lang('Calendar.you')) . '</span>' : '') . '</span>';
};
?>
<?= $this->section('content') ?>
<section class="section" id="calendar-session">
    <div class="mb-3 d-none">
        <a href="<?= esc($back, 'attr') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> <?= esc(lang('Calendar.session.back')) ?></a>
    </div>
    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header"><h5 class="card-title mb-0"><?= esc(lang('Calendar.session.details')) ?></h5></div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-5"><?= esc(lang('Calendar.session.kind')) ?></dt>
                        <dd class="col-7">
                            <span class="badge <?= $isGroup ? 'bg-info text-dark' : 'bg-success' ?>">
                                <i class="bi <?= $isGroup ? 'bi-people' : 'bi-person' ?>"></i> <?= esc((string) ($kinds[$session['kind'] ?? ''] ?? '')) ?>
                            </span>
                        </dd>
                        <dt class="col-5"><?= esc(lang('Calendar.session.service')) ?></dt>
                        <dd class="col-7">
                            <?= esc((string) ($session['service_name'] ?? '')) ?>
                            <?php if (! empty($session['variant_label'])) : ?>
                                <div class="small text-muted"><?= esc((string) $session['variant_label']) ?></div>
                            <?php endif; ?>
                        </dd>
                        <dt class="col-5"><?= esc(lang('Calendar.session.store')) ?></dt>
                        <dd class="col-7"><?= esc((string) ($session['store']['store_name'] ?? '')) ?></dd>
                        <?php if ($isGroup) : ?>
                            <dt class="col-5"><?= esc(lang('Calendar.session.status')) ?></dt>
                            <dd class="col-7"><?= esc((string) ($statuses[$status] ?? $status)) ?></dd>
                        <?php endif; ?>
                        <dt class="col-5"><?= esc(lang('Calendar.session.seats')) ?></dt>
                        <dd class="col-7"><?= esc(lang('Calendar.participants', [(int) ($session['seats_booked'] ?? 0), (int) ($session['capacity'] ?? 0)])) ?></dd>
                    </dl>
                    <?php if (! empty($session['session_description'])) : ?>
                        <hr>
                        <p class="mb-0 small" style="white-space: pre-line"><?= esc((string) $session['session_description']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header"><h5 class="card-title mb-0"><i class="bi bi-clock"></i> <?= esc(lang('Calendar.session.times')) ?></h5></div>
                <div class="card-body">
                    <?php if ([] === $slots) : ?>
                        <p class="text-muted mb-0"><?= esc(lang('Calendar.session.no-slots')) ?></p>
                    <?php else : ?>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                <tr>
                                    <?php if (count($slots) > 1) : ?><th>#</th><?php endif; ?>
                                    <th><?= esc(lang('Calendar.session.date')) ?></th>
                                    <th><?= esc(lang('Calendar.session.time')) ?></th>
                                    <th><?= esc(lang('Calendar.session.resources')) ?></th>
                                    <th><?= esc(lang('Calendar.session.staff')) ?></th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($slots as $slot) :
                                    $sameDay = substr((string) $slot['local_starts_at'], 0, 10) === substr((string) $slot['local_ends_at'], 0, 10);
                                    ?>
                                    <tr>
                                        <?php if (count($slots) > 1) : ?><td><?= (int) $slot['slot_number'] ?></td><?php endif; ?>
                                        <td class="text-nowrap"><?= esc($dateOf($slot['local_starts_at'] ?? null)) ?></td>
                                        <td class="text-nowrap fw-semibold">
                                            <?= esc($timeOf($slot['local_starts_at'] ?? null)) ?> –
                                            <?= esc(($sameDay ? '' : $dateOf($slot['local_ends_at'] ?? null) . ' ') . $timeOf($slot['local_ends_at'] ?? null)) ?>
                                        </td>
                                        <td>
                                            <?php foreach ((array) ($slot['resources'] ?? []) as $resource) : ?>
                                                <div class="text-nowrap"><?= esc((string) $resource['resource_name']) ?>
                                                    <span class="small text-muted"><?= esc((string) $resource['type_name']) ?></span></div>
                                            <?php endforeach; ?>
                                            <?php if ([] === (array) ($slot['resources'] ?? [])) : ?>
                                                <span class="small text-muted"><?= esc(lang('Calendar.no-resource')) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php foreach ((array) ($slot['staff'] ?? []) as $member) : ?>
                                                <div><?= $person($member) ?></div>
                                            <?php endforeach; ?>
                                            <?php if ([] === (array) ($slot['staff'] ?? [])) : ?>
                                                <span class="small text-muted"><?= esc(lang('Calendar.no-staff')) ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header"><h5 class="card-title mb-0"><i class="bi bi-person-badge"></i> <?= esc(lang('Calendar.session.colleagues')) ?></h5></div>
                <div class="card-body">
                    <?php if ([] === $staff) : ?>
                        <p class="text-muted mb-0"><?= esc(lang('Calendar.no-staff')) ?></p>
                    <?php else : ?>
                        <ul class="list-unstyled mb-0">
                            <?php foreach ($staff as $member) : ?>
                                <li class="mb-2"><?= $person($member) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header"><h5 class="card-title mb-0"><i class="bi bi-people"></i> <?= esc(lang('Calendar.session.participants')) ?></h5></div>
                <div class="card-body">
                    <?php if ([] === $bookings) : ?>
                        <p class="text-muted mb-0"><?= esc(lang('Calendar.session.no-bookings')) ?></p>
                    <?php else : ?>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                <tr>
                                    <th><?= esc(lang('Calendar.session.order')) ?></th>
                                    <th><?= esc(lang('Calendar.session.booked-by')) ?></th>
                                    <?php if ($isGroup) : ?><th class="text-end"><?= esc(lang('Calendar.session.seats-booked')) ?></th><?php endif; ?>
                                    <th><?= esc(lang('Calendar.session.names')) ?></th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($bookings as $booking) : ?>
                                    <tr>
                                        <td class="text-nowrap">
                                            <a href="<?= esc(base_url('admin/sales/order/' . rawurlencode((string) $booking['order_public_id'])), 'attr') ?>">
                                                <?= esc((string) ($booking['order_number'] ?? $booking['order_public_id'])) ?>
                                            </a>
                                            <?php if (! empty($booking['no_show'])) : ?>
                                                <span class="badge bg-dark"><?= esc(lang('Calendar.no-show')) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= esc((string) $booking['name']) ?></td>
                                        <?php if ($isGroup) : ?><td class="text-end"><?= (int) $booking['quantity'] ?></td><?php endif; ?>
                                        <td><?= esc(implode(', ', (array) ($booking['participants'] ?? []))) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>
<?= $this->endSection() ?>
