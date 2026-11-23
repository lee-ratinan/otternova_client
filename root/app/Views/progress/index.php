<?php
/**
 * The setup guide (Progress::index()): a progress bar, then the steps of the business's plan by phase, each filled in
 * by the script below from admin/progress/check/{step}, so each loads (or fails) apart
 *
 * @var array<string, list<string>> $phases       phase => its steps shown, in order
 * @var bool                         $paid         whether the business is on a paid plan
 * @var string                       $businessName the session business
 * @var string                       $checkUrl     admin/progress/check/, the step appended
 * @var string                       $locale
 */

use App\Controllers\Progress;

$this->extend('_layout_admin');

$number = 0;
?>
<?= $this->section('content') ?>
<div class="page-progress" id="progress">
    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-start gap-3 mb-3">
                <div class="me-auto">
                    <h4 class="mb-1"><?= esc(lang('Progress.title', [$businessName])) ?></h4>
                    <p class="text-muted mb-0"><?= esc(lang('Progress.subtitle')) ?></p>
                </div>
                <span class="badge <?= $paid ? 'bg-primary' : 'bg-secondary' ?> fs-6"><?= esc(lang($paid ? 'Progress.plan.paid' : 'Progress.plan.free')) ?></span>
            </div>
            <div class="d-flex justify-content-between small mb-1">
                <span data-progress-label><?= esc(lang('Progress.loading')) ?></span>
                <span class="fw-semibold" data-progress-percent></span>
            </div>
            <div class="progress" role="progressbar" aria-label="<?= esc(lang('Progress.bar-label'), 'attr') ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" data-progress-bar>
                <div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 0%"></div>
            </div>
            <div class="alert alert-success mt-3 mb-0 d-none" role="status" data-progress-complete>
                <i class="bi bi-check2-circle"></i> <?= esc(lang('Progress.complete')) ?>
            </div>
            <div class="mt-3 d-none" data-progress-next>
                <span class="text-muted small"><?= esc(lang('Progress.next')) ?></span>
                <a href="#" class="btn btn-primary btn-sm ms-2" data-progress-next-link><i class="bi bi-arrow-right"></i> <span></span></a>
            </div>
        </div>
    </div>

    <?php foreach ($phases as $phase => $steps) : ?>
        <div class="card mb-3" data-phase="<?= esc($phase, 'attr') ?>">
            <div class="card-header d-flex align-items-center">
                <h6 class="card-title mb-0 me-auto"><?= esc(lang('Progress.phase.' . $phase . '.title')) ?></h6>
                <span class="small text-muted" data-phase-count></span>
            </div>
            <div class="card-body pb-0">
                <p class="text-muted small"><?= esc(lang('Progress.phase.' . $phase . '.text')) ?></p>
            </div>
            <ul class="list-group list-group-flush">
                <?php foreach ($steps as $step) :
                    [$url, $icon, $required, $for] = Progress::STEPS[$step];
                    $number++;
                    ?>
                    <li class="list-group-item d-flex align-items-start gap-3 py-3" data-step="<?= esc($step, 'attr') ?>"
                        data-required="<?= $required ? '1' : '0' ?>" data-title="<?= esc(lang('Progress.step.' . $step . '.title'), 'attr') ?>"
                        data-url="<?= esc(base_url($url), 'attr') ?>">
                        <span class="d-inline-flex align-items-center justify-content-center rounded-circle border flex-shrink-0 fw-semibold"
                              style="width: 2.25rem; height: 2.25rem;" data-step-badge>
                            <span data-step-number><?= $number ?></span>
                        </span>
                        <div class="flex-grow-1 min-w-0">
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <i class="bi <?= $icon ?> text-primary"></i>
                                <span class="fw-semibold"><?= esc(lang('Progress.step.' . $step . '.title')) ?></span>
                                <?php if (! $required) : ?>
                                    <span class="badge bg-light text-dark border"><?= esc(lang('Progress.optional')) ?></span>
                                <?php endif; ?>
                                <?php if ('paid' === $for) : ?>
                                    <span class="badge bg-primary-subtle text-primary-emphasis border"><?= esc(lang('Progress.paid-only')) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="text-muted small mt-1"><?= esc(lang('Progress.step.' . $step . '.text')) ?></div>
                            <div class="small mt-1" data-step-note>
                                <span class="placeholder-glow" aria-hidden="true"><span class="placeholder col-4"></span></span>
                                <span class="visually-hidden"><?= esc(lang('Progress.loading')) ?></span>
                            </div>
                        </div>
                        <a href="<?= esc(base_url($url), 'attr') ?>" class="btn btn-sm btn-outline-secondary flex-shrink-0" data-step-link>
                            <?= esc(lang('Progress.open')) ?> <i class="bi bi-arrow-right"></i>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endforeach; ?>
</div>

