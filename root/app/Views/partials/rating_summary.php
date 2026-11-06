<?php
/**
 * A rating with its breakdown: the average as stars, the number of approved reviews, and a bar for each number of
 * stars (5 at the top) with how many reviews gave it. The elements carry data-rating-* attributes, so a page's script
 * can fill them again (see scripts/reviews).
 *
 * @var array $rating rating_count, rating_average, rating_breakdown (stars 1-5 => reviews), as the API sends them
 */
$count     = (int) ($rating['rating_count'] ?? 0);
$average   = (float) ($rating['rating_average'] ?? 0);
$breakdown = (array) ($rating['rating_breakdown'] ?? []);
?>
<div class="row g-3 align-items-center" data-rating-summary>
    <div class="col-sm-4 col-lg-3 text-center">
        <div class="display-6 fw-semibold lh-1" data-rating-average><?= number_format($average, 2) ?></div>
        <div class="text-warning my-1" data-rating-stars aria-hidden="true">
            <?php for ($star = 1; $star <= 5; $star++): ?>
                <i class="bi <?= $average >= $star - 0.25 ? 'bi-star-fill' : ($average >= $star - 0.75 ? 'bi-star-half' : 'bi-star') ?>"></i>
            <?php endfor ?>
        </div>
        <div class="small text-muted" data-rating-count><?= esc(0 === $count ? lang('Reviews.summary.none') : lang('Reviews.summary.count', [$count])) ?></div>
    </div>
    <div class="col-sm-8 col-lg-9">
        <?php for ($star = 5; $star >= 1; $star--): ?>
            <?php $reviews = (int) ($breakdown[$star] ?? 0); $share = 0 === $count ? 0 : round($reviews * 100 / $count); ?>
            <div class="d-flex align-items-center gap-2 small mb-1">
                <span class="text-nowrap" style="width: 3.5rem;"><?= $star ?> <i class="bi bi-star-fill text-warning"></i></span>
                <div class="progress flex-grow-1" style="height: 0.6rem;" role="progressbar" aria-label="<?= esc(lang('Reviews.summary.stars', [$star]), 'attr') ?>"
                     aria-valuemin="0" aria-valuemax="<?= $count ?>" aria-valuenow="<?= $reviews ?>" data-rating-bar="<?= $star ?>">
                    <div class="progress-bar bg-warning" style="width: <?= $share ?>%;"></div>
                </div>
                <span class="text-muted text-end" style="width: 2.5rem;" data-rating-reviews="<?= $star ?>"><?= $reviews ?></span>
            </div>
        <?php endfor ?>
    </div>
</div>
