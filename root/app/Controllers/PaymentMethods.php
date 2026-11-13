<?php

namespace App\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;

/**
 * The ways the customers of the session business can pay it, from LINK_INTERNAL_API v1/client/payment-method*:
 * at every store or at one, each with a name and instruction in the web pages' languages. Its owners and managers
 * only (see BusinessManagerFilter), as the API allows. Nothing is deleted: a method is made inactive instead.
 *
 * What a method can be is the API's (Config\BusinessPayment there): v1/client/payment-methods sends it as
 * "options", methods (method_code values) and handlers (handler_code => {methods, settings, ...}), and the form
 * offers only those. The labels are this app's (Language/<locale>/PaymentMethods.php), the code where one is missing.
 */
class PaymentMethods extends BaseController
{
    private const ACTIVE   = 'Y';
    private const INACTIVE = 'N';

    /** The handler that turns a PromptPay ID into a QR code; its setting is the form's promptpay_id field */
    private const HANDLER_PROMPTPAY_QR = 'promptpay_qr';

    /** The fields of the form that go to the API as "payment_method" as they are */
    private const METHOD_FIELDS = ['method_code', 'handler_code', 'method_name', 'instruction', 'is_active', 'sort_order'];

    /** The settings fields of the form, each sent as settings.<name> when its handler takes it */
    private const SETTING_FIELDS = ['promptpay_id'];

    /** Form field names of a method's name and instruction in a language: translation_name_<language_code> */
    private const NAME_PREFIX        = 'translation_name_';
    private const INSTRUCTION_PREFIX = 'translation_instruction_';

    /** Longest name and instruction the API takes */
    private const NAME_MAX        = 100;
    private const INSTRUCTION_MAX = 2000;

    /**
     * The payment methods (generic_list), by order and name; each row opens one. ?store= (a public_id) narrows it
     * to the methods offered at that store: the business-wide ones and its own.
     */
    public function index(): string
    {
        return view('generic_list', [
            'locale'      => $this->request->getLocale(),
            'slug'        => 'payment-methods',
            'breadcrumbs' => [base_url('admin/business') => lang('System.page.business')],
            'user'        => (array) session('user'),
            'listConfig'  => $this->listConfig(),
        ]);
    }

    /**
     * DataTables data of index(). A business has a handful of methods, so the API sends them all and the search,
     * order and paging are done here.
     */
    public function data(): ResponseInterface
    {
        $config = $this->listConfig();
        $params = lb_request($this->request, $config);
        $store  = (string) $this->request->getGet('store');
        [$code, $body] = $this->callApi('v1/client/payment-methods', ['business_slug' => (string) currentBusinessSlug()]
            + ('' === $store ? [] : ['store_public_id' => $store]));
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->response->setJSON(lb_response($params['draw'], 0, 0, [])
                + ['error' => (string) ($body['message'] ?? lang('PaymentMethods.list.load-failed')), 'csrf_hash' => csrf_hash()]);
        }

        $locale     = $this->request->getLocale();
        $storeNames = $this->storeNames($locale);
        $rows       = [];
        foreach ((array) ($body['data']['payment_methods'] ?? []) as $method) {
            $storeId = (string) ($method['store_public_id'] ?? '');
            $rows[]  = [
                'public_id'    => (string) ($method['public_id'] ?? ''),
                'method_name'  => self::methodName($method, $locale),
                'store_name'   => '' === $storeId ? lang('PaymentMethods.all-stores') : ($storeNames[$storeId] ?? $storeId),
                'method_code'  => self::label('method', (string) ($method['method_code'] ?? '')),
                'handler_code' => self::label('handler', (string) ($method['handler_code'] ?? '')),
                'is_active'    => (string) ($method['is_active'] ?? ''),
                'sort_order'   => (int) ($method['sort_order'] ?? 0),
            ];
        }
        $total    = count($rows);
        $statuses = lang('PaymentMethods.enum.is_active');
        $rows     = $this->searchAndOrder($rows, $params, static fn (array $row, string $column) => 'is_active' === $column
            ? (string) ($statuses[$row[$column]] ?? $row[$column])
            : (string) $row[$column]);
        $filtered = count($rows);
        $rows     = lb_rows(array_slice($rows, $params['start'], $params['length']), $config);

