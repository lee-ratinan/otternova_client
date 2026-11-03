<?php
/**
 * Generic list view (DataTables, server-side).
 *
 * Expects ONE variable from the controller:  $listConfig  (structure documented in
 * app/Helpers/list_helper.php, sample in ListExample.php).
 *
 *   return view('generic_list', ['listConfig' => $listConfig]);
 *
 * Requirements in _layout: jQuery, DataTables js + css, Bootstrap 5, Bootstrap Icons.
 * toastr is optional (used to show data-loading errors).
 */
$this->extend('_layout');
?>
<?= $this->section('content') ?>
    <section class="section">
        <div class="row g-4">
            <div class="col-12">

                <?php // Card: title/subtitle, Add button (only if add.url is set), filters (only if any), table ?>
                <?= lb_render($listConfig) ?>

                <?php
                /* ---------------------------------------------------------------------------
                 * OPTIONAL: the same page built from the small functions, for a custom layout.
                 *
                 *   <div class="card">
                 *       <?= lb_header($listConfig) ?>
                 *       <div class="card-body">
                 *           <?= lb_add_button($listConfig) ?>        // '' when add.url is empty
                 *           <?= lb_filters($listConfig) ?>           // '' when no filters were passed
                 *           <?= lb_table($listConfig) ?>             // first <th> = edit button column
                 *       </div>
                 *   </div>
                 * ------------------------------------------------------------------------- */
                ?>

            </div>
        </div>
    </section>

<?php // Generates the JavaScript: DataTables server-side init, the edit-button first column
// (URL taken from the JSON), and reloading the data whenever a filter changes. ?>
<?= lb_script($listConfig) ?>
<?= $this->endSection() ?>