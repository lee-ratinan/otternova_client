<?php

namespace App\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;

/**
 * What the session business records about its people (business_client_attribute: "Certification", "Specialized in",
 * "Height"), from LINK_INTERNAL_API v1/client/staff-attribute*: its owners and managers only (the routes' manager
 * filter). The business decides what it records; each person's values are filled in on their profile page
 * (StaffProfiles). Deleting an attribute deletes everyone's values of it.
 */
class StaffAttributes extends BaseController
{
    /** Form field name of the attribute's name in a language: translation_<language_code> */
    private const TRANSLATION_PREFIX = 'translation_';

    private const FORM_ID = 'staff-attribute-form';

    /**
     * The attributes (generic_list), in their order; each row opens one
     */
    public function index(): string
    {
        return view('generic_list', [
            'locale'      => $this->request->getLocale(),
            'slug'        => 'staff-attributes',
            'breadcrumbs' => [base_url('admin/staff') => lang('System.page.staff')],
            'user'        => (array) session('user'),
            'listConfig'  => $this->listConfig(),
        ]);
    }

    /**
     * DataTables data of index(); the API sends them all, and the search, order and paging are done here
     */
    public function data(): ResponseInterface
    {
        $config = $this->listConfig();
        $params = lb_request($this->request, $config);
        [$attributes, $error] = $this->loadAttributes();
        if (null !== $error) {
            return $this->response->setJSON(lb_response($params['draw'], 0, 0, []) + ['error' => $error, 'csrf_hash' => csrf_hash()]);
        }

        $locale     = $this->request->getLocale();
        $visibility = lang('StaffAttributes.visibility');
        $rows       = [];
        foreach ($attributes as $attribute) {
            $rows[] = [
                'public_id'      => (string) ($attribute['public_id'] ?? ''),
                'attribute_name' => (string) (($attribute['translations'] ?? [])[$locale] ?? $attribute['attribute_name'] ?? ''),
                'is_public'      => empty($attribute['is_public']) ? 'private' : 'public',
                'sort_order'     => (int) ($attribute['sort_order'] ?? 0),
                'value_count'    => (int) ($attribute['value_count'] ?? 0),
            ];
        }
        $total    = count($rows);
        $rows     = $this->searchAndOrder($rows, $params, static fn (array $row, string $column) => 'is_public' === $column
            ? (string) ($visibility[$row[$column]] ?? $row[$column]) : (string) $row[$column]);
        $filtered = count($rows);
        $rows     = lb_rows(array_slice($rows, $params['start'], $params['length']), $config);

        return $this->response->setJSON(lb_response($params['draw'], $total, $filtered, $rows) + ['csrf_hash' => csrf_hash()]);
    }

    /**
     * The form to add an attribute, or, with $publicId, one opened in view mode (the Edit button switches the page
     * to edit mode, and Delete deletes it with everyone's values of it)
     * @param string|null $publicId business_client_attribute.public_id
     */
    public function form(?string $publicId = null): string
    {
        $values    = ['is_public' => '1', 'sort_order' => 0];
        $attribute = null;
        if (null !== $publicId) {
            $attribute = $this->findAttribute($publicId);
            if (null === $attribute) {
                throw PageNotFoundException::forPageNotFound(lang('StaffAttributes.form.not-found'));
            }
            $values = [
                'attribute_name' => (string) ($attribute['attribute_name'] ?? ''),
                'is_public'      => empty($attribute['is_public']) ? '0' : '1',
                'sort_order'     => (int) ($attribute['sort_order'] ?? 0),
            ];
            foreach ((array) ($attribute['translations'] ?? []) as $language => $name) {
                $values[self::TRANSLATION_PREFIX . $language] = $name;
            }
        }
        $locale    = $this->request->getLocale();
        $count     = (int) ($attribute['value_count'] ?? 0);

        return view('generic_form', [
            'locale'      => $locale,
            'slug'        => 'staff-attributes',
            'page_title'  => $values['attribute_name'] ?? lang('System.page.staff-attribute-create'),
            'breadcrumbs' => [
                base_url('admin/staff')            => lang('System.page.staff'),
                base_url('admin/staff/attributes') => lang('System.page.staff-attributes'),
            ],
            'user'        => (array) session('user'),
            'formConfig'  => [
                'form_id'  => self::FORM_ID,
                'title'    => lang(null === $publicId ? 'StaffAttributes.form.create-title' : 'StaffAttributes.form.edit-title'),
                'subtitle' => lang('StaffAttributes.form.subtitle', [(string) currentBusinessName()]),
                'mode'     => null === $publicId ? 'create' : 'view',
                'id_field' => 'public_id',
                'id'       => (string) $publicId,
                'locale'   => $locale,
                'urls'     => [
                    'submit' => base_url('admin/staff/attributes/save'),
                    'delete' => null === $publicId ? '' : base_url('admin/staff/attributes/delete'),
                    'edit'   => null === $publicId ? '' : base_url('admin/staff/attributes/edit/' . rawurlencode($publicId)),
                    'list'   => base_url('admin/staff/attributes'),
                ],
                'messages' => [
                    'saved'         => lang(null === $publicId ? 'StaffAttributes.form.created' : 'StaffAttributes.form.saved'),
                    'saveFailed'    => lang('StaffAttributes.form.failed'),
                    'deleted'       => lang('StaffAttributes.form.deleted'),
                    'deleteFailed'  => lang('StaffAttributes.form.delete-failed'),
                    'confirmDelete' => lang(0 === $count ? 'StaffAttributes.form.confirm-delete' : 'StaffAttributes.form.confirm-delete-values', [$count]),
                ],
                'values'   => $values,
                'fields'   => $this->fields(),
            ],
        ]);
    }

