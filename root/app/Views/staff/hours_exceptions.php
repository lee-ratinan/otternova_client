<?php
/**
 * A person's changed days from today on, below the forms of their working hours page (Staff::hours()): each row can be
 * loaded into the day form to change it, or removed (scripts/store_hours handles both buttons).
 *
 * @var list<array> $exceptions exception_date, exception_type, exception_reason, closed, hours [{store_public_id, open_time, close_time}]
 * @var array       $stores     public_id => store name
 * @var string      $locale
 */
$types  = lang('StaffHours.type');
$badges = ['V' => 'info', 'S' => 'warning', 'O' => 'secondary'];
?>
<div class="card mt-4" data-hours-exceptions>
    <div class="card-header">
        <h5 class="card-title mb-0"><?= esc(lang('StaffHours.list.title')) ?></h5>
        <p class="text-muted small mb-0"><?= esc(lang('StaffHours.list.subtitle')) ?></p>
    </div>
    <div class="card-body">
        <?php if ([] === $exceptions): ?>
            <p class="text-muted mb-0"><?= esc(lang('StaffHours.list.none')) ?></p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                    <tr>
                        <th><?= esc(lang('StaffHours.list.date')) ?></th>
                        <th><?= esc(lang('StaffHours.list.type')) ?></th>
                        <th><?= esc(lang('StaffHours.list.shifts')) ?></th>
                        <th><?= esc(lang('StaffHours.list.note')) ?></th>
                        <th class="text-end"></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($exceptions as $exception): ?>
                        <?php
                        $type   = (string) $exception['exception_type'];
                        $shifts = [];
                        foreach ((array) $exception['hours'] as $shift) {
                            $nextDay  = $shift['close_time'] <= $shift['open_time'] ? ' ' . lang('StoreHours.editor.next-day-short') : '';
                            $shifts[] = ($stores[$shift['store_public_id']] ?? '?') . ' ' . $shift['open_time'] . '–' . $shift['close_time'] . $nextDay;
                        }
                        ?>
                        <tr>
                            <td class="text-nowrap"><?= esc((string) formatDate($exception['exception_date'], $locale)) ?></td>
                            <td><span class="badge bg-<?= esc($badges[$type] ?? 'secondary', 'attr') ?>"><?= esc($types[$type] ?? $type) ?></span></td>
                            <td>
                                <?php if (! empty($exception['closed'])): ?>
                                    <?= esc(lang('StaffHours.day.off')) ?>
                                <?php else: ?>
                                    <?= esc(implode(', ', $shifts)) ?>
                                <?php endif ?>
                            </td>
                            <td><?= esc((string) ($exception['exception_reason'] ?? '')) ?></td>
                            <td class="text-end text-nowrap">
                                <button type="button" class="btn btn-sm btn-outline-primary" data-exception-edit="<?= esc($exception['exception_date'], 'attr') ?>">
                                    <i class="bi bi-pencil"></i> <?= esc(lang('StoreHours.list.edit')) ?>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger" data-exception-delete="<?= esc($exception['exception_date'], 'attr') ?>">
                                    <i class="bi bi-trash"></i> <?= esc(lang('StoreHours.list.delete')) ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        <?php endif ?>
    </div>
</div>
