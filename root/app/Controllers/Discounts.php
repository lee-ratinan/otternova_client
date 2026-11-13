<?php

namespace App\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use RuntimeException;

/**
 * The discount codes the customers of the session business can use on the mart, from LINK_INTERNAL_API
 * v1/client/discount*: its owners and managers only (see BusinessManagerFilter), as the API allows. Nothing is
 * deleted: a discount is made inactive instead, as the orders placed with it refer to it.
 *
 * The API keeps valid_from and expires_at in UTC. The form shows and takes them in the viewer's local time:
 * scripts/discount_form turns the saved UTC values into local ones, and sends the browser's timezone as the
 * "timezone" field, by which save() turns them back into UTC.
 */
class Discounts extends BaseController
{
    private const ACTIVE   = 'Y';
    private const INACTIVE = 'N';

    private const TYPE_PERCENT = 'P';
    private const TYPE_FIXED   = 'F';

    /** The fields of the form that go to the API as "discount" as they are (trimmed) */
    private const DISCOUNT_FIELDS = ['discount_code', 'description', 'discount_type', 'discount_value', 'applies_to', 'min_subtotal', 'max_redemptions', 'is_active'];

    /** The fields of the form in local time, sent to the API in UTC */
    private const DATETIME_FIELDS = ['valid_from', 'expires_at'];

    /** The form field with the browser's IANA timezone, set by scripts/discount_form */
    private const TIMEZONE_FIELD = 'timezone';

    /** Longest code and description the API takes */
    private const CODE_MAX        = 30;
    private const DESCRIPTION_MAX = 255;

    /** The status badges of the list, by what status() says */
    private const STATUS_CLASSES = ['active' => 'bg-success', 'scheduled' => 'bg-info', 'expired' => 'bg-secondary', 'used-up' => 'bg-warning text-dark', 'inactive' => 'bg-secondary'];

    /**
     * The discounts (generic_list), by code; each row opens one
     */
    public function index(): string
    {
        return view('generic_list', [
            'locale'      => $this->request->getLocale(),
            'slug'        => 'discounts',
            'breadcrumbs' => [base_url('admin/business') => lang('System.page.business')],
            'user'        => (array) session('user'),
            'listConfig'  => $this->listConfig(),
        ]);
    }

    /**
     * DataTables data of index(). A business has a few dozen codes at most, so the API sends them all and the
     * search, order and paging are done here.
     */
    public function data(): ResponseInterface
    {
        $config = $this->listConfig();
        $params = lb_request($this->request, $config);
        [$code, $body] = $this->callApi('v1/client/discounts', ['business_slug' => (string) currentBusinessSlug()]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->response->setJSON(lb_response($params['draw'], 0, 0, [])
                + ['error' => (string) ($body['message'] ?? lang('Discounts.list.load-failed')), 'csrf_hash' => csrf_hash()]);
        }

        $currency = (string) ($body['data']['currency_code'] ?? self::currency());
        $applies  = lang('Discounts.enum.applies_to');
        $now      = gmdate('Y-m-d H:i:s');
        $rows     = [];
        foreach ((array) ($body['data']['discounts'] ?? []) as $discount) {
            $discount = (array) $discount;
            $max      = $discount['max_redemptions'] ?? null;
            $rows[]   = [
                'public_id'      => (string) ($discount['public_id'] ?? ''),
                'discount_code'  => (string) ($discount['discount_code'] ?? ''),
                'description'    => (string) ($discount['description'] ?? ''),
                'discount_value' => self::valueText($discount, $currency),
                'applies_to'     => (string) ($applies[$discount['applies_to'] ?? ''] ?? ($discount['applies_to'] ?? '')),
                'usage'          => (int) ($discount['redemption_count'] ?? 0) . (null === $max ? '' : ' / ' . (int) $max),
                'expires_at'     => (string) ($discount['expires_at'] ?? ''),
                'status'         => self::status($discount, $now),
            ];
        }
        $total    = count($rows);
        $statuses = lang('Discounts.enum.status');
        $rows     = $this->searchAndOrder($rows, $params, static fn (array $row, string $column) => 'status' === $column
            ? (string) ($statuses[$row[$column]] ?? $row[$column])
            : (string) $row[$column]);
        $filtered = count($rows);
        $rows     = lb_rows(array_slice($rows, $params['start'], $params['length']), $config);

        return $this->response->setJSON(lb_response($params['draw'], $total, $filtered, $rows) + ['csrf_hash' => csrf_hash()]);
    }

