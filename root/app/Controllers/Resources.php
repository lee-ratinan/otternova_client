<?php

namespace App\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;

/**
 * The resources the session business's services need besides a person, from LINK_INTERNAL_API v1/client/resource*:
 * the kinds of resource (resource types, "Massage room"), the real ones at each physical store (resources, rooms
 * "101" to "103"), and, on a service's page, which types it needs (see Artifacts::form() and saveNeeds()). Its
 * owners and managers only (see BusinessManagerFilter), as the API allows. Nothing is deleted: a type or a resource
 * is made inactive instead.
 */
class Resources extends BaseController
{
    private const STATUS_ACTIVE   = 'A';
    private const STATUS_INACTIVE = 'I';
    private const STORE_PHYSICAL  = 'P';

    /** The fields of the forms that go to the API as "resource_type" and "resource" */
    private const TYPE_FIELDS     = ['type_name', 'type_description', 'buffer_minutes', 'type_status'];
    private const RESOURCE_FIELDS = ['resource_name', 'capacity', 'resource_status', 'sort_order'];

    /** Form field names of a type's name and description in a language: translation_name_<language_code> */
    private const NAME_PREFIX        = 'translation_name_';
    private const DESCRIPTION_PREFIX = 'translation_description_';

    /** The name the inputs of the needs editor (resources/needs_editor) start with */
    public const NEEDS_FIELD = 'needs';

    // ---------------------------------------------------------------- resource types

    /**
     * The resource types (generic_list), by name; each row opens one
     */
    public function types(): string
    {
        return view('generic_list', [
            'locale'      => $this->request->getLocale(),
            'slug'        => 'resource-types',
            'breadcrumbs' => [base_url('admin/business') => lang('System.page.business')],
            'user'        => (array) session('user'),
            'listConfig'  => $this->typeListConfig(),
        ]);
    }

    /**
     * DataTables data of types(). A business has a handful of types, so the API sends them all and the search,
     * order and paging are done here.
     */
    public function typesData(): ResponseInterface
    {
        $config = $this->typeListConfig();
        $params = lb_request($this->request, $config);
        [$types, $error] = $this->loadTypes();
        if (null !== $error) {
            return $this->response->setJSON(lb_response($params['draw'], 0, 0, []) + ['error' => $error, 'csrf_hash' => csrf_hash()]);
        }

        $locale = $this->request->getLocale();
        $rows   = [];
        foreach ($types as $type) {
            $rows[] = [
                'public_id'      => (string) ($type['public_id'] ?? ''),
                'type_name'      => self::typeName($type, $locale),
                'type_status'    => (string) ($type['type_status'] ?? ''),
                'buffer_minutes' => (int) ($type['buffer_minutes'] ?? 0),
                'resource_count' => (int) ($type['active_resource_count'] ?? 0) . ' / ' . (int) ($type['resource_count'] ?? 0),
                'service_count'  => (int) ($type['service_count'] ?? 0),
            ];
        }
        $total    = count($rows);
        $statuses = lang('Resources.enum.status');
        $rows     = $this->searchAndOrder($rows, $params, static fn (array $row, string $column) => 'type_status' === $column
            ? (string) ($statuses[$row[$column]] ?? $row[$column])
            : (string) $row[$column]);
        $filtered = count($rows);
        $rows     = lb_rows(array_slice($rows, $params['start'], $params['length']), $config);

        return $this->response->setJSON(lb_response($params['draw'], $total, $filtered, $rows) + ['csrf_hash' => csrf_hash()]);
    }

