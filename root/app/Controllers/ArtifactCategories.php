<?php

namespace App\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;

/**
 * The categories of the session business's products and services (artifact_category), from LINK_INTERNAL_API
 * v1/client/artifact-categor*: its owners and managers only (see BusinessManagerFilter). A category is never
 * deleted; an inactive one is taken out of use.
 */
class ArtifactCategories extends BaseController
{
    private const STATUS_ACTIVE   = 'A';
    private const STATUS_INACTIVE = 'I';

    /** The fields of the form that go to the API as "category" */
    private const FIELDS = ['category_name', 'category_status', 'sort_order'];

    /** Form field name of the name in a language: translation_<language_code> */
    private const TRANSLATION_PREFIX = 'translation_';

    /**
     * The categories (generic_list), in their order; each row opens one
     */
    public function index(): string
    {
        return view('generic_list', [
            'locale'      => $this->request->getLocale(),
            'slug'        => 'artifact-categories',
            'breadcrumbs' => [base_url('admin/artifacts') => lang('System.page.artifacts')],
            'user'        => (array) session('user'),
            'listConfig'  => $this->listConfig(),
        ]);
    }

    /**
     * DataTables data of index(). A business has a handful of categories, so the API sends them all and the
     * search, order and paging are done here.
     */
    public function data(): ResponseInterface
    {
        $config = $this->listConfig();
        $params = lb_request($this->request, $config);
        [$categories, $error] = $this->loadCategories();
        if (null !== $error) {
            return $this->response->setJSON(lb_response($params['draw'], 0, 0, []) + ['error' => $error, 'csrf_hash' => csrf_hash()]);
        }

        $locale = $this->request->getLocale();
        $rows   = [];
        foreach ($categories as $category) {
            $rows[] = [
                'public_id'       => (string) ($category['public_id'] ?? ''),
                // the name in the UI language, when the category has one
                'category_name'   => (string) (($category['translations'] ?? [])[$locale] ?? $category['category_name'] ?? ''),
                'category_status' => (string) ($category['category_status'] ?? ''),
                'sort_order'      => (int) ($category['sort_order'] ?? 0),
                'artifact_count'  => (int) ($category['artifact_count'] ?? 0),
            ];
        }
        $total    = count($rows);
        $statuses = lang('ArtifactCategories.enum.category_status');
        $rows     = $this->searchAndOrder($rows, $params, static fn (array $row, string $column) => match ($column) {
            'category_status' => (string) ($statuses[$row[$column]] ?? $row[$column]),
            // numbers in order, as the comparison is a natural one
            default => (string) $row[$column],
        });
        $filtered = count($rows);
        $rows     = lb_rows(array_slice($rows, $params['start'], $params['length']), $config);

        return $this->response->setJSON(lb_response($params['draw'], $total, $filtered, $rows) + ['csrf_hash' => csrf_hash()]);
    }

