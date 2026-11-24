<?php
return [
    'form'          => [
        'title'    => 'Business Details',
        'subtitle' => 'The details of {0}. Fields marked with * are required',
        'saved'    => 'Business saved.',
        'failed'   => 'Could not save the business. Please try again.',
    ],
    'help'          => [
        'business-slug' => 'The identifier of your business, used in the link to your online storefront. Use lowercase letters, numbers and hyphens, e.g. otter-cafe or คาเฟ่ออทเทอร์. Your store address: {0}',
        'fixed'         => 'Cannot be changed once the business is created.',
    ],
    'slug-confirm'  => [
        'title'   => 'Change the business URL name?',
        'text'    => 'The URL to your store will be changed immediately, from {0} to {1}. Links to the old address will stop working. Please confirm!',
        'confirm' => 'Yes, change it',
        'cancel'  => 'Cancel',
    ],
    'managers-only' => 'Only the owners and managers of this business can manage it and its stores.',
    // whether the business shows on OtterNovaMart (partials/business_open, Admin::businessOpenPost()): owners only
    'open'          => [
        'title'         => 'Open on OtterNovaMart',
        'subtitle'      => 'Whether customers can find and order from your shop. Only owners can change this.',
        'state-open'    => 'Open: customers can find your shop and order',
        'state-closed'  => 'Closed: your shop is hidden',
        'help'          => 'While closed, your shop is left out of the search, its pages say it is not open yet, and no orders can be placed. Orders placed already can still be checked and paid.',
        'paid-warning'  => 'You are on a paid plan. Closing your shop does not pause or extend your subscription: it renews on the same date, and the plan is billed as usual.',
        'view-shop'     => 'View your shop',
        'opened'        => 'Your shop is open on OtterNovaMart.',
        'closed'        => 'Your shop is closed on OtterNovaMart.',
        'failed'        => 'Could not change whether your shop is open. Please try again.',
        'confirm-close' => 'Close your shop? Customers will not be able to find it or place orders until you open it again.',
        'confirm-yes'   => 'Yes, close it',
        'confirm-no'    => 'Keep it open',
        'owner-only'    => 'Only the owners of this business can open or close it.',
    ],
];
