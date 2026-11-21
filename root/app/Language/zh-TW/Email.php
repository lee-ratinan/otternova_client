<?php
// Subject and body of each EmailService::TYPE_* email; {placeholders} come from the $data passed to send()
return [
    'login_otp' => [
        'subject' => '您的 OtterNova 登入驗證碼',
        'body'    => "{full_name} 您好：\n\n您的登入驗證碼是 {otp}，將於 {minutes} 分鐘後過期。\n\n如果您沒有嘗試登入，請變更您的密碼。",
    ],
    'account_activation' => [
        'subject' => '啟用您的 OtterNova 帳號',
        'body'    => "{full_name} 您好：\n\n感謝您註冊 OtterNova。請開啟以下連結以啟用您的帳號：\n\n{link}\n\n此連結將於 {hours} 小時後過期。如果您沒有註冊，請忽略此郵件。",
    ],
    'account_exists'     => [
        'subject' => '您的 OtterNova 帳號',
        'body'    => "{full_name} 您好：\n\n有人嘗試使用此電子郵件地址註冊 OtterNova，但此地址已有帳號。您可以在這裡登入：\n\n{link}\n\n如果您忘記密碼，請使用登入頁面上的「忘記密碼」。如果這不是您本人，請忽略此郵件。",
    ],
    'password_reset'     => [
        'subject' => '重設您的 OtterNova 密碼',
        'body'    => "{full_name} 您好：\n\n我們收到重設您 OtterNova 帳號密碼的要求。請開啟以下連結以設定新密碼：\n\n{link}\n\n此連結將於 {minutes} 分鐘後過期，且只能使用一次。如果您沒有提出此要求，請忽略此郵件：您的密碼將維持不變。",
    ],
];
