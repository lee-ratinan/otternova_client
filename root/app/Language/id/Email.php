<?php
// Subject and body of each EmailService::TYPE_* email; {placeholders} come from the $data passed to send()
return [
    'login_otp' => [
        'subject' => 'Kode masuk OtterNova Anda',
        'body'    => "Halo {full_name},\n\nKode masuk Anda adalah {otp}. Kode ini berlaku selama {minutes} menit.\n\nJika Anda tidak mencoba masuk, silakan ubah kata sandi Anda.",
    ],
    'account_activation' => [
        'subject' => 'Aktifkan akun OtterNova Anda',
        'body'    => "Halo {full_name},\n\nTerima kasih telah mendaftar di OtterNova. Silakan buka tautan ini untuk mengaktifkan akun Anda:\n\n{link}\n\nTautan ini berlaku selama {hours} jam. Jika Anda tidak mendaftar, abaikan email ini.",
    ],
    'account_exists'     => [
        'subject' => 'Akun OtterNova Anda',
        'body'    => "Halo {full_name},\n\nSeseorang mencoba mendaftar di OtterNova dengan alamat email ini, tetapi alamat ini sudah memiliki akun. Anda dapat masuk di sini:\n\n{link}\n\nJika Anda lupa kata sandi, gunakan \"Lupa Kata Sandi\" di halaman masuk. Jika itu bukan Anda, abaikan email ini.",
    ],
    'password_reset'     => [
        'subject' => 'Atur ulang kata sandi OtterNova Anda',
        'body'    => "Halo {full_name},\n\nKami menerima permintaan untuk mengatur ulang kata sandi akun OtterNova Anda. Silakan buka tautan ini untuk memilih kata sandi baru:\n\n{link}\n\nTautan ini berlaku selama {minutes} menit dan hanya dapat digunakan sekali. Jika Anda tidak memintanya, abaikan email ini: kata sandi Anda tetap sama.",
    ],
];
