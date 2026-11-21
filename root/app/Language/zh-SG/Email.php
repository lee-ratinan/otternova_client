<?php
// Subject and body of each EmailService::TYPE_* email; {placeholders} come from the $data passed to send()
return [
    'login_otp' => [
        'subject' => '您的 OtterNova 登录验证码',
        'body'    => "{full_name}，您好：\n\n您的登录验证码是 {otp}，将在 {minutes} 分钟后过期。\n\n如果您没有尝试登录，请更改您的密码。",
    ],
    'account_activation' => [
        'subject' => '激活您的 OtterNova 账户',
        'body'    => "{full_name}，您好：\n\n感谢您注册 OtterNova。请打开以下链接以激活您的账户：\n\n{link}\n\n此链接将在 {hours} 小时后过期。如果您没有注册，请忽略此电邮。",
    ],
    'account_exists'     => [
        'subject' => '您的 OtterNova 账户',
        'body'    => "{full_name}，您好：\n\n有人尝试使用此电邮地址注册 OtterNova，但此地址已有账户。您可以在这里登录：\n\n{link}\n\n如果您忘记了密码，请使用登录页面上的「忘记密码」。如果这不是您本人，请忽略此电邮。",
    ],
    'password_reset'     => [
        'subject' => '重设您的 OtterNova 密码',
        'body'    => "{full_name}，您好：\n\n我们收到了重设您 OtterNova 账户密码的请求。请打开以下链接以设置新密码：\n\n{link}\n\n此链接将在 {minutes} 分钟后过期，且只能使用一次。如果您没有提出此请求，请忽略此电邮：您的密码将保持不变。",
    ],
];
