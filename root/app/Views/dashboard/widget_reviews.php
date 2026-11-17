<?php
/**
 * Dashboard widget: the business's rating and the reviews waiting for approval (Dashboard::reviews())
 *
 * @var string|null $error
 * @var int         $pending the reviews waiting for approval
 * @var list<array> $reviews the latest of them
 * @var array       $rating  business_rating of v1/client/artifact-reviews
 * @var string      $locale
 */
if (null !== $error) : ?>
    <div class="alert alert-warning mb-0"><?= esc($error) ?></div>
<?php return; endif;

$average = (float) ($rating['rating_average'] ?? 0);
$count   = (int) ($rating['rating_count'] ?? 0);
?>
<div class="d-flex align-items-center gap-3 mb-3">
    <div class="text-center">
        <div class="fs-3 fw-semibold lh-1"><?= number_format($average, 2) ?></div>
        <div class="text-warning small" aria-hidden="true">
            <?php for ($star = 1; $star <= 5; $star++) : ?>
                <i class="bi <?= $average >= $star - 0.25 ? 'bi-star-fill' : ($average >= $star - 0.75 ? 'bi-star-half' : 'bi-star') ?>"></i>
            <?php endfor; ?>
        </div>
    </div>
    <div class="small text-muted"><?= esc(0 === $count ? lang('Reviews.summary.none') : lang('Reviews.summary.count', [$count])) ?></div>
    <a href="<?= base_url('admin/reviews') ?>" class="ms-auto badge text-decoration-none <?= $pending > 0 ? 'bg-warning text-dark' : 'bg-light text-muted border' ?>" data-reviews-pending>
        <?= esc(lang('Dashboard.reviews.pending', [$pending])) ?>
    </a>
</div>
<?php if ([] === $reviews) : ?>
    <div class="text-center text-muted py-2"><?= esc(lang('Dashboard.reviews.none-pending')) ?></div>
<?php else : ?>
    <ul class="list-group list-group-flush">
        <?php foreach ($reviews as $review) :
            $stars = max(0, min(5, (int) ($review['rating'] ?? 0)));
            ?>
            <li class="list-group-item px-0">
                <div class="d-flex justify-content-between gap-2">
                    <span class="fw-semibold text-truncate"><?= esc((string) ($review['review_title'] ?? '')) ?></span>
                    <span class="text-warning text-nowrap small" title="<?= $stars ?>/5"><?= str_repeat('<i class="bi bi-star-fill"></i>', $stars) ?></span>
                </div>
                <div class="small text-muted text-truncate">
                    <?= esc((string) ($review['artifact_name'] ?? '')) ?> · <?= esc((string) ($review['customer_name'] ?? '')) ?>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
