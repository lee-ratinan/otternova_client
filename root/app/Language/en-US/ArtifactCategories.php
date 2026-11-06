<?php
return [
    'list'              => [
        'title'          => 'Categories',
        'subtitle'       => 'The categories that group the products and services of {0}, in the order customers see them.',
        'add'            => 'Add New Category',
        'load-failed'    => 'Could not load the categories. Please try again.',
        'artifact-count' => 'Products and Services',
    ],
    'form'              => [
        'create-title' => 'Add a Category',
        'edit-title'   => 'Edit Category',
        'subtitle'     => 'A category of the products and services of {0}. Fields marked with * are required',
        'saved'        => 'Category saved.',
        'created'      => 'Category created.',
        'failed'       => 'Could not save the category. Please try again.',
        'not-found'    => 'Category not found.',
    ],
    'fields'            => [
        'category_name'   => 'Name',
        'category_status' => 'Status',
        'sort_order'      => 'Order',
    ],
    'enum'              => [
        'category_status' => ['A' => 'Active', 'I' => 'Inactive'],
    ],
    'heading'           => [
        'category'     => 'Category',
        'translations' => 'Category Name in Other Languages',
    ],
    'help'              => [
        'status'     => 'Categories cannot be deleted; an inactive one is taken out of use.',
        'sort-order' => 'Lower numbers come first.',
    ],
    'translation-label' => 'Name in {0}',
];
