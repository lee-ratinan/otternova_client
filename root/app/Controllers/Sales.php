<?php

namespace App\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * The orders the session business's customers placed on the mart, from LINK_INTERNAL_API v1/client/order*: anyone
 * working in the business sees them, changes their order and fulfillment statuses and records their payments, as the
 * API allows; only its owners and managers record refunds. The financial status is never set here: the API works it
 * out from the payments and refunds recorded.
 */
class Sales extends BaseController
{
    public const ORDER_STATUSES       = ['open', 'in_progress', 'closed'];
    public const FINANCIAL_STATUSES   = ['pending', 'partially_paid', 'paid', 'partially_refunded', 'full_refunded'];
    public const FULFILLMENT_STATUSES = ['open', 'partially_fulfilled', 'fully_fulfilled'];

    /** Badge classes of each status; fulfillment "none" is an order with nothing to send */
    public const BADGES = [
        'order_status'       => ['open' => 'bg-primary', 'in_progress' => 'bg-info text-dark', 'closed' => 'bg-secondary'],
        'financial_status'   => ['pending' => 'bg-warning text-dark', 'partially_paid' => 'bg-info text-dark', 'paid' => 'bg-success',
            'partially_refunded' => 'bg-secondary', 'full_refunded' => 'bg-dark'],
        'fulfillment_status' => ['open' => 'bg-warning text-dark', 'partially_fulfilled' => 'bg-info text-dark', 'fully_fulfilled' => 'bg-success',
            'none' => 'bg-light text-muted border'],
        'transaction_status' => ['pending' => 'bg-warning text-dark', 'succeeded' => 'bg-success', 'failed' => 'bg-danger', 'voided' => 'bg-secondary'],
    ];

    /**
     * The orders (generic_list), newest first, searched and filtered by the API
     */
    public function index(): string|RedirectResponse
    {
        if (null === currentBusinessSlug()) {
            return redirect()->to(base_url('admin/dashboard'));
        }
        return view('generic_list', [
            'locale'     => $this->request->getLocale(),
            'slug'       => 'sales',
            'user'       => (array) session('user'),
            'listConfig' => $this->listConfig(),
        ]);
    }

    /**
     * DataTables data of index(): one page from LINK_INTERNAL_API v1/client/orders { business_slug, start, length,
     * search, filters }, which searches, filters and pages them itself, newest first
     */
    public function data(): ResponseInterface
    {
        $config  = $this->listConfig();
        $params  = lb_request($this->request, $config);
        $filters = [];
        $search  = '';
        foreach ($params['filters'] as $filter) {
            if ('search' === $filter['def']['name']) {
                $search = trim((string) $filter['value']);
            } else {
                $filters[$filter['def']['name']] = (string) $filter['value'];
            }
        }
        [$code, $body] = $this->callApi('v1/client/orders', [
            'business_slug' => (string) currentBusinessSlug(),
            'start'         => $params['start'],
            'length'        => $params['length'],
            'search'        => $search,
            'filters'       => (object) $filters,
        ]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->response->setJSON(lb_response($params['draw'], 0, 0, []) + [
                'error'     => $body['message'] ?? lang('Sales.list.load-failed'),
                'csrf_hash' => csrf_hash(),
            ]);
        }

        $rows = [];
        foreach ((array) ($body['data']['rows'] ?? []) as $order) {
            $contact = array_filter([(string) ($order['email_address'] ?? ''), (string) ($order['telephone_number'] ?? '')]);
            $rows[]  = [
                'public_id'          => (string) ($order['public_id'] ?? ''),
                'order_number'       => (string) ($order['order_number'] ?? ''),
                'created_at'         => (string) ($order['created_at'] ?? ''),
                'customer'           => '<div>' . esc((string) ($order['customer_name'] ?? '')) . '</div>'
                    . ($contact ? '<div class="small text-muted">' . esc(implode(' · ', $contact)) . '</div>' : ''),
                'line_count'         => (int) ($order['line_count'] ?? 0),
                'total_amount'       => formatMoney($order['total_amount'] ?? null, (string) ($order['currency_code'] ?? '')),
                'order_status'       => (string) ($order['order_status'] ?? ''),
                'financial_status'   => (string) ($order['financial_status'] ?? ''),
                'fulfillment_status' => (string) ($order['fulfillment_status'] ?? 'none'),
            ];
        }
        return $this->response->setJSON(lb_response(
            $params['draw'],
            (int) ($body['data']['records_total'] ?? 0),
            (int) ($body['data']['records_filtered'] ?? 0),
            lb_rows($rows, $config)
        ) + ['csrf_hash' => csrf_hash()]);
    }

