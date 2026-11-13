<?php
/**
 * The services editor of a person's page (Staff::form()): a row for each service of the business, ticked when the
 * person may perform it, with their commission on it, a percentage or a fixed amount per service (0 for none). The
 * inputs are named $field[<public_id>][assigned|commission_value|commission_unit]; scripts/staff_form turns a row's
 * commission off while it is not ticked. A service the person is still assigned to upcoming sessions of
 * (upcoming_sessions) cannot be unticked: scripts/staff_form ticks it again and alerts them to take the person off
 * those sessions first (the API refuses it too).
 *
 * @var list<array> $services public_id, artifact_name, artifact_status, translations, assigned, commission_value, commission_unit, upcoming_sessions
 * @var string      $field
 * @var string      $currency business_master.currency_code
 * @var string      $locale
 */
$statuses = lang('Staff.services.status');
?>
<div class="row mb-3">
    <div class="col-12" data-services-editor data-sessions-url="<?= esc(base_url('admin/sessions'), 'attr') ?>"
        data-sessions-title="<?= esc(lang('Staff.services.in-sessions-title'), 'attr') ?>" data-sessions-message="<?= esc(lang('Staff.services.in-sessions', ['{name}', '{sessions}']), 'attr') ?>"
        data-sessions-open="<?= esc(lang('Staff.services.open-sessions'), 'attr') ?>" data-sessions-close="<?= esc(lang('Staff.services.close'), 'attr') ?>">
        <?php if ([] === $services): ?>
            <p class="text-muted mb-0"><?= esc(lang('Staff.services.none')) ?></p>
        <?php else: ?>
            <p class="form-text mt-0"><?= esc(lang('Staff.services.help')) ?></p>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                    <tr>
                        <th style="width: 3rem"></th>
                        <th><?= esc(lang('Staff.services.service')) ?></th>
                        <th style="min-width: 16rem"><?= esc(lang('Staff.services.commission')) ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($services as $i => $service): ?>
                        <?php
                        $key    = $field . '[' . $service['public_id'] . ']';
                        $id     = 'service-' . $i;
                        $name   = (string) (($service['translations'] ?? [])[$locale] ?? $service['artifact_name']);
                        $status = (string) $service['artifact_status'];
                        ?>
                        <tr data-service-row data-service-name="<?= esc($name, 'attr') ?>"
                            data-service-sessions="<?= esc((string) json_encode(array_values((array) ($service['upcoming_sessions'] ?? [])), JSON_UNESCAPED_UNICODE), 'attr') ?>">
                            <td>
                                <input class="form-check-input" type="checkbox" id="<?= esc($id, 'attr') ?>" name="<?= esc($key . '[assigned]', 'attr') ?>" value="1"
                                    data-service-assigned<?= ! empty($service['assigned']) ? ' checked' : '' ?>>
                            </td>
                            <td>
                                <label class="form-check-label" for="<?= esc($id, 'attr') ?>"><?= esc($name) ?></label>
                                <?php if ('A' !== $status): ?>
                                    <span class="badge bg-<?= 'D' === $status ? 'danger' : 'secondary' ?> ms-1"><?= esc($statuses[$status] ?? $status) ?></span>
                                <?php endif ?>
                            </td>
                            <td>
                                <div class="input-group input-group-sm">
                                    <input type="number" class="form-control" min="0" step="0.01" name="<?= esc($key . '[commission_value]', 'attr') ?>"
                                        value="<?= esc((string) $service['commission_value'], 'attr') ?>" aria-label="<?= esc(lang('Staff.services.commission'), 'attr') ?>" data-service-input>
                                    <select class="form-select" style="max-width: 9rem" name="<?= esc($key . '[commission_unit]', 'attr') ?>"
                                        aria-label="<?= esc(lang('Staff.services.unit'), 'attr') ?>" data-service-input>
                                        <option value="P"<?= 'P' === $service['commission_unit'] ? ' selected' : '' ?>><?= esc(lang('Staff.services.unit-percentage')) ?></option>
                                        <option value="F"<?= 'F' === $service['commission_unit'] ? ' selected' : '' ?>><?= esc(lang('Staff.services.unit-fixed', [$currency])) ?></option>
                                    </select>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        <?php endif ?>
    </div>
</div>
