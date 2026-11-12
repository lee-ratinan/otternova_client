<?php

namespace App\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use CURLFile;
use RuntimeException;

/**
 * The products (P) and services (S) of the session business, "artifacts" in the API: the list, the form of one with
 * its variants, and its images. Its owners and managers only (see BusinessManagerFilter), as LINK_INTERNAL_API
 * v1/client/artifact* allows.
 *
 * The plan's caps (Config\PlanLimits in the API) are shown before the API would refuse: the Add button is disabled
 * once neither type can be added, and a type at its cap cannot be picked; the variants editor stops at the variants
 * per artifact, and the images card at the images per artifact. The API still refuses what goes over them.
 */
class Artifacts extends BaseController
{
    private const TYPE_PRODUCT = 'P';
    private const TYPE_SERVICE = 'S';
    private const TYPES        = [self::TYPE_PRODUCT, self::TYPE_SERVICE];

    private const STATUS_INACTIVE     = 'I';
    private const STATUS_DISCONTINUED = 'D';
    private const STATUSES            = ['A', self::STATUS_INACTIVE, self::STATUS_DISCONTINUED];
    private const LABELS              = ['N', 'P', 'R', 'L'];
    private const TAX_NONE            = 'X';
    private const TAXES               = ['I', 'E', self::TAX_NONE];

    /** artifact_category.category_status of an inactive category */
    private const CATEGORY_INACTIVE = 'I';

    /** The fields of the form that go to the API as "artifact"; artifact_type too, on a create only */
    private const FIELDS = ['artifact_name', 'artifact_description', 'artifact_status', 'artifact_label', 'category_public_id',
        'tax_calculation', 'tax_percentage'];

    /** The fields of a variant the variants editor sends, as the API takes them */
    private const VARIANT_FIELDS = ['public_id', 'sku', 'barcode', 'price_active', 'price_compare', 'variant_status', 'weight_grams',
        'duration_minutes', 'booking_type', 'staff_required', 'capacity', 'attribute_values', 'inventory'];

    /** Form field names of the name and description in a language: translation_name_<language_code> */
    private const NAME_PREFIX        = 'translation_name_';
    private const DESCRIPTION_PREFIX = 'translation_description_';

    /** Hidden field of the form holding the variants editor's variants, as JSON */
    private const VARIANTS_FIELD = 'variants_json';

    private const FORM_ID = 'artifact-form';

    /**
     * The products and services of the session business (generic_list). Each row opens one; the subtitle tells how
     * many of each the plan allows.
     */
    public function index(): string
    {
        [, $error, $data] = $this->loadArtifacts([]);
        $config = $this->listConfig($this->categoryOptions(true));
        if (null === $error) {
            $caps = (array) ($data['caps'] ?? []);
            $config['subtitle'] .= ' ' . lang('Artifacts.list.caps', [
                planName((string) ($data['plan_code'] ?? '')),
                (int) ($caps[self::TYPE_PRODUCT]['count'] ?? 0), (int) ($caps[self::TYPE_PRODUCT]['max'] ?? 0),
                (int) ($caps[self::TYPE_SERVICE]['count'] ?? 0), (int) ($caps[self::TYPE_SERVICE]['max'] ?? 0),
            ]);
            $config['add_disabled'] = $this->addDisabled($data);
        }
        return view('generic_list', [
            'locale'     => $this->request->getLocale(),
            'slug'       => 'artifacts',
            'user'       => (array) session('user'),
            'listConfig' => $config,
        ]);
    }