    /**
     * The form to add a resource type, or, with $publicId, one opened in view mode
     * @param string|null $publicId resource_type.public_id
     */
    public function typeForm(?string $publicId = null): string
    {
        $values = [];
        if (null !== $publicId) {
            $type = $this->findType($publicId);
            if (null === $type) {
                throw PageNotFoundException::forPageNotFound(lang('Resources.type-form.not-found'));
            }
            $values = $type;
            foreach ((array) ($type['translations'] ?? []) as $language => $texts) {
                $values[self::NAME_PREFIX . $language]        = $texts['type_name'] ?? '';
                $values[self::DESCRIPTION_PREFIX . $language] = $texts['type_description'] ?? '';
            }
            unset($values['translations']);
        }
        $locale = $this->request->getLocale();
        return view('generic_form', [
            'locale'      => $locale,
            'slug'        => 'resource-types',
            'page_title'  => $values['type_name'] ?? lang('System.page.resource-type-create'),
            'breadcrumbs' => [
                base_url('admin/business')                => lang('System.page.business'),
                base_url('admin/business/resource-types') => lang('System.page.resource-types'),
            ],
            'user'        => (array) session('user'),
            'formConfig'  => [
                'form_id'  => 'resource-type-form',
                'title'    => lang(null === $publicId ? 'Resources.type-form.create-title' : 'Resources.type-form.edit-title'),
                'subtitle' => lang('Resources.type-form.subtitle', [(string) currentBusinessName()]),
                'mode'     => null === $publicId ? 'create' : 'view',
                'id_field' => 'public_id',
                'id'       => (string) $publicId,
                'locale'   => $locale,
                'urls'     => [
                    'submit' => base_url('admin/business/resource-types/save'),
                    'edit'   => null === $publicId ? '' : base_url('admin/business/resource-types/edit/' . rawurlencode($publicId)),
                    'list'   => base_url('admin/business/resource-types'),
                ],
                'messages' => ['saved' => lang(null === $publicId ? 'Resources.type-form.created' : 'Resources.type-form.saved'),
                    'saveFailed' => lang('Resources.type-form.failed')],
                'values'   => $values,
                'fields'   => $this->typeFields(),
            ],
            'extraHtml'   => null === $publicId ? '' : view('resources/type_resources_link', [
                'url'   => base_url('admin/business/resources?type=' . rawurlencode($publicId)),
                'count' => (int) ($values['resource_count'] ?? 0),
            ]),
        ]);
    }

