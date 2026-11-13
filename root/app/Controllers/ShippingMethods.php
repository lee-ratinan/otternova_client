<?php

namespace App\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;

/**
 * The ways the customers of the session business can get its products, and what each costs, from LINK_INTERNAL_API
 * v1/client/shipping-method*: at every store or at one, each with a name and description in the web pages' languages
 * and its fee brackets. Its owners and managers only (see BusinessManagerFilter), as the API allows. Nothing is
 * deleted: a method is made inactive instead.
 *
 * A method's fee is worked out from its brackets (the API's business_shipping_rate): by the order's subtotal
 * (fee_basis P, in the business's currency) or its weight (W, in grams), with free shipping from free_over_amount
 * whatever the basis. The brackets are edited in a table of their own (shipping/rates_editor, scripts/shipping_method_form),
 * sent as JSON in RATES_FIELD; no brackets at all means free.
 */
class ShippingMethods extends BaseController
{
    private const ACTIVE   = 'Y';
    private const INACTIVE = 'N';

    private const BASIS_SUBTOTAL = 'P';
    private const BASIS_WEIGHT   = 'W';

    /** The fields of the form that go to the API as "shipping_method" as they are */
    private const METHOD_FIELDS = ['method_type', 'method_name', 'description', 'fee_basis', 'free_over_amount', 'eta_days_min', 'eta_days_max', 'is_active', 'sort_order'];

    /** The columns of a bracket */
    private const RATE_FIELDS = ['range_from', 'range_to', 'fee_amount', 'step_size', 'step_fee'];

    /** The hidden field of the brackets editor: the brackets as JSON */
    private const RATES_FIELD = 'rates_json';

    /** Form field names of a method's name and description in a language: translation_name_<language_code> */
    private const NAME_PREFIX        = 'translation_name_';
    private const DESCRIPTION_PREFIX = 'translation_description_';

    /** Longest name and description the API takes, and the most brackets */
    private const NAME_MAX        = 100;
    private const DESCRIPTION_MAX = 2000;
    private const RATES_MAX       = 50;

