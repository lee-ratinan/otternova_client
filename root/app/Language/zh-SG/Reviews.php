<?php
return [
    'title'          => '评价',
    'subtitle'       => '顾客对{0}产品与服务的评价。批准评价后会显示给其他顾客并计入评分；隐藏则会将其撤下。',
    'load-failed'    => '无法加载评价。请再试一次。',
    'action-failed'  => '无法保存评价。请再试一次。',
    'approve'        => '批准',
    'hide'           => '隐藏',
    'replied'        => '回复：',
    'summary'        => [
        'title'    => '{0}的评分',
        'subtitle' => '所有产品与服务合计，仅计已批准的评价。',
        'count'    => '{0} 条已批准评价',
        'none'     => '尚无已批准的评价',
        'stars'    => '{0} 星',
        'heading'  => '评分',
    ],
    'column'         => [
        'created_at'    => '日期',
        'artifact_name' => '产品或服务',
        'customer_name' => '顾客',
        'rating'        => '评分',
        'review'        => '评价',
        'review_status' => '状态',
        'actions'       => '操作',
    ],
    'filter'         => [
        'review_status' => '状态',
    ],
    'enum'           => [
        'review_status' => ['P' => '待处理', 'A' => '已批准', 'H' => '已隐藏'],
    ],
];
