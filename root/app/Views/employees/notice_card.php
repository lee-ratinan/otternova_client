<?php
/**
 * A card below the employee form saying why an action is not offered (e.g. an owner who cannot leave yet, or a
 * rehire over the plan's cap), passed to generic_form.php as $extraHtml
 *
 * @var string $title
 * @var string $text
 */
?>
<div class="card mt-4">
    <div class="card-header"><h5 class="card-title mb-0"><?= esc($title) ?></h5></div>
    <div class="card-body"><p class="text-muted mb-0"><?= esc($text) ?></p></div>
</div>
