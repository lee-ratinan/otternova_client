<?php
return [
    'list'              => [
        'title'       => 'スタッフ属性',
        'subtitle'    => '{0}が人について記録する項目（資格や専門分野など）です。各人の値はプロフィールで入力します。',
        'add'         => '新しい属性を追加',
        'load-failed' => '属性を読み込めませんでした。もう一度お試しください。',
        'value-count' => '記録された値',
    ],
    'form'              => [
        'create-title'          => '属性を追加',
        'edit-title'            => '属性を編集',
        'subtitle'              => '{0}が人について記録する項目です。* の付いた項目は必須です',
        'saved'                 => '属性を保存しました。',
        'created'               => '属性を作成しました。',
        'failed'                => '属性を保存できませんでした。もう一度お試しください。',
        'deleted'               => '属性を削除しました。',
        'delete-failed'         => '属性を削除できませんでした。もう一度お試しください。',
        'confirm-delete'        => 'この属性を削除しますか？',
        'confirm-delete-values' => 'この属性を削除しますか？スタッフについて記録された{0}件の値も削除され、元に戻せません。',
        'not-found'             => '属性が見つかりません。',
    ],
    'fields'            => [
        'attribute_name' => '名前',
        'is_public'      => 'お客様に表示',
        'sort_order'     => '順序',
    ],
    'heading'           => [
        'attribute'    => '属性',
        'translations' => '他の言語での名前',
    ],
    'help'              => [
        'attribute-name'   => '資格、専門分野、身長など。',
        'is-public-switch' => 'お客様向けのスタッフプロフィールに表示する',
        'is-public'        => 'オフにすると、オーナーとマネージャーのみが閲覧できます。',
        'sort-order'       => '属性は小さい数字から順に表示されます。',
    ],
    'visibility'        => [
        'public'  => 'お客様に表示',
        'private' => 'マネージャーのみ',
    ],
    'translation-label' => '{0}での名前',
];
