<?php
return [
    'fields' => [
        'account_name'  => 'Nama Akaun',
        'account_type'  => 'Jenis Akaun',
        'currency_code' => 'Mata Wang',
        'account_class' => 'Kelas Akaun',
    ],
    'enum'   => [
        'account_type'  => ['I' => 'Dalaman', 'X' => 'Luaran'],
        'account_class' => ['A' => 'Aset', 'L' => 'Liabiliti', 'E' => 'Ekuiti', 'R' => 'Hasil', 'X' => 'Perbelanjaan'],
    ],
];
