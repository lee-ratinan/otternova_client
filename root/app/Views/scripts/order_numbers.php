<?php
/**
 * The order number settings page (OrderNumbers::index()): the preview follows the form as it is changed, and says
 * when the reset is more often than the date part shows
 * @var string $next the number the next order gets as the settings are saved
 */
$json = static fn ($value): string => json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
?>
(function () {
    const form = document.getElementById('order-numbers-form');
    const preview = document.getElementById('order-number-preview');
    const note = document.getElementById('order-number-preview-note');
    const rule = document.getElementById('order-number-rule');
    if (!form || !preview) return;
    const saved = <?= $json($next) ?>;
    const texts = <?= $json(['next' => lang('OrderNumbers.next-help'), 'example' => lang('OrderNumbers.example-help')]) ?>;
    const rank = { N: 0, Y: 1, M: 2, D: 3 };
    const field = (name) => form.querySelector('[data-fb-pane="edit"] [name="' + name + '"]') || form.querySelector('[name="' + name + '"]');
    const two = (n) => String(n).padStart(2, '0');

    const update = () => {
        const prefix = field('order_prefix');
        if (prefix && prefix.value !== prefix.value.toUpperCase()) prefix.value = prefix.value.toUpperCase();
        const part = field('order_date_part').value;
        const reset = field('order_reset').value;
        const digits = parseInt(field('order_digits').value, 10) || 4;
        const now = new Date();
        const date = { N: '', Y: two(now.getFullYear() % 100), M: two(now.getFullYear() % 100) + two(now.getMonth() + 1),
            D: two(now.getFullYear() % 100) + two(now.getMonth() + 1) + two(now.getDate()) }[part] || '';
        preview.textContent = (prefix ? prefix.value.replace(/[^A-Z]/g, '') : '') + date + '-' + '1'.padStart(digits, '0');
        note.textContent = texts.example;
        rule.hidden = reset === 'N' || (rank[part] || 0) >= (rank[reset] || 0);
    };
    form.addEventListener('input', update);
    form.addEventListener('change', update);
    // back to what is saved when the form goes back to view
    form.addEventListener('reset', () => setTimeout(() => { preview.textContent = saved; note.textContent = texts.next; rule.hidden = true; }));
})();
