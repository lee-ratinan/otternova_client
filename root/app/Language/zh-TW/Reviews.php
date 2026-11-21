<?php
return [
    'title'          => '評價',
    'subtitle'       => '顧客對{0}商品與服務的評價。核准評價後會顯示給其他顧客並計入評分；隱藏則會將其撤下。',
    'load-failed'    => '無法載入評價。請再試一次。',
    'action-failed'  => '無法儲存評價。請再試一次。',
    'approve'        => '核准',
    'hide'           => '隱藏',
    'replied'        => '回覆：',
    'summary'        => [
        'title'    => '{0}的評分',
        'subtitle' => '所有商品與服務合計，僅計已核准的評價。',
        'count'    => '{0} 則已核准評價',
        'none'     => '尚無已核准的評價',
        'stars'    => '{0} 星',
        'heading'  => '評分',
    ],
    'column'         => [
        'created_at'    => '日期',
        'artifact_name' => '商品或服務',
        'customer_name' => '顧客',
        'rating'        => '評分',
        'review'        => '評價',
        'review_status' => '狀態',
        'actions'       => '動作',
    ],
    'filter'         => [
        'review_status' => '狀態',
    ],
    'enum'           => [
        'review_status' => ['P' => '待處理', 'A' => '已核准', 'H' => '已隱藏'],
    ],
];
