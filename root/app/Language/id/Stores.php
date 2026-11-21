<?php
return [
    'list'              => [
        'title'       => 'Toko',
        'subtitle'    => 'Toko {0}: cabang fisik dan toko online.',
        'add'         => 'Tambah Toko Baru',
        'load-failed' => 'Tidak dapat memuat toko. Silakan coba lagi.',
        'limit-reached' => 'Paket {1} memungkinkan hingga {0} toko. Silakan tingkatkan paket Anda untuk menambah lebih banyak.',
        'column'      => [
            'store_name'         => 'Toko',
            'store_type'         => 'Jenis',
            'store_phone_number' => 'Nomor Telepon',
            'location'           => 'Lokasi',
        ],
    ],
    'form'              => [
        'create-title' => 'Tambah Toko',
        'edit-title'   => 'Ubah Toko',
        'subtitle'    => 'Toko {0}. Kolom bertanda * wajib diisi',
        'saved'        => 'Toko disimpan.',
        'created'      => 'Toko dibuat.',
        'failed'       => 'Tidak dapat menyimpan toko. Silakan coba lagi.',
        'not-found'    => 'Toko tidak ditemukan.',
    ],
    'heading'           => [
        'store'        => 'Toko',
        'address'      => 'Alamat',
        'receipt'      => 'Struk',
        'translation'  => 'Dalam {0}',
    ],
    'help'              => [
        'store-type'     => 'Toko online tidak memiliki alamat.',
        'store-timezone' => 'Jam buka toko mengikuti zona waktu ini.',
        'translation-name' => 'Diperlukan untuk menyimpan alamat dalam bahasa ini.',
        'receipt-format' => 'Kertas printer struk toko ini. Kosongkan jika toko tidak memiliki printer; maka tidak ada struk yang dicetak di sini.',
        'receipt-footer' => 'Dicetak di bagian bawah setiap struk, seperti ucapan terima kasih atau kebijakan pengembalian.',
        'google-maps-url' => 'Opsional. Di Google Maps, buka toko, pilih Bagikan, lalu salin tautannya (https://maps.app.goo.gl/...). Ini adalah tombol "Buka di Google Maps" di halaman Hubungi Kami toko Anda; tanpanya, tombol tersebut mencari alamat di Google Maps.',
    ],
    'hours-link'        => [
        'title' => 'Jam Buka',
        'text'  => 'Jam mingguan reguler toko ini, serta tanggal toko tutup atau buka pada jam lain.',
        'open'  => 'Atur Jam Buka',
    ],
    'translation'       => [
        'store_name'           => 'Nama Toko',
        'store_address_line_1' => 'Alamat Baris 1',
        'store_address_line_2' => 'Alamat Baris 2',
        'store_city'           => 'Kota',
        'receipt_footer'       => 'Catatan Kaki Struk',
    ],
    'receipt'           => [
        'no-printer' => 'Tanpa printer',
    ],
];
