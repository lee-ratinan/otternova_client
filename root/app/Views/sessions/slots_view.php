<?php
/**
 * The slots of a session in the view pane of its form, read only, in the store's timezone
 *
 * @var array $slots as v1/client/session sends them
 */
$statuses = lang('ServiceSessions.enum.slot_status');
$column   = lang('ServiceSessions.slots.column');
$time     = static fn (?string $local): string => null === $local ? '' : substr($local, 11, 5);
?>
<div class="row mb-3">
    <div class="col-12">
        <?php if ([] === $slots): ?>
            <p class="text-muted mb-0"><?= esc(lang('ServiceSessions.slots.none')) ?></p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-1">
                    <thead>
                    <tr>
                        <th>#</th>
                        <th><?= esc($column['date']) ?></th>
                        <th><?= esc($column['time']) ?></th>
                        <th><?= esc($column['staff']) ?></th>
                        <th><?= esc($column['resources']) ?></th>
                        <th><?= esc($column['slot_status']) ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($slots as $slot): ?>
                        <?php $cancelled = 'X' === ($slot['slot_status'] ?? ''); ?>
                        <tr class="<?= $cancelled ? 'text-muted' : '' ?>">
                            <td><?= (int) $slot['slot_number'] ?></td>
                            <td class="text-nowrap"><?= esc(substr((string) ($slot['local_starts_at'] ?? ''), 0, 10)) ?></td>
                            <td class="text-nowrap"><?= esc($time($slot['local_starts_at'] ?? null) . '–' . $time($slot['local_ends_at'] ?? null)) ?></td>
                            <td><?= esc(implode(', ', array_column((array) ($slot['staff'] ?? []), 'full_name'))) ?></td>
                            <td><?= esc(implode(', ', array_column((array) ($slot['resources'] ?? []), 'resource_name'))) ?></td>
                            <td><span class="badge <?= $cancelled ? 'bg-secondary' : 'bg-success' ?>"><?= esc($statuses[$slot['slot_status'] ?? ''] ?? '') ?></span></td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
            <div class="form-text"><?= esc(lang('ServiceSessions.slots.timezone')) ?></div>
        <?php endif ?>
    </div>
</div>