    /**
     * The form to add a discount, or, with $publicId, one opened in view mode
     * @param string|null $publicId business_discount.public_id
     */
    public function form(?string $publicId = null): string
    {
        $values   = [];
        $currency = self::currency();
        if (null !== $publicId) {
            [$discounts, $currency] = $this->loadDiscounts();
            $discount = self::find($discounts, $publicId);
            if (null === $discount) {
                throw PageNotFoundException::forPageNotFound(lang('Discounts.form.not-found'));
            }
            $values = array_intersect_key($discount, array_flip([...self::DISCOUNT_FIELDS, ...self::DATETIME_FIELDS, 'redemption_count'])) + [
                'public_id' => (string) $discount['public_id'],
            ];
        }

        $formId = 'discount-form';
        return view('generic_form', [
            'locale'       => $this->request->getLocale(),
            'slug'         => 'discounts',
            'page_title'   => $values['discount_code'] ?? lang('System.page.discount-create'),
            'breadcrumbs'  => [
                base_url('admin/business')           => lang('System.page.business'),
                base_url('admin/business/discounts') => lang('System.page.discounts'),
            ],
            'user'         => (array) session('user'),
            'formConfig'   => [
                'form_id'  => $formId,
                'title'    => lang(null === $publicId ? 'Discounts.form.create-title' : 'Discounts.form.edit-title'),
                'subtitle' => lang('Discounts.form.subtitle', [(string) currentBusinessName()]),
                'mode'     => null === $publicId ? 'create' : 'view',
                'id_field' => 'public_id',
                'id'       => (string) $publicId,
                'locale'   => $this->request->getLocale(),
                'urls'     => [
                    'submit' => base_url('admin/business/discounts/save'),
                    'edit'   => null === $publicId ? '' : base_url('admin/business/discounts/edit/' . rawurlencode($publicId)),
                    'list'   => base_url('admin/business/discounts'),
                ],
                'messages' => ['saved' => lang(null === $publicId ? 'Discounts.form.created' : 'Discounts.form.saved'),
                    'saveFailed' => lang('Discounts.form.failed')],
                'values'   => $values,
                'fields'   => $this->fields($currency, null !== $publicId),
            ],
            'customScript' => view('scripts/discount_form', [
                'formId'        => $formId,
                'timezoneField' => self::TIMEZONE_FIELD,
                'utc'           => array_intersect_key($values, array_flip(self::DATETIME_FIELDS)),
                'datetimes'     => self::DATETIME_FIELDS,
            ]),
        ]);
    }

    /**
     * Create or save a discount through LINK_INTERNAL_API: v1/client/discount/create { business_slug, discount }, or,
     * with public_id, v1/client/discount/edit { public_id, discount }. Empty optional fields are sent as null; the
     * local datetimes are sent in UTC, by the form's timezone field (UTC when it is missing or unknown).
     */
    public function save(): ResponseInterface
    {
        $post     = (array) $this->request->getPost();
        $publicId = trim((string) ($post['public_id'] ?? ''));
        $discount = [];
        foreach (self::DISCOUNT_FIELDS as $field) {
            $value            = trim((string) ($post[$field] ?? ''));
            $discount[$field] = '' === $value && in_array($field, ['description', 'min_subtotal', 'max_redemptions'], true) ? null : $value;
        }
        $discount['discount_code'] = strtoupper($discount['discount_code']);
        $timezone                  = self::timezone((string) ($post[self::TIMEZONE_FIELD] ?? ''));
        foreach (self::DATETIME_FIELDS as $field) {
            $discount[$field] = self::toUtc(trim((string) ($post[$field] ?? '')), $timezone);
        }

        [$code, $body] = '' === $publicId
            ? $this->callApi('v1/client/discount/create', ['business_slug' => (string) currentBusinessSlug(), 'discount' => $discount])
            : $this->callApi('v1/client/discount/edit', ['public_id' => $publicId, 'discount' => $discount]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->saveFailed($code, $body, lang('Discounts.form.failed'));
        }
        $savedId = (string) ($body['data']['public_id'] ?? $publicId);
        return $this->response->setJSON([
            'success'   => true,
            'message'   => $body['message'] ?? lang('' === $publicId ? 'Discounts.form.created' : 'Discounts.form.saved'),
            'redirect'  => base_url('admin/business/discounts/edit/' . rawurlencode($savedId)),
            'csrf_hash' => csrf_hash(),
        ]);
    }

    // ---------------------------------------------------------------- configs