    /**
     * The shipping methods (generic_list), by order and name; each row opens one. ?store= (a public_id) narrows it
     * to the methods offered at that store: the business-wide ones and its own.
     */
    public function index(): string
    {
        return view('generic_list', [
            'locale'      => $this->request->getLocale(),
            'slug'        => 'shipping-methods',
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
        [$code, $body] = $this->callApi('v1/client/shipping-methods', ['business_slug' => (string) currentBusinessSlug()]
            + ('' === $store ? [] : ['store_public_id' => $store]));
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->response->setJSON(lb_response($params['draw'], 0, 0, [])
                + ['error' => (string) ($body['message'] ?? lang('ShippingMethods.list.load-failed')), 'csrf_hash' => csrf_hash()]);
        }

        $locale     = $this->request->getLocale();
        $storeNames = $this->storeNames($locale);
        $currency   = self::currency();
        $rows       = [];
        foreach ((array) ($body['data']['shipping_methods'] ?? []) as $method) {
            $storeId = (string) ($method['store_public_id'] ?? '');
            $rows[]  = [
                'public_id'   => (string) ($method['public_id'] ?? ''),
                'method_name' => self::methodName($method, $locale),
                'store_name'  => '' === $storeId ? lang('ShippingMethods.all-stores') : ($storeNames[$storeId] ?? $storeId),
                'method_type' => self::label('method_type', (string) ($method['method_type'] ?? '')),
                'fee'         => self::feeSummary((array) $method, $currency),
                'is_active'   => (string) ($method['is_active'] ?? ''),
                'sort_order'  => (int) ($method['sort_order'] ?? 0),
            ];
        }
        $total    = count($rows);
        $statuses = lang('ShippingMethods.enum.is_active');
        $rows     = $this->searchAndOrder($rows, $params, static fn (array $row, string $column) => 'is_active' === $column
            ? (string) ($statuses[$row[$column]] ?? $row[$column])
            : (string) $row[$column]);
        $filtered = count($rows);
        $rows     = lb_rows(array_slice($rows, $params['start'], $params['length']), $config);

        return $this->response->setJSON(lb_response($params['draw'], $total, $filtered, $rows) + ['csrf_hash' => csrf_hash()]);
    }

    /**
     * The form to add a shipping method, or, with $publicId, one opened in view mode. ?store= picks the store of a
     * new one at first.
     * @param string|null $publicId business_shipping_method.public_id
     */
    public function form(?string $publicId = null): string
    {
        $methods = $this->loadMethods();
        $values  = [];
        $rates   = [];
        if (null !== $publicId) {
            $method = self::find($methods, $publicId);
            if (null === $method) {
                throw PageNotFoundException::forPageNotFound(lang('ShippingMethods.form.not-found'));
            }
            foreach (self::METHOD_FIELDS as $field) {
                $values[$field] = null === ($method[$field] ?? null) ? '' : (string) $method[$field];
            }
            $values['public_id']       = (string) $method['public_id'];
            $values['store_public_id'] = (string) ($method['store_public_id'] ?? '');
            foreach ((array) ($method['translations'] ?? []) as $language => $texts) {
                $values[self::NAME_PREFIX . $language]        = (string) (((array) $texts)['method_name'] ?? '');
                $values[self::DESCRIPTION_PREFIX . $language] = (string) (((array) $texts)['description'] ?? '');
            }
            $rates = array_values((array) ($method['rates'] ?? []));
        } elseif ('' !== (string) $this->request->getGet('store')) {
            $values['store_public_id'] = (string) $this->request->getGet('store');
        }

        $locale   = $this->request->getLocale();
        $formId   = 'shipping-method-form';
        $currency = self::currency();
        $basis    = (string) ($values['fee_basis'] ?? self::BASIS_SUBTOTAL);

        return view('generic_form', [
            'locale'       => $locale,
            'slug'         => 'shipping-methods',
            'page_title'   => $values['method_name'] ?? lang('System.page.shipping-method-create'),
            'breadcrumbs'  => [
                base_url('admin/business')                  => lang('System.page.business'),
                base_url('admin/business/shipping-methods') => lang('System.page.shipping-methods'),
            ],
            'user'         => (array) session('user'),
            'formConfig'   => [
                'form_id'  => $formId,
                'title'    => lang(null === $publicId ? 'ShippingMethods.form.create-title' : 'ShippingMethods.form.edit-title'),
                'subtitle' => lang('ShippingMethods.form.subtitle', [(string) currentBusinessName()]),
                'mode'     => null === $publicId ? 'create' : 'view',
                'id_field' => 'public_id',
                'id'       => (string) $publicId,
                'locale'   => $locale,
                'urls'     => [
                    'submit' => base_url('admin/business/shipping-methods/save'),
                    'edit'   => null === $publicId ? '' : base_url('admin/business/shipping-methods/edit/' . rawurlencode($publicId)),
                    'list'   => base_url('admin/business/shipping-methods'),
                ],
                'messages' => ['saved' => lang(null === $publicId ? 'ShippingMethods.form.created' : 'ShippingMethods.form.saved'),
                    'saveFailed' => lang('ShippingMethods.form.failed')],
                'values'   => $values,
                'fields'   => $this->fields($this->storeNames($locale), $currency, array_map(static fn (array $rate) => self::rateText($rate, $basis, $currency), $rates)),
            ],
            'customScript' => view('scripts/shipping_method_form', [
                'formId'     => $formId,
                'ratesField' => self::RATES_FIELD,
                'rates'      => $rates,
                'currency'   => $currency,
                'max'        => self::RATES_MAX,
            ]),
        ]);
    }

    /**
     * Create or save a shipping method through LINK_INTERNAL_API: v1/client/shipping-method/create { business_slug,
     * shipping_method, rates, translations }, or, with public_id, v1/client/shipping-method/edit { public_id,
     * shipping_method, rates, translations }. store_public_id '' is every store (null), and the empty optional
     * fields (free_over_amount, eta_days_*, description) are null. The brackets are always sent, so a change of
     * fee_basis carries its new brackets; the API writes nothing when they are the same.
     * The form has the texts in the web pages' languages only; on an edit, the other languages' are kept, as the API
     * replaces them all.
     */
    public function save(): ResponseInterface
    {
        $post     = (array) $this->request->getPost();
        $publicId = trim((string) ($post['public_id'] ?? ''));
        $method   = [];
        foreach (self::METHOD_FIELDS as $field) {
            $value          = is_string($post[$field] ?? null) ? trim($post[$field]) : '';
            $method[$field] = '' === $value && in_array($field, ['description', 'free_over_amount', 'eta_days_min', 'eta_days_max'], true) ? null : $value;
        }
        $store = trim((string) ($post['store_public_id'] ?? ''));
        $method['store_public_id'] = '' === $store ? null : $store;

        $ratesIn = json_decode((string) ($post[self::RATES_FIELD] ?? ''), true);
        if (! is_array($ratesIn) || ! array_is_list($ratesIn)) {
            return $this->saveFailed(422, ['message' => lang('ShippingMethods.form.invalid-rates')], lang('ShippingMethods.form.failed'));
        }
        $rates = [];
        foreach ($ratesIn as $rateIn) {
            if (! is_array($rateIn)) {
                return $this->saveFailed(422, ['message' => lang('ShippingMethods.form.invalid-rates')], lang('ShippingMethods.form.failed'));
            }
            $rate = [];
            foreach (self::RATE_FIELDS as $field) {
                $value        = $rateIn[$field] ?? null;
                $value        = is_string($value) || is_int($value) || is_float($value) ? trim((string) $value) : '';
                $rate[$field] = '' === $value ? null : $value;
            }
            $rates[] = $rate;
        }

        $names = [];
        if ('' !== $publicId) {
            try {
                $stored = self::find($this->loadMethods(), $publicId);
            } catch (RuntimeException $e) {
                return $this->saveFailed(500, ['message' => $e->getMessage()], lang('ShippingMethods.form.failed'));
            }
            if (null === $stored) {
                return $this->saveFailed(404, ['message' => lang('ShippingMethods.form.not-found')], lang('ShippingMethods.form.failed'));
            }
            $names = (array) ($stored['translations'] ?? []);
        }
        foreach (array_keys($this->websiteLanguages()) as $language) {
            $names[$language] = [
                'method_name' => (string) ($post[self::NAME_PREFIX . $language] ?? ''),
                'description' => (string) ($post[self::DESCRIPTION_PREFIX . $language] ?? ''),
            ];
        }

        $payload = ['shipping_method' => $method, 'rates' => $rates, 'translations' => (object) $names];
        [$code, $body] = '' === $publicId
            ? $this->callApi('v1/client/shipping-method/create', ['business_slug' => (string) currentBusinessSlug()] + $payload)
            : $this->callApi('v1/client/shipping-method/edit', ['public_id' => $publicId] + $payload);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->methodFailed($code, $body);
        }
        $savedId = (string) ($body['data']['public_id'] ?? $publicId);
        return $this->response->setJSON([
            'success'   => true,
            'message'   => $body['message'] ?? lang('' === $publicId ? 'ShippingMethods.form.created' : 'ShippingMethods.form.saved'),
            'redirect'  => base_url('admin/business/shipping-methods/edit/' . rawurlencode($savedId)),
            'csrf_hash' => csrf_hash(),
        ]);
    }

