<?php
return [
    'list'              => [
        'title'       => '门店',
        'subtitle'    => '{0}的门店：实体分店与网店。',
        'add'         => '添加新门店',
        'load-failed' => '无法加载门店。请再试一次。',
        'limit-reached' => '{1}配套最多允许 {0} 家门店。请升级配套以添加更多。',
        'column'      => [
            'store_name'         => '门店',
            'store_type'         => '类型',
            'store_phone_number' => '电话号码',
            'location'           => '地点',
        ],
    ],
    'form'              => [
        'create-title' => '添加门店',
        'edit-title'   => '编辑门店',
        'subtitle'    => '{0}的门店。标有 * 的栏位为必填',
        'saved'        => '门店已保存。',
        'created'      => '门店已创建。',
        'failed'       => '无法保存门店。请再试一次。',
        'not-found'    => '找不到门店。',
    ],
    'heading'           => [
        'store'        => '门店',
        'address'      => '地址',
        'receipt'      => '收据',
        'translation'  => '{0}',
    ],
    'help'              => [
        'store-type'     => '网店没有地址。',
        'store-timezone' => '门店的营业时间以此时区为准。',
        'translation-name' => '以此语言保存地址时需要。',
        'receipt-format' => '此门店收据打印机的纸张。若门店没有打印机请留空，此处将不打印收据。',
        'receipt-footer' => '打印在每张收据底部，例如感谢语或退货政策。',
        'google-maps-url' => '选填。在 Google 地图中打开门店，选择「分享」并复制链接（https://maps.app.goo.gl/...）。它是您商店「联系我们」页面上的「在 Google 地图中打开」按钮；若未填写，按钮将在 Google 地图中搜索地址。',
    ],
    'hours-link'        => [
        'title' => '营业时间',
        'text'  => '此门店的常规每周营业时间，以及休息或在其他时间营业的日期。',
        'open'  => '设置营业时间',
    ],
    'translation'       => [
        'store_name'           => '门店名称',
        'store_address_line_1' => '地址第 1 行',
        'store_address_line_2' => '地址第 2 行',
        'store_city'           => '城市',
        'receipt_footer'       => '收据页脚',
    ],
    'receipt'           => [
        'no-printer' => '无打印机',
    ],
];