    /**
     * Fields of the form
     * @param string $currency the business's currency code
     * @param bool   $saved    an existing discount: how often it was used is shown
     */
    private function fields(string $currency, bool $saved): array
    {
        $types    = lang('Discounts.enum.discount_type');
        $applies  = lang('Discounts.enum.applies_to');
        $statuses = lang('Discounts.enum.is_active');

        $fields = [
            ['type' => 'heading', 'label' => lang('Discounts.heading.discount')],
            ['name' => 'discount_code', 'type' => 'text', 'label' => lang('Discounts.fields.discount_code'),
                'rules' => 'required|min_length[3]|max_length[' . self::CODE_MAX . ']|regex_match[/^[A-Za-z0-9][A-Za-z0-9_-]*$/]',
                'attrs' => ['autocomplete' => 'off', 'style' => 'text-transform: uppercase', 'spellcheck' => 'false'],
                'help' => lang('Discounts.help.discount-code')],
            ['name' => 'description', 'type' => 'text', 'label' => lang('Discounts.fields.description'), 'optional' => true,
                'rules' => 'max_length[' . self::DESCRIPTION_MAX . ']', 'help' => lang('Discounts.help.description')],
            ['name' => 'discount_type', 'type' => 'select', 'label' => lang('Discounts.fields.discount_type'), 'options' => $types,
                'default' => self::TYPE_PERCENT, 'rules' => 'required|in_list[' . self::TYPE_PERCENT . ',' . self::TYPE_FIXED . ']'],
            ['name' => 'discount_value', 'type' => 'number', 'label' => lang('Discounts.fields.discount_value'),
                'rules' => 'required|decimal|greater_than[0]', 'attrs' => ['min' => 0.01, 'step' => 0.01],
                'help' => lang('Discounts.help.discount-value', [$currency])],
            ['name' => 'applies_to', 'type' => 'select', 'label' => lang('Discounts.fields.applies_to'), 'options' => $applies,
                'default' => 'A', 'rules' => 'required|in_list[A,P,S]', 'help' => lang('Discounts.help.applies-to')],
            ['name' => 'is_active', 'type' => 'select', 'label' => lang('Discounts.fields.is_active'), 'options' => $statuses,
                'default' => self::ACTIVE, 'rules' => 'required|in_list[' . self::ACTIVE . ',' . self::INACTIVE . ']', 'help' => lang('Discounts.help.is-active')],
            ['type' => 'heading', 'label' => lang('Discounts.heading.limits')],
            ['name' => 'min_subtotal', 'type' => 'number', 'label' => lang('Discounts.fields.min_subtotal', [$currency]), 'optional' => true,
                'rules' => 'permit_empty|decimal|greater_than_equal_to[0]', 'attrs' => ['min' => 0, 'step' => 0.01],
                'view' => static fn ($v) => '' === (string) $v ? lang('Discounts.no-minimum') : self::money($v, $currency),
                'help' => lang('Discounts.help.min-subtotal')],
            ['name' => 'max_redemptions', 'type' => 'number', 'label' => lang('Discounts.fields.max_redemptions'), 'optional' => true,
                'rules' => 'permit_empty|is_natural_no_zero', 'attrs' => ['min' => 1, 'step' => 1],
                'view' => static fn ($v) => '' === (string) $v ? lang('Discounts.no-limit') : (string) $v,
                'help' => lang('Discounts.help.max-redemptions')],
        ];
        if ($saved) {
            $fields[] = ['name' => 'redemption_count', 'type' => 'number', 'label' => lang('Discounts.fields.redemption_count'), 'show_in' => ['view']];
        }
        // in view mode, scripts/discount_form shows them in the viewer's local time
        $fields[] = ['name' => 'valid_from', 'type' => 'datetime-local', 'label' => lang('Discounts.fields.valid_from'), 'optional' => true,
            'view' => static fn ($v) => '' === (string) $v ? lang('Discounts.always') : $v . ' UTC', 'help' => lang('Discounts.help.valid-from')];
        $fields[] = ['name' => 'expires_at', 'type' => 'datetime-local', 'label' => lang('Discounts.fields.expires_at'), 'optional' => true,
            'view' => static fn ($v) => '' === (string) $v ? lang('Discounts.never') : $v . ' UTC', 'help' => lang('Discounts.help.expires-at')];
        $fields[] = ['name' => self::TIMEZONE_FIELD, 'type' => 'hidden', 'default' => 'UTC'];

        return $fields;
    }

