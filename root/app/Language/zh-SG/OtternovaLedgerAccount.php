<?php
return [
    'fields' => [
        'account_name'  => '账户名称',
        'account_type'  => '账户类型',
        'currency_code' => '货币',
        'account_class' => '账户类别',
    ],
    'enum'   => [
        'account_type'  => ['I' => '内部', 'X' => '外部'],
        'account_class' => ['A' => '资产', 'L' => '负债', 'E' => '权益', 'R' => '收入', 'X' => '支出'],
    ],
];
