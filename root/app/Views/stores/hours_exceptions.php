<?php
/**
 * The special hours of a store from today on, below the forms of its hours page (StoreHours::index()): each row can be
 * loaded into the special-hours form to change it, or removed (scripts/store_hours handles both buttons).
 *
 * @var list<array> $exceptions exception_date, exception_reason, closed, hours [{open_time, close_time}]
 * @var string      $locale
 */
?>
<div class="card mt-4" data-hours-exceptions>
    <div class="card-header">
        <h5 class="card-title mb-0"><?= esc(lang('StoreHours.list.title')) ?></h5>
        <p class="text-muted small mb-0"><?= esc(lang('StoreHours.list.subtitle')) ?></p>
    </div>
    <div class="card-body">
        <?php if ([] === $exceptions): ?>
            <p class="text-muted mb-0"><?= esc(lang('StoreHours.list.none')) ?></p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                    <tr>
                        <th><?= esc(lang('StoreHours.list.date')) ?></th>
                        <th><?= esc(lang('StoreHours.list.reason')) ?></th>
                        <th><?= esc(lang('StoreHours.list.hours')) ?></th>
                        <th class="text-end"></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($exceptions as $exception): ?>
                        <?php
                        $hours = [];
                        foreach ((array) ($exception['hours'] ?? []) as $opening) {
                            $nextDay = $opening['close_time'] <= $opening['open_time'] ? ' ' . lang('StoreHours.editor.next-day-short') : '';
                            $hours[] = $opening['open_time'] . '–' . $opening['close_time'] . $nextDay;
                        }
                        ?>
                        <tr>
                            <td class="text-nowrap"><?= esc((string) formatDate($exception['exception_date'], $locale)) ?></td>
                            <td><?= esc((string) $exception['exception_reason']) ?></td>
                            <td>
                                <?php if (! empty($exception['closed'])): ?>
                                    <span class="badge bg-secondary"><?= esc(lang('StoreHours.special.closed')) ?></span>
                                <?php else: ?>
                                    <?= esc(implode(', ', $hours)) ?>
                                <?php endif ?>
                            </td>
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
