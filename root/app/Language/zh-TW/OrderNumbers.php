<?php

// App\Controllers\OrderNumbers: how the business's order numbers look
return [
    'title'             => '訂單編號',
    'subtitle'          => '{0}訂單的編號方式。已編號的訂單保留原編號。',
    'prefix'            => '前綴',
    'prefix-help'       => '最多 4 個字母，A 至 Z。留空表示不使用。',
    'none'              => '無',
    'date-part'         => '日期部分',
    'date-part-help'    => '顯示在前綴之後的日期，以商家時間為準。',
    'date-part-options' => ['N' => '無', 'Y' => '年（26）', 'M' => '年和月（2611）', 'D' => '年、月和日（261113）'],
    'reset'             => '重新從 1 開始',
    'reset-help'        => '流水號何時回到 1。頻率不能高於日期部分所顯示的。',
    'reset-options'     => ['N' => '永不', 'Y' => '每年', 'M' => '每月', 'D' => '每天'],
    'digits'            => '流水號',
    'digits-help'       => '最少位數；更大的數字會自動變長。',
    'digits-option'     => '{0} 位（{1}）',
    'next'              => '下一個訂單編號',
    'next-help'         => '下一筆訂單將取得的編號。',
    'example-help'      => '儲存後，每個週期第一個編號的樣式。',
    'rule'              => '編號重新開始的頻率不能高於日期部分所顯示的：每日重設需要日，每月重設需要月，每年重設需要日期部分。',
    'saved'             => '訂單編號設定已儲存。',
    'failed'            => '無法儲存訂單編號設定。請再試一次。',
    'load-failed'       => '無法載入訂單編號設定。請再試一次。',
];
