<?php
/**
 * The fee brackets of a shipping method in the view pane of its form, read only, by range_from
 *
 * @var list<string> $rates each bracket as it reads (ShippingMethods::rateText())
 */
?>
<div class="row mb-3">
    <div class="col-12">
        <?php if ([] === $rates): ?>
            <p class="text-muted mb-0"><?= esc(lang('ShippingMethods.rates.none')) ?></p>
        <?php else: ?>
            <ol class="mb-0 ps-3">
                <?php foreach ($rates as $rate): ?>
                    <li><?= esc($rate) ?></li>
                <?php endforeach ?>
            </ol>
        <?php endif ?>
    </div>
</div>
