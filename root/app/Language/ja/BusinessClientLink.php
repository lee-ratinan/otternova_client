<?php
return [
    'fields' => [
        'business_id'         => 'ビジネス',
        'client_id'           => 'クライアント',
        'client_status'       => 'ステータス',
        'client_role'         => '役割',
        'is_default_business' => '既定のビジネス',
        'start_date'          => '開始日',
        'termination_date'    => '終了日',
        'owner_notes'         => 'オーナーのメモ',
    ],
    'enum'   => [
        'client_status'       => ['A' => '有効', 'T' => '終了'],
        'client_role'         => ['O' => 'オーナー', 'M' => 'マネージャー', 'E' => '従業員'],
        'is_default_business' => ['Y' => 'はい', 'N' => 'いいえ'],
    ],
];
