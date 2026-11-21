<?php
return [
    'all-stores'  => 'Semua kedai',
    'list'        => [
        'title'       => 'Kaedah Pembayaran',
        'subtitle'    => 'Cara pelanggan {0} boleh membayar',
        'add'         => 'Tambah Kaedah Pembayaran',
        'load-failed' => 'Tidak dapat memuatkan kaedah pembayaran. Sila cuba lagi.',
    ],
    'form'        => [
        'create-title' => 'Tambah Kaedah Pembayaran',
        'edit-title'   => 'Kaedah Pembayaran',
        'subtitle'     => 'Cara pelanggan {0} boleh membayar',
        'not-found'    => 'Kaedah pembayaran tidak ditemui.',
        'created'      => 'Kaedah pembayaran ditambah.',
        'saved'        => 'Kaedah pembayaran disimpan.',
        'failed'       => 'Tidak dapat menyimpan kaedah pembayaran. Sila cuba lagi.',
    ],
    'heading'     => [
        'method'      => 'Kaedah Pembayaran',
        'translation' => 'Dalam {0}',
    ],
    'fields'      => [
        'store'        => 'Kedai',
        'method_code'  => 'Pelanggan Membayar Dengan',
        'handler_code' => 'Dikendalikan Oleh',
        'promptpay_id' => 'ID PromptPay',
        'method_name'  => 'Nama',
        'instruction'  => 'Arahan',
        'is_active'    => 'Status',
        'sort_order'   => 'Susunan',
    ],
    'translation' => [
        'method_name' => 'Nama dalam {0}',
        'instruction' => 'Arahan dalam {0}',
    ],
    'enum'        => [
        'method'    => ['cash' => 'Tunai', 'bank_transfer' => 'Pindahan Bank', 'promptpay' => 'PromptPay', 'other' => 'Lain-lain'],
        'handler'   => ['instruction' => 'Papar arahan', 'promptpay_qr' => 'Kod QR PromptPay'],
        'is_active' => ['Y' => 'Aktif', 'N' => 'Tidak Aktif'],
    ],
    'help'        => [
        'store'            => 'Semua kedai: ditawarkan di setiap kedai perniagaan. Pilih kedai untuk menawarkannya di sana sahaja, seperti akaun bank cawangan itu.',
        'method-code'      => 'Apa yang digunakan pelanggan untuk membayar.',
        'handler-code'     => 'Bagaimana pelanggan diberitahu untuk membayar. Kod QR PromptPay mengandungi amaun; kakitangan anda masih menyemak pembayaran.',
        'promptpay-id'     => 'Nombor telefon bimbit (10 digit), nombor pengenalan atau nombor cukai (13 digit) atau ID e-dompet (15 digit) yang dibayar oleh kod QR.',
        'method-name'      => 'Apa yang dilihat pelanggan, seperti "Tunai" atau "Pindahan Bank – KBank".',
        'instruction'      => 'Apa yang diberitahu kepada pelanggan, seperti "Bawa wang tunai dan bayar di kaunter sebelum perkhidmatan" atau bank, nombor akaun dan nama akaun.',
        'is-active'        => 'Kaedah tidak aktif tidak ditawarkan kepada pelanggan.',
        'sort-order'       => 'Nombor yang lebih kecil dipaparkan dahulu.',
        'translation-name' => 'Jika dibiarkan kosong, nama di atas dipaparkan dalam bahasa ini.',
    ],
];