    /**
     * The form to add a category, or, with $publicId, one opened in view mode (the Edit button switches the page
     * to edit mode)
     * @param string|null $publicId artifact_category.public_id
     */
    public function form(?string $publicId = null): string
    {
        $values = [];
        if (null !== $publicId) {
            $category = $this->findCategory($publicId);
            if (null === $category) {
                throw PageNotFoundException::forPageNotFound(lang('ArtifactCategories.form.not-found'));
            }
            $values = $category;
            foreach ((array) ($category['translations'] ?? []) as $language => $name) {
                $values[self::TRANSLATION_PREFIX . $language] = $name;
            }
            unset($values['translations']);
        }
        $locale = $this->request->getLocale();
        return view('generic_form', [
            'locale'      => $locale,
            'slug'        => 'artifact-categories',
            'page_title'  => $values['category_name'] ?? lang('System.page.artifact-category-create'),
            'breadcrumbs' => [
                base_url('admin/artifacts')            => lang('System.page.artifacts'),
                base_url('admin/artifacts/categories') => lang('System.page.artifact-categories'),
            ],
            'user'        => (array) session('user'),
            'formConfig'  => [
                'form_id'  => 'artifact-category-form',
                'title'    => lang(null === $publicId ? 'ArtifactCategories.form.create-title' : 'ArtifactCategories.form.edit-title'),
                'subtitle' => lang('ArtifactCategories.form.subtitle', [(string) currentBusinessName()]),
                'mode'     => null === $publicId ? 'create' : 'view',
                'id_field' => 'public_id',
                'id'       => (string) $publicId,
                'locale'   => $locale,
                'urls'     => [
                    'submit' => base_url('admin/artifacts/categories/save'),
                    'edit'   => null === $publicId ? '' : base_url('admin/artifacts/categories/edit/' . rawurlencode($publicId)),
                    'list'   => base_url('admin/artifacts/categories'),
                ],
                'messages' => ['saved' => lang(null === $publicId ? 'ArtifactCategories.form.created' : 'ArtifactCategories.form.saved'),
                    'saveFailed' => lang('ArtifactCategories.form.failed')],
                'values'   => $values,
                'fields'   => $this->fields(),
            ],
        ]);
    }

    /**
     * Create or save a category through LINK_INTERNAL_API: v1/client/artifact-category/create { business_slug,
     * category, translations }, or, with public_id, v1/client/artifact-category/edit { public_id, category,
     * translations }. The form has the names in the web pages' languages only; on an edit, the names in other languages are
     * kept, as the API replaces them all.
     */
    public function save(): ResponseInterface
    {
        $post     = (array) $this->request->getPost();
        $publicId = trim((string) ($post['public_id'] ?? ''));
        $category = array_intersect_key($post, array_flip(self::FIELDS));
        $names    = [];
        if ('' !== $publicId) {
            $stored = $this->findCategory($publicId);
            if (null === $stored) {
                return $this->saveFailed(404, ['message' => lang('ArtifactCategories.form.not-found')], lang('ArtifactCategories.form.failed'));
            }
            $names = (array) ($stored['translations'] ?? []);
        }
        foreach (array_keys($this->websiteLanguages()) as $language) {
            $names[$language] = (string) ($post[self::TRANSLATION_PREFIX . $language] ?? '');
        }

        [$code, $body] = '' === $publicId
            ? $this->callApi('v1/client/artifact-category/create', ['business_slug' => (string) currentBusinessSlug(), 'category' => $category, 'translations' => (object) $names])
            : $this->callApi('v1/client/artifact-category/edit', ['public_id' => $publicId, 'category' => $category, 'translations' => (object) $names]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->saveFailed($code, $this->translationErrors($body), lang('ArtifactCategories.form.failed'));
        }
        $savedId = (string) ($body['data']['public_id'] ?? $publicId);
        return $this->response->setJSON([
            'success'   => true,
            'message'   => $body['message'] ?? lang('' === $publicId ? 'ArtifactCategories.form.created' : 'ArtifactCategories.form.saved'),
            'redirect'  => '' === $savedId ? base_url('admin/artifacts/categories') : base_url('admin/artifacts/categories/edit/' . rawurlencode($savedId)),
            'csrf_hash' => csrf_hash(),
        ]);
    }

