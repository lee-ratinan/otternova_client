<?php
return [
    'list'              => [
        'title'          => 'カテゴリー',
        'subtitle'       => '{0}の商品やサービスをまとめるカテゴリーです。お客様に表示される順に並んでいます。',
        'add'            => '新しいカテゴリーを追加',
        'load-failed'    => 'カテゴリーを読み込めませんでした。もう一度お試しください。',
        'artifact-count' => '商品・サービス',
    ],
    'form'              => [
        'create-title' => 'カテゴリーを追加',
        'edit-title'   => 'カテゴリーを編集',
        'subtitle'     => '{0}の商品・サービスのカテゴリーです。* の付いた項目は必須です',
        'saved'        => 'カテゴリーを保存しました。',
        'created'      => 'カテゴリーを作成しました。',
        'failed'       => 'カテゴリーを保存できませんでした。もう一度お試しください。',
        'not-found'    => 'カテゴリーが見つかりません。',
    ],
    'fields'            => [
        'category_name'   => '名前',
        'category_status' => 'ステータス',
        'sort_order'      => '表示順',
    ],
    'enum'              => [
        'category_status' => ['A' => '有効', 'I' => '無効'],
    ],
    'heading'           => [
        'category'     => 'カテゴリー',
        'translations' => '他の言語でのカテゴリー名',
    ],
    'help'              => [
        'status'     => 'カテゴリーは削除できません。無効にすると使用されなくなります。',
        'sort-order' => '小さい数字ほど先に表示されます。',
    ],
    'translation-label' => '{0}での名前',
];