<script>
(function (cfg) {
    'use strict';
    var csrfHash = cfg.csrf.hash;
    var root = document.getElementById('progress');
    var steps = Array.prototype.slice.call(root.querySelectorAll('[data-step]'));

    // the badge, note and button of a step by its state: done, todo, optional, or error (not known)
    function show(item, state, note) {
        item.setAttribute('data-state', state);
        var badge = item.querySelector('[data-step-badge]');
        badge.className = badge.className.replace(/\b(bg-success|text-white|border-success|border-warning|border-danger)\b/g, '').trim();
        var number = badge.querySelector('[data-step-number]');
        var tick = badge.querySelector('i');
        if (tick) { tick.remove(); }
        number.classList.toggle('d-none', state === 'done');
        if (state === 'done') {
            badge.classList.add('bg-success', 'text-white', 'border-success');
            var icon = document.createElement('i');
            icon.className = 'bi bi-check-lg';
            badge.appendChild(icon);
        } else if (state === 'todo') {
            badge.classList.add('border-warning');
        } else if (state === 'error') {
            badge.classList.add('border-danger');
        }

        var box = item.querySelector('[data-step-note]');
        box.innerHTML = '';
        var text = document.createElement('span');
        text.className = state === 'done' ? 'text-success' : (state === 'todo' ? 'text-warning-emphasis' : (state === 'error' ? 'text-danger' : 'text-muted'));
        text.textContent = note || '';
        box.appendChild(text);
        if (state === 'error') {
            var retry = document.createElement('button');
            retry.type = 'button';
            retry.className = 'btn btn-link btn-sm p-0 ms-2 align-baseline';
            retry.textContent = cfg.retry;
            retry.addEventListener('click', function () { load(item).then(summarise); });
            box.appendChild(retry);
        }

        var link = item.querySelector('[data-step-link]');
        link.classList.toggle('btn-outline-secondary', state !== 'todo');
        link.classList.toggle('btn-primary', state === 'todo');
    }

    function load(item) {
        var headers = {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'};
        headers[cfg.csrf.header] = csrfHash;
        return fetch(cfg.url + encodeURIComponent(item.getAttribute('data-step')), {method: 'POST', headers: headers, credentials: 'same-origin'})
            .then(function (response) {
                return response.json().catch(function () { return {}; }).then(function (json) { return {ok: response.ok, json: json}; });
            })
            .then(function (result) {
                var json = result.json || {};
                if (json.csrf_hash) { csrfHash = json.csrf_hash; }
                if (json.redirect && json.status === 'error') { window.location.href = json.redirect; return; } // session expired
                if (!result.ok || !json.success) { show(item, 'error', json.message || cfg.failed); return; }
                show(item, json.state, json.note);
            })
            .catch(function () { show(item, 'error', cfg.failed); });
    }

    // the bar counts the required steps; the next step is the first required one still to do, else the first optional
    function summarise() {
        var required = steps.filter(function (item) { return item.getAttribute('data-required') === '1'; });
        var done = required.filter(function (item) { return item.getAttribute('data-state') === 'done'; }).length;
        var percent = required.length ? Math.round(done * 100 / required.length) : 100;
        root.querySelector('[data-progress-label]').textContent = cfg.label.replace('{0}', done).replace('{1}', required.length);
        root.querySelector('[data-progress-percent]').textContent = percent + '%';
        var bar = root.querySelector('[data-progress-bar]');
        bar.setAttribute('aria-valuenow', String(percent));
        var fill = bar.querySelector('.progress-bar');
        fill.style.width = percent + '%';
        fill.classList.toggle('bg-success', percent === 100);

        root.querySelectorAll('[data-phase]').forEach(function (phase) {
            var items = phase.querySelectorAll('[data-step]');
            var finished = Array.prototype.filter.call(items, function (item) { return item.getAttribute('data-state') === 'done'; }).length;
            phase.querySelector('[data-phase-count]').textContent = cfg.phaseCount.replace('{0}', finished).replace('{1}', items.length);
        });

        var pending = steps.filter(function (item) { return !item.hasAttribute('data-state'); }).length > 0;
        var next = required.filter(function (item) { return item.getAttribute('data-state') === 'todo'; })[0]
            || steps.filter(function (item) { return item.getAttribute('data-state') === 'optional'; })[0];
        steps.forEach(function (item) { item.classList.toggle('bg-body-tertiary', item === next); });
        var nextBox = root.querySelector('[data-progress-next]');
        nextBox.classList.toggle('d-none', pending || !next);
        if (next) {
            var link = nextBox.querySelector('[data-progress-next-link]');
            link.href = next.getAttribute('data-url');
            link.querySelector('span').textContent = next.getAttribute('data-title');
        }
        root.querySelector('[data-progress-complete]').classList.toggle('d-none', pending || done < required.length);
        if (!pending) { fill.classList.remove('progress-bar-striped', 'progress-bar-animated'); }
    }

    // one at a time, as the CSRF token may change with each answer
    steps.reduce(function (chain, item) {
        return chain.then(function () { return load(item); }).then(summarise);
    }, Promise.resolve());
})(<?= json_encode([
    'url'        => $checkUrl,
    'failed'     => lang('Progress.load-failed'),
    'retry'      => lang('Progress.retry'),
    'label'      => lang('Progress.bar', ['{0}', '{1}']),
    'phaseCount' => lang('Progress.phase-count', ['{0}', '{1}']),
    'csrf'       => ['header' => csrf_header(), 'hash' => csrf_hash()],
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>);
</script>
<?= $this->endSection() ?>
