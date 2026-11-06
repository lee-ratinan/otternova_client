<?php
/**
 * The card above the reviews list: the session business's rating with its breakdown (partials/rating_summary),
 * which scripts/reviews fills again after a review is approved or hidden
 *
 * @var array $rating the business_rating of v1/client/artifact-reviews
 */
?>
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title"><?= esc(lang('Reviews.summary.title', [(string) currentBusinessName()])) ?></h5>
        <p class="card-subtitle"><?= esc(lang('Reviews.summary.subtitle')) ?></p>
    </div>
    <div class="card-body">
        <?= view('partials/rating_summary', ['rating' => $rating]) ?>
    </div>
</div>