        return $this->response->setJSON(lb_response($params['draw'], $total, $filtered, $rows) + ['csrf_hash' => csrf_hash()]);
    }

    /**
     * The form to add a payment method, or, with $publicId, one opened in view mode. ?store= picks the store of a
     * new one at first.
     * @param string|null $publicId business_payment_method.public_id
     */
    public function form(?string $publicId = null): string
    {
        [$methods, $options] = $this->loadMethods();
        $values = [];
        if (null !== $publicId) {
            $method = self::find($methods, $publicId);
            if (null === $method) {
                throw PageNotFoundException::forPageNotFound(lang('PaymentMethods.form.not-found'));
            }
            $values = array_intersect_key($method, array_flip(self::METHOD_FIELDS)) + [
                'public_id'       => (string) $method['public_id'],
                'store_public_id' => (string) ($method['store_public_id'] ?? ''),
            ];
            foreach (self::SETTING_FIELDS as $setting) {
                $values[$setting] = (string) (((array) ($method['settings'] ?? []))[$setting] ?? '');
            }
            foreach ((array) ($method['translations'] ?? []) as $language => $texts) {
                $values[self::NAME_PREFIX . $language]        = (string) (((array) $texts)['method_name'] ?? '');
                $values[self::INSTRUCTION_PREFIX . $language] = (string) (((array) $texts)['instruction'] ?? '');
            }
        } elseif ('' !== (string) $this->request->getGet('store')) {
            $values['store_public_id'] = (string) $this->request->getGet('store');
        }

        $locale   = $this->request->getLocale();
        $formId   = 'payment-method-form';
        $handlers = (array) ($options['handlers'] ?? []);

        return view('generic_form', [
            'locale'       => $locale,
            'slug'         => 'payment-methods',
            'page_title'   => $values['method_name'] ?? lang('System.page.payment-method-create'),
            'breadcrumbs'  => [
                base_url('admin/business')                 => lang('System.page.business'),
                base_url('admin/business/payment-methods') => lang('System.page.payment-methods'),
            ],
            'user'         => (array) session('user'),
            'formConfig'   => [
                'form_id'  => $formId,
                'title'    => lang(null === $publicId ? 'PaymentMethods.form.create-title' : 'PaymentMethods.form.edit-title'),
                'subtitle' => lang('PaymentMethods.form.subtitle', [(string) currentBusinessName()]),
                'mode'     => null === $publicId ? 'create' : 'view',
                'id_field' => 'public_id',
                'id'       => (string) $publicId,
                'locale'   => $locale,
                'urls'     => [
                    'submit' => base_url('admin/business/payment-methods/save'),
                    'edit'   => null === $publicId ? '' : base_url('admin/business/payment-methods/edit/' . rawurlencode($publicId)),
                    'list'   => base_url('admin/business/payment-methods'),
                ],
                'messages' => ['saved' => lang(null === $publicId ? 'PaymentMethods.form.created' : 'PaymentMethods.form.saved'),
                    'saveFailed' => lang('PaymentMethods.form.failed')],
                'values'   => $values,
                'fields'   => $this->fields($this->storeNames($locale), (array) ($options['methods'] ?? []), $handlers),
            ],
            'customScript' => view('scripts/payment_method_form', [
                'formId'   => $formId,
                'handlers' => array_map(static fn ($handler) => [
                    'methods'     => array_values((array) ($handler['methods'] ?? [])),
                    'settings'    => array_values(array_intersect(self::SETTING_FIELDS, (array) ($handler['settings'] ?? []))),
                    'instruction' => (bool) ($handler['instruction_required'] ?? false),
                ], $handlers),
                'settings' => self::SETTING_FIELDS,
            ]),
        ]);
    }

    /**
     * Create or save a payment method through LINK_INTERNAL_API: v1/client/payment-method/create { business_slug,
     * payment_method, translations }, or, with public_id, v1/client/payment-method/edit { public_id, payment_method,
     * translations }. store_public_id '' is every store (null). Only the settings the handler takes are sent.
     * The form has the texts in the web pages' languages only; on an edit, the other languages' are kept, as the API
     * replaces them all.
     */
    public function save(): ResponseInterface
    {
        $post     = (array) $this->request->getPost();
        $publicId = trim((string) ($post['public_id'] ?? ''));
        $method   = array_map(static fn ($value) => is_string($value) ? trim($value) : '', array_intersect_key($post, array_flip(self::METHOD_FIELDS)));
        $store    = trim((string) ($post['store_public_id'] ?? ''));
        $method['store_public_id'] = '' === $store ? null : $store;
        $method['settings']        = (object) [];
        if (self::HANDLER_PROMPTPAY_QR === ($method['handler_code'] ?? '')) {
            $method['settings'] = ['promptpay_id' => (string) preg_replace('/[\s-]+/', '', (string) ($post['promptpay_id'] ?? ''))];
        }

        $names = [];
        if ('' !== $publicId) {
            try {
                $stored = self::find($this->loadMethods()[0], $publicId);
            } catch (RuntimeException $e) {
                return $this->saveFailed(500, ['message' => $e->getMessage()], lang('PaymentMethods.form.failed'));
            }
            if (null === $stored) {
                return $this->saveFailed(404, ['message' => lang('PaymentMethods.form.not-found')], lang('PaymentMethods.form.failed'));
            }
            $names = (array) ($stored['translations'] ?? []);
        }
        foreach (array_keys($this->websiteLanguages()) as $language) {
            $names[$language] = [
                'method_name' => (string) ($post[self::NAME_PREFIX . $language] ?? ''),
                'instruction' => (string) ($post[self::INSTRUCTION_PREFIX . $language] ?? ''),
            ];
        }

        $payload = ['payment_method' => $method, 'translations' => (object) $names];
        [$code, $body] = '' === $publicId
            ? $this->callApi('v1/client/payment-method/create', ['business_slug' => (string) currentBusinessSlug()] + $payload)
            : $this->callApi('v1/client/payment-method/edit', ['public_id' => $publicId] + $payload);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->methodFailed($code, $body);
        }
        $savedId = (string) ($body['data']['public_id'] ?? $publicId);
        return $this->response->setJSON([
            'success'   => true,
            'message'   => $body['message'] ?? lang('' === $publicId ? 'PaymentMethods.form.created' : 'PaymentMethods.form.saved'),
            'redirect'  => base_url('admin/business/payment-methods/edit/' . rawurlencode($savedId)),
            'csrf_hash' => csrf_hash(),
        ]);
    }

    // ---------------------------------------------------------------- configs

    /**
     * Fields of the form: the method, then its name and instruction in each language of the web pages
     * @param array<string, string> $stores   public_id => name
     * @param list<string>          $methods  method_code values the API takes
     * @param array                 $handlers handler_code => what the API says of it
     */
    private function fields(array $stores, array $methods, array $handlers): array
    {
        $storeOptions   = ['' => lang('PaymentMethods.all-stores')] + $stores;
        $methodOptions  = [];
        foreach ($methods as $code) {
            $methodOptions[(string) $code] = self::label('method', (string) $code);
        }
        $handlerOptions = [];
        foreach (array_keys($handlers) as $code) {
            $handlerOptions[(string) $code] = self::label('handler', (string) $code);
        }
        $statuses = lang('PaymentMethods.enum.is_active');

        $fields = [
            ['type' => 'heading', 'label' => lang('PaymentMethods.heading.method')],
            // "All stores" is the select's empty option (its placeholder), sent as ''
            ['name' => 'store_public_id', 'type' => 'select', 'label' => lang('PaymentMethods.fields.store'), 'options' => $stores,
                'placeholder' => lang('PaymentMethods.all-stores'), 'view' => static fn ($v) => $storeOptions[(string) $v] ?? $v,
                'help' => lang('PaymentMethods.help.store')],
            ['name' => 'method_code', 'type' => 'select', 'label' => lang('PaymentMethods.fields.method_code'), 'options' => $methodOptions,
                'rules' => 'required', 'view' => static fn ($v) => $methodOptions[(string) $v] ?? $v, 'help' => lang('PaymentMethods.help.method-code')],
            ['name' => 'handler_code', 'type' => 'select', 'label' => lang('PaymentMethods.fields.handler_code'), 'options' => $handlerOptions,
                'rules' => 'required', 'view' => static fn ($v) => $handlerOptions[(string) $v] ?? $v, 'help' => lang('PaymentMethods.help.handler-code')],
            ['name' => 'promptpay_id', 'type' => 'text', 'label' => lang('PaymentMethods.fields.promptpay_id'), 'rules' => 'required|max_length[20]',
                'attrs' => ['inputmode' => 'numeric', 'autocomplete' => 'off'], 'help' => lang('PaymentMethods.help.promptpay-id')],
            ['name' => 'method_name', 'type' => 'text', 'label' => lang('PaymentMethods.fields.method_name'), 'rules' => 'required|max_length[' . self::NAME_MAX . ']',
                'help' => lang('PaymentMethods.help.method-name')],
            ['name' => 'instruction', 'type' => 'textarea', 'label' => lang('PaymentMethods.fields.instruction'), 'rules' => 'max_length[' . self::INSTRUCTION_MAX . ']',
                'rows' => 4, 'help' => lang('PaymentMethods.help.instruction')],
            ['name' => 'is_active', 'type' => 'select', 'label' => lang('PaymentMethods.fields.is_active'), 'options' => $statuses,
                'default' => self::ACTIVE, 'rules' => 'required|in_list[' . self::ACTIVE . ',' . self::INACTIVE . ']', 'help' => lang('PaymentMethods.help.is-active')],
            ['name' => 'sort_order', 'type' => 'number', 'label' => lang('PaymentMethods.fields.sort_order'), 'default' => 0,
                'rules' => 'required|is_natural|less_than_equal_to[255]', 'attrs' => ['min' => 0, 'max' => 255, 'step' => 1],
                'help' => lang('PaymentMethods.help.sort-order')],
        ];
        foreach ($this->websiteLanguages() as $language => $label) {
            $fields[] = ['type' => 'heading', 'label' => lang('PaymentMethods.heading.translation', [$label])];
            $fields[] = ['name' => self::NAME_PREFIX . $language, 'type' => 'text', 'label' => lang('PaymentMethods.translation.method_name', [$label]),
                'rules' => 'max_length[' . self::NAME_MAX . ']', 'optional' => true, 'help' => lang('PaymentMethods.help.translation-name')];
            $fields[] = ['name' => self::INSTRUCTION_PREFIX . $language, 'type' => 'textarea', 'label' => lang('PaymentMethods.translation.instruction', [$label]),
                'rules' => 'max_length[' . self::INSTRUCTION_MAX . ']', 'optional' => true, 'rows' => 4];
        }
        return $fields;
    }

    /**
     * Table Builder config of index(); its data and Add carry the page's ?store=
     */
    private function listConfig(): array
    {
        $store = (string) $this->request->getGet('store');
        $query = '' === $store ? '' : '?' . http_build_query(['store' => $store]);
        return [
            'table_id' => 'payment-methods-table',
            'title'    => lang('PaymentMethods.list.title'),
            'subtitle' => lang('PaymentMethods.list.subtitle', [(string) currentBusinessName()]),
            'id_field' => 'public_id',
            'urls'     => [
                'data'   => base_url('admin/business/payment-methods/data') . $query,
                'create' => base_url('admin/business/payment-methods/create') . $query,
                'edit'   => base_url('admin/business/payment-methods/edit/{public_id}'),
            ],
            'labels'   => ['add' => lang('PaymentMethods.list.add')],
            'messages' => ['loadFailed' => lang('PaymentMethods.list.load-failed')],
            'columns'  => [
                ['data' => 'method_name', 'title' => lang('PaymentMethods.fields.method_name')],
                ['data' => 'store_name', 'title' => lang('PaymentMethods.fields.store')],
                ['data' => 'method_code', 'title' => lang('PaymentMethods.fields.method_code')],
                ['data' => 'handler_code', 'title' => lang('PaymentMethods.fields.handler_code')],
                ['data' => 'is_active', 'title' => lang('PaymentMethods.fields.is_active'), 'type' => 'badge', 'badges' => [
                    self::ACTIVE   => ['label' => lang('PaymentMethods.enum.is_active')[self::ACTIVE], 'class' => 'bg-success'],
                    self::INACTIVE => ['label' => lang('PaymentMethods.enum.is_active')[self::INACTIVE], 'class' => 'bg-secondary'],
                ]],
                ['data' => 'sort_order', 'title' => lang('PaymentMethods.fields.sort_order'), 'type' => 'number', 'decimals' => 0, 'class' => 'text-end', 'searchable' => false],
            ],
            'order'    => [['sort_order', 'asc'], ['method_name', 'asc']],
        ];
    }

    // ---------------------------------------------------------------- loading

    /**
     * Every payment method of the session business and what a method can be, from v1/client/payment-methods
     * @return array{0: list<array>, 1: array} [methods, options]
     * @throws RuntimeException when they cannot be loaded
     */
    private function loadMethods(): array
    {
        [$code, $body] = $this->callApi('v1/client/payment-methods', ['business_slug' => (string) currentBusinessSlug()]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            throw new RuntimeException((string) ($body['message'] ?? lang('PaymentMethods.list.load-failed')));
        }
        return [(array) ($body['data']['payment_methods'] ?? []), (array) ($body['data']['options'] ?? [])];
    }

    /**
     * The method with this public_id (the API has no single-method call)
     */
    private static function find(array $methods, string $publicId): ?array
    {
        foreach ($methods as $method) {
            if ((string) ($method['public_id'] ?? '') === $publicId) {
                return (array) $method;
            }
        }
        return null;
    }

    /**
     * @return array<string, string> store public_id => its name in the UI language
     * @throws RuntimeException when the stores cannot be loaded
     */
    private function storeNames(string $locale): array
    {
        [$code, $body] = $this->callApi('v1/client/stores', ['business_slug' => (string) currentBusinessSlug()]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            throw new RuntimeException((string) ($body['message'] ?? lang('Stores.list.load-failed')));
        }
        $out = [];
        foreach ((array) ($body['data']['stores'] ?? []) as $store) {
            $out[(string) $store['public_id']] = self::storeName((array) $store, $locale);
        }
        return $out;
    }

    /**
     * A method's name in the UI language, when it has one
     */
    private static function methodName(array $method, string $locale): string
    {
        $name = ((((array) ($method['translations'] ?? []))[$locale] ?? [])['method_name'] ?? null);
        return (string) (null === $name || '' === $name ? ($method['method_name'] ?? '') : $name);
    }

    /**
     * The label of a method_code ($group 'method') or handler_code ('handler'); the code itself for one this app
     * has no label for yet (one the API added since)
     */
    private static function label(string $group, string $code): string
    {
        $labels = (array) lang('PaymentMethods.enum.' . $group);
        return (string) ($labels[$code] ?? $code);
    }

    /**
     * Form response of a failed create or edit: the API names the errors settings.<name> and
     * translations.<language>.<column>, which are renamed for the form
     */
    private function methodFailed(int $code, array $body): ResponseInterface
    {
        $errors = [];
        foreach ((array) ($body['errors'] ?? []) as $key => $message) {
            $key = (string) $key;
            if (preg_match('/^translations\.([^.]+)(?:\.(method_name|instruction))?$/', $key, $m)) {
                $key = ('instruction' === ($m[2] ?? '') ? self::INSTRUCTION_PREFIX : self::NAME_PREFIX) . $m[1];
            } elseif (preg_match('/^settings\.(.+)$/', $key, $m)) {
                $key = $m[1];
            }
            $errors[$key] = $message;
        }
        return $this->response->setStatusCode($code === 200 ? 422 : $code)->setJSON([
            'success'   => false,
            'message'   => $body['message'] ?? lang('PaymentMethods.form.failed'),
            'errors'    => $errors ?: null,
            'csrf_hash' => csrf_hash(),
        ]);
    }
}