    /**
     * DataTables data of index(), from LINK_INTERNAL_API v1/client/artifacts { business_slug, filters }. A business
     * has a few dozen artifacts at most, so the API sends them all and the search, order and paging are done here.
     */
    public function data(): ResponseInterface
    {
        $config  = $this->listConfig();
        $params  = lb_request($this->request, $config);
        $filters = [];
        foreach ($params['filters'] as $filter) {
            $filters[$filter['def']['name']] = (string) $filter['value'];
        }
        [$artifacts, $error] = $this->loadArtifacts($filters);
        if (null !== $error) {
            return $this->response->setJSON(lb_response($params['draw'], 0, 0, []) + ['error' => $error, 'csrf_hash' => csrf_hash()]);
        }

        $currency   = (string) (session('business')['business']['currency_code'] ?? '');
        $categories = $this->categoryOptions();
        $rows       = [];
        foreach ($artifacts as $artifact) {
            $compare = $artifact['price_compare_lowest'] ?? null;
            $count   = (int) ($artifact['rating_count'] ?? 0);
            $cover   = (string) ($artifact['cover_url'] ?? '');
            $rows[]  = [
                'public_id'       => (string) ($artifact['public_id'] ?? ''),
                'cover'           => '' === $cover
                    ? '<span class="d-inline-flex align-items-center justify-content-center rounded-2 bg-body-secondary text-muted" style="width: 40px; height: 40px;"><i class="bi bi-image"></i></span>'
                    : '<img src="' . esc($cover, 'attr') . '" alt="" class="rounded-2" style="width: 40px; height: 40px; object-fit: cover;" loading="lazy">',
                'artifact_name'   => (string) ($artifact['artifact_name'] ?? ''),
                'artifact_type'   => (string) ($artifact['artifact_type'] ?? ''),
                'artifact_status' => (string) ($artifact['artifact_status'] ?? ''),
                'artifact_label'  => (string) ($artifact['artifact_label'] ?? ''),
                'category'        => (string) ($categories[(string) ($artifact['category_public_id'] ?? '')] ?? ''),
                'price'           => esc(formatMoney($artifact['price_active_lowest'] ?? null, $currency))
                    . (null === $compare ? '' : ' <del class="text-muted small">' . esc(formatMoney($compare, $currency)) . '</del>'),
                'price_value'     => (string) ($artifact['price_active_lowest'] ?? '0'),
                'variant_count'   => (int) ($artifact['variant_count'] ?? 0),
                'rating'          => 0 === $count ? '<span class="text-muted">-</span>'
                    : '<i class="bi bi-star-fill text-warning"></i> ' . esc((string) $artifact['rating_average']) . ' <span class="text-muted small">(' . $count . ')</span>',
                'rating_value'    => (string) ($artifact['rating_average'] ?? '0'),
            ];
        }
        $total = count($rows);
        $enums = lang('Artifacts.enum');
        $rows  = $this->searchAndOrder($rows, $params, static fn (array $row, string $column) => match ($column) {
            'artifact_type', 'artifact_status', 'artifact_label' => (string) ($enums[$column][$row[$column]] ?? $row[$column]),
            'price'  => $row['price_value'],
            'rating' => $row['rating_value'],
            default  => (string) $row[$column],
        });
        $filtered = count($rows);
        $rows     = lb_rows(array_slice($rows, $params['start'], $params['length']), $config);

        return $this->response->setJSON(lb_response($params['draw'], $total, $filtered, $rows) + ['csrf_hash' => csrf_hash()]);
    }

