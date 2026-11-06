<?php
/**
 * Generic form view.
 *
 * Expects ONE variable from the controller:  $formConfig  (see the structure documented in
 * app/Helpers/form_helper.php, or the sample in FormExample.php).
 *
 *   return view('generic_form', ['formConfig' => $formConfig]);
 *
 * Optional: $extraForms, more form configs rendered below it, each with its own card and script
 * (e.g. the stand-alone upload forms of 'file' fields).
 * Optional: $extraHtml, markup of the page's own rendered below the forms (e.g. a card the form builder cannot make).
 * It is output as given: render it from a view of its own, so values reach it escaped.
 * Optional: $customScript, the page's own JavaScript (without <script> tags), added after the form builder's
 * scripts so window.FormBuilder[form_id] is there. Render it from a view of its own, so values reach it escaped:
 *
 *   'customScript' => view('scripts/create_business', ['urls' => [...]]),
 *
 * It is output as given: never put user input in it unescaped.
 *
 * Requirements in _layout: Bootstrap 5, Bootstrap Icons, toastr (JS + CSS).
 * Optional: SweetAlert2 (window.Swal) for a nicer delete confirmation; falls back to confirm().
 */
$this->extend('_layout_admin');
helper('form'); // loads app/Helpers/form_helper.php (all functions are prefixed fb_)
?>
<?= $this->section('content') ?>
    <section class="section">
        <div class="row g-4">
            <div class="col-12">

                <?php // Whole card: header + create/edit/view fields + buttons, all from the config ?>
                <?= fb_render($formConfig) ?>

                <?php foreach ($extraForms ?? [] as $extraForm): ?>
                    <div class="mt-4"><?= fb_render($extraForm) ?></div>
                <?php endforeach ?>

                <?= $extraHtml ?? '' ?>

                <?php
                /* ---------------------------------------------------------------------------
                 * OPTIONAL: the same form built from the small functions instead of fb_render().
                 * Use this when you need a custom layout. Every piece can be called on its own and
                 * repeatedly (e.g. inside a loop, or to add extra rows between sections).
                 *
                 *   $cfg = fb_defaults($formConfig);
                 *   <div class="card">
                 *       <?= fb_header($cfg) ?>
                 *       <div class="card-body">
                 *           <?= fb_form_open($cfg) ?>
                 *           <?= fb_hidden_fields($cfg) ?>
                 *
                 *           <div data-fb-pane="edit">
                 *               <?= fb_rows($cfg, 'edit') ?>                    // every field
                 *               // or one field at a time:
                 *               <?php foreach (fb_fields($cfg, 'edit') as $f): ?>
                 *                   <?= fb_edit_row($f, $cfg) ?>               // label + control + help + error
                 *                   // <?= fb_label($f, $cfg) . fb_control($f) . fb_help($f) ?>
                 *               <?php endforeach ?>
                 *               <?= fb_buttons_edit($cfg) ?>
                 *           </div>
                 *
                 *           <div data-fb-pane="view">
                 *               <?= fb_rows($cfg, 'view') ?>
                 *               <?= fb_buttons_view($cfg) ?>
                 *           </div>
                 *       </form></div>
                 *   </div>
                 * ------------------------------------------------------------------------- */
                ?>

            </div>
        </div>
    </section>

<?php // Generates the JavaScript: validation on change, submit via fetch, toastr messages,
// view <-> edit switching, delete confirmation and reset. ?>
<?= fb_script($formConfig) ?>
<?php foreach ($extraForms ?? [] as $extraForm): ?>
    <?= fb_script($extraForm) ?>
<?php endforeach ?>
<?php if (! empty($customScript)): ?>
<script>
<?= $customScript ?>
</script>
<?php endif ?>
<?= $this->endSection() ?>