<?php

// App\Controllers\OrderNumbers: how the business's order numbers look
return [
    'title'             => 'Nombor Pesanan',
    'subtitle'          => 'Cara pesanan {0} dinomborkan. Pesanan yang sudah bernombor mengekalkan nombornya.',
    'prefix'            => 'Awalan',
    'prefix-help'       => 'Sehingga 4 huruf, A hingga Z. Biarkan kosong jika tiada.',
    'none'              => 'Tiada',
    'date-part'         => 'Bahagian tarikh',
    'date-part-help'    => 'Tarikh yang dipaparkan selepas awalan, mengikut waktu perniagaan.',
    'date-part-options' => ['N' => 'Tiada', 'Y' => 'Tahun (26)', 'M' => 'Tahun dan bulan (2611)', 'D' => 'Tahun, bulan dan hari (261113)'],
    'reset'             => 'Mula semula dari 1',
    'reset-help'        => 'Bila nombor berjalan kembali ke 1. Ia tidak boleh lebih kerap daripada yang ditunjukkan oleh bahagian tarikh.',
    'reset-options'     => ['N' => 'Tidak pernah', 'Y' => 'Setiap tahun', 'M' => 'Setiap bulan', 'D' => 'Setiap hari'],
    'digits'            => 'Nombor berjalan',
    'digits-help'       => 'Lebar minimumnya; nombor yang lebih besar hanya menjadi lebih panjang.',
    'digits-option'     => '{0} digit ({1})',
    'next'              => 'Nombor pesanan seterusnya',
    'next-help'         => 'Nombor yang akan diberikan kepada pesanan seterusnya.',
    'example-help'      => 'Rupa nombor pertama dalam satu tempoh selepas disimpan.',
    'rule'              => 'Nombor tidak boleh dimulakan semula lebih kerap daripada yang ditunjukkan oleh bahagian tarikh: set semula harian memerlukan hari, set semula bulanan memerlukan bulan, set semula tahunan memerlukan bahagian tarikh.',
    'saved'             => 'Tetapan nombor pesanan disimpan.',
    'failed'            => 'Tidak dapat menyimpan tetapan nombor pesanan. Sila cuba lagi.',
    'load-failed'       => 'Tidak dapat memuatkan tetapan nombor pesanan. Sila cuba lagi.',
];