    // ---------------------------------------------------------------- configs

    /**
     * Fields of the form: the method, its fee (the brackets editor, a table of its own in view mode), then its name
     * and description in each language of the web pages
     * @param array<string, string> $stores    public_id => name
     * @param string                $currency  the business's currency code
     * @param list<string>          $rateTexts the saved brackets as they read, for the view pane
     */
    private function fields(array $stores, string $currency, array $rateTexts): array
    {
        $storeOptions = ['' => lang('ShippingMethods.all-stores')] + $stores;
        $types        = (array) lang('ShippingMethods.enum.method_type');
        $bases        = (array) lang('ShippingMethods.enum.fee_basis');
        $statuses     = (array) lang('ShippingMethods.enum.is_active');
        $days         = static fn ($v) => '' === (string) $v ? '—' : (string) $v;

        $fields = [
            ['type' => 'heading', 'label' => lang('ShippingMethods.heading.method')],
            ['name' => 'store_public_id', 'type' => 'select', 'label' => lang('ShippingMethods.fields.store'), 'options' => $stores,
                'placeholder' => lang('ShippingMethods.all-stores'), 'view' => static fn ($v) => $storeOptions[(string) $v] ?? $v,
                'help' => lang('ShippingMethods.help.store')],
            ['name' => 'method_type', 'type' => 'select', 'label' => lang('ShippingMethods.fields.method_type'), 'options' => $types,
                'rules' => 'required|in_list[H,L,S]', 'view' => static fn ($v) => $types[(string) $v] ?? $v, 'help' => lang('ShippingMethods.help.method-type')],
            ['name' => 'method_name', 'type' => 'text', 'label' => lang('ShippingMethods.fields.method_name'), 'rules' => 'required|max_length[' . self::NAME_MAX . ']',
                'help' => lang('ShippingMethods.help.method-name')],
            ['name' => 'description', 'type' => 'textarea', 'label' => lang('ShippingMethods.fields.description'), 'rules' => 'max_length[' . self::DESCRIPTION_MAX . ']',
                'optional' => true, 'rows' => 3, 'help' => lang('ShippingMethods.help.description')],
            ['name' => 'eta_days_min', 'type' => 'number', 'label' => lang('ShippingMethods.fields.eta_days_min'), 'rules' => 'permit_empty|is_natural|less_than_equal_to[255]',
                'optional' => true, 'attrs' => ['min' => 0, 'max' => 255, 'step' => 1], 'view' => $days, 'help' => lang('ShippingMethods.help.eta')],
            ['name' => 'eta_days_max', 'type' => 'number', 'label' => lang('ShippingMethods.fields.eta_days_max'), 'rules' => 'permit_empty|is_natural|less_than_equal_to[255]',
                'optional' => true, 'attrs' => ['min' => 0, 'max' => 255, 'step' => 1], 'view' => $days],
            ['name' => 'is_active', 'type' => 'select', 'label' => lang('ShippingMethods.fields.is_active'), 'options' => $statuses,
                'default' => self::ACTIVE, 'rules' => 'required|in_list[' . self::ACTIVE . ',' . self::INACTIVE . ']', 'view' => static fn ($v) => $statuses[(string) $v] ?? $v,
                'help' => lang('ShippingMethods.help.is-active')],
            ['name' => 'sort_order', 'type' => 'number', 'label' => lang('ShippingMethods.fields.sort_order'), 'default' => 0,
                'rules' => 'required|is_natural|less_than_equal_to[255]', 'attrs' => ['min' => 0, 'max' => 255, 'step' => 1],
                'help' => lang('ShippingMethods.help.sort-order')],

            ['type' => 'heading', 'label' => lang('ShippingMethods.heading.fee')],
            ['name' => 'fee_basis', 'type' => 'select', 'label' => lang('ShippingMethods.fields.fee_basis'), 'options' => $bases,
                'default' => self::BASIS_SUBTOTAL, 'rules' => 'required|in_list[' . self::BASIS_SUBTOTAL . ',' . self::BASIS_WEIGHT . ']',
                'view' => static fn ($v) => $bases[(string) $v] ?? $v, 'help' => lang('ShippingMethods.help.fee-basis')],
            ['name' => 'free_over_amount', 'type' => 'number', 'label' => lang('ShippingMethods.fields.free_over_amount', [$currency]),
                'rules' => 'permit_empty|decimal|greater_than_equal_to[0]', 'optional' => true, 'attrs' => ['min' => 0, 'step' => '0.01'],
                'view' => static fn ($v) => '' === (string) $v ? lang('ShippingMethods.never-free') : self::money($v, $currency),
                'help' => lang('ShippingMethods.help.free-over-amount')],
            ['type' => 'html', 'show_in' => ['create', 'edit'], 'html' => view('shipping/rates_editor', ['field' => self::RATES_FIELD])],
            ['type' => 'html', 'show_in' => ['view'], 'html' => view('shipping/rates_view', ['rates' => $rateTexts])],
        ];
        foreach ($this->websiteLanguages() as $language => $label) {
            $fields[] = ['type' => 'heading', 'label' => lang('ShippingMethods.heading.translation', [$label])];
            $fields[] = ['name' => self::NAME_PREFIX . $language, 'type' => 'text', 'label' => lang('ShippingMethods.translation.method_name', [$label]),
                'rules' => 'max_length[' . self::NAME_MAX . ']', 'optional' => true, 'help' => lang('ShippingMethods.help.translation-name')];
            $fields[] = ['name' => self::DESCRIPTION_PREFIX . $language, 'type' => 'textarea', 'label' => lang('ShippingMethods.translation.description', [$label]),
                'rules' => 'max_length[' . self::DESCRIPTION_MAX . ']', 'optional' => true, 'rows' => 3];
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
            'table_id' => 'shipping-methods-table',
            'title'    => lang('ShippingMethods.list.title'),
            'subtitle' => lang('ShippingMethods.list.subtitle', [(string) currentBusinessName()]),
            'id_field' => 'public_id',
            'urls'     => [
                'data'   => base_url('admin/business/shipping-methods/data') . $query,
                'create' => base_url('admin/business/shipping-methods/create') . $query,
                'edit'   => base_url('admin/business/shipping-methods/edit/{public_id}'),
            ],
            'labels'   => ['add' => lang('ShippingMethods.list.add')],
            'messages' => ['loadFailed' => lang('ShippingMethods.list.load-failed')],
            'columns'  => [
                ['data' => 'method_name', 'title' => lang('ShippingMethods.fields.method_name')],
                ['data' => 'store_name', 'title' => lang('ShippingMethods.fields.store')],
                ['data' => 'method_type', 'title' => lang('ShippingMethods.fields.method_type')],
                ['data' => 'fee', 'title' => lang('ShippingMethods.list.fee'), 'orderable' => false],
                ['data' => 'is_active', 'title' => lang('ShippingMethods.fields.is_active'), 'type' => 'badge', 'badges' => [
                    self::ACTIVE   => ['label' => lang('ShippingMethods.enum.is_active')[self::ACTIVE], 'class' => 'bg-success'],
                    self::INACTIVE => ['label' => lang('ShippingMethods.enum.is_active')[self::INACTIVE], 'class' => 'bg-secondary'],
                ]],
                ['data' => 'sort_order', 'title' => lang('ShippingMethods.fields.sort_order'), 'type' => 'number', 'decimals' => 0, 'class' => 'text-end', 'searchable' => false],
            ],
            'order'    => [['sort_order', 'asc'], ['method_name', 'asc']],
        ];
    }

