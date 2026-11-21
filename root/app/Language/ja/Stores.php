<?php
return [
    'list'              => [
        'title'       => '店舗',
        'subtitle'    => '{0}の店舗：実店舗とオンラインストア。',
        'add'         => '新しい店舗を追加',
        'load-failed' => '店舗を読み込めませんでした。もう一度お試しください。',
        'limit-reached' => '{1}プランでは最大{0}店舗まで登録できます。追加するにはプランをアップグレードしてください。',
        'column'      => [
            'store_name'         => '店舗',
            'store_type'         => '種類',
            'store_phone_number' => '電話番号',
            'location'           => '所在地',
        ],
    ],
    'form'              => [
        'create-title' => '店舗を追加',
        'edit-title'   => '店舗を編集',
        'subtitle'    => '{0}の店舗です。* の付いた項目は必須です',
        'saved'        => '店舗を保存しました。',
        'created'      => '店舗を作成しました。',
        'failed'       => '店舗を保存できませんでした。もう一度お試しください。',
        'not-found'    => '店舗が見つかりません。',
    ],
    'heading'           => [
        'store'        => '店舗',
        'address'      => '住所',
        'receipt'      => 'レシート',
        'translation'  => '{0}',
    ],
    'help'              => [
        'store-type'     => 'オンラインストアには住所はありません。',
        'store-timezone' => '店舗の営業時間はこのタイムゾーンで設定されます。',
        'translation-name' => 'この言語で住所を保存するために必要です。',
        'receipt-format' => 'この店舗のレシートプリンターの用紙です。プリンターがない場合は空欄にしてください。その場合、ここではレシートは印刷されません。',
        'receipt-footer' => 'すべてのレシートの下部に印刷されます（お礼の言葉や返品ポリシーなど）。',
        'google-maps-url' => '任意。Googleマップで店舗を開き、「共有」を選んでリンク（https://maps.app.goo.gl/...）をコピーしてください。ショップの「お問い合わせ」ページの「Googleマップで開く」ボタンになります。ない場合、ボタンはGoogleマップで住所を検索します。',
    ],
    'hours-link'        => [
        'title' => '営業時間',
        'text'  => 'この店舗の通常の週間営業時間と、休業日や特別営業時間です。',
        'open'  => '営業時間を設定',
    ],
    'translation'       => [
        'store_name'           => '店舗名',
        'store_address_line_1' => '住所1',
        'store_address_line_2' => '住所2',
        'store_city'           => '市区町村',
        'receipt_footer'       => 'レシートのフッター',
    ],
    'receipt'           => [
        'no-printer' => 'プリンターなし',
    ],
];
