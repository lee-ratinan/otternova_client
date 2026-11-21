<?php
return [
    'fields' => [
        'account_name'  => '勘定名',
        'account_type'  => '勘定種別',
        'currency_code' => '通貨',
        'account_class' => '勘定区分',
    ],
    'enum'   => [
        'account_type'  => ['I' => '内部', 'X' => '外部'],
        'account_class' => ['A' => '資産', 'L' => '負債', 'E' => '純資産', 'R' => '収益', 'X' => '費用'],
    ],
];
