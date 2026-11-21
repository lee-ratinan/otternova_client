<?php
// Subject and body of each EmailService::TYPE_* email; {placeholders} come from the $data passed to send()
return [
    'login_otp' => [
        'subject' => 'Kod log masuk OtterNova anda',
        'body'    => "Helo {full_name},\n\nKod log masuk anda ialah {otp}. Ia tamat tempoh dalam {minutes} minit.\n\nJika anda tidak cuba log masuk, sila tukar kata laluan anda.",
    ],
    'account_activation' => [
        'subject' => 'Aktifkan akaun OtterNova anda',
        'body'    => "Helo {full_name},\n\nTerima kasih kerana mendaftar dengan OtterNova. Sila buka pautan ini untuk mengaktifkan akaun anda:\n\n{link}\n\nPautan ini tamat tempoh dalam {hours} jam. Jika anda tidak mendaftar, anda boleh abaikan e-mel ini.",
    ],
    'account_exists'     => [
        'subject' => 'Akaun OtterNova anda',
        'body'    => "Helo {full_name},\n\nSeseorang cuba mendaftar dengan OtterNova menggunakan alamat e-mel ini, tetapi ia sudah mempunyai akaun. Anda boleh log masuk di sini:\n\n{link}\n\nJika anda terlupa kata laluan, gunakan \"Lupa Kata Laluan\" di halaman log masuk. Jika itu bukan anda, anda boleh abaikan e-mel ini.",
    ],
    'password_reset'     => [
        'subject' => 'Tetapkan semula kata laluan OtterNova anda',
        'body'    => "Helo {full_name},\n\nKami menerima permintaan untuk menetapkan semula kata laluan akaun OtterNova anda. Sila buka pautan ini untuk memilih kata laluan baharu:\n\n{link}\n\nPautan ini tamat tempoh dalam {minutes} minit dan hanya boleh digunakan sekali. Jika anda tidak memintanya, anda boleh abaikan e-mel ini: kata laluan anda kekal sama.",
    ],
];
