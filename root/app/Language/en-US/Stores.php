<?php
return [
    'list'              => [
        'title'       => 'Stores',
        'subtitle'    => 'The stores of {0}: physical branches and online stores.',
        'add'         => 'Add New Store',
        'load-failed' => 'Could not load the stores. Please try again.',
        'limit-reached' => 'The {1} plan allows up to {0} stores. Please upgrade your plan to add more.',
        'column'      => [
            'store_name'         => 'Store',
            'store_type'         => 'Type',
            'store_phone_number' => 'Phone Number',
            'location'           => 'Location',
        ],
    ],
    'form'              => [
        'create-title' => 'Add a Store',
        'edit-title'   => 'Edit Store',
        'subtitle'    => 'A store of {0}. Fields marked with * are required',
        'saved'        => 'Store saved.',
        'created'      => 'Store created.',
        'failed'       => 'Could not save the store. Please try again.',
        'not-found'    => 'Store not found.',
    ],
    'heading'           => [
        'store'        => 'Store',
        'address'      => 'Address',
        'receipt'      => 'Receipts',
        'translation'  => 'In {0}',
    ],
    'help'              => [
        'store-type'     => 'An online store has no address.',
        'store-timezone' => 'The opening hours of the store are in this timezone.',
        'translation-name' => 'Needed to save the address in this language.',
        'receipt-format' => 'The paper of this store\'s receipt printer. Leave it empty if the store has no printer; then no receipts are printed here.',
        'receipt-footer' => 'Printed at the bottom of every receipt, such as a thank-you or the return policy.',
    ],
    'hours-link'        => [
        'title' => 'Opening Hours',
        'text'  => 'The regular weekly hours of this store, and the dates it closes or opens at other times.',
        'open'  => 'Set Opening Hours',
    ],
    'translation'       => [
        'store_name'           => 'Store Name',
        'store_address_line_1' => 'Address Line 1',
        'store_address_line_2' => 'Address Line 2',
        'store_city'           => 'City',
        'receipt_footer'       => 'Receipt Footer',
    ],
    'receipt'           => [
        'no-printer' => 'No printer',
    ],
];
