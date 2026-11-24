<?php
return [
    'form'          => [
        'title'    => 'Detail Bisnis',
        'subtitle' => 'Detail {0}. Kolom bertanda * wajib diisi',
        'saved'    => 'Bisnis disimpan.',
        'failed'   => 'Tidak dapat menyimpan bisnis. Silakan coba lagi.',
    ],
    'help'          => [
        'business-slug' => 'Pengenal bisnis Anda, digunakan dalam tautan ke toko online Anda. Gunakan huruf kecil, angka, dan tanda hubung, mis. otter-cafe atau คาเฟ่ออทเทอร์. Alamat toko Anda: {0}',
        'fixed'         => 'Tidak dapat diubah setelah bisnis dibuat.',
    ],
    'slug-confirm'  => [
        'title'   => 'Ubah nama URL bisnis?',
        'text'    => 'URL toko Anda akan langsung berubah dari {0} menjadi {1}. Tautan ke alamat lama tidak akan berfungsi lagi. Silakan konfirmasi!',
        'confirm' => 'Ya, ubah',
        'cancel'  => 'Batal',
    ],
    'managers-only' => 'Hanya pemilik dan manajer bisnis ini yang dapat mengelola bisnis dan tokonya.',
    // whether the business shows on OtterNovaMart (partials/business_open, Admin::businessOpenPost()): owners only
    'open'          => [
        'title'         => 'Buka di OtterNovaMart',
        'subtitle'      => 'Apakah pelanggan dapat menemukan dan memesan dari toko Anda. Hanya pemilik yang dapat mengubahnya.',
        'state-open'    => 'Buka: pelanggan dapat menemukan toko Anda dan memesan',
        'state-closed'  => 'Tutup: toko Anda disembunyikan',
        'help'          => 'Selama tutup, toko Anda tidak muncul di pencarian, halamannya menyatakan belum buka, dan tidak ada pesanan yang dapat dibuat. Pesanan yang sudah ada masih dapat diperiksa dan dibayar.',
        'paid-warning'  => 'Anda menggunakan paket berbayar. Menutup toko tidak menjeda atau memperpanjang langganan Anda: langganan diperpanjang pada tanggal yang sama, dan paket ditagih seperti biasa.',
        'view-shop'     => 'Lihat toko Anda',
        'opened'        => 'Toko Anda kini buka di OtterNovaMart.',
        'closed'        => 'Toko Anda kini tutup di OtterNovaMart.',
        'failed'        => 'Tidak dapat mengubah status buka toko Anda. Silakan coba lagi.',
        'confirm-close' => 'Tutup toko Anda? Pelanggan tidak akan dapat menemukannya atau memesan sampai Anda membukanya lagi.',
        'confirm-yes'   => 'Ya, tutup',
        'confirm-no'    => 'Tetap buka',
        'owner-only'    => 'Hanya pemilik bisnis ini yang dapat membuka atau menutupnya.',
    ],
];
