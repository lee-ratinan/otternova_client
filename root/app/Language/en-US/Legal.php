<?php

/**
 * The Terms of Service and Privacy Policy acceptance page (admin/legal/accept); see App\Controllers\Legal
 */
return [
    'document' => [
        'T' => 'Terms of Service',
        'P' => 'Privacy Policy',
    ],
    'accept'   => [
        'title'          => 'We have updated our terms',
        'subtitle'       => 'Please review and accept the documents below to continue using OtterNova.',
        'version'        => 'Version {0}',
        'read-full'      => 'Open the full document in a new tab',
        'agree'          => 'I have read and agree to the documents above',
        'agree-required' => 'Please tick the box to accept the documents.',
        'submit'         => 'Accept and continue',
        'decline'        => 'Do not agree?',
        'sign-out'       => 'Sign out',
        'required'       => 'Please accept the updated Terms of Service and Privacy Policy first.',
        'outdated'       => 'The documents were updated again while this page was open. The page will now reload so you can review the latest version.',
        'failed'         => 'Your acceptance could not be saved. Please try again.',
    ],
];
