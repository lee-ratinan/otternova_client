<?php
/**
 * The variants of an artifact in the view pane of its form, read only
 *
 * @var string $type       artifact_type: P has barcode, weight and stock; S has duration
 * @var array  $variants   as v1/client/artifact sends them
 * @var array  $attributes Artifacts::loadAttributes()
 * @var array  $stores     [['public_id', 'name'], ...]
 * @var array  $labels     public_id => "Variant 1 · Blue / M · SKU"
 * @var string $currency   the business's currency_code
 * @var int    $max        the plan's cap of variants per artifact
 * @var string $planName
 */
$product  = 'P' === $type;
$column   = lang('Artifacts.variants.column');
$statuses = lang('Artifacts.enum.variant_status');
$values   = [];
foreach ($attributes as $attribute) {
    foreach ($attribute['values'] as $value) {
        $values[$value['public_id']] = $value['name'];
    }
}
$storeNames = array_column($stores, 'name', 'public_id');
?>
<div class="row mb-3">
    <div class="col-12">
        <?php if ([] === $variants): ?>
            <p class="text-muted mb-0"><?= esc(lang('Artifacts.variants.none')) ?></p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-1">
                    <thead>
                    <tr>
                        <th>#</th>
                        <th><?= esc(lang('Artifacts.variants.options')) ?></th>
                        <th><?= esc($column['sku']) ?></th>
                        <?php if ($product): ?><th><?= esc($column['barcode']) ?></th><?php endif ?>
                        <th class="text-end"><?= esc($column['price_active']) ?></th>
                        <th class="text-end"><?= esc($column['price_compare']) ?></th>
                        <th class="text-end"><?= esc($column[$product ? 'weight_grams' : 'duration_minutes']) ?></th>
                        <th><?= esc($column['variant_status']) ?></th>
                        <?php if ($product): ?><th><?= esc(lang('Artifacts.variants.stock')) ?></th><?php endif ?>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach (array_values($variants) as $i => $variant): ?>
                        <?php
                        $options = array_filter(array_map(static fn (array $v) => $values[$v['value_public_id'] ?? ''] ?? '', (array) ($variant['attribute_values'] ?? [])));
                        $stock   = array_map(static fn (array $s) => ($storeNames[$s['store_public_id'] ?? ''] ?? '?') . ': ' . (int) $s['quantity'], (array) ($variant['inventory'] ?? []));
                        $amount  = $variant[$product ? 'weight_grams' : 'duration_minutes'] ?? null;
                        ?>
                        <tr>
                            <td class="text-muted"><?= $i + 1 ?></td>
                            <td><?= esc(implode(' / ', $options)) ?: '<span class="text-muted">-</span>' ?></td>
                            <td><?= esc((string) ($variant['sku'] ?? '')) ?></td>
                            <?php if ($product): ?><td><?= esc((string) ($variant['barcode'] ?? '')) ?></td><?php endif ?>
                            <td class="text-end text-nowrap"><?= esc(formatMoney($variant['price_active'] ?? null, $currency)) ?></td>
                            <td class="text-end text-nowrap"><?= null === ($variant['price_compare'] ?? null) ? '' : '<del class="text-muted">' . esc(formatMoney($variant['price_compare'], $currency)) . '</del>' ?></td>
                            <td class="text-end"><?= null === $amount ? '' : (int) $amount ?></td>
                            <td>
                                <span class="badge <?= 'A' === ($variant['variant_status'] ?? '') ? 'bg-success' : 'bg-secondary' ?>"><?= esc($statuses[$variant['variant_status'] ?? ''] ?? '') ?></span>
                            </td>
                            <?php if ($product): ?><td class="small"><?= esc(implode(', ', $stock)) ?></td><?php endif ?>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        <?php endif ?>
        <div class="form-text"><?= esc(lang('Artifacts.variants.count', [count($variants), $max, $planName])) ?></div>
    </div>
</div>
