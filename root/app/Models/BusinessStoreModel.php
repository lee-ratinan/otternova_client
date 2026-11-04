<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Model for the business_store table: a branch of a business, physical (P, such as "Bangkok") or online (O, such
 * as "Online Store"). Only a physical store has an address; its subdivision is one of the business's country.
 * The client app reads and writes stores through LINK_INTERNAL_API (v1/client/stores, store/create, store/edit);
 * this model gives the Form and Table Builders their field configuration.
 */
class BusinessStoreModel extends Model
{
    use FieldConfigurationTrait;

    // store_type
    public const TYPE_PHYSICAL = 'P';
    public const TYPE_ONLINE   = 'O';

    /** Columns that only a physical store has */
    public const ADDRESS_FIELDS = [
        'store_address_line_1',
        'store_address_line_2',
        'store_city',
        'store_subdivision_code',
        'store_postal_code',
    ];

    protected $table         = 'business_store';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'store_name',
        'store_type',
        'store_phone_number',
        'store_address_line_1',
        'store_address_line_2',
        'store_city',
        'store_subdivision_code',
        'store_postal_code',
    ];

    // the API's rules; whether the subdivision belongs to the business's country is the API's to check
    protected $validationRules = [
        'store_name'             => 'required|max_length[255]',
        'store_type'             => 'required|in_list[P,O]',
        'store_phone_number'     => 'permit_empty|max_length[32]',
        'store_address_line_1'   => 'permit_empty|max_length[255]',
        'store_address_line_2'   => 'permit_empty|max_length[255]',
        'store_city'             => 'permit_empty|max_length[128]',
        'store_subdivision_code' => 'permit_empty|max_length[8]',
        'store_postal_code'      => 'permit_empty|max_length[16]',
    ];

    /**
     * Field configuration shared by the Form Builder (as 'fields') and the Table Builder (via toColumns()),
     * keyed by field name so a controller can unset() what it does not need.
     * @return array
     */
    public function getConfiguration(): array
    {
        $fields = [
            'store_name'             => ['type' => 'text'],
            'store_type'             => ['type' => 'select'],
            'store_phone_number'     => ['type' => 'tel'],
            'store_address_line_1'   => ['type' => 'text'],
            'store_address_line_2'   => ['type' => 'text'],
            'store_city'             => ['type' => 'text'],
            'store_subdivision_code' => ['type' => 'text', 'searchable' => false], // depends on the business's country
            'store_postal_code'      => ['type' => 'text'],
        ];
        $enums  = [
            'store_type' => [self::TYPE_PHYSICAL => 'primary', self::TYPE_ONLINE => 'info'],
        ];

        return $this->buildConfiguration('BusinessStore', $fields, $enums);
    }
}
