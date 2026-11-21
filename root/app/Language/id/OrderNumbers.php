<?php

// App\Controllers\OrderNumbers: how the business's order numbers look
return [
    'title'             => 'Nomor Pesanan',
    'subtitle'          => 'Cara penomoran pesanan {0}. Pesanan yang sudah bernomor tetap menggunakan nomornya.',
    'prefix'            => 'Awalan',
    'prefix-help'       => 'Hingga 4 huruf, A sampai Z. Kosongkan jika tidak ada.',
    'none'              => 'Tidak ada',
    'date-part'         => 'Bagian tanggal',
    'date-part-help'    => 'Tanggal yang ditampilkan setelah awalan, menurut jam bisnis.',
    'date-part-options' => ['N' => 'Tidak ada', 'Y' => 'Tahun (26)', 'M' => 'Tahun dan bulan (2611)', 'D' => 'Tahun, bulan, dan hari (261113)'],
    'reset'             => 'Mulai lagi dari 1',
    'reset-help'        => 'Kapan nomor urut kembali ke 1. Tidak boleh lebih sering daripada yang ditunjukkan bagian tanggal.',
    'reset-options'     => ['N' => 'Tidak pernah', 'Y' => 'Setiap tahun', 'M' => 'Setiap bulan', 'D' => 'Setiap hari'],
    'digits'            => 'Nomor urut',
    'digits-help'       => 'Lebar minimumnya; angka yang lebih besar akan menjadi lebih panjang.',
    'digits-option'     => '{0} digit ({1})',
    'next'              => 'Nomor pesanan berikutnya',
    'next-help'         => 'Nomor yang akan didapat pesanan berikutnya.',
    'example-help'      => 'Tampilan nomor pertama dalam satu periode setelah disimpan.',
    'rule'              => 'Nomor tidak dapat dimulai ulang lebih sering daripada yang ditunjukkan bagian tanggal: reset harian memerlukan hari, reset bulanan memerlukan bulan, reset tahunan memerlukan bagian tanggal.',
    'saved'             => 'Pengaturan nomor pesanan disimpan.',
    'failed'            => 'Tidak dapat menyimpan pengaturan nomor pesanan. Silakan coba lagi.',
    'load-failed'       => 'Tidak dapat memuat pengaturan nomor pesanan. Silakan coba lagi.',
];
