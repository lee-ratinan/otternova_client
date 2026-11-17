<?php
/**
 * The bookings of the session business on a day (Calendar::index()): a date filter, then one row per scheduled slot
 * of a group session or private appointment, by start time, with its resources, its people and its customers
 * (names for an appointment, a head count for a group), each opening the session (calendar/session)
 *
 * @var string      $date         'Y-m-d', the day shown
 * @var bool        $mine         only the bookings the signed-in person works
 * @var int         $mineCount    of the day's bookings, those they work
 * @var int         $total        the day's bookings
 * @var list<array> $entries      v1/client/calendar's entries
 * @var string|null $error        why the day could not be loaded
 * @var string      $businessName the session business
 * @var string      $locale
 */
$this->extend('_layout_admin');

$day      = new DateTimeImmutable($date);
$link     = static fn (string $to, ?bool $onlyMine = null): string => base_url('admin/calendar') . '?' . http_build_query(array_filter(['date' => $to, 'mine' => ($onlyMine ?? $mine) ? '1' : null]));
$time     = static function (?string $local) use ($date, $locale): string {
    $local = (string) $local;
    if ('' === $local) {
        return '';
    }
    // a booking running over midnight shows the other day's date
    return (substr($local, 0, 10) === $date ? '' : formatDate(substr($local, 0, 10), $locale) . ' ') . substr($local, 11, 5);
};
$kinds    = (array) lang('Calendar.kind');
$statuses = (array) lang('ServiceSessions.enum.status');
?>
<?= $this->section('content') ?>
<div class="card" id="calendar-day">
    <div class="card-header">
        <h5 class="card-title mb-0"><?= esc(lang('Calendar.day.title')) ?> · <?= esc(formatDate($date, $locale)) ?></h5>
        <small class="text-muted"><?= esc(lang('Calendar.day.subtitle', [$businessName])) ?></small>
    </div>
    <div class="card-body">
        <form method="get" action="<?= base_url('admin/calendar') ?>" class="row g-2 align-items-end mb-3">
            <div class="col-12 col-sm-auto">
                <label for="calendar-date" class="form-label small mb-1"><?= esc(lang('Calendar.day.date')) ?></label>
                <input type="date" class="form-control" id="calendar-date" name="date" value="<?= esc($date, 'attr') ?>" required onchange="this.form.submit()">
            </div>
            <?php if ($mine) : ?>
                <input type="hidden" name="mine" value="1">
            <?php endif; ?>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary"><?= esc(lang('Calendar.day.go')) ?></button>
            </div>
            <div class="col-auto btn-group" role="group">
                <a class="btn btn-outline-secondary" href="<?= esc($link($day->modify('-1 day')->format('Y-m-d')), 'attr') ?>" title="<?= esc(lang('Calendar.day.previous'), 'attr') ?>"
                   aria-label="<?= esc(lang('Calendar.day.previous'), 'attr') ?>"><i class="bi bi-chevron-left"></i></a>
                <a class="btn btn-outline-secondary" href="<?= esc(base_url('admin/calendar') . ($mine ? '?mine=1' : ''), 'attr') ?>"><?= esc(lang('Calendar.day.today')) ?></a>
                <a class="btn btn-outline-secondary" href="<?= esc($link($day->modify('+1 day')->format('Y-m-d')), 'attr') ?>" title="<?= esc(lang('Calendar.day.next'), 'attr') ?>"
                   aria-label="<?= esc(lang('Calendar.day.next'), 'attr') ?>"><i class="bi bi-chevron-right"></i></a>
            </div>
            <div class="col-auto ms-sm-auto">
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" role="switch" id="calendar-mine" <?= $mine ? 'checked' : '' ?>
                           onchange="window.location.href = this.checked ? <?= esc(json_encode($link($date, true)), 'attr') ?> : <?= esc(json_encode($link($date, false)), 'attr') ?>">
                    <label class="form-check-label" for="calendar-mine"><?= esc(lang('Calendar.day.only-mine')) ?></label>
                </div>
            </div>
        </form>

        <?php if (null !== $error) : ?>
            <div class="alert alert-danger mb-0"><?= esc($error) ?></div>
        <?php elseif ([] === $entries) : ?>
            <div class="text-center text-muted py-5">
                <i class="bi bi-calendar-x fs-1 d-block mb-2"></i>
                <?= esc(lang('Calendar.day.empty')) ?>
            </div>
        <?php else : ?>
            <p class="small text-muted mb-2"><?= esc(lang('Calendar.day.summary', [$total, $mineCount])) ?></p>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                    <tr>
                        <th class="text-nowrap"><?= esc(lang('Calendar.column.time')) ?></th>
                        <th style="min-width: 14rem"><?= esc(lang('Calendar.column.booking')) ?></th>
                        <th><?= esc(lang('Calendar.column.resources')) ?></th>
                        <th><?= esc(lang('Calendar.column.staff')) ?></th>
                        <th><?= esc(lang('Calendar.column.customers')) ?></th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($entries as $entry) :
                        $isGroup   = 'G' === ($entry['kind'] ?? '');
                        $detailUrl = base_url('admin/calendar/session/' . rawurlencode((string) $entry['session_public_id'])) . '?date=' . $date;
                        $status    = (string) ($entry['session_status'] ?? '');
                        ?>
                        <tr class="<?= ! empty($entry['is_mine']) ? 'table-primary' : '' ?>">
                            <td class="text-nowrap">
                                <div class="fw-semibold"><?= esc($time($entry['local_starts_at'] ?? null)) ?> – <?= esc($time($entry['local_ends_at'] ?? null)) ?></div>
                                <div class="small text-muted"><i class="bi bi-shop"></i> <?= esc((string) ($entry['store']['store_name'] ?? '')) ?></div>
                            </td>
                            <td>
                                <a href="<?= esc($detailUrl, 'attr') ?>" class="fw-semibold text-decoration-none"><?= esc((string) ($entry['name'] ?? '')) ?></a>
                                <div class="d-flex flex-wrap gap-1 mt-1">
                                    <span class="badge <?= $isGroup ? 'bg-info text-dark' : 'bg-success' ?>">
                                        <i class="bi <?= $isGroup ? 'bi-people' : 'bi-person' ?>"></i> <?= esc((string) ($kinds[$entry['kind'] ?? ''] ?? '')) ?>
                                    </span>
                                    <?php if ($isGroup && 'O' !== $status) : ?>
                                        <span class="badge bg-secondary"><?= esc((string) ($statuses[$status] ?? $status)) ?></span>
                                    <?php endif; ?>
                                    <?php if ((int) ($entry['slot_count'] ?? 1) > 1) : ?>
                                        <span class="badge bg-light text-dark border"><?= esc(lang('Calendar.slot-of', [(int) $entry['slot_number'], (int) $entry['slot_count']])) ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($isGroup && ($entry['service_name'] ?? '') !== ($entry['name'] ?? '')) : ?>
                                    <div class="small text-muted"><?= esc((string) $entry['service_name']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ([] === (array) ($entry['resources'] ?? [])) : ?>
                                    <span class="text-muted small"><?= esc(lang('Calendar.no-resource')) ?></span>
                                <?php else : ?>
                                    <?php foreach ((array) $entry['resources'] as $resource) : ?>
                                        <div class="text-nowrap"><?= esc((string) $resource['resource_name']) ?>
                                            <span class="small text-muted"><?= esc((string) $resource['type_name']) ?></span></div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ([] === (array) ($entry['staff'] ?? [])) : ?>
                                    <span class="text-muted small"><?= esc(lang('Calendar.no-staff')) ?></span>
                                <?php else : ?>
                                    <?php foreach ((array) $entry['staff'] as $person) : ?>
                                        <div class="text-nowrap">
                                            <a href="<?= esc(base_url('admin/public-profile/' . rawurlencode((string) $person['uid'])), 'attr') ?>" class="text-decoration-none text-reset">
                                                <img src="<?= esc(avatarUrl($person['avatar_path'] ?? null), 'attr') ?>" alt="" class="rounded-circle me-1" width="20" height="20" style="object-fit: cover">
                                                <?= esc((string) $person['name']) ?>
                                            </a>
                                            <?php if (! empty($person['is_me'])) : ?>
                                                <span class="badge bg-primary"><?= esc(lang('Calendar.you')) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($isGroup) : ?>
                                    <span class="text-nowrap"><i class="bi bi-people text-muted"></i>
                                        <?= esc(lang('Calendar.participants', [(int) ($entry['seats_booked'] ?? 0), (int) ($entry['capacity'] ?? 0)])) ?></span>
                                <?php else : ?>
                                    <?php foreach ((array) ($entry['customers'] ?? []) as $customer) :
                                        $names = array_values(array_filter((array) ($customer['participants'] ?? []), static fn ($n): bool => '' !== trim((string) $n)));
                                        ?>
                                        <div>
                                            <?= esc(implode(', ', $names ?: [(string) $customer['name']])) ?>
                                            <?php if ($names !== [] && ! in_array((string) $customer['name'], $names, true)) : ?>
                                                <span class="small text-muted">(<?= esc((string) $customer['name']) ?>)</span>
                                            <?php endif; ?>
                                            <?php if (! empty($customer['no_show'])) : ?>
                                                <span class="badge bg-dark"><?= esc(lang('Calendar.no-show')) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <a href="<?= esc($detailUrl, 'attr') ?>" class="btn btn-sm btn-outline-primary text-nowrap"><i class="bi bi-eye"></i> <?= esc(lang('Calendar.day.view')) ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
