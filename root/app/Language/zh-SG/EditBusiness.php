<?php
return [
    'form'          => [
        'title'    => '商家详情',
        'subtitle' => '{0}的详情。标有 * 的栏位为必填',
        'saved'    => '商家已保存。',
        'failed'   => '无法保存商家。请再试一次。',
    ],
    'help'          => [
        'business-slug' => '您商家的标识名称，将作为您网店链接的一部分。可使用字母（拉丁字母须小写）、数字和连字符，例如 otter-cafe 或 水獭咖啡。 您的商店网址：{0}',
        'fixed'         => '商家创建后无法更改。',
    ],
    'slug-confirm'  => [
        'title'   => '更改商家网址名称？',
        'text'    => '您的商店网址将立即从 {0} 更改为 {1}。指向旧网址的链接将失效。请确认！',
        'confirm' => '是，更改',
        'cancel'  => '取消',
    ],
    'managers-only' => '只有此商家的负责人和经理才能管理商家及其门店。',
    // whether the business shows on OtterNovaMart (partials/business_open, Admin::businessOpenPost()): owners only
    'open'          => [
        'title'         => '在 OtterNovaMart 上开放',
        'subtitle'      => '顾客是否能找到您的商店并下单。只有业主可以更改。',
        'state-open'    => '开放：顾客可以找到您的商店并下单',
        'state-closed'  => '关闭：您的商店已隐藏',
        'help'          => '关闭期间，您的商店不会出现在搜索结果中，页面会显示尚未开放，也无法下单。已有的订单仍可查询和付款。',
        'view-shop'     => '查看您的商店',
        'opened'        => '您的商店已在 OtterNovaMart 上开放。',
        'closed'        => '您的商店已在 OtterNovaMart 上关闭。',
        'failed'        => '无法更改商店的开放状态。请再试一次。',
        'confirm-close' => '要关闭您的商店吗？在您重新开放之前，顾客将无法找到它或下单。',
        'confirm-yes'   => '是，关闭',
        'confirm-no'    => '保持开放',
        'owner-only'    => '只有此商家的业主可以开放或关闭它。',
    ],
];
