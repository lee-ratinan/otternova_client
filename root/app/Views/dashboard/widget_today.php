<?php
/**
 * Dashboard widget: today's bookings (Dashboard::today()); an employee's own only, everyone's for owners and managers
 *
 * @var string|null $error
 * @var string      $date     'Y-m-d', the business's today
 * @var bool        $onlyMine the list is the client's own bookings
 * @var int         $total    the day's bookings
 * @var int         $mine     of them, the client's
 * @var list<array> $entries  v1/client/calendar entries shown
 * @var int         $more     entries not shown
 * @var int         $seats    seats booked in the day's group sessions
 * @var int         $capacity seats of the day's group sessions
 * @var int         $noShows  bookings with a no-show
 * @var string      $locale
 */
if (null !== $error) : ?>
    <div class="alert alert-warning mb-0"><?= esc($error) ?></div>
<?php return; endif;

$kinds = (array) lang('Calendar.kind');
$time  = static fn (?string $local): string => substr((string) $local, 11, 5);
$stats = $onlyMine
    ? [[lang('Dashboard.today.mine'), $mine], [lang('Dashboard.today.all'), $total]]
    : [[lang('Dashboard.today.bookings'), $total], [lang('Dashboard.today.mine'), $mine],
        [lang('Dashboard.today.seats'), $capacity > 0 ? $seats . ' / ' . $capacity : '—'], [lang('Dashboard.today.no-shows'), $noShows]];
?>
<div class="d-flex justify-content-between align-items-baseline mb-2">
    <span class="small text-muted"><?= esc(formatDate($date, $locale)) ?></span>
</div>
<div class="row g-2 mb-3">
    <?php foreach ($stats as [$label, $value]) : ?>
        <div class="col">
            <div class="border rounded p-2 text-center h-100">
                <div class="fs-5 fw-semibold"><?= esc((string) $value) ?></div>
                <div class="small text-muted"><?= esc($label) ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php if ([] === $entries) : ?>
    <div class="text-center text-muted py-4" data-today-empty>
        <i class="bi bi-calendar-x fs-2 d-block mb-1"></i>
        <?= esc(lang($onlyMine ? 'Dashboard.today.empty-mine' : 'Dashboard.today.empty')) ?>
    </div>
<?php else : ?>
    <ul class="list-group list-group-flush">
        <?php foreach ($entries as $entry) :
            $isGroup = 'G' === ($entry['kind'] ?? '');
            $url     = base_url('admin/calendar/session/' . rawurlencode((string) ($entry['session_public_id'] ?? ''))) . '?date=' . $date;
            $staff   = array_map(static fn (array $p): string => (string) ($p['name'] ?? ''), (array) ($entry['staff'] ?? []));
            ?>
            <li class="list-group-item px-0 d-flex gap-3 align-items-start<?= ! $onlyMine && ! empty($entry['is_mine']) ? ' bg-primary-subtle' : '' ?>">
                <div class="text-nowrap fw-semibold" style="min-width: 6.5rem"><?= esc($time($entry['local_starts_at'] ?? null)) ?> – <?= esc($time($entry['local_ends_at'] ?? null)) ?></div>
                <div class="flex-grow-1">
                    <a href="<?= esc($url, 'attr') ?>" class="fw-semibold text-decoration-none"><?= esc((string) ($entry['name'] ?? '')) ?></a>
                    <span class="badge <?= $isGroup ? 'bg-info text-dark' : 'bg-success' ?> ms-1"><?= esc((string) ($kinds[$entry['kind'] ?? ''] ?? '')) ?></span>
                    <div class="small text-muted">
                        <i class="bi bi-shop"></i> <?= esc((string) ($entry['store']['store_name'] ?? '')) ?>
                        <?php if (! $onlyMine && [] !== $staff) : ?> · <i class="bi bi-person"></i> <?= esc(implode(', ', $staff)) ?><?php endif; ?>
                    </div>
                </div>
                <div class="small text-nowrap text-muted">
                    <?php if ($isGroup) : ?>
                        <i class="bi bi-people"></i> <?= (int) ($entry['seats_booked'] ?? 0) ?> / <?= (int) ($entry['capacity'] ?? 0) ?>
                    <?php else : ?>
                        <?= esc(implode(', ', array_map(static fn (array $c): string => (string) ($c['name'] ?? ''), (array) ($entry['customers'] ?? [])))) ?>
                    <?php endif; ?>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php if ($more > 0) : ?>
        <a href="<?= esc(base_url('admin/calendar') . ($onlyMine ? '?mine=1' : ''), 'attr') ?>" class="small d-block mt-2"><?= esc(lang('Dashboard.more', [$more])) ?></a>
    <?php endif; ?>
<?php endif; ?>