    /**
     * One order: its lines, customer, shipping, payments, and the forms that change its statuses and record its
     * payments, from LINK_INTERNAL_API v1/client/order { public_id }
     * @param string $publicId order_master.public_id
     */
    public function order(string $publicId): string|RedirectResponse
    {
        if (null === currentBusinessSlug()) {
            return redirect()->to(base_url('admin/dashboard'));
        }
        [$code, $body] = $this->callApi('v1/client/order', ['public_id' => $publicId]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            throw PageNotFoundException::forPageNotFound(lang('Sales.order.not-found'));
        }
        $data  = (array) $body['data'];
        $order = (array) $data['order'];
        return view('sales/order', [
            'locale'         => $this->request->getLocale(),
            'slug'           => 'sales',
            'page_title'     => esc(lang('Sales.order.title', [(string) ($order['order_number'] ?? '')])),
            'breadcrumbs'    => [base_url('admin/sales') => lang('System.page.sales')],
            'user'           => (array) session('user'),
            'order'          => $order,
            'paymentMethods' => (array) ($data['payment_methods'] ?? []),
            'timezone'       => (string) ($data['timezone'] ?? 'UTC'),
            'canRefund'      => (bool) ($data['can_refund'] ?? false),
            'badges'         => self::BADGES,
        ]);
    }

    /**
     * Change the order and fulfillment statuses through LINK_INTERNAL_API v1/client/order/status
     * { public_id, order_status, fulfillment_status }
     */
    public function saveStatus(): ResponseInterface
    {
        $payload = ['public_id' => $this->input('public_id')];
        foreach (['order_status', 'fulfillment_status'] as $field) {
            if ('' !== $this->input($field)) {
                $payload[$field] = $this->input($field);
            }
        }
        return $this->relay('v1/client/order/status', $payload);
    }

    /**
     * Record a payment or a refund through LINK_INTERNAL_API v1/client/order/payment/create { public_id, payment }
     */
    public function createPayment(): ResponseInterface
    {
        $payment = [];
        foreach (['transaction_type', 'transaction_status', 'amount', 'payment_method_public_id', 'reference', 'note', 'failure_reason'] as $field) {
            if ('' !== $this->input($field)) {
                $payment[$field] = $this->input($field);
            }
        }
        return $this->relay('v1/client/order/payment/create', ['public_id' => $this->input('public_id'), 'payment' => (object) $payment]);
    }

    /**
     * Settle a pending payment or refund through LINK_INTERNAL_API v1/client/order/payment/settle
     * { public_id, transaction_status, failure_reason, reference }
     */
    public function settlePayment(): ResponseInterface
    {
        $payload = ['public_id' => $this->input('public_id'), 'transaction_status' => $this->input('transaction_status')];
        foreach (['failure_reason', 'reference'] as $field) {
            if ('' !== $this->input($field)) {
                $payload[$field] = $this->input($field);
            }
        }
        return $this->relay('v1/client/order/payment/settle', $payload);
    }

    /**
     * A field of the page's JSON body (or form post), trimmed
     */
    private function input(string $name): string
    {
        $value = $this->request->getJsonVar($name) ?? $this->request->getPost($name);
        return is_scalar($value) ? trim((string) $value) : '';
    }

