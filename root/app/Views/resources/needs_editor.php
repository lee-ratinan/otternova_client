<?php
/**
 * The "Resources needed" editor of a service's page (Resources::needsFormConfig()): a row for each resource type of
 * the business, ticked when the service needs it, with how many one booking holds. The inputs are named
 * $field[<public_id>][needed|quantity]; scripts/service_resources turns a row's quantity off while it is not ticked.
 *
 * @var list<array> $rows     public_id, type_name, type_status, needed, quantity, available (active resources)
 * @var string      $field
 * @var string      $typesUrl the resource types page
 */
$statuses = lang('Resources.enum.status');
?>
<div class="row mb-3">
    <div class="col-12" data-needs-editor>
        <?php if ([] === $rows): ?>
            <p class="text-muted mb-0">
                <?= esc(lang('Resources.needs.none')) ?>
                <a href="<?= esc($typesUrl, 'attr') ?>"><?= esc(lang('Resources.needs.add-types')) ?></a>
            </p>
        <?php else: ?>
            <p class="form-text mt-0"><?= esc(lang('Resources.needs.help')) ?></p>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                    <tr>
                        <th style="width: 3rem"></th>
                        <th><?= esc(lang('Resources.fields.type')) ?></th>
                        <th class="text-end"><?= esc(lang('Resources.needs.available')) ?></th>
                        <th style="width: 9rem"><?= esc(lang('Resources.needs.quantity')) ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $i => $row): ?>
                        <?php
                        $key = $field . '[' . $row['public_id'] . ']';
                        $id  = 'need-' . $i;
                        ?>
                        <tr data-need-row>
                            <td>
                                <input class="form-check-input" type="checkbox" id="<?= esc($id, 'attr') ?>" name="<?= esc($key . '[needed]', 'attr') ?>" value="1"
                                    data-need-ticked<?= $row['needed'] ? ' checked' : '' ?>>
                            </td>
                            <td>
                                <label class="form-check-label" for="<?= esc($id, 'attr') ?>"><?= esc($row['type_name']) ?></label>
                                <?php if ('A' !== $row['type_status']): ?>
                                    <span class="badge bg-secondary ms-1"><?= esc($statuses[$row['type_status']] ?? $row['type_status']) ?></span>
                                <?php endif ?>
                            </td>
                            <td class="text-end<?= 0 === $row['available'] ? ' text-danger' : '' ?>"><?= esc((string) $row['available']) ?></td>
                            <td>
                                <input type="number" class="form-control form-control-sm" min="1" max="255" step="1" name="<?= esc($key . '[quantity]', 'attr') ?>"
                                    value="<?= esc((string) $row['quantity'], 'attr') ?>" aria-label="<?= esc(lang('Resources.needs.quantity'), 'attr') ?>" data-need-input>
                            </td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        <?php endif ?>
    </div>
</div>