    /**
     * Fields of the category form: the category, then its name in each language of the business's web pages
     * (optional, sent as translations; none while it has no languages)
     */
    private function fields(): array
    {
        $fields = [
            ['type' => 'heading', 'label' => lang('ArtifactCategories.heading.category')],
            ['name' => 'category_name', 'type' => 'text', 'label' => lang('ArtifactCategories.fields.category_name'), 'rules' => 'required|max_length[255]'],
            ['name' => 'category_status', 'type' => 'select', 'label' => lang('ArtifactCategories.fields.category_status'),
                'options' => lang('ArtifactCategories.enum.category_status'), 'default' => self::STATUS_ACTIVE,
                'rules' => 'required|in_list[' . self::STATUS_ACTIVE . ',' . self::STATUS_INACTIVE . ']', 'help' => lang('ArtifactCategories.help.status')],
            ['name' => 'sort_order', 'type' => 'number', 'label' => lang('ArtifactCategories.fields.sort_order'), 'default' => 0,
                'rules' => 'required|is_natural|less_than_equal_to[65535]', 'attrs' => ['min' => 0, 'max' => 65535, 'step' => 1],
                'help' => lang('ArtifactCategories.help.sort-order')],
        ];
        $languages = $this->websiteLanguages();
        if ([] !== $languages) {
            $fields[] = ['type' => 'heading', 'label' => lang('ArtifactCategories.heading.translations')];
        }
        foreach ($languages as $language => $label) {
            $fields[] = ['name' => self::TRANSLATION_PREFIX . $language, 'type' => 'text', 'label' => lang('ArtifactCategories.translation-label', [$label]),
                'rules' => 'max_length[255]', 'optional' => true];
        }
        return $fields;
    }

    /**
     * Table Builder config of index()
     */
    private function listConfig(): array
    {
        $statuses = lang('ArtifactCategories.enum.category_status');
        return [
            'table_id' => 'artifact-categories-table',
            'title'    => lang('ArtifactCategories.list.title'),
            'subtitle' => lang('ArtifactCategories.list.subtitle', [(string) currentBusinessName()]),
            'id_field' => 'public_id',
            'urls'     => [
                'data'   => base_url('admin/artifacts/categories/data'),
                'create' => base_url('admin/artifacts/categories/create'),
                'edit'   => base_url('admin/artifacts/categories/edit/{public_id}'),
            ],
            'labels'   => ['add' => lang('ArtifactCategories.list.add')],
            'messages' => ['loadFailed' => lang('ArtifactCategories.list.load-failed')],
            'columns'  => [
                ['data' => 'category_name', 'title' => lang('ArtifactCategories.fields.category_name')],
                ['data' => 'category_status', 'title' => lang('ArtifactCategories.fields.category_status'), 'type' => 'badge', 'badges' => [
                    self::STATUS_ACTIVE   => ['label' => $statuses[self::STATUS_ACTIVE], 'class' => 'bg-success'],
                    self::STATUS_INACTIVE => ['label' => $statuses[self::STATUS_INACTIVE], 'class' => 'bg-secondary'],
                ]],
                ['data' => 'sort_order', 'title' => lang('ArtifactCategories.fields.sort_order'), 'type' => 'number', 'decimals' => 0, 'class' => 'text-end', 'searchable' => false],
                ['data' => 'artifact_count', 'title' => lang('ArtifactCategories.list.artifact-count'), 'type' => 'number', 'decimals' => 0, 'class' => 'text-end', 'searchable' => false],
            ],
            'order'    => [['sort_order', 'asc'], ['category_name', 'asc']],
        ];
    }

    /**
     * The categories of the session business, from LINK_INTERNAL_API v1/client/artifact-categories
     * @return array{0: array, 1: string|null} [categories, null] or [[], error message]
     */
    private function loadCategories(): array
    {
        [$code, $body] = $this->callApi('v1/client/artifact-categories', ['business_slug' => (string) currentBusinessSlug()]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return [[], (string) ($body['message'] ?? lang('ArtifactCategories.list.load-failed'))];
        }
        return [(array) ($body['data']['categories'] ?? []), null];
    }

    /**
     * One category of the session business by its public_id (the API has no single-category call)
     * @return array|null null when the business has no such category
     * @throws RuntimeException when the categories cannot be loaded
     */
    private function findCategory(string $publicId): ?array
    {
        [$categories, $error] = $this->loadCategories();
        if (null !== $error) {
            throw new RuntimeException($error);
        }
        foreach ($categories as $category) {
            if ((string) ($category['public_id'] ?? '') === $publicId) {
                return (array) $category;
            }
        }
        return null;
    }
}
