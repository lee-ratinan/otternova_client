<?php
/**
 * A page of its own made to be printed on A4: black on white, no colours, tables ruled in black, the header row
 * repeated on every sheet. The toolbar above it (Print, Back) is not printed. The page's own view fills the
 * "content" section; see revenue/export and payroll/export.
 *
 * @var string $locale
 * @var string $title        the report's name, also the document's title (the PDF's file name when saved)
 * @var string $subtitle     the period it covers
 * @var string $businessName the session business
 * @var string $back         the link back to the report on screen
 */
$printedBy = (string) (session('user')['full_name'] ?? '');
?>
<!DOCTYPE html>
<html lang="<?= esc($locale, 'attr') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= esc($title . ' · ' . $businessName . ' · ' . $subtitle) ?></title>
    <!-- Favicons -->
    <link href="<?= assets_url('img/logo.webp') ?>" rel="icon">
    <link href="<?= assets_url('img/logo.webp') ?>" rel="apple-touch-icon">
    <style>
        @page { size: A4 portrait; margin: 12mm 10mm 14mm; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; background: #e9ecef; color: #000; }
        body { font-family: Tahoma, "Noto Sans Thai", "Leelawadee UI", "Thonburi", Arial, sans-serif; font-size: 9pt; line-height: 1.3;
            -webkit-print-color-adjust: economy; print-color-adjust: economy; }
        .toolbar { display: flex; gap: 8px; justify-content: center; padding: 12px; font-size: 14px; }
        .toolbar a, .toolbar button { font: inherit; padding: 6px 16px; border: 1px solid #000; border-radius: 0; background: #fff; color: #000;
            cursor: pointer; text-decoration: none; }
        .toolbar button.primary { background: #000; color: #fff; }
        /* an A4 sheet less its margins on screen */
        .paper { background: #fff; width: 210mm; max-width: 100%; min-height: 297mm; margin: 0 auto 24px; padding: 12mm 10mm;
            box-shadow: 0 1px 4px rgba(0, 0, 0, .25); }
        header { display: flex; justify-content: space-between; align-items: flex-end; gap: 6mm; border-bottom: 2px solid #000;
            padding-bottom: 2mm; margin-bottom: 4mm; }
        header h1 { font-size: 14pt; margin: 0; }
        header .business { font-size: 11pt; font-weight: bold; }
        header .meta { text-align: right; font-size: 8pt; }
        h2 { font-size: 10.5pt; margin: 5mm 0 2mm; }
        p { margin: 0 0 2mm; }
        .small { font-size: 8pt; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 3mm; }
        thead { display: table-header-group; }
        /* the totals once, at the end, not on every sheet */
        tfoot { display: table-row-group; }
        tr { page-break-inside: avoid; break-inside: avoid; }
        th, td { border: 1px solid #000; padding: 1.2mm 1.5mm; vertical-align: top; text-align: left; }
        thead th { font-weight: bold; border-bottom-width: 2px; }
        tfoot td { font-weight: bold; border-top-width: 2px; }
        .num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .nowrap { white-space: nowrap; }
        .center { text-align: center; }
        .struck { text-decoration: line-through; }
        .empty { padding: 8mm 0; text-align: center; border: 1px solid #000; }
        .error { padding: 4mm; border: 2px solid #000; font-weight: bold; }
        @media print {
            html, body { background: #fff; }
            .toolbar { display: none; }
            .paper { width: auto; min-height: 0; margin: 0; padding: 0; box-shadow: none; }
            a { color: #000; text-decoration: none; }
        }
    </style>
</head>
<body>
<div class="toolbar">
    <button type="button" class="primary" data-print><?= esc(lang('System.print.print')) ?></button>
    <a href="<?= esc($back, 'attr') ?>"><?= esc(lang('System.print.back')) ?></a>
</div>
<div class="paper">
    <header>
        <div>
            <div class="business"><?= esc($businessName) ?></div>
            <h1><?= esc($title) ?></h1>
            <div><?= esc($subtitle) ?></div>
        </div>
        <div class="meta">
            <div data-printed-at data-template="<?= esc(lang('System.print.printed-at', ['{0}']), 'attr') ?>"></div>
            <?php if ('' !== $printedBy) : ?><div><?= esc(lang('System.print.printed-by', [$printedBy])) ?></div><?php endif; ?>
        </div>
    </header>
    <?= $this->renderSection('content') ?>
</div>
<script>
(function () {
    // the time it is printed, on the clock of whoever prints it
    var at = document.querySelector('[data-printed-at]');
    at.textContent = at.dataset.template.replace('{0}', new Date().toLocaleString(document.documentElement.lang || undefined,
        {dateStyle: 'medium', timeStyle: 'short'}));
    document.querySelector('[data-print]').addEventListener('click', function () { window.print(); });
})();
</script>
</body>
</html>
