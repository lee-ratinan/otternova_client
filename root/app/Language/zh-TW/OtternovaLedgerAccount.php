<?php
return [
    'fields' => [
        'account_name'  => '科目名稱',
        'account_type'  => '科目類型',
        'currency_code' => '貨幣',
        'account_class' => '科目類別',
    ],
    'enum'   => [
        'account_type'  => ['I' => '內部', 'X' => '外部'],
        'account_class' => ['A' => '資產', 'L' => '負債', 'E' => '權益', 'R' => '收入', 'X' => '費用'],
    ],
];
