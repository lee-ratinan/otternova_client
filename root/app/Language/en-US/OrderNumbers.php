<?php

// App\Controllers\OrderNumbers: how the business's order numbers look
return [
    'title'             => 'Order Numbers',
    'subtitle'          => 'How the orders of {0} are numbered. Orders numbered already keep their numbers.',
    'prefix'            => 'Prefix',
    'prefix-help'       => 'Up to 4 letters, A to Z. Leave it empty for none.',
    'none'              => 'None',
    'date-part'         => 'Date part',
    'date-part-help'    => 'The date shown after the prefix, on the business\'s clock.',
    'date-part-options' => ['N' => 'None', 'Y' => 'Year (26)', 'M' => 'Year and month (2611)', 'D' => 'Year, month and day (261113)'],
    'reset'             => 'Start again from 1',
    'reset-help'        => 'When the running number goes back to 1. It cannot be more often than the date part shows.',
    'reset-options'     => ['N' => 'Never', 'Y' => 'Every year', 'M' => 'Every month', 'D' => 'Every day'],
    'digits'            => 'Running number',
    'digits-help'       => 'Its least width; a larger number simply gets longer.',
    'digits-option'     => '{0} digits ({1})',
    'next'              => 'Next order number',
    'next-help'         => 'The number the next order will get.',
    'example-help'      => 'How the first number of a period will look once saved.',
    'rule'              => 'The numbers cannot start again more often than the date part shows: a daily reset needs the day, a monthly reset the month, a yearly reset a date part.',
    'saved'             => 'Order number settings saved.',
    'failed'            => 'Could not save the order number settings. Please try again.',
    'load-failed'       => 'Could not load the order number settings. Please try again.',
];
