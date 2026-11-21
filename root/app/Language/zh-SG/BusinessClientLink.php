<?php
return [
    'fields' => [
        'business_id'         => '商家',
        'client_id'           => '客户',
        'client_status'       => '状态',
        'client_role'         => '角色',
        'is_default_business' => '默认商家',
        'start_date'          => '开始日期',
        'termination_date'    => '终止日期',
        'owner_notes'         => '负责人备注',
    ],
    'enum'   => [
        'client_status'       => ['A' => '启用', 'T' => '已终止'],
        'client_role'         => ['O' => '负责人', 'M' => '经理', 'E' => '员工'],
        'is_default_business' => ['Y' => '是', 'N' => '否'],
    ],
];
