<?php
return [
    'all-stores'  => 'All stores',
    'list'        => [
        'title'       => 'Payment Methods',
        'subtitle'    => 'How the customers of {0} can pay',
        'add'         => 'Add Payment Method',
        'load-failed' => 'Could not load the payment methods. Please try again.',
    ],
    'form'        => [
        'create-title' => 'Add Payment Method',
        'edit-title'   => 'Payment Method',
        'subtitle'     => 'A way the customers of {0} can pay',
        'not-found'    => 'Payment method not found.',
        'created'      => 'Payment method added.',
        'saved'        => 'Payment method saved.',
        'failed'       => 'Could not save the payment method. Please try again.',
    ],
    'heading'     => [
        'method'      => 'Payment Method',
        'translation' => 'In {0}',
    ],
    'fields'      => [
        'store'        => 'Store',
        'method_code'  => 'Customers Pay By',
        'handler_code' => 'Handled By',
        'promptpay_id' => 'PromptPay ID',
        'method_name'  => 'Name',
        'instruction'  => 'Instruction',
        'is_active'    => 'Status',
        'sort_order'   => 'Sort Order',
    ],
    'translation' => [
        'method_name' => 'Name in {0}',
        'instruction' => 'Instruction in {0}',
    ],
    'enum'        => [
        'method'    => ['cash' => 'Cash', 'bank_transfer' => 'Bank Transfer', 'promptpay' => 'PromptPay', 'other' => 'Other'],
        'handler'   => ['instruction' => 'Show the instruction', 'promptpay_qr' => 'PromptPay QR code'],
        'is_active' => ['Y' => 'Active', 'N' => 'Inactive'],
    ],
    'help'        => [
        'store'            => 'All stores: offered at every store of the business. Pick a store to offer it there only, such as a bank account of that branch.',
        'method-code'      => 'What the customer pays with.',
        'handler-code'     => 'How the customer is told to pay. A PromptPay QR code has the amount in it; your staff still check the payment.',
        'promptpay-id'     => 'The mobile number (10 digits), national or tax ID (13 digits) or e-wallet ID (15 digits) the QR code pays.',
        'method-name'      => 'What customers see, such as "Cash" or "Bank Transfer – KBank".',
        'instruction'      => 'What customers are told, such as "Bring cash and pay at the counter before the service" or the bank, account number and account name.',
        'is-active'        => 'Inactive methods are not offered to customers.',
        'sort-order'       => 'Lower numbers are shown first.',
        'translation-name' => 'Left empty, the name above is shown in this language.',
    ],
];