    /**
     * The form to add a product or service, or, with $publicId, one opened in view mode (the Edit button switches
     * the page to edit mode), with its images below. A new one goes to the list when neither type can be added.
     * @param string|null $publicId artifact_master.public_id
     */
    public function form(?string $publicId = null): string|RedirectResponse
    {
        $detail = null;
        $values = [];
        if (null === $publicId) {
            [, $error, $data] = $this->loadArtifacts([]);
            if (null !== $error) {
                throw new RuntimeException($error);
            }
            if ('' !== $this->addDisabled($data)) {
                return redirect()->to(base_url('admin/artifacts'));
            }
            $plan       = (string) ($data['plan_code'] ?? '');
            $typeCaps   = (array) ($data['caps'] ?? []);
            $variantCap = ['max' => (int) ($data['per_artifact']['variants'] ?? 0), 'count' => 0];
        } else {
            $detail = $this->loadArtifact($publicId);
            if (null === $detail) {
                throw PageNotFoundException::forPageNotFound(lang('Artifacts.form.not-found'));
            }
            $plan       = (string) ($detail['plan_code'] ?? '');
            $typeCaps   = [];
            $variantCap = (array) ($detail['caps']['variants'] ?? []);
            $values     = (array) $detail['artifact'];
            foreach ((array) ($values['translations'] ?? []) as $language => $texts) {
                $values[self::NAME_PREFIX . $language]        = $texts['artifact_name'] ?? '';
                $values[self::DESCRIPTION_PREFIX . $language] = $texts['artifact_description'] ?? '';
            }
            unset($values['translations']);
        }
        $type       = (string) ($values['artifact_type'] ?? '');
        $locale     = $this->request->getLocale();
        $attributes = $this->loadAttributes($locale);
        $stores     = $this->storeOptions($locale);
        $variants   = (array) ($detail['variants'] ?? []);
        $editor     = [
            'type'       => $type,
            'variants'   => $variants,
            'attributes' => $attributes,
            'stores'     => $stores,
            'labels'     => $this->variantLabels($variants, $attributes, $type),
            'max'        => (int) ($variantCap['max'] ?? 0),
            'planName'   => planName($plan),
        ];
        $needsForm = null;
        if (null !== $publicId && self::TYPE_SERVICE === $type) {
            $resources = new Resources();
            $resources->initController($this->request, $this->response, service('logger'));
            $needsForm = $resources->needsFormConfig($publicId, $locale);
        }

        return view('generic_form', [
            'locale'       => $locale,
            'slug'         => 'artifacts',
            'page_title'   => $values['artifact_name'] ?? lang('System.page.artifact-create'),
            'breadcrumbs'  => [
                base_url('admin/artifacts') => lang('System.page.artifacts'),
            ],
            'user'         => (array) session('user'),
            'formConfig'   => [
                'form_id'  => self::FORM_ID,
                'title'    => lang(null === $publicId ? 'Artifacts.form.create-title' : 'Artifacts.form.edit-title'),
                'subtitle' => lang('Artifacts.form.subtitle', [(string) currentBusinessName()]),
                'mode'     => null === $publicId ? 'create' : 'view',
                'id_field' => 'public_id',
                'id'       => (string) $publicId,
                'locale'   => $locale,
                'urls'     => [
                    'submit' => base_url('admin/artifacts/save'),
                    'edit'   => null === $publicId ? '' : base_url('admin/artifacts/edit/' . rawurlencode($publicId)),
                    'list'   => base_url('admin/artifacts'),
                ],
                'messages' => ['saved' => lang(null === $publicId ? 'Artifacts.form.created' : 'Artifacts.form.saved'), 'saveFailed' => lang('Artifacts.form.failed')],
                'values'   => $values,
                'fields'   => $this->fields($type, $typeCaps, $plan, (string) ($values['artifact_status'] ?? ''), $editor,
                    array_intersect_key((array) ($detail['artifact'] ?? []), array_flip(['rating_count', 'rating_average', 'rating_breakdown']))),
            ],
            // a saved service: the resources it needs besides a person (see App\Controllers\Resources)
            'extraForms'   => $needsForm === null ? [] : [$needsForm],
            'extraHtml'    => view('artifacts/images', [
                'publicId' => $publicId,
                'images'   => (array) ($detail['images'] ?? []),
                'labels'   => $editor['labels'],
                'planName' => planName($plan),
            ]),
            'customScript' => view('scripts/artifact_form', [
                'formId'        => self::FORM_ID,
                'variantsField' => self::VARIANTS_FIELD,
                'editor'        => $editor,
                'taxNone'       => self::TAX_NONE,
                'images'        => null === $publicId ? null : [
                    'artifact' => $publicId,
                    'images'   => (array) ($detail['images'] ?? []),
                    'max'      => (int) ($detail['caps']['images']['max'] ?? 0),
                    'planName' => planName($plan),
                    'urls'     => [
                        'upload'  => base_url('admin/artifacts/image-upload'),
                        'delete'  => base_url('admin/artifacts/image-delete'),
                        'reorder' => base_url('admin/artifacts/image-reorder'),
                    ],
                ],
            ]) . (null === $needsForm ? '' : "\n" . view('scripts/service_resources')),
        ]);
    }

