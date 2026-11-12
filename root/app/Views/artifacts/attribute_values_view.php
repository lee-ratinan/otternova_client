<?php
/**
 * The choices of an option in the view pane of its form, read only, in their order
 *
 * @var array $values    as v1/client/artifact-attributes sends them: public_id, attribute_value, color_code, sort_order,
 *                        translations
 * @var array $languages websiteLanguages(), the languages of the business's web pages
 */
?>
<div class="row mb-3">
    <div class="col-12">
        <?php if ([] === $values): ?>
            <p class="text-muted mb-0"><?= esc(lang('ArtifactAttributes.values.none')) ?></p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                    <tr>
                        <th>#</th>
                        <th><?= esc(lang('ArtifactAttributes.values.value')) ?></th>
                        <th><?= esc(lang('ArtifactAttributes.values.color')) ?></th>
                        <?php foreach ($languages as $label): ?>
                            <th><?= esc(lang('ArtifactAttributes.values.in-language', [$label])) ?></th>
                        <?php endforeach ?>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach (array_values($values) as $i => $value): ?>
                        <tr>
                            <td class="text-muted"><?= $i + 1 ?></td>
                            <td><?= esc((string) ($value['attribute_value'] ?? '')) ?></td>
                            <?php $color = (string) ($value['color_code'] ?? ''); ?>
                            <td class="text-nowrap">
                                <?php if (1 === preg_match('/^#[0-9a-f]{6}$/i', $color)): ?>
                                    <span class="d-inline-block border rounded align-middle me-1" style="width: 1.25rem; height: 1.25rem; background-color: <?= esc($color, 'attr') ?>;"></span>
                                    <code><?= esc($color) ?></code>
                                <?php else: ?>
                                    <span class="text-muted">&mdash;</span>
                                <?php endif ?>
                            </td>
                            <?php foreach (array_keys($languages) as $language): ?>
                                <td><?= esc((string) (((array) ($value['translations'] ?? []))[$language] ?? '')) ?></td>
                            <?php endforeach ?>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        <?php endif ?>
    </div>
</div>