    // ---------------------------------------------------------------- texts

    /**
     * A method's fee in a few words for the list: free, by subtotal or by weight, with free shipping from an amount
     */
    private static function feeSummary(array $method, string $currency): string
    {
        $rates = (array) ($method['rates'] ?? []);
        $text  = [] === $rates
            ? lang('ShippingMethods.summary.free')
            : lang(self::BASIS_WEIGHT === ($method['fee_basis'] ?? '') ? 'ShippingMethods.summary.by-weight' : 'ShippingMethods.summary.by-subtotal', [count($rates)]);
        if ([] !== $rates && null !== ($method['free_over_amount'] ?? null)) {
            $text .= ' · ' . lang('ShippingMethods.summary.free-from', [self::money($method['free_over_amount'], $currency)]);
        }
        return $text;
    }

    /**
     * A bracket as it reads: "0.00 – 500.00 THB: 60.00 THB", "1,000 g and more: 50.00 THB + 15.00 THB per 1,000 g"
     * @param array  $rate     range_from, range_to, fee_amount, step_size, step_fee, as the API sends them
     * @param string $basis    P or W
     * @param string $currency
     */
    private static function rateText(array $rate, string $basis, string $currency): string
    {
        $range = static fn ($v) => self::BASIS_WEIGHT === $basis ? number_format((float) $v) . ' g' : self::money($v, $currency);
        $text  = null === ($rate['range_to'] ?? null)
            ? lang('ShippingMethods.rates.from-up', [$range($rate['range_from'] ?? 0)])
            : lang('ShippingMethods.rates.from-to', [$range($rate['range_from'] ?? 0), $range($rate['range_to'])]);
        $fee = self::money($rate['fee_amount'] ?? 0, $currency);
        if (null !== ($rate['step_size'] ?? null) && null !== ($rate['step_fee'] ?? null)) {
            $fee = lang('ShippingMethods.rates.fee-step', [$fee, self::money($rate['step_fee'], $currency), $range($rate['step_size'])]);
        }
        return $text . ': ' . $fee;
    }