    /**
     * Create or save an attribute through LINK_INTERNAL_API: v1/client/staff-attribute/create { business_slug,
     * attribute, translations }, or, with public_id, v1/client/staff-attribute/edit { public_id, attribute,
     * translations }. The form has the names in the web pages' languages only; on an edit, the names in other
     * languages are kept, as the API replaces them all.
     */
    public function save(): ResponseInterface
    {
        $post     = (array) $this->request->getPost();
        $publicId = trim((string) ($post['public_id'] ?? ''));
        $names    = [];
        if ('' !== $publicId) {
            $stored = $this->findAttribute($publicId);
            if (null === $stored) {
                return $this->saveFailed(404, ['message' => lang('StaffAttributes.form.not-found')], lang('StaffAttributes.form.failed'));
            }
            $names = (array) ($stored['translations'] ?? []);
        }
        foreach (array_keys($this->websiteLanguages()) as $language) {
            $names[$language] = (string) ($post[self::TRANSLATION_PREFIX . $language] ?? '');
        }
        $payload = [
            'attribute'    => [
                'attribute_name' => (string) ($post['attribute_name'] ?? ''),
                'is_public'      => '1' === (string) ($post['is_public'] ?? '0'),
                'sort_order'     => trim((string) ($post['sort_order'] ?? '')) === '' ? 0 : trim((string) $post['sort_order']),
            ],
            'translations' => (object) $names,
        ];
        [$code, $body] = '' === $publicId
            ? $this->callApi('v1/client/staff-attribute/create', ['business_slug' => (string) currentBusinessSlug()] + $payload)
            : $this->callApi('v1/client/staff-attribute/edit', ['public_id' => $publicId] + $payload);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            // translations.<language> is the form's translation_<language>
            $errors = [];
            foreach ((array) ($body['errors'] ?? []) as $key => $message) {
                $errors[preg_match('/^translations\.([^.]+)/', (string) $key, $m) ? self::TRANSLATION_PREFIX . $m[1] : (string) $key] = $message;
            }
            $body['errors'] = $errors;
            return $this->response->setStatusCode($code === 200 ? 422 : $code)->setJSON([
                'success'   => false,
                'message'   => $body['message'] ?? lang('StaffAttributes.form.failed'),
                'errors'    => $errors ?: null,
                'csrf_hash' => csrf_hash(),
            ]);
        }
        $savedId = (string) ($body['data']['attribute']['public_id'] ?? $publicId);
        return $this->response->setJSON([
            'success'   => true,
            'message'   => $body['message'] ?? lang('' === $publicId ? 'StaffAttributes.form.created' : 'StaffAttributes.form.saved'),
            'redirect'  => base_url('admin/staff/attributes/edit/' . rawurlencode($savedId)),
            'csrf_hash' => csrf_hash(),
        ]);
    }

    /**
     * Delete an attribute with everyone's values of it through LINK_INTERNAL_API v1/client/staff-attribute/delete
     */
    public function delete(): ResponseInterface
    {
        $publicId = trim((string) $this->request->getPost('public_id'));
        [$code, $body] = $this->callApi('v1/client/staff-attribute/delete', ['public_id' => $publicId]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->saveFailed($code, $body, lang('StaffAttributes.form.delete-failed'));
        }
        return $this->response->setJSON([
            'success'   => true,
            'message'   => $body['message'] ?? lang('StaffAttributes.form.deleted'),
            'redirect'  => base_url('admin/staff/attributes'),
            'csrf_hash' => csrf_hash(),
        ]);
    }

    /**
     * Fields of the attribute form: its name, whether customers see it, its place, then its name in each language
     * of the business's web pages (optional, sent as translations; none while it has no languages)
     */
    private function fields(): array
    {
        $visibility = lang('StaffAttributes.visibility');
        $fields     = [
            ['type' => 'heading', 'label' => lang('StaffAttributes.heading.attribute')],
            ['name' => 'attribute_name', 'type' => 'text', 'label' => lang('StaffAttributes.fields.attribute_name'), 'rules' => 'required|max_length[64]',
                'help' => lang('StaffAttributes.help.attribute-name')],
            ['name' => 'is_public', 'type' => 'switch', 'label' => lang('StaffAttributes.fields.is_public'), 'text' => lang('StaffAttributes.help.is-public-switch'),
                'checked_value' => '1', 'off_value' => '0', 'help' => lang('StaffAttributes.help.is-public'),
                'view_labels' => ['on' => $visibility['public'], 'off' => $visibility['private']]],
            ['name' => 'sort_order', 'type' => 'number', 'label' => lang('StaffAttributes.fields.sort_order'), 'rules' => 'permit_empty|integer|greater_than_equal_to[0]|less_than_equal_to[65535]',
                'min' => 0, 'max' => 65535, 'step' => 1, 'optional' => true, 'help' => lang('StaffAttributes.help.sort-order')],
        ];
        $languages = $this->websiteLanguages();
        if ([] !== $languages) {
            $fields[] = ['type' => 'heading', 'label' => lang('StaffAttributes.heading.translations')];
        }
        foreach ($languages as $language => $label) {
            $fields[] = ['name' => self::TRANSLATION_PREFIX . $language, 'type' => 'text', 'label' => lang('StaffAttributes.translation-label', [$label]),
                'rules' => 'max_length[64]', 'optional' => true];
        }
        return $fields;
    }

    /**
     * Table Builder config of index()
     */
    private function listConfig(): array
    {
        $visibility = lang('StaffAttributes.visibility');
        return [
            'table_id' => 'staff-attributes-table',
            'title'    => lang('StaffAttributes.list.title'),
            'subtitle' => lang('StaffAttributes.list.subtitle', [(string) currentBusinessName()]),
            'id_field' => 'public_id',
            'urls'     => [
                'data'   => base_url('admin/staff/attributes/data'),
                'create' => base_url('admin/staff/attributes/create'),
                'edit'   => base_url('admin/staff/attributes/edit/{public_id}'),
            ],
            'labels'   => ['add' => lang('StaffAttributes.list.add')],
            'messages' => ['loadFailed' => lang('StaffAttributes.list.load-failed')],
            'columns'  => [
                ['data' => 'sort_order', 'title' => lang('StaffAttributes.fields.sort_order'), 'type' => 'number', 'decimals' => 0, 'class' => 'text-end', 'searchable' => false],
                ['data' => 'attribute_name', 'title' => lang('StaffAttributes.fields.attribute_name')],
                ['data' => 'is_public', 'title' => lang('StaffAttributes.fields.is_public'), 'type' => 'badge', 'badges' => [
                    'public'  => ['label' => $visibility['public'], 'class' => 'bg-success'],
                    'private' => ['label' => $visibility['private'], 'class' => 'bg-secondary'],
                ]],
                ['data' => 'value_count', 'title' => lang('StaffAttributes.list.value-count'), 'type' => 'number', 'decimals' => 0, 'class' => 'text-end', 'searchable' => false],
            ],
            'order'    => [['sort_order', 'asc'], ['attribute_name', 'asc']],
        ];
    }

    /**
     * The attributes of the session business, from LINK_INTERNAL_API v1/client/staff-attributes
     * @return array{0: array, 1: string|null} [attributes, null] or [[], error message]
     */
    private function loadAttributes(): array
    {
        [$code, $body] = $this->callApi('v1/client/staff-attributes', ['business_slug' => (string) currentBusinessSlug()]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return [[], (string) ($body['message'] ?? lang('StaffAttributes.list.load-failed'))];
        }
        return [(array) ($body['data']['attributes'] ?? []), null];
    }

    /**
     * One attribute of the session business by its public_id (the API has no single-attribute call)
     * @return array|null null when the business has no such attribute
     * @throws RuntimeException when the attributes cannot be loaded
     */
    private function findAttribute(string $publicId): ?array
    {
        [$attributes, $error] = $this->loadAttributes();
        if (null !== $error) {
            throw new RuntimeException($error);
        }
        foreach ($attributes as $attribute) {
            if ((string) ($attribute['public_id'] ?? '') === $publicId) {
                return (array) $attribute;
            }
        }
        return null;
    }
}
