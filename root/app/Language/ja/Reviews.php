<?php
return [
    'title'          => 'レビュー',
    'subtitle'       => '{0}の商品やサービスについてのお客様の声です。承認すると他のお客様に表示され、評価に反映されます。非表示にすると取り下げられます。',
    'load-failed'    => 'レビューを読み込めませんでした。もう一度お試しください。',
    'action-failed'  => 'レビューを保存できませんでした。もう一度お試しください。',
    'approve'        => '承認',
    'hide'           => '非表示',
    'replied'        => '返信：',
    'summary'        => [
        'title'    => '{0}の評価',
        'subtitle' => 'すべての商品・サービスの合計（承認済みレビューのみ）。',
        'count'    => '承認済みレビュー {0}件',
        'none'     => '承認済みのレビューはまだありません',
        'stars'    => '星{0}つ',
        'heading'  => '評価',
    ],
    'column'         => [
        'created_at'    => '日付',
        'artifact_name' => '商品・サービス',
        'customer_name' => '顧客',
        'rating'        => '評価',
        'review'        => 'レビュー',
        'review_status' => 'ステータス',
        'actions'       => '操作',
    ],
    'filter'         => [
        'review_status' => 'ステータス',
    ],
    'enum'           => [
        'review_status' => ['P' => '保留中', 'A' => '承認済み', 'H' => '非表示'],
    ],
];
