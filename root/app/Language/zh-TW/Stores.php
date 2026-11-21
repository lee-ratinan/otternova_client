<?php
return [
    'list'              => [
        'title'       => '門市',
        'subtitle'    => '{0}的門市：實體分店與網路商店。',
        'add'         => '新增門市',
        'load-failed' => '無法載入門市。請再試一次。',
        'limit-reached' => '{1}方案最多允許 {0} 間門市。請升級方案以新增更多。',
        'column'      => [
            'store_name'         => '門市',
            'store_type'         => '類型',
            'store_phone_number' => '電話號碼',
            'location'           => '地點',
        ],
    ],
    'form'              => [
        'create-title' => '新增門市',
        'edit-title'   => '編輯門市',
        'subtitle'    => '{0}的門市。標有 * 的欄位為必填',
        'saved'        => '門市已儲存。',
        'created'      => '門市已建立。',
        'failed'       => '無法儲存門市。請再試一次。',
        'not-found'    => '找不到門市。',
    ],
    'heading'           => [
        'store'        => '門市',
        'address'      => '地址',
        'receipt'      => '收據',
        'translation'  => '{0}',
    ],
    'help'              => [
        'store-type'     => '網路商店沒有地址。',
        'store-timezone' => '門市的營業時間以此時區為準。',
        'translation-name' => '以此語言儲存地址時需要。',
        'receipt-format' => '此門市收據印表機的紙張。若門市沒有印表機請留空，此處將不列印收據。',
        'receipt-footer' => '列印在每張收據底部，例如感謝語或退貨政策。',
        'google-maps-url' => '選填。在 Google 地圖中開啟門市，選擇「分享」並複製連結（https://maps.app.goo.gl/...）。它是您商店「聯絡我們」頁面上的「在 Google 地圖中開啟」按鈕；若未填寫，按鈕將在 Google 地圖中搜尋地址。',
    ],
    'hours-link'        => [
        'title' => '營業時間',
        'text'  => '此門市的固定每週營業時間，以及公休或在其他時間營業的日期。',
        'open'  => '設定營業時間',
    ],
    'translation'       => [
        'store_name'           => '門市名稱',
        'store_address_line_1' => '地址第 1 行',
        'store_address_line_2' => '地址第 2 行',
        'store_city'           => '城市',
        'receipt_footer'       => '收據頁尾',
    ],
    'receipt'           => [
        'no-printer' => '無印表機',
    ],
];
