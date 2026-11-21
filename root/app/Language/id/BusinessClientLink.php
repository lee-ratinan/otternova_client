<?php
return [
    'fields' => [
        'business_id'         => 'Bisnis',
        'client_id'           => 'Klien',
        'client_status'       => 'Status',
        'client_role'         => 'Peran',
        'is_default_business' => 'Bisnis Utama',
        'start_date'          => 'Tanggal Mulai',
        'termination_date'    => 'Tanggal Berakhir',
        'owner_notes'         => 'Catatan Pemilik',
    ],
    'enum'   => [
        'client_status'       => ['A' => 'Aktif', 'T' => 'Diakhiri'],
        'client_role'         => ['O' => 'Pemilik', 'M' => 'Manajer', 'E' => 'Karyawan'],
        'is_default_business' => ['Y' => 'Ya', 'N' => 'Tidak'],
    ],
];
