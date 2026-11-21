<?php
// Subject and body of each EmailService::TYPE_* email; {placeholders} come from the $data passed to send()
return [
    'login_otp' => [
        'subject' => 'OtterNova のサインインコード',
        'body'    => "{full_name} 様\n\nサインインコードは {otp} です。有効期限は {minutes} 分です。\n\nサインインを試みた覚えがない場合は、パスワードを変更してください。",
    ],
    'account_activation' => [
        'subject' => 'OtterNova アカウントを有効化してください',
        'body'    => "{full_name} 様\n\nOtterNova にご登録いただきありがとうございます。以下のリンクを開いてアカウントを有効化してください：\n\n{link}\n\nリンクの有効期限は {hours} 時間です。登録した覚えがない場合は、このメールを無視してください。",
    ],
    'account_exists'     => [
        'subject' => 'OtterNova アカウントについて',
        'body'    => "{full_name} 様\n\nこのメールアドレスで OtterNova への登録が試みられましたが、すでにアカウントが存在します。こちらからサインインできます：\n\n{link}\n\nパスワードをお忘れの場合は、サインインページの「パスワードを忘れた場合」をご利用ください。お心当たりがない場合は、このメールを無視してください。",
    ],
    'password_reset'     => [
        'subject' => 'OtterNova のパスワード再設定',
        'body'    => "{full_name} 様\n\nOtterNova アカウントのパスワード再設定のリクエストを受け付けました。以下のリンクを開いて新しいパスワードを設定してください：\n\n{link}\n\nリンクの有効期限は {minutes} 分で、1回のみ使用できます。お心当たりがない場合は、このメールを無視してください。パスワードは変更されません。",
    ],
];
