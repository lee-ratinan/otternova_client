<?php
return [
    'list'              => [
        'title'       => '员工属性',
        'subtitle'    => '{0}为其人员记录的信息，例如证书或专长。每人的值在其个人资料中填写。',
        'add'         => '添加新属性',
        'load-failed' => '无法加载属性。请再试一次。',
        'value-count' => '已记录的值',
    ],
    'form'              => [
        'create-title'          => '添加属性',
        'edit-title'            => '编辑属性',
        'subtitle'              => '{0}为其人员记录的信息。标有 * 的栏位为必填',
        'saved'                 => '属性已保存。',
        'created'               => '属性已创建。',
        'failed'                => '无法保存属性。请再试一次。',
        'deleted'               => '属性已删除。',
        'delete-failed'         => '无法删除属性。请再试一次。',
        'confirm-delete'        => '删除此属性？',
        'confirm-delete-values' => '删除此属性？为员工记录的 {0} 个值也会一并删除，且无法恢复。',
        'not-found'             => '找不到属性。',
    ],
    'fields'            => [
        'attribute_name' => '名称',
        'is_public'      => '对顾客显示',
        'sort_order'     => '顺序',
    ],
    'heading'           => [
        'attribute'    => '属性',
        'translations' => '其他语言的名称',
    ],
    'help'              => [
        'attribute-name'   => '例如证书、专长或身高。',
        'is-public-switch' => '在员工资料中向顾客显示',
        'is-public'        => '关闭后仅负责人和经理可见。',
        'sort-order'       => '属性按数字由小到大显示。',
    ],
    'visibility'        => [
        'public'  => '对顾客显示',
        'private' => '仅限经理',
    ],
    'translation-label' => '{0}名称',
];