    /**
     * The API's answer for the page's script: { success, message, errors, csrf_hash }, with the API's HTTP status
     */
    private function relay(string $endpoint, array $payload): ResponseInterface
    {
        [$code, $body] = $this->callApi($endpoint, $payload);
        $ok = $code === 200 && ($body['status'] ?? '') === 'success';
        return $this->response->setStatusCode($ok ? 200 : ($code === 200 ? 502 : $code))->setJSON([
            'success'   => $ok,
            'message'   => $body['message'] ?? ($ok ? '' : lang('Sales.order.save-failed')),
            'errors'    => (array) ($body['errors'] ?? []),
            'csrf_hash' => csrf_hash(),
        ]);
    }

    /**
     * Table Builder config of index(): the API searches and orders the orders, so DataTables' own search box and
     * column ordering are off; the search box is one of the filters
     */
    private function listConfig(): array
    {
        $badges = static function (string $field, array $values): array {
            $labels = lang('Sales.enum.' . $field);
            $out    = [];
            foreach ($values as $value) {
                $out[$value] = ['label' => $labels[$value] ?? $value, 'class' => self::BADGES[$field][$value] ?? 'bg-secondary'];
            }
            return $out;
        };
        $column = static fn (string $data, array $more = []) => ['data' => $data, 'title' => lang('Sales.column.' . $data),
            'orderable' => false, 'searchable' => false] + $more;
        $options = static fn (string $field, array $values): array => array_intersect_key(lang('Sales.enum.' . $field), array_flip($values));
        return [
            'table_id'   => 'sales-table',
            'title'      => lang('Sales.list.title'),
            'subtitle'   => lang('Sales.list.subtitle', [(string) currentBusinessName()]),
            'id_field'   => 'public_id',
            'urls'       => ['data' => base_url('admin/sales/data'), 'edit' => base_url('admin/sales/order/{public_id}')],
            'labels'     => ['edit' => lang('Sales.list.view'), 'reset' => lang('Sales.list.reset'), 'all' => lang('Sales.list.all')],
            'messages'   => ['loadFailed' => lang('Sales.list.load-failed')],
            'columns'    => [
                $column('order_number', ['class' => 'text-nowrap fw-semibold']),
                $column('created_at', ['type' => 'datetime', 'class' => 'text-nowrap']),
                $column('customer', ['type' => 'html']),
                $column('line_count', ['class' => 'text-end']),
                $column('total_amount', ['class' => 'text-end text-nowrap']),
                $column('order_status', ['type' => 'badge', 'badges' => $badges('order_status', self::ORDER_STATUSES)]),
                $column('financial_status', ['type' => 'badge', 'badges' => $badges('financial_status', self::FINANCIAL_STATUSES)]),
                $column('fulfillment_status', ['type' => 'badge', 'badges' => $badges('fulfillment_status', [...self::FULFILLMENT_STATUSES, 'none'])]),
            ],
            'filters'    => [
                ['name' => 'search', 'type' => 'search', 'label' => lang('Sales.filter.search'), 'placeholder' => lang('Sales.filter.search-placeholder'), 'col' => 4],
                ['name' => 'date_from', 'type' => 'date', 'label' => lang('Sales.filter.date_from'), 'col' => 2],
                ['name' => 'date_to', 'type' => 'date', 'label' => lang('Sales.filter.date_to'), 'col' => 2],
                ['name' => 'order_status', 'type' => 'select', 'label' => lang('Sales.filter.order_status'), 'options' => $options('order_status', self::ORDER_STATUSES), 'col' => 2],
                ['name' => 'financial_status', 'type' => 'select', 'label' => lang('Sales.filter.financial_status'), 'options' => $options('financial_status', self::FINANCIAL_STATUSES), 'col' => 2],
                ['name' => 'fulfillment_status', 'type' => 'select', 'label' => lang('Sales.filter.fulfillment_status'),
                    'options' => $options('fulfillment_status', [...self::FULFILLMENT_STATUSES, 'none']), 'col' => 2],
            ],
            'order'      => [],
            'dt_options' => ['searching' => false, 'ordering' => false],
        ];
    }
}