    /**
     * Create or save a product or service of the session business through LINK_INTERNAL_API:
     * v1/client/artifact/create { business_slug, artifact, translations, variants }, or, with public_id,
     * v1/client/artifact/edit { public_id, artifact, translations, variants }. The form has the names in the UI
     * languages only; on an edit, the names in other languages are kept, as the API replaces them all.
     */
    public function save(): ResponseInterface
    {
        $post     = (array) $this->request->getPost();
        $publicId = trim((string) ($post['public_id'] ?? ''));
        $artifact = array_intersect_key($post, array_flip(self::FIELDS));
        foreach (['artifact_label', 'category_public_id', 'tax_percentage'] as $field) {
            if (array_key_exists($field, $artifact) && '' === trim((string) $artifact[$field])) {
                $artifact[$field] = null;
            }
        }
        if ('' === $publicId) {
            $artifact['artifact_type'] = (string) ($post['artifact_type'] ?? '');
        }
        $variants = $this->variantsIn((string) ($post[self::VARIANTS_FIELD] ?? ''));
        if (null === $variants) {
            return $this->saveFailed(422, ['message' => lang('Artifacts.form.invalid-variants')], lang('Artifacts.form.failed'));
        }

        $names = [];
        if ('' !== $publicId) {
            $stored = $this->loadArtifact($publicId);
            if (null === $stored) {
                return $this->saveFailed(404, ['message' => lang('Artifacts.form.not-found')], lang('Artifacts.form.failed'));
            }
            $names = (array) ($stored['artifact']['translations'] ?? []);
        }
        foreach (array_keys($this->websiteLanguages()) as $language) {
            $names[$language] = [
                'artifact_name'        => (string) ($post[self::NAME_PREFIX . $language] ?? ''),
                'artifact_description' => (string) ($post[self::DESCRIPTION_PREFIX . $language] ?? ''),
            ];
        }

        $payload = ['artifact' => $artifact, 'translations' => (object) $names, 'variants' => $variants];
        [$code, $body] = '' === $publicId
            ? $this->callApi('v1/client/artifact/create', ['business_slug' => (string) currentBusinessSlug()] + $payload)
            : $this->callApi('v1/client/artifact/edit', ['public_id' => $publicId] + $payload);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->artifactFailed($code, $body);
        }
        $savedId = (string) ($body['data']['artifact']['public_id'] ?? $publicId);
        return $this->response->setJSON([
            'success'   => true,
            'message'   => lang('' === $publicId ? 'Artifacts.form.created' : 'Artifacts.form.saved'),
            'redirect'  => '' === $savedId ? base_url('admin/artifacts') : base_url('admin/artifacts/edit/' . rawurlencode($savedId)),
            'csrf_hash' => csrf_hash(),
        ]);
    }

    /**
     * Add an image to a product or service through LINK_INTERNAL_API v1/client/artifact/image-upload, multipart
     * { artifact_public_id, variant_public_id, image }; refused by the API once the plan's cap is reached
     * @return ResponseInterface { success, message, image: { public_id, variant_public_id, url, sort_order } }
     */
    public function imageUpload(): ResponseInterface
    {
        $file = $this->request->getFile('image');
        if (null === $file || ! $file->isValid()) {
            $tooLarge = null !== $file && in_array($file->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
            return $this->actionFailed($tooLarge ? 413 : 422, ['message' => lang($tooLarge ? 'Artifacts.images.too-large' : 'Artifacts.images.choose')]);
        }
        [$code, $body] = $this->callApi('v1/client/artifact/image-upload', [
            'artifact_public_id' => (string) $this->request->getPost('artifact_public_id'),
            'variant_public_id'  => (string) $this->request->getPost('variant_public_id'),
            'image'              => new CURLFile($file->getTempName(), $file->getMimeType(), $file->getClientName()),
        ], true);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->actionFailed($code, $body);
        }
        return $this->response->setJSON([
            'success'   => true,
            'message'   => $body['message'] ?? lang('Artifacts.images.uploaded'),
            'image'     => (array) ($body['data'] ?? []),
            'csrf_hash' => csrf_hash(),
        ]);
    }

    /**
     * Delete an image of a product or service through LINK_INTERNAL_API v1/client/artifact/image-delete { public_id }
     */
    public function imageDelete(): ResponseInterface
    {
        [$code, $body] = $this->callApi('v1/client/artifact/image-delete', ['public_id' => (string) $this->request->getJsonVar('public_id')]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->actionFailed($code, $body);
        }
        return $this->response->setJSON([
            'success'   => true,
            'message'   => $body['message'] ?? lang('Artifacts.images.deleted'),
            'csrf_hash' => csrf_hash(),
        ]);
    }

    /**
     * Put the images of a product or service in order through LINK_INTERNAL_API v1/client/artifact/image-reorder
     * { artifact_public_id, order: [public_id, ...] }, the first being the cover
     * @return ResponseInterface { success, message, images }
     */
    public function imageReorder(): ResponseInterface
    {
        $order = $this->request->getJsonVar('order');
        [$code, $body] = $this->callApi('v1/client/artifact/image-reorder', [
            'artifact_public_id' => (string) $this->request->getJsonVar('artifact_public_id'),
            'order'              => is_array($order) ? array_values(array_map('strval', $order)) : [],
        ]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->actionFailed($code, $body);
        }
        return $this->response->setJSON([
            'success'   => true,
            'message'   => $body['message'] ?? lang('Artifacts.images.reordered'),
            'images'    => (array) ($body['data']['images'] ?? []),
            'csrf_hash' => csrf_hash(),
        ]);
    }

    /**
     * Table Builder config of index(): each row's edit button opens the artifact, the Add button the form
     * @param array $categories public_id => name, for the category filter (not needed to read the filters)
     */
    private function listConfig(array $categories = []): array
    {
        $enums  = lang('Artifacts.enum');
        $badges = static fn (string $field, array $colours) => array_map(
            static fn (string $value) => ['label' => $enums[$field][$value], 'class' => 'bg-' . $colours[$value]],
            array_combine(array_keys($colours), array_keys($colours))
        );
        return [
            'table_id' => 'artifacts-table',
            'title'    => lang('Artifacts.list.title'),
            'subtitle' => lang('Artifacts.list.subtitle', [(string) currentBusinessName()]),
            'id_field' => 'public_id',
            'urls'     => [
                'data'   => base_url('admin/artifacts/data'),
                'create' => base_url('admin/artifacts/create'),
                'edit'   => base_url('admin/artifacts/edit/{public_id}'),
            ],
            'labels'   => ['add' => lang('Artifacts.list.add')],
            'messages' => ['loadFailed' => lang('Artifacts.list.load-failed')],
            'columns'  => [
                ['data' => 'cover', 'title' => lang('Artifacts.list.column.cover'), 'type' => 'html', 'orderable' => false, 'searchable' => false],
                ['data' => 'artifact_name', 'title' => lang('Artifacts.list.column.artifact_name')],
                ['data' => 'artifact_type', 'title' => lang('Artifacts.list.column.artifact_type'), 'type' => 'badge',
                    'badges' => $badges('artifact_type', ['P' => 'primary', 'S' => 'info'])],
                ['data' => 'artifact_status', 'title' => lang('Artifacts.list.column.artifact_status'), 'type' => 'badge',
                    'badges' => $badges('artifact_status', ['A' => 'success', 'I' => 'secondary', 'D' => 'dark'])],
                ['data' => 'artifact_label', 'title' => lang('Artifacts.list.column.artifact_label'), 'type' => 'badge',
                    'badges' => $badges('artifact_label', ['N' => 'success', 'P' => 'warning', 'R' => 'primary', 'L' => 'danger'])],
                ['data' => 'category', 'title' => lang('Artifacts.list.column.category')],
                ['data' => 'price', 'title' => lang('Artifacts.list.column.price'), 'type' => 'html', 'class' => 'text-end text-nowrap', 'searchable' => false],
                ['data' => 'variant_count', 'title' => lang('Artifacts.list.column.variant_count'), 'type' => 'number', 'decimals' => 0, 'class' => 'text-end', 'searchable' => false],
                ['data' => 'rating', 'title' => lang('Artifacts.list.column.rating'), 'type' => 'html', 'class' => 'text-nowrap', 'searchable' => false],
            ],
            'filters'  => [
                ['name' => 'artifact_type', 'type' => 'select', 'label' => lang('Artifacts.fields.artifact_type'), 'options' => $enums['artifact_type']],
                ['name' => 'artifact_status', 'type' => 'select', 'label' => lang('Artifacts.fields.artifact_status'), 'options' => $enums['artifact_status']],
                ['name' => 'category_public_id', 'type' => 'select', 'label' => lang('Artifacts.fields.category_public_id'), 'options' => $categories],
            ],
            'order'    => [['artifact_name', 'asc']],
        ];
    }

    /**
     * Why no product or service can be added: neither type is under its plan's cap
     * @param array $data the data of v1/client/artifacts (plan_code, caps)
     * @return string '' when one of the types can be added
     */
    private function addDisabled(array $data): string
    {
        $reasons = [];
        foreach (self::TYPES as $type) {
            $cap = (array) ($data['caps'][$type] ?? []);
            if ($cap['can_add'] ?? true) {
                return '';
            }
            $reasons[] = $this->capReason($type, (int) ($cap['max'] ?? 0), (string) ($data['plan_code'] ?? ''));
        }
        return implode(' ', $reasons);
    }

    /**
     * Why a type cannot be added: the plan does not include it, or allows no more
     */
    private function capReason(string $type, int $max, string $plan): string
    {
        $typeName = lang('Artifacts.list.type-names.' . $type);
        return 0 === $max
            ? lang('Artifacts.list.not-included', [planName($plan), $typeName])
            : lang('Artifacts.list.limit-reached', [$max, planName($plan), $typeName]);
    }

    /**
     * Fields of the artifact form: the artifact, its tax, the variants editor (a view of its own in view mode) and
     * the name and description in each language of the business's web pages (optional, sent as translations;
     * none while it has no languages)
     * @param string $type     artifact_type of the artifact being edited; '' for a new one
     * @param array  $typeCaps a new one: caps of v1/client/artifacts, by type; a type at its cap cannot be picked
     * @param string $plan     plan_code of the business
     * @param string $status   artifact_status of the artifact being edited
     * @param array  $editor   what the variants editor and view are built from
     * @param array  $rating   the artifact's rating_count, rating_average and rating_breakdown, for the view pane
     */
    private function fields(string $type, array $typeCaps, string $plan, string $status, array $editor, array $rating = []): array
    {
        $enums = lang('Artifacts.enum');
        $label = static fn (string $field) => lang('Artifacts.fields.' . $field);

        // a new one: a type the plan has no room for is listed, disabled, with the reason
        $typeOptions = [];
        $typeHelp    = [lang('Artifacts.help.artifact-type')];
        $firstOpen   = null;
        foreach (self::TYPES as $value) {
            $cap  = (array) ($typeCaps[$value] ?? []);
            $open = '' !== $type || ($cap['can_add'] ?? true);
            $firstOpen ??= $open ? $value : null;
            $typeOptions[] = ['value' => $value, 'label' => $enums['artifact_type'][$value], 'disabled' => ! $open];
            if (! $open) {
                $typeHelp[] = $this->capReason($value, (int) ($cap['max'] ?? 0), $plan);
            }
        }
        $statusFixed = self::STATUS_DISCONTINUED === $status;
        $categories  = $this->categoryOptions(false);

        $fields = [
            ['type' => 'heading', 'label' => lang('Artifacts.heading.artifact')],
            ['name' => 'artifact_type', 'type' => 'select', 'label' => $label('artifact_type'), 'options' => $typeOptions, 'default' => $firstOpen,
                'rules' => 'required|in_list[' . implode(',', self::TYPES) . ']', 'disabled_in' => ['edit'],
                'help' => '' === $type ? implode(' ', $typeHelp) : lang('Artifacts.help.artifact-type-fixed')],
            ['name' => 'artifact_name', 'type' => 'text', 'label' => $label('artifact_name'), 'rules' => 'required|max_length[255]'],
            ['name' => 'artifact_description', 'type' => 'textarea', 'label' => $label('artifact_description'), 'rules' => 'max_length[10000]',
                'optional' => true, 'rows' => 4],
            ['name' => 'category_public_id', 'type' => 'select', 'label' => $label('category_public_id'), 'options' => $categories,
                'optional' => true, 'help' => [] === $categories ? lang('Artifacts.help.no-categories') : null],
            ['name' => 'artifact_status', 'type' => 'select', 'label' => $label('artifact_status'), 'options' => $enums['artifact_status'],
                'default' => self::STATUS_INACTIVE, 'rules' => 'required|in_list[' . implode(',', self::STATUSES) . ']',
                'disabled_in' => $statusFixed ? ['edit'] : [], 'help' => lang($statusFixed ? 'Artifacts.help.discontinued' : 'Artifacts.help.artifact-status')],
            ['name' => 'artifact_label', 'type' => 'select', 'label' => $label('artifact_label'), 'options' => $enums['artifact_label'],
                'optional' => true, 'rules' => 'in_list[' . implode(',', self::LABELS) . ']'],
            ['type' => 'heading', 'label' => lang('Artifacts.heading.tax')],
            ['name' => 'tax_calculation', 'type' => 'select', 'label' => $label('tax_calculation'), 'options' => $enums['tax_calculation'],
                'default' => self::TAX_NONE, 'rules' => 'required|in_list[' . implode(',', self::TAXES) . ']'],
            ['name' => 'tax_percentage', 'type' => 'number', 'label' => $label('tax_percentage'), 'optional' => true,
                'attrs' => ['min' => 0.01, 'max' => 100, 'step' => 0.01], 'help' => lang('Artifacts.help.tax-percentage')],
            ['type' => 'heading', 'label' => lang('Artifacts.heading.variants')],
            ['type' => 'html', 'show_in' => ['create', 'edit'], 'html' => view('artifacts/variants_editor', ['field' => self::VARIANTS_FIELD] + $editor)],
            ['type' => 'html', 'show_in' => ['view'], 'html' => view('artifacts/variants_view', $editor + [
                'currency' => (string) (session('business')['business']['currency_code'] ?? ''),
            ])],
            ['type' => 'heading', 'label' => lang('Reviews.summary.heading'), 'show_in' => ['view']],
            ['type' => 'html', 'show_in' => ['view'], 'html' => '<div class="row mb-3"><div class="col-12">'
                . view('partials/rating_summary', ['rating' => $rating]) . '</div></div>'],
        ];
        $languages = $this->websiteLanguages();
        if ([] !== $languages) {
            $fields[] = ['type' => 'heading', 'label' => lang('Artifacts.heading.translations')];
        }
        foreach ($languages as $language => $name) {
            $fields[] = ['name' => self::NAME_PREFIX . $language, 'type' => 'text', 'label' => lang('Artifacts.translation-name', [$name]),
                'rules' => 'max_length[255]', 'optional' => true];
            $fields[] = ['name' => self::DESCRIPTION_PREFIX . $language, 'type' => 'textarea', 'label' => lang('Artifacts.translation-description', [$name]),
                'rules' => 'max_length[10000]', 'optional' => true, 'rows' => 3];
        }
        return $fields;
    }

    /**
     * The variants the variants editor sent, with only the fields the API takes
     * @param string $json the hidden field's JSON: a list of variants
     * @return list<array>|null null when it cannot be read
     */
    private function variantsIn(string $json): ?array
    {
        $in = json_decode($json, true);
        if (! is_array($in) || ! array_is_list($in)) {
            return null;
        }
        $variants = [];
        foreach ($in as $variant) {
            if (! is_array($variant)) {
                return null;
            }
            $variant = array_intersect_key($variant, array_flip(self::VARIANT_FIELDS));
            if ('' === (string) ($variant['public_id'] ?? '')) {
                unset($variant['public_id']);
            }
            $variant['attribute_values'] = array_values(array_filter(array_map('strval', (array) ($variant['attribute_values'] ?? [])), 'strlen'));
            // a store left empty is not stocked (or keeps its stock on an edit)
            $variant['inventory'] = array_values(array_filter((array) ($variant['inventory'] ?? []), static fn ($stock) => is_array($stock)
                && '' !== (string) ($stock['store_public_id'] ?? '') && '' !== trim((string) ($stock['quantity'] ?? ''))));
            $variants[] = $variant;
        }
        return $variants;
    }

    /**
     * Form response of a failed create or edit: the API names the errors of the names translations.<language>.<column>
     * and those of a variant variants.<index>.<field>; the first are renamed for the form, the others told by the
     * variant's number, as the variants editor is not one of the form's fields
     */
    private function artifactFailed(int $code, array $body): ResponseInterface
    {
        $columns = lang('Artifacts.variants.column');
        $errors  = [];
        foreach ((array) ($body['errors'] ?? []) as $key => $message) {
            $key = (string) $key;
            if (preg_match('/^translations\.([^.]+)(?:\.(artifact_name|artifact_description))?$/', $key, $m)) {
                $errors[('artifact_description' === ($m[2] ?? '') ? self::DESCRIPTION_PREFIX : self::NAME_PREFIX) . $m[1]] = $message;
            } elseif (preg_match('/^variants\.(\d+)\.([a-z_]+)/', $key, $m)) {
                $field        = isset($columns[$m[2]]) ? $columns[$m[2]] . ': ' : '';
                $errors[$key] = lang('Artifacts.variants.error', [(int) $m[1] + 1, $field . $message]);
            } else {
                $errors[$key] = $message;
            }
        }
        return $this->response->setStatusCode($code === 200 ? 422 : $code)->setJSON([
            'success'   => false,
            'message'   => $body['message'] ?? lang('Artifacts.form.failed'),
            'errors'    => $errors ?: null,
            'csrf_hash' => csrf_hash(),
        ]);
    }

    /**
     * JSON answer of a failed image call
     */
    private function actionFailed(int $code, array $body): ResponseInterface
    {
        return $this->response->setStatusCode($code === 200 ? 502 : $code)->setJSON([
            'success'   => false,
            'message'   => $body['message'] ?? lang('Artifacts.images.failed'),
            'csrf_hash' => csrf_hash(),
        ]);
    }

    /**
     * How each variant is named where one is picked or listed: "Variant 2 · Blue / M · TS-BLUE"; a service's also
     * has its length and booking type, which tell its variants apart too: "Variant 2 · 90 min · Group · JP-90G"
     * @param array  $variants   as v1/client/artifact sends them
     * @param array  $attributes loadAttributes()
     * @param string $type       artifact_type
     * @return array public_id => label
     */
    private function variantLabels(array $variants, array $attributes, string $type): array
    {
        $bookings = lang('Artifacts.enum.booking_type');
        $names = [];
        foreach ($attributes as $attribute) {
            foreach ($attribute['values'] as $value) {
                $names[$value['public_id']] = $value['name'];
            }
        }
        $labels = [];
        foreach (array_values($variants) as $i => $variant) {
            $values = array_map(static fn (array $value) => $names[$value['value_public_id'] ?? ''] ?? '', (array) ($variant['attribute_values'] ?? []));
            $labels[(string) $variant['public_id']] = implode(' · ', array_filter([
                lang('Artifacts.variants.variant-n', [$i + 1]),
                implode(' / ', array_filter($values)),
                self::TYPE_SERVICE === $type && null !== ($variant['duration_minutes'] ?? null) ? lang('Artifacts.variants.minutes', [(int) $variant['duration_minutes']]) : '',
                self::TYPE_SERVICE === $type ? (string) ($bookings[$variant['booking_type'] ?? ''] ?? '') : '',
                (string) ($variant['sku'] ?? ''),
            ]));
        }
        return $labels;
    }

    /**
     * The artifacts of the session business, from LINK_INTERNAL_API v1/client/artifacts
     * @param array $filters artifact_type, artifact_status, category_public_id
     * @return array{0: array, 1: string|null, 2: array} [artifacts, null, the whole data (plan_code, caps, per_artifact)]
     *         or [[], error message, []]
     */
    private function loadArtifacts(array $filters): array
    {
        [$code, $body] = $this->callApi('v1/client/artifacts', ['business_slug' => (string) currentBusinessSlug(), 'filters' => (object) $filters]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return [[], (string) ($body['message'] ?? lang('Artifacts.list.load-failed')), []];
        }
        $data = (array) ($body['data'] ?? []);
        return [(array) ($data['artifacts'] ?? []), null, $data];
    }

    /**
     * One artifact of the session business, from LINK_INTERNAL_API v1/client/artifact { public_id }
     * @return array|null { artifact, variants, images, plan_code, caps }; null when it is not one of the business's
     * @throws RuntimeException when it cannot be loaded
     */
    private function loadArtifact(string $publicId): ?array
    {
        [$code, $body] = $this->callApi('v1/client/artifact', ['public_id' => $publicId]);
        if (404 === $code) {
            return null;
        }
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            throw new RuntimeException((string) ($body['message'] ?? lang('Artifacts.list.load-failed')));
        }
        return (array) ($body['data'] ?? []);
    }

    /**
     * The categories of the session business, from LINK_INTERNAL_API v1/client/artifact-categories, named in the
     * UI language; an inactive one is marked
     * @param bool $quiet true: none when they cannot be loaded (a filter can go without); false: throws
     * @return array public_id => name
     */
    private function categoryOptions(bool $quiet = true): array
    {
        [$code, $body] = $this->callApi('v1/client/artifact-categories', ['business_slug' => (string) currentBusinessSlug()]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            if ($quiet) {
                return [];
            }
            throw new RuntimeException((string) ($body['message'] ?? lang('Artifacts.list.load-failed')));
        }
        $locale  = $this->request->getLocale();
        $options = [];
        foreach ((array) ($body['data']['categories'] ?? []) as $category) {
            $name = (string) (($category['translations'] ?? [])[$locale] ?? $category['category_name'] ?? '');
            $options[(string) $category['public_id']] = self::CATEGORY_INACTIVE === ($category['category_status'] ?? '')
                ? lang('Artifacts.form.category-inactive', [$name]) : $name;
        }
        return $options;
    }

    /**
     * The attributes of the session business with their values, from LINK_INTERNAL_API v1/client/artifact-attributes,
     * named in the UI language
     * @return list<array{public_id: string, name: string, values: list<array{public_id: string, name: string}>}>
     */
    private function loadAttributes(string $locale): array
    {
        [$code, $body] = $this->callApi('v1/client/artifact-attributes', ['business_slug' => (string) currentBusinessSlug()]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            throw new RuntimeException((string) ($body['message'] ?? lang('Artifacts.list.load-failed')));
        }
        $attributes = [];
        foreach ((array) ($body['data']['attributes'] ?? []) as $attribute) {
            $values = [];
            foreach ((array) ($attribute['values'] ?? []) as $value) {
                $values[] = ['public_id' => (string) $value['public_id'], 'name' => (string) (($value['translations'] ?? [])[$locale] ?? $value['attribute_value'] ?? '')];
            }
            $attributes[] = [
                'public_id' => (string) $attribute['public_id'],
                'name'      => (string) (($attribute['translations'] ?? [])[$locale] ?? $attribute['attribute_name'] ?? ''),
                'values'    => $values,
            ];
        }
        return $attributes;
    }

    /**
     * The stores of the session business, for the stock of a product's variants
     * @return list<array{public_id: string, name: string}>
     */
    private function storeOptions(string $locale): array
    {
        [$code, $body] = $this->callApi('v1/client/stores', ['business_slug' => (string) currentBusinessSlug()]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            throw new RuntimeException((string) ($body['message'] ?? lang('Stores.list.load-failed')));
        }
        $stores = [];
        foreach ((array) ($body['data']['stores'] ?? []) as $store) {
            $stores[] = ['public_id' => (string) $store['public_id'], 'name' => (string) (($store['translations'] ?? [])[$locale] ?? $store['store_name'] ?? '')];
        }
        return $stores;
    }
}
