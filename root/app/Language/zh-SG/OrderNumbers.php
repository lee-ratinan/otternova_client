<?php

// App\Controllers\OrderNumbers: how the business's order numbers look
return [
    'title'             => '订单编号',
    'subtitle'          => '{0}订单的编号方式。已编号的订单保留原编号。',
    'prefix'            => '前缀',
    'prefix-help'       => '最多 4 个字母，A 至 Z。留空表示不使用。',
    'none'              => '无',
    'date-part'         => '日期部分',
    'date-part-help'    => '显示在前缀之后的日期，以商家时间为准。',
    'date-part-options' => ['N' => '无', 'Y' => '年（26）', 'M' => '年和月（2611）', 'D' => '年、月和日（261113）'],
    'reset'             => '重新从 1 开始',
    'reset-help'        => '流水号何时回到 1。频率不能高于日期部分所显示的。',
    'reset-options'     => ['N' => '永不', 'Y' => '每年', 'M' => '每月', 'D' => '每天'],
    'digits'            => '流水号',
    'digits-help'       => '最少位数；更大的数字会自动变长。',
    'digits-option'     => '{0} 位（{1}）',
    'next'              => '下一个订单编号',
    'next-help'         => '下一笔订单将获得的编号。',
    'example-help'      => '保存后，每个周期第一个编号的样式。',
    'rule'              => '编号重新开始的频率不能高于日期部分所显示的：每日重置需要日，每月重置需要月，每年重置需要日期部分。',
    'saved'             => '订单编号设置已保存。',
    'failed'            => '无法保存订单编号设置。请再试一次。',
    'load-failed'       => '无法加载订单编号设置。请再试一次。',
];
