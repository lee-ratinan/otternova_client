<?php
return [
    'title'          => 'Ulasan',
    'subtitle'       => 'Apa kata pelanggan tentang produk dan layanan {0}. Setujui ulasan untuk menampilkannya kepada pelanggan lain dan menghitungnya dalam penilaian; sembunyikan untuk menurunkannya.',
    'load-failed'    => 'Tidak dapat memuat ulasan. Silakan coba lagi.',
    'action-failed'  => 'Tidak dapat menyimpan ulasan. Silakan coba lagi.',
    'approve'        => 'Setujui',
    'hide'           => 'Sembunyikan',
    'replied'        => 'Balasan:',
    'summary'        => [
        'title'    => 'Penilaian {0}',
        'subtitle' => 'Semua produk dan layanan, hanya ulasan yang disetujui.',
        'count'    => '{0} ulasan disetujui',
        'none'     => 'Belum ada ulasan yang disetujui',
        'stars'    => '{0} bintang',
        'heading'  => 'Penilaian',
    ],
    'column'         => [
        'created_at'    => 'Tanggal',
        'artifact_name' => 'Produk atau Layanan',
        'customer_name' => 'Pelanggan',
        'rating'        => 'Penilaian',
        'review'        => 'Ulasan',
        'review_status' => 'Status',
        'actions'       => 'Tindakan',
    ],
    'filter'         => [
        'review_status' => 'Status',
    ],
    'enum'           => [
        'review_status' => ['P' => 'Menunggu', 'A' => 'Disetujui', 'H' => 'Disembunyikan'],
    ],
];