    /**
     * An amount with 2 decimals and its currency code
     */
    private static function money(mixed $amount, string $currency): string
    {
        return trim(number_format((float) $amount, 2) . ' ' . $currency);
    }

    /**
     * The session business's currency code, which its prices and fees are in
     */
    private static function currency(): string
    {
        $business = session('business');
        return is_array($business) ? (string) ($business['business']['currency_code'] ?? '') : '';
    }

    // ---------------------------------------------------------------- loading

    /**
     * Every shipping method of the session business, from v1/client/shipping-methods
     * @return list<array>
     * @throws RuntimeException when they cannot be loaded
     */
    private function loadMethods(): array
    {
        [$code, $body] = $this->callApi('v1/client/shipping-methods', ['business_slug' => (string) currentBusinessSlug()]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            throw new RuntimeException((string) ($body['message'] ?? lang('ShippingMethods.list.load-failed')));
        }
        return (array) ($body['data']['shipping_methods'] ?? []);
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
     * The label of an enum value; the value itself for one this app has no label for yet
     */
    private static function label(string $group, string $code): string
    {
        $labels = (array) lang('ShippingMethods.enum.' . $group);
        return (string) ($labels[$code] ?? $code);
    }

    /**
     * Form response of a failed create or edit: the API names the errors translations.<language>.<column> and
     * rates.<i>.<column>, renamed for the form; a bracket's error says which bracket and column, as the brackets
     * editor has no error slot of its own
     */
    private function methodFailed(int $code, array $body): ResponseInterface
    {
        $columns = (array) lang('ShippingMethods.rates.columns');
        $errors  = [];
        foreach ((array) ($body['errors'] ?? []) as $key => $message) {
            $key = (string) $key;
            if (preg_match('/^translations\.([^.]+)(?:\.(method_name|description))?$/', $key, $m)) {
                $key = ('description' === ($m[2] ?? '') ? self::DESCRIPTION_PREFIX : self::NAME_PREFIX) . $m[1];
            } elseif (preg_match('/^rates\.(\d+)(?:\.(\w+))?$/', $key, $m)) {
                $where   = lang('ShippingMethods.rates.bracket', [(int) $m[1] + 1]) . (isset($m[2]) ? ' (' . ($columns[$m[2]] ?? $m[2]) . ')' : '');
                $message = $where . ': ' . $message;
            }
            $errors[$key] = $message;
        }
        return $this->response->setStatusCode($code === 200 ? 422 : $code)->setJSON([
            'success'   => false,
            'message'   => $body['message'] ?? lang('ShippingMethods.form.failed'),
            'errors'    => $errors ?: null,
            'csrf_hash' => csrf_hash(),
        ]);
    }
}
