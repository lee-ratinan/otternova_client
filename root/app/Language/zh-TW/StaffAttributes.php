<?php
return [
    'list'              => [
        'title'       => '員工屬性',
        'subtitle'    => '{0}為其人員記錄的資訊，例如證照或專長。每人的值在其個人檔案中填寫。',
        'add'         => '新增屬性',
        'load-failed' => '無法載入屬性。請再試一次。',
        'value-count' => '已記錄的值',
    ],
    'form'              => [
        'create-title'          => '新增屬性',
        'edit-title'            => '編輯屬性',
        'subtitle'              => '{0}為其人員記錄的資訊。標有 * 的欄位為必填',
        'saved'                 => '屬性已儲存。',
        'created'               => '屬性已建立。',
        'failed'                => '無法儲存屬性。請再試一次。',
        'deleted'               => '屬性已刪除。',
        'delete-failed'         => '無法刪除屬性。請再試一次。',
        'confirm-delete'        => '要刪除此屬性嗎？',
        'confirm-delete-values' => '要刪除此屬性嗎？為員工記錄的 {0} 個值也會一併刪除，且無法復原。',
        'not-found'             => '找不到屬性。',
    ],
    'fields'            => [
        'attribute_name' => '名稱',
        'is_public'      => '對顧客顯示',
        'sort_order'     => '順序',
    ],
    'heading'           => [
        'attribute'    => '屬性',
        'translations' => '其他語言的名稱',
    ],
    'help'              => [
        'attribute-name'   => '例如證照、專長或身高。',
        'is-public-switch' => '在員工檔案中向顧客顯示',
        'is-public'        => '關閉後僅負責人和經理可見。',
        'sort-order'       => '屬性依數字由小到大顯示。',
    ],
    'visibility'        => [
        'public'  => '對顧客顯示',
        'private' => '僅限經理',
    ],
    'translation-label' => '{0}名稱',
];
