<?php
return [
    'title'          => 'Reviews',
    'subtitle'       => 'What customers say about the products and services of {0}. Approve a review to show it to other customers and count it towards the rating; hide one to take it down.',
    'load-failed'    => 'Could not load the reviews. Please try again.',
    'action-failed'  => 'Could not save the review. Please try again.',
    'approve'        => 'Approve',
    'hide'           => 'Hide',
    'replied'        => 'Reply:',
    'summary'        => [
        'title'    => 'Rating of {0}',
        'subtitle' => 'All products and services together, approved reviews only.',
        'count'    => '{0} approved reviews',
        'none'     => 'No approved reviews yet',
        'stars'    => '{0} stars',
        'heading'  => 'Rating',
    ],
    'column'         => [
        'created_at'    => 'Date',
        'artifact_name' => 'Product or Service',
        'customer_name' => 'Customer',
        'rating'        => 'Rating',
        'review'        => 'Review',
        'review_status' => 'Status',
        'actions'       => 'Actions',
    ],
    'filter'         => [
        'review_status' => 'Status',
    ],
    'enum'           => [
        'review_status' => ['P' => 'Pending', 'A' => 'Approved', 'H' => 'Hidden'],
    ],
];
