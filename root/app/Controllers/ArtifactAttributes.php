<?php

namespace App\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;

/**
 * The options the variants of the session business's products and services differ by (artifact_attribute: Color,
 * Size) with their choices (artifact_attribute_value: Blue, Red), from LINK_INTERNAL_API v1/client/artifact-attribute*:
 * its owners and managers only (see BusinessManagerFilter). Neither is ever deleted, as variants may use them.
 */
class ArtifactAttributes extends BaseController
{
    /** Form field name of the option's name in a language: translation_<language_code> */
    private const TRANSLATION_PREFIX = 'translation_';

    /** Hidden field of the form holding the choices editor's choices, as JSON */
    private const VALUES_FIELD = 'values_json';

    /** The most choices the API takes at a time */
    private const MAX_VALUES = 100;

    private const FORM_ID = 'artifact-attribute-form';

    /**
     * The options (generic_list), by name; each row opens one
     */
    public function index(): string
    {
        return view('generic_list', [
            'locale'      => $this->request->getLocale(),
            'slug'        => 'artifact-attributes',
            'breadcrumbs' => [base_url('admin/artifacts') => lang('System.page.artifacts')],
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

        $locale = $this->request->getLocale();
        $rows   = [];
        foreach ($attributes as $attribute) {
            $values = array_map(static fn (array $value) => (string) (($value['translations'] ?? [])[$locale] ?? $value['attribute_value'] ?? ''), (array) ($attribute['values'] ?? []));
            $rows[] = [
                'public_id'      => (string) ($attribute['public_id'] ?? ''),
                'attribute_name' => (string) (($attribute['translations'] ?? [])[$locale] ?? $attribute['attribute_name'] ?? ''),
                'values'         => implode(', ', $values),
                'value_count'    => count($values),
            ];
        }
        $total    = count($rows);
        $rows     = $this->searchAndOrder($rows, $params, static fn (array $row, string $column) => (string) $row[$column]);
        $filtered = count($rows);
        $rows     = lb_rows(array_slice($rows, $params['start'], $params['length']), $config);

        return $this->response->setJSON(lb_response($params['draw'], $total, $filtered, $rows) + ['csrf_hash' => csrf_hash()]);
    }

    /**
     * The form to add an option, or, with $publicId, one opened in view mode (the Edit button switches the page to
     * edit mode), with the editor of its choices
     * @param string|null $publicId artifact_attribute.public_id
     */
    public function form(?string $publicId = null): string
    {
        $values    = [];
        $attribute = null;
        if (null !== $publicId) {
            $attribute = $this->findAttribute($publicId);
            if (null === $attribute) {
                throw PageNotFoundException::forPageNotFound(lang('ArtifactAttributes.form.not-found'));
            }
            $values = ['attribute_name' => (string) ($attribute['attribute_name'] ?? '')];
            foreach ((array) ($attribute['translations'] ?? []) as $language => $name) {
                $values[self::TRANSLATION_PREFIX . $language] = $name;
            }
        }
        $locale    = $this->request->getLocale();
        $languages = $this->websiteLanguages();
        $choices   = (array) ($attribute['values'] ?? []);

        return view('generic_form', [
            'locale'       => $locale,
            'slug'         => 'artifact-attributes',
            'page_title'   => $values['attribute_name'] ?? lang('System.page.artifact-attribute-create'),
            'breadcrumbs'  => [
                base_url('admin/artifacts')            => lang('System.page.artifacts'),
                base_url('admin/artifacts/attributes') => lang('System.page.artifact-attributes'),
            ],
            'user'         => (array) session('user'),
            'formConfig'   => [
                'form_id'  => self::FORM_ID,
                'title'    => lang(null === $publicId ? 'ArtifactAttributes.form.create-title' : 'ArtifactAttributes.form.edit-title'),
                'subtitle' => lang('ArtifactAttributes.form.subtitle', [(string) currentBusinessName()]),
                'mode'     => null === $publicId ? 'create' : 'view',
                'id_field' => 'public_id',
                'id'       => (string) $publicId,
                'locale'   => $locale,
                'urls'     => [
                    'submit' => base_url('admin/artifacts/attributes/save'),
                    'edit'   => null === $publicId ? '' : base_url('admin/artifacts/attributes/edit/' . rawurlencode($publicId)),
                    'list'   => base_url('admin/artifacts/attributes'),
                ],
                'messages' => ['saved' => lang(null === $publicId ? 'ArtifactAttributes.form.created' : 'ArtifactAttributes.form.saved'),
                    'saveFailed' => lang('ArtifactAttributes.form.failed')],
                'values'   => $values,
                'fields'   => $this->fields($choices, $languages),
            ],
            'customScript' => view('scripts/attribute_form', [
                'formId'      => self::FORM_ID,
                'valuesField' => self::VALUES_FIELD,
                'values'      => $choices,
                'languages'   => $languages,
                'max'         => self::MAX_VALUES,
            ]),
        ]);
    }

    /**
     * Create or save an option through LINK_INTERNAL_API: v1/client/artifact-attribute/create { business_slug,
     * attribute, translations, values }, or, with public_id, v1/client/artifact-attribute/edit { public_id,
     * attribute, translations, values }. The form has the names in the web pages' languages only; on an edit, the names in
     * other languages are kept, of the option and of each saved choice, as the API replaces them all.
     */
    public function save(): ResponseInterface
    {
        $post     = (array) $this->request->getPost();
        $publicId = trim((string) ($post['public_id'] ?? ''));
        $choices  = json_decode((string) ($post[self::VALUES_FIELD] ?? ''), true);
        if (! is_array($choices) || ! array_is_list($choices)) {
            return $this->saveFailed(422, ['message' => lang('ArtifactAttributes.form.invalid-values')], lang('ArtifactAttributes.form.failed'));
        }
        $names       = [];
        $storedNames = []; // value public_id => language => name
        if ('' !== $publicId) {
            $stored = $this->findAttribute($publicId);
            if (null === $stored) {
                return $this->saveFailed(404, ['message' => lang('ArtifactAttributes.form.not-found')], lang('ArtifactAttributes.form.failed'));
            }
            $names = (array) ($stored['translations'] ?? []);
            foreach ((array) ($stored['values'] ?? []) as $value) {
                $storedNames[(string) $value['public_id']] = (array) ($value['translations'] ?? []);
            }
        }
        $languages = array_keys($this->websiteLanguages());
        foreach ($languages as $language) {
            $names[$language] = (string) ($post[self::TRANSLATION_PREFIX . $language] ?? '');
        }

        $values = [];
        foreach ($choices as $i => $choice) {
            if (! is_array($choice)) {
                return $this->saveFailed(422, ['message' => lang('ArtifactAttributes.form.invalid-values')], lang('ArtifactAttributes.form.failed'));
            }
            $id         = (string) ($choice['public_id'] ?? '');
            $valueNames = $storedNames[$id] ?? [];
            foreach ($languages as $language) {
                $valueNames[$language] = (string) (((array) ($choice['translations'] ?? []))[$language] ?? '');
            }
            $value = ['attribute_value' => (string) ($choice['attribute_value'] ?? ''), 'sort_order' => $i, 'translations' => (object) $valueNames];
            if ('' !== $id) {
                $value['public_id'] = $id;
            }
            $values[] = $value;
        }

        $payload = ['attribute' => ['attribute_name' => (string) ($post['attribute_name'] ?? '')], 'translations' => (object) $names, 'values' => $values];
        [$code, $body] = '' === $publicId
            ? $this->callApi('v1/client/artifact-attribute/create', ['business_slug' => (string) currentBusinessSlug()] + $payload)
            : $this->callApi('v1/client/artifact-attribute/edit', ['public_id' => $publicId] + $payload);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->attributeFailed($code, $body);
        }
        $savedId = (string) ($body['data']['attribute']['public_id'] ?? $publicId);
        return $this->response->setJSON([
            'success'   => true,
            'message'   => $body['message'] ?? lang('' === $publicId ? 'ArtifactAttributes.form.created' : 'ArtifactAttributes.form.saved'),
            'redirect'  => '' === $savedId ? base_url('admin/artifacts/attributes') : base_url('admin/artifacts/attributes/edit/' . rawurlencode($savedId)),
            'csrf_hash' => csrf_hash(),
        ]);
    }

    /**
     * Fields of the option form: its name, the choices editor (a table of its own in view mode), then its name in
     * each language of the business's web pages (optional, sent as translations; none while it has no languages)
     * @param array $choices   the saved choices, as v1/client/artifact-attributes sends them
     * @param array $languages websiteLanguages()
     */
    private function fields(array $choices, array $languages): array
    {
        $fields = [
            ['type' => 'heading', 'label' => lang('ArtifactAttributes.heading.attribute')],
            ['name' => 'attribute_name', 'type' => 'text', 'label' => lang('ArtifactAttributes.fields.attribute_name'), 'rules' => 'required|max_length[64]',
                'help' => lang('ArtifactAttributes.help.attribute-name')],
            ['type' => 'heading', 'label' => lang('ArtifactAttributes.heading.values')],
            ['type' => 'html', 'show_in' => ['create', 'edit'], 'html' => view('artifacts/attribute_values_editor', ['field' => self::VALUES_FIELD])],
            ['type' => 'html', 'show_in' => ['view'], 'html' => view('artifacts/attribute_values_view', ['values' => $choices, 'languages' => $languages])],
        ];
        if ([] !== $languages) {
            $fields[] = ['type' => 'heading', 'label' => lang('ArtifactAttributes.heading.translations')];
        }
        foreach ($languages as $language => $label) {
            $fields[] = ['name' => self::TRANSLATION_PREFIX . $language, 'type' => 'text', 'label' => lang('ArtifactAttributes.translation-label', [$label]),
                'rules' => 'max_length[64]', 'optional' => true];
        }
        return $fields;
    }

    /**
     * Form response of a failed create or edit: the API names the errors of the option's names
     * translations.<language>, renamed for the form, and those of a choice values.<index>.<field>, told by the
     * choice's number as the choices editor is not one of the form's fields
     */
    private function attributeFailed(int $code, array $body): ResponseInterface
    {
        $languages = $this->websiteLanguages();
        $errors    = [];
        foreach ((array) ($body['errors'] ?? []) as $key => $message) {
            $key = (string) $key;
            if (preg_match('/^translations\.([^.]+)/', $key, $m)) {
                $errors[self::TRANSLATION_PREFIX . $m[1]] = $message;
            } elseif (preg_match('/^values\.(\d+)\.translations\.([^.]+)/', $key, $m)) {
                $errors[$key] = lang('ArtifactAttributes.values.error', [(int) $m[1] + 1, ($languages[$m[2]] ?? $m[2]) . ': ' . $message]);
            } elseif (preg_match('/^values\.(\d+)/', $key, $m)) {
                $errors[$key] = lang('ArtifactAttributes.values.error', [(int) $m[1] + 1, $message]);
            } else {
                $errors[$key] = $message;
            }
        }
        return $this->response->setStatusCode($code === 200 ? 422 : $code)->setJSON([
            'success'   => false,
            'message'   => $body['message'] ?? lang('ArtifactAttributes.form.failed'),
            'errors'    => $errors ?: null,
            'csrf_hash' => csrf_hash(),
        ]);
    }

    /**
     * Table Builder config of index()
     */
    private function listConfig(): array
    {
        return [
            'table_id' => 'artifact-attributes-table',
            'title'    => lang('ArtifactAttributes.list.title'),
            'subtitle' => lang('ArtifactAttributes.list.subtitle', [(string) currentBusinessName()]),
            'id_field' => 'public_id',
            'urls'     => [
                'data'   => base_url('admin/artifacts/attributes/data'),
                'create' => base_url('admin/artifacts/attributes/create'),
                'edit'   => base_url('admin/artifacts/attributes/edit/{public_id}'),
            ],
            'labels'   => ['add' => lang('ArtifactAttributes.list.add')],
            'messages' => ['loadFailed' => lang('ArtifactAttributes.list.load-failed')],
            'columns'  => [
                ['data' => 'attribute_name', 'title' => lang('ArtifactAttributes.fields.attribute_name')],
                ['data' => 'values', 'title' => lang('ArtifactAttributes.list.values'), 'orderable' => false],
                ['data' => 'value_count', 'title' => lang('ArtifactAttributes.list.value-count'), 'type' => 'number', 'decimals' => 0, 'class' => 'text-end', 'searchable' => false],
            ],
            'order'    => [['attribute_name', 'asc']],
        ];
    }

    /**
     * The options of the session business with their choices, from LINK_INTERNAL_API v1/client/artifact-attributes
     * @return array{0: array, 1: string|null} [attributes, null] or [[], error message]
     */
    private function loadAttributes(): array
    {
        [$code, $body] = $this->callApi('v1/client/artifact-attributes', ['business_slug' => (string) currentBusinessSlug()]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return [[], (string) ($body['message'] ?? lang('ArtifactAttributes.list.load-failed'))];
        }
        return [(array) ($body['data']['attributes'] ?? []), null];
    }

    /**
     * One option of the session business by its public_id (the API has no single-option call)
     * @return array|null null when the business has no such option
     * @throws RuntimeException when the options cannot be loaded
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
