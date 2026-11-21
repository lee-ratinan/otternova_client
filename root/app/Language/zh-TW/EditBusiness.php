<?php
return [
    'form'          => [
        'title'    => '商家詳細資料',
        'subtitle' => '{0}的詳細資料。標有 * 的欄位為必填',
        'saved'    => '商家已儲存。',
        'failed'   => '無法儲存商家。請再試一次。',
    ],
    'help'          => [
        'business-slug' => '您商家的識別名稱，將作為您網路商店連結的一部分。可使用字母（拉丁字母須小寫）、數字和連字號，例如 otter-cafe 或 水獺咖啡。 您的商店網址：{0}',
        'fixed'         => '商家建立後無法變更。',
    ],
    'slug-confirm'  => [
        'title'   => '要變更商家網址名稱嗎？',
        'text'    => '您的商店網址將立即從 {0} 變更為 {1}。指向舊網址的連結將失效。請確認！',
        'confirm' => '是，變更',
        'cancel'  => '取消',
    ],
    'managers-only' => '只有此商家的負責人和經理才能管理商家及其門市。',
    // whether the business shows on OtterNovaMart (partials/business_open, Admin::businessOpenPost()): owners only
    'open'          => [
        'title'         => '在 OtterNovaMart 上開放',
        'subtitle'      => '顧客是否能找到您的商店並下單。只有負責人可以變更。',
        'state-open'    => '開放：顧客可以找到您的商店並下單',
        'state-closed'  => '關閉：您的商店已隱藏',
        'help'          => '關閉期間，您的商店不會出現在搜尋結果中，頁面會顯示尚未開放，也無法下單。既有的訂單仍可查詢和付款。',
        'view-shop'     => '查看您的商店',
        'opened'        => '您的商店已在 OtterNovaMart 上開放。',
        'closed'        => '您的商店已在 OtterNovaMart 上關閉。',
        'failed'        => '無法變更商店的開放狀態。請再試一次。',
        'confirm-close' => '要關閉您的商店嗎？在您重新開放之前，顧客將無法找到它或下單。',
        'confirm-yes'   => '是，關閉',
        'confirm-no'    => '保持開放',
        'owner-only'    => '只有此商家的負責人可以開放或關閉它。',
    ],
];
