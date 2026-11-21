<?php
return [
    'fields' => [
        'account_name'  => 'Nama Akun',
        'account_type'  => 'Jenis Akun',
        'currency_code' => 'Mata Uang',
        'account_class' => 'Kelas Akun',
    ],
    'enum'   => [
        'account_type'  => ['I' => 'Internal', 'X' => 'Eksternal'],
        'account_class' => ['A' => 'Aset', 'L' => 'Liabilitas', 'E' => 'Ekuitas', 'R' => 'Pendapatan', 'X' => 'Beban'],
    ],
];