    /**
     * Table Builder config of index()
     */
    private function listConfig(): array
    {
        $statuses = lang('Discounts.enum.status');
        $badges   = [];
        foreach (self::STATUS_CLASSES as $status => $class) {
            $badges[$status] = ['label' => $statuses[$status], 'class' => $class];
        }
        return [
            'table_id' => 'discounts-table',
            'title'    => lang('Discounts.list.title'),
            'subtitle' => lang('Discounts.list.subtitle', [(string) currentBusinessName()]),
            'id_field' => 'public_id',
            'urls'     => [
                'data'   => base_url('admin/business/discounts/data'),
                'create' => base_url('admin/business/discounts/create'),
                'edit'   => base_url('admin/business/discounts/edit/{public_id}'),
            ],
            'labels'   => ['add' => lang('Discounts.list.add')],
            'messages' => ['loadFailed' => lang('Discounts.list.load-failed')],
            'columns'  => [
                ['data' => 'discount_code', 'title' => lang('Discounts.fields.discount_code')],
                ['data' => 'description', 'title' => lang('Discounts.fields.description')],
                ['data' => 'discount_value', 'title' => lang('Discounts.fields.discount_value'), 'class' => 'text-end', 'searchable' => false],
                ['data' => 'applies_to', 'title' => lang('Discounts.fields.applies_to')],
                ['data' => 'usage', 'title' => lang('Discounts.fields.usage'), 'class' => 'text-end', 'searchable' => false],
                ['data' => 'expires_at', 'title' => lang('Discounts.fields.expires_at'), 'type' => 'datetime', 'searchable' => false],
                ['data' => 'status', 'title' => lang('Discounts.fields.status'), 'type' => 'badge', 'badges' => $badges],
            ],
            'order'    => [['discount_code', 'asc']],
        ];
    }

    // ---------------------------------------------------------------- loading

    /**
     * Every discount of the session business and its currency, from v1/client/discounts
     * @return array{0: list<array>, 1: string} [discounts, currency_code]
     * @throws RuntimeException when they cannot be loaded
     */
    private function loadDiscounts(): array
    {
        [$code, $body] = $this->callApi('v1/client/discounts', ['business_slug' => (string) currentBusinessSlug()]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            throw new RuntimeException((string) ($body['message'] ?? lang('Discounts.list.load-failed')));
        }
        return [(array) ($body['data']['discounts'] ?? []), (string) ($body['data']['currency_code'] ?? self::currency())];
    }

    /**
     * The discount with this public_id (the API has no single-discount call)
     */
    private static function find(array $discounts, string $publicId): ?array
    {
        foreach ($discounts as $discount) {
            if ((string) ($discount['public_id'] ?? '') === $publicId) {
                return (array) $discount;
            }
        }
        return null;
    }

    // ---------------------------------------------------------------- formatting

    /**
     * Where a discount stands at $now (UTC): inactive, used-up, expired, scheduled (not valid yet) or active
     */
    private static function status(array $discount, string $now): string
    {
        $max = $discount['max_redemptions'] ?? null;
        return match (true) {
            self::ACTIVE !== ($discount['is_active'] ?? '')                                       => 'inactive',
            null !== $max && (int) ($discount['redemption_count'] ?? 0) >= (int) $max             => 'used-up',
            null !== ($discount['expires_at'] ?? null) && (string) $discount['expires_at'] <= $now => 'expired',
            null !== ($discount['valid_from'] ?? null) && (string) $discount['valid_from'] > $now  => 'scheduled',
            default                                                                               => 'active',
        };
    }

    /**
     * "10%" or "100.00 THB"
     */
    private static function valueText(array $discount, string $currency): string
    {
        $value = (string) ($discount['discount_value'] ?? '');
        return self::TYPE_PERCENT === ($discount['discount_type'] ?? '')
            ? rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.') . '%'
            : self::money($value, $currency);
    }

    private static function money(mixed $amount, string $currency): string
    {
        return trim(number_format((float) $amount, 2) . ' ' . $currency);
    }

    /**
     * The session business's currency code
     */
    private static function currency(): string
    {
        $business = session('business');
        return is_array($business) ? (string) ($business['business']['currency_code'] ?? '') : '';
    }

    /**
     * The timezone the form's datetimes were entered in: the browser's, or UTC when it is not one PHP knows
     */
    private static function timezone(string $name): DateTimeZone
    {
        try {
            return new DateTimeZone('' === $name ? 'UTC' : $name);
        } catch (Exception) {
            return new DateTimeZone('UTC');
        }
    }

    /**
     * A local datetime-local value ("2026-12-01T09:00") in UTC as the API takes it ("2026-12-01 02:00:00"); empty is
     * null, and anything that is not a datetime is sent as it is, for the API to refuse
     */
    private static function toUtc(string $local, DateTimeZone $timezone): ?string
    {
        if ('' === $local) {
            return null;
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}(:\d{2})?$/', $local)) {
            return $local;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i' . (strlen($local) > 16 ? ':s' : ''), str_replace('T', ' ', $local), $timezone);
        return false === $date ? $local : $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