    /**
     * Create or save a resource type through LINK_INTERNAL_API: v1/client/resource-type/create { business_slug,
     * resource_type, translations }, or, with public_id, v1/client/resource-type/edit { public_id, resource_type,
     * translations }. The form has the texts in the web pages' languages only; on an edit, the other languages' are kept, as
     * the API replaces them all.
     */
    public function typeSave(): ResponseInterface
    {
        $post     = (array) $this->request->getPost();
        $publicId = trim((string) ($post['public_id'] ?? ''));
        $type     = array_intersect_key($post, array_flip(self::TYPE_FIELDS));
        $names    = [];
        if ('' !== $publicId) {
            $stored = $this->findType($publicId);
            if (null === $stored) {
                return $this->saveFailed(404, ['message' => lang('Resources.type-form.not-found')], lang('Resources.type-form.failed'));
            }
            $names = (array) ($stored['translations'] ?? []);
        }
        foreach (array_keys($this->websiteLanguages()) as $language) {
            $names[$language] = [
                'type_name'        => (string) ($post[self::NAME_PREFIX . $language] ?? ''),
                'type_description' => (string) ($post[self::DESCRIPTION_PREFIX . $language] ?? ''),
            ];
        }

        $payload = ['resource_type' => $type, 'translations' => (object) $names];
        [$code, $body] = '' === $publicId
            ? $this->callApi('v1/client/resource-type/create', ['business_slug' => (string) currentBusinessSlug()] + $payload)
            : $this->callApi('v1/client/resource-type/edit', ['public_id' => $publicId] + $payload);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->typeFailed($code, $body);
        }
        $savedId = (string) ($body['data']['public_id'] ?? $publicId);
        return $this->response->setJSON([
            'success'   => true,
            'message'   => $body['message'] ?? lang('' === $publicId ? 'Resources.type-form.created' : 'Resources.type-form.saved'),
            'redirect'  => base_url('admin/business/resource-types/edit/' . rawurlencode($savedId)),
            'csrf_hash' => csrf_hash(),
        ]);
    }

    // ---------------------------------------------------------------- resources

    /**
     * The resources (generic_list) of every store, by store, type and order; ?store= and ?type= (public_ids) narrow
     * it to one store or type
     */
    public function index(): string
    {
        return view('generic_list', [
            'locale'      => $this->request->getLocale(),
            'slug'        => 'resources',
            'breadcrumbs' => [base_url('admin/business') => lang('System.page.business')],
            'user'        => (array) session('user'),
            'listConfig'  => $this->resourceListConfig(),
        ]);
    }

    /**
     * DataTables data of index(), from v1/client/resources, narrowed by the page's ?store= and ?type=
     */
    public function data(): ResponseInterface
    {
        $config  = $this->resourceListConfig();
        $params  = lb_request($this->request, $config);
        $filters = $this->listFilters();
        [$code, $body] = $this->callApi('v1/client/resources', ['business_slug' => (string) currentBusinessSlug()] + $filters);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->response->setJSON(lb_response($params['draw'], 0, 0, [])
                + ['error' => (string) ($body['message'] ?? lang('Resources.list.load-failed')), 'csrf_hash' => csrf_hash()]);
        }

        $locale    = $this->request->getLocale();
        $storeName = $this->storeNames($locale);
        $typeName  = [];
        foreach ($this->loadTypes()[0] as $type) {
            $typeName[(string) $type['public_id']] = self::typeName($type, $locale);
        }
        $rows = [];
        foreach ((array) ($body['data']['resources'] ?? []) as $resource) {
            $store  = (array) ($resource['store'] ?? []);
            $type   = (array) ($resource['resource_type'] ?? []);
            $rows[] = [
                'public_id'       => (string) ($resource['public_id'] ?? ''),
                'resource_name'   => (string) ($resource['resource_name'] ?? ''),
                'capacity'        => (int) ($resource['capacity'] ?? 1),
                'store_name'      => $storeName[(string) ($store['public_id'] ?? '')] ?? (string) ($store['store_name'] ?? ''),
                'type_name'       => $typeName[(string) ($type['public_id'] ?? '')] ?? (string) ($type['type_name'] ?? ''),
                'resource_status' => (string) ($resource['resource_status'] ?? ''),
                'sort_order'      => (int) ($resource['sort_order'] ?? 0),
            ];
        }
        $total    = count($rows);
        $statuses = lang('Resources.enum.status');
        $rows     = $this->searchAndOrder($rows, $params, static fn (array $row, string $column) => 'resource_status' === $column
            ? (string) ($statuses[$row[$column]] ?? $row[$column])
            : (string) $row[$column]);
        $filtered = count($rows);
        $rows     = lb_rows(array_slice($rows, $params['start'], $params['length']), $config);

        return $this->response->setJSON(lb_response($params['draw'], $total, $filtered, $rows) + ['csrf_hash' => csrf_hash()]);
    }

    /**
     * The form to add a resource, or, with $publicId, one opened in view mode. Its store and type are picked when
     * it is added and cannot change; ?store= and ?type= pick them at first.
     * @param string|null $publicId resource_master.public_id
     */
    public function form(?string $publicId = null): string
    {
        $values = [];
        if (null !== $publicId) {
            $resource = $this->findResource($publicId);
            if (null === $resource) {
                throw PageNotFoundException::forPageNotFound(lang('Resources.form.not-found'));
            }
            $values = [
                'public_id'       => (string) $resource['public_id'],
                'resource_name'   => (string) $resource['resource_name'],
                'capacity'        => (int) ($resource['capacity'] ?? 1),
                'resource_status' => (string) $resource['resource_status'],
                'sort_order'      => (int) $resource['sort_order'],
                'store_public_id' => (string) ($resource['store']['public_id'] ?? ''),
                'type_public_id'  => (string) ($resource['resource_type']['public_id'] ?? ''),
            ];
        } else {
            $values = array_filter(['store_public_id' => (string) $this->request->getGet('store'), 'type_public_id' => (string) $this->request->getGet('type')], 'strlen');
        }
        $locale = $this->request->getLocale();
        $stores = $this->physicalStoreOptions($locale, $values['store_public_id'] ?? null);
        $types  = $this->typeOptions($locale, $values['type_public_id'] ?? null);

        return view('generic_form', [
            'locale'      => $locale,
            'slug'        => 'resources',
            'page_title'  => $values['resource_name'] ?? lang('System.page.resource-create'),
            'breadcrumbs' => [
                base_url('admin/business')           => lang('System.page.business'),
                base_url('admin/business/resources') => lang('System.page.resources'),
            ],
            'user'        => (array) session('user'),
            'formConfig'  => [
                'form_id'  => 'resource-form',
                'title'    => lang(null === $publicId ? 'Resources.form.create-title' : 'Resources.form.edit-title'),
                'subtitle' => lang('Resources.form.subtitle', [(string) currentBusinessName()]),
                'mode'     => null === $publicId ? 'create' : 'view',
                'id_field' => 'public_id',
                'id'       => (string) $publicId,
                'locale'   => $locale,
                'urls'     => [
                    'submit' => base_url('admin/business/resources/save'),
                    'edit'   => null === $publicId ? '' : base_url('admin/business/resources/edit/' . rawurlencode($publicId)),
                    'list'   => base_url('admin/business/resources'),
                ],
                'messages' => ['saved' => lang(null === $publicId ? 'Resources.form.created' : 'Resources.form.saved'),
                    'saveFailed' => lang('Resources.form.failed')],
                'values'   => $values,
                'fields'   => $this->resourceFields($stores, $types),
            ],
        ]);
    }

    /**
     * Create or save a resource through LINK_INTERNAL_API: v1/client/resource/create { business_slug, resource:
     * { store_public_id, type_public_id, ... } }, or, with public_id, v1/client/resource/edit { public_id, resource },
     * which takes no store or type, as they cannot change
     */
    public function save(): ResponseInterface
    {
        $post     = (array) $this->request->getPost();
        $publicId = trim((string) ($post['public_id'] ?? ''));
        $resource = array_intersect_key($post, array_flip(self::RESOURCE_FIELDS));
        if ('' === $publicId) {
            $resource['store_public_id'] = (string) ($post['store_public_id'] ?? '');
            $resource['type_public_id']  = (string) ($post['type_public_id'] ?? '');
        }
        [$code, $body] = '' === $publicId
            ? $this->callApi('v1/client/resource/create', ['business_slug' => (string) currentBusinessSlug(), 'resource' => $resource])
            : $this->callApi('v1/client/resource/edit', ['public_id' => $publicId, 'resource' => $resource]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->saveFailed($code, $body, lang('Resources.form.failed'));
        }
        $savedId = (string) ($body['data']['public_id'] ?? $publicId);
        return $this->response->setJSON([
            'success'   => true,
            'message'   => $body['message'] ?? lang('' === $publicId ? 'Resources.form.created' : 'Resources.form.saved'),
            'redirect'  => base_url('admin/business/resources/edit/' . rawurlencode($savedId)),
            'csrf_hash' => csrf_hash(),
        ]);
    }

    // ---------------------------------------------------------------- what a service needs

    /**
     * The form config of a service's "Resources needed" card, for Artifacts::form(): a row per resource type, ticked
     * when the service needs it, with how many one booking holds. An inactive type is listed only while the service
     * still needs it.
     * @param string $publicId artifact_master.public_id of the service
     * @param string $locale
     * @return array generic_form's extraForms entry
     * @throws RuntimeException when the API cannot be reached
     */
    public function needsFormConfig(string $publicId, string $locale): array
    {
        [$code, $body] = $this->callApi('v1/client/artifact/resources', ['public_id' => $publicId]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            throw new RuntimeException((string) ($body['message'] ?? lang('Resources.needs.load-failed')));
        }
        $needed = [];
        foreach ((array) ($body['data']['resources'] ?? []) as $need) {
            $needed[(string) $need['public_id']] = (int) ($need['quantity'] ?? 1);
        }
        [$types, $error] = $this->loadTypes();
        if (null !== $error) {
            throw new RuntimeException($error);
        }
        $rows = [];
        foreach ($types as $type) {
            $id = (string) ($type['public_id'] ?? '');
            if (self::STATUS_ACTIVE !== ($type['type_status'] ?? '') && ! isset($needed[$id])) {
                continue;
            }
            $rows[] = [
                'public_id'   => $id,
                'type_name'   => self::typeName($type, $locale),
                'type_status' => (string) ($type['type_status'] ?? ''),
                'needed'      => isset($needed[$id]),
                'quantity'    => $needed[$id] ?? 1,
                'available'   => (int) ($type['active_resource_count'] ?? 0),
            ];
        }

        return [
            'form_id'   => 'service-resources-form',
            'title'     => lang('Resources.needs.title'),
            'subtitle'  => lang('Resources.needs.subtitle'),
            'mode'      => 'edit',
            'id_prefix' => 'needs-',
            'locale'    => $locale,
            'urls'      => ['submit' => base_url('admin/artifacts/resources/save')],
            'messages'  => ['saved' => lang('Resources.needs.saved'), 'saveFailed' => lang('Resources.needs.failed')],
            'values'    => ['artifact_public_id' => $publicId],
            'fields'    => [
                ['type' => 'html', 'html' => view('resources/needs_editor', [
                    'rows'     => $rows,
                    'field'    => self::NEEDS_FIELD,
                    'typesUrl' => base_url('admin/business/resource-types'),
                ])],
                ['name' => 'artifact_public_id', 'type' => 'hidden', 'default' => $publicId],
            ],
        ];
    }

    /**
     * Save what a service needs through LINK_INTERNAL_API v1/client/artifact/resources/save { public_id, resources }:
     * only the ticked types are sent
     */
    public function saveNeeds(): ResponseInterface
    {
        $post     = (array) $this->request->getPost();
        $publicId = trim((string) ($post['artifact_public_id'] ?? ''));
        $needs    = [];
        foreach ((array) ($post[self::NEEDS_FIELD] ?? []) as $typeId => $need) {
            $need = (array) $need;
            if (empty($need['needed'])) {
                continue;
            }
            $quantity = trim((string) ($need['quantity'] ?? ''));
            $needs[]  = ['public_id' => (string) $typeId, 'quantity' => '' === $quantity ? 1 : $quantity];
        }
        [$code, $body] = $this->callApi('v1/client/artifact/resources/save', ['public_id' => $publicId, 'resources' => $needs]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            // the editor's rows are not fields of the form, so the API's errors (resources.<index>.<field>) are told in the message
            $errors = array_values(array_unique(array_map('strval', (array) ($body['errors'] ?? []))));
            if ([] !== $errors) {
                $body['message'] = implode(' ', $errors);
                unset($body['errors']);
            }
            return $this->saveFailed($code, $body, lang('Resources.needs.failed'));
        }
        return $this->response->setJSON([
            'success'   => true,
            'message'   => $body['message'] ?? lang('Resources.needs.saved'),
            'redirect'  => base_url('admin/artifacts/edit/' . rawurlencode($publicId)),
            'csrf_hash' => csrf_hash(),
        ]);
    }

    // ---------------------------------------------------------------- configs

    /**
     * Fields of the resource type form: the type, then its name and description in each language of the
     * business's web pages (none while it has no languages)
     */
    private function typeFields(): array
    {
        $statuses = lang('Resources.enum.status');
        $fields   = [
            ['type' => 'heading', 'label' => lang('Resources.heading.type')],
            ['name' => 'type_name', 'type' => 'text', 'label' => lang('Resources.fields.type_name'), 'rules' => 'required|max_length[255]',
                'help' => lang('Resources.help.type-name')],
            ['name' => 'type_description', 'type' => 'textarea', 'label' => lang('Resources.fields.type_description'), 'rules' => 'max_length[10000]',
                'optional' => true, 'rows' => 3],
            ['name' => 'buffer_minutes', 'type' => 'number', 'label' => lang('Resources.fields.buffer_minutes'), 'default' => 0,
                'rules' => 'required|is_natural|less_than_equal_to[1440]', 'attrs' => ['min' => 0, 'max' => 1440, 'step' => 1],
                'help' => lang('Resources.help.buffer')],
            ['name' => 'type_status', 'type' => 'select', 'label' => lang('Resources.fields.type_status'), 'options' => $statuses,
                'default' => self::STATUS_ACTIVE, 'rules' => 'required|in_list[' . self::STATUS_ACTIVE . ',' . self::STATUS_INACTIVE . ']',
                'help' => lang('Resources.help.type-status')],
        ];
        $languages = $this->websiteLanguages();
        if ([] !== $languages) {
            $fields[] = ['type' => 'heading', 'label' => lang('Resources.heading.translations')];
        }
        foreach ($languages as $language => $label) {
            $fields[] = ['name' => self::NAME_PREFIX . $language, 'type' => 'text', 'label' => lang('Resources.translation-name', [$label]),
                'rules' => 'max_length[255]', 'optional' => true];
            $fields[] = ['name' => self::DESCRIPTION_PREFIX . $language, 'type' => 'textarea', 'label' => lang('Resources.translation-description', [$label]),
                'rules' => 'max_length[10000]', 'optional' => true, 'rows' => 3];
        }
        return $fields;
    }

    /**
     * Fields of the resource form; the store and the type are picked when it is added only
     * @param array<string, string> $stores public_id => name
     * @param array                 $types  options of the type select
     */
    private function resourceFields(array $stores, array $types): array
    {
        $statuses = lang('Resources.enum.status');
        return [
            ['type' => 'heading', 'label' => lang('Resources.heading.resource')],
            ['name' => 'store_public_id', 'type' => 'select', 'label' => lang('Resources.fields.store'), 'options' => $stores,
                'rules' => 'required', 'disabled_in' => ['edit'], 'view' => static fn ($v) => $stores[$v] ?? $v,
                'help' => lang([] === $stores ? 'Resources.help.no-store' : 'Resources.help.store')],
            ['name' => 'type_public_id', 'type' => 'select', 'label' => lang('Resources.fields.type'), 'options' => $types,
                'rules' => 'required', 'disabled_in' => ['edit'], 'view' => static function ($v) use ($types) {
                    foreach ($types as $option) {
                        if ($option['value'] === $v) {
                            return $option['label'];
                        }
                    }
                    return $v;
                }, 'help' => lang([] === $types ? 'Resources.help.no-type' : 'Resources.help.type')],
            ['name' => 'resource_name', 'type' => 'text', 'label' => lang('Resources.fields.resource_name'), 'rules' => 'required|max_length[255]',
                'help' => lang('Resources.help.resource-name')],
            ['name' => 'capacity', 'type' => 'number', 'label' => lang('Resources.fields.capacity'), 'default' => 1,
                'rules' => 'required|is_natural_no_zero|less_than_equal_to[65535]', 'attrs' => ['min' => 1, 'max' => 65535, 'step' => 1],
                'help' => lang('Resources.help.capacity')],
            ['name' => 'resource_status', 'type' => 'select', 'label' => lang('Resources.fields.resource_status'), 'options' => $statuses,
                'default' => self::STATUS_ACTIVE, 'rules' => 'required|in_list[' . self::STATUS_ACTIVE . ',' . self::STATUS_INACTIVE . ']',
                'help' => lang('Resources.help.resource-status')],
            ['name' => 'sort_order', 'type' => 'number', 'label' => lang('Resources.fields.sort_order'), 'default' => 0,
                'rules' => 'required|is_natural|less_than_equal_to[65535]', 'attrs' => ['min' => 0, 'max' => 65535, 'step' => 1],
                'help' => lang('Resources.help.sort-order')],
        ];
    }

    /**
     * Table Builder config of types()
     */
    private function typeListConfig(): array
    {
        return [
            'table_id' => 'resource-types-table',
            'title'    => lang('Resources.type-list.title'),
            'subtitle' => lang('Resources.type-list.subtitle', [(string) currentBusinessName()]),
            'id_field' => 'public_id',
            'urls'     => [
                'data'   => base_url('admin/business/resource-types/data'),
                'create' => base_url('admin/business/resource-types/create'),
                'edit'   => base_url('admin/business/resource-types/edit/{public_id}'),
            ],
            'labels'   => ['add' => lang('Resources.type-list.add')],
            'messages' => ['loadFailed' => lang('Resources.type-list.load-failed')],
            'columns'  => [
                ['data' => 'type_name', 'title' => lang('Resources.fields.type_name')],
                ['data' => 'type_status', 'title' => lang('Resources.fields.type_status'), 'type' => 'badge', 'badges' => $this->statusBadges()],
                ['data' => 'buffer_minutes', 'title' => lang('Resources.fields.buffer_minutes'), 'type' => 'number', 'decimals' => 0, 'class' => 'text-end', 'searchable' => false],
                ['data' => 'resource_count', 'title' => lang('Resources.type-list.resource-count'), 'class' => 'text-end', 'searchable' => false],
                ['data' => 'service_count', 'title' => lang('Resources.type-list.service-count'), 'type' => 'number', 'decimals' => 0, 'class' => 'text-end', 'searchable' => false],
            ],
            'order'    => [['type_name', 'asc']],
        ];
    }

    /**
     * Table Builder config of index(); its data and Add carry the page's ?store= and ?type=
     */
    private function resourceListConfig(): array
    {
        $query = http_build_query(array_filter(['store' => (string) $this->request->getGet('store'), 'type' => (string) $this->request->getGet('type')], 'strlen'));
        $query = '' === $query ? '' : '?' . $query;
        return [
            'table_id' => 'resources-table',
            'title'    => lang('Resources.list.title'),
            'subtitle' => lang('Resources.list.subtitle', [(string) currentBusinessName()]),
            'id_field' => 'public_id',
            'urls'     => [
                'data'   => base_url('admin/business/resources/data') . $query,
                'create' => base_url('admin/business/resources/create') . $query,
                'edit'   => base_url('admin/business/resources/edit/{public_id}'),
            ],
            'labels'   => ['add' => lang('Resources.list.add')],
            'messages' => ['loadFailed' => lang('Resources.list.load-failed')],
            'columns'  => [
                ['data' => 'store_name', 'title' => lang('Resources.fields.store')],
                ['data' => 'type_name', 'title' => lang('Resources.fields.type')],
                ['data' => 'resource_name', 'title' => lang('Resources.fields.resource_name')],
                ['data' => 'capacity', 'title' => lang('Resources.fields.capacity'), 'type' => 'number', 'decimals' => 0, 'class' => 'text-end', 'searchable' => false],
                ['data' => 'resource_status', 'title' => lang('Resources.fields.resource_status'), 'type' => 'badge', 'badges' => $this->statusBadges()],
                ['data' => 'sort_order', 'title' => lang('Resources.fields.sort_order'), 'type' => 'number', 'decimals' => 0, 'class' => 'text-end', 'searchable' => false],
            ],
            'order'    => [['store_name', 'asc'], ['type_name', 'asc'], ['sort_order', 'asc'], ['resource_name', 'asc']],
        ];
    }

    private function statusBadges(): array
    {
        $statuses = lang('Resources.enum.status');
        return [
            self::STATUS_ACTIVE   => ['label' => $statuses[self::STATUS_ACTIVE], 'class' => 'bg-success'],
            self::STATUS_INACTIVE => ['label' => $statuses[self::STATUS_INACTIVE], 'class' => 'bg-secondary'],
        ];
    }

    // ---------------------------------------------------------------- loading

    /**
     * The resource types of the session business, from LINK_INTERNAL_API v1/client/resource-types
     * @return array{0: array, 1: string|null} [types, null] or [[], error message]
     */
    private function loadTypes(): array
    {
        [$code, $body] = $this->callApi('v1/client/resource-types', ['business_slug' => (string) currentBusinessSlug()]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return [[], (string) ($body['message'] ?? lang('Resources.type-list.load-failed'))];
        }
        return [(array) ($body['data']['resource_types'] ?? []), null];
    }

    /**
     * One type of the session business by its public_id (the API has no single-type call)
     * @throws RuntimeException when the types cannot be loaded
     */
    private function findType(string $publicId): ?array
    {
        [$types, $error] = $this->loadTypes();
        if (null !== $error) {
            throw new RuntimeException($error);
        }
        foreach ($types as $type) {
            if ((string) ($type['public_id'] ?? '') === $publicId) {
                return (array) $type;
            }
        }
        return null;
    }

    /**
     * One resource of the session business by its public_id (the API has no single-resource call)
     * @throws RuntimeException when the resources cannot be loaded
     */
    private function findResource(string $publicId): ?array
    {
        [$code, $body] = $this->callApi('v1/client/resources', ['business_slug' => (string) currentBusinessSlug()]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            throw new RuntimeException((string) ($body['message'] ?? lang('Resources.list.load-failed')));
        }
        foreach ((array) ($body['data']['resources'] ?? []) as $resource) {
            if ((string) ($resource['public_id'] ?? '') === $publicId) {
                return (array) $resource;
            }
        }
        return null;
    }

    /**
     * The stores of the session business, from v1/client/stores
     * @return list<array>
     * @throws RuntimeException when they cannot be loaded
     */
    private function loadStores(): array
    {
        [$code, $body] = $this->callApi('v1/client/stores', ['business_slug' => (string) currentBusinessSlug()]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            throw new RuntimeException((string) ($body['message'] ?? lang('Stores.list.load-failed')));
        }
        return (array) ($body['data']['stores'] ?? []);
    }

    /**
     * @return array<string, string> store public_id => its name in the UI language
     */
    private function storeNames(string $locale): array
    {
        $out = [];
        foreach ($this->loadStores() as $store) {
            $out[(string) $store['public_id']] = (string) (($store['translations'] ?? [])[$locale] ?? $store['store_name'] ?? '');
        }
        return $out;
    }

    /**
     * The physical stores, the only ones that may have resources, and $keep, the resource's own store
     * @return array<string, string> public_id => name
     */
    private function physicalStoreOptions(string $locale, ?string $keep): array
    {
        $out = [];
        foreach ($this->loadStores() as $store) {
            $id = (string) $store['public_id'];
            if (self::STORE_PHYSICAL === ($store['store_type'] ?? '') || $id === $keep) {
                $out[$id] = (string) (($store['translations'] ?? [])[$locale] ?? $store['store_name'] ?? '');
            }
        }
        return $out;
    }

    /**
     * The active types, the only ones a new resource may be of, and $keep, the resource's own type
     * @return list<array{value: string, label: string}>
     */
    private function typeOptions(string $locale, ?string $keep): array
    {
        [$types, $error] = $this->loadTypes();
        if (null !== $error) {
            throw new RuntimeException($error);
        }
        $out = [];
        foreach ($types as $type) {
            $id = (string) ($type['public_id'] ?? '');
            if (self::STATUS_ACTIVE === ($type['type_status'] ?? '') || $id === $keep) {
                $out[] = ['value' => $id, 'label' => self::typeName($type, $locale)];
            }
        }
        return $out;
    }

    /**
     * ?store= and ?type= of the list page, as the API's filters
     * @return array{store_public_id?: string, type_public_id?: string}
     */
    private function listFilters(): array
    {
        return array_filter([
            'store_public_id' => (string) $this->request->getGet('store'),
            'type_public_id'  => (string) $this->request->getGet('type'),
        ], 'strlen');
    }

    /**
     * A type's name in the UI language, when it has one
     */
    private static function typeName(array $type, string $locale): string
    {
        return (string) ((($type['translations'] ?? [])[$locale] ?? [])['type_name'] ?? $type['type_name'] ?? '');
    }

    /**
     * Form response of a failed type create or edit: the API names the errors of the texts
     * translations.<language>.<column>, which are renamed for the form
     */
    private function typeFailed(int $code, array $body): ResponseInterface
    {
        $errors = [];
        foreach ((array) ($body['errors'] ?? []) as $key => $message) {
            $key = (string) $key;
            if (preg_match('/^translations\.([^.]+)(?:\.(type_name|type_description))?$/', $key, $m)) {
                $errors[('type_description' === ($m[2] ?? '') ? self::DESCRIPTION_PREFIX : self::NAME_PREFIX) . $m[1]] = $message;
            } else {
                $errors[$key] = $message;
            }
        }
        return $this->response->setStatusCode($code === 200 ? 422 : $code)->setJSON([
            'success'   => false,
            'message'   => $body['message'] ?? lang('Resources.type-form.failed'),
            'errors'    => $errors ?: null,
            'csrf_hash' => csrf_hash(),
        ]);
    }
}
