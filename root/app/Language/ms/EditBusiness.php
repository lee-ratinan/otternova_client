<?php
return [
    'form'          => [
        'title'    => 'Butiran Perniagaan',
        'subtitle' => 'Butiran {0}. Medan bertanda * adalah wajib',
        'saved'    => 'Perniagaan disimpan.',
        'failed'   => 'Tidak dapat menyimpan perniagaan. Sila cuba lagi.',
    ],
    'help'          => [
        'business-slug' => 'Pengenal perniagaan anda, digunakan dalam pautan ke kedai dalam talian anda. Gunakan huruf kecil, nombor dan sempang, cth. otter-cafe atau คาเฟ่ออทเทอร์. Alamat kedai anda: {0}',
        'fixed'         => 'Tidak boleh ditukar selepas perniagaan dicipta.',
    ],
    'slug-confirm'  => [
        'title'   => 'Tukar nama URL perniagaan?',
        'text'    => 'URL kedai anda akan ditukar serta-merta, daripada {0} kepada {1}. Pautan ke alamat lama akan berhenti berfungsi. Sila sahkan!',
        'confirm' => 'Ya, tukar',
        'cancel'  => 'Batal',
    ],
    'managers-only' => 'Hanya pemilik dan pengurus perniagaan ini boleh mengurus perniagaan dan kedainya.',
    // whether the business shows on OtterNovaMart (partials/business_open, Admin::businessOpenPost()): owners only
    'open'          => [
        'title'         => 'Buka di OtterNovaMart',
        'subtitle'      => 'Sama ada pelanggan boleh mencari dan membuat pesanan dari kedai anda. Hanya pemilik boleh menukarnya.',
        'state-open'    => 'Buka: pelanggan boleh mencari kedai anda dan membuat pesanan',
        'state-closed'  => 'Tutup: kedai anda disembunyikan',
        'help'          => 'Semasa ditutup, kedai anda tidak muncul dalam carian, halamannya menyatakan ia belum dibuka, dan tiada pesanan boleh dibuat. Pesanan sedia ada masih boleh disemak dan dibayar.',
        'paid-warning'  => 'Anda menggunakan pelan berbayar. Menutup kedai anda tidak menjeda atau melanjutkan langganan anda: ia diperbaharui pada tarikh yang sama, dan pelan dibilkan seperti biasa.',
        'view-shop'     => 'Lihat kedai anda',
        'opened'        => 'Kedai anda kini dibuka di OtterNovaMart.',
        'closed'        => 'Kedai anda kini ditutup di OtterNovaMart.',
        'failed'        => 'Tidak dapat menukar status buka kedai anda. Sila cuba lagi.',
        'confirm-close' => 'Tutup kedai anda? Pelanggan tidak akan dapat mencarinya atau membuat pesanan sehingga anda membukanya semula.',
        'confirm-yes'   => 'Ya, tutup',
        'confirm-no'    => 'Kekal dibuka',
        'owner-only'    => 'Hanya pemilik perniagaan ini boleh membuka atau menutupnya.',
    ],
];
