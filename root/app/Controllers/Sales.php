<?php

namespace App\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * The orders the session business's customers placed on the mart, from LINK_INTERNAL_API v1/client/order*: anyone
 * working in the business sees them, changes their order and fulfillment statuses and records their payments, as the
 * API allows; only its owners and managers record refunds. The financial status is never set here: the API works it
 * out from the payments and refunds recorded. Anyone may cancel an order before any of it is sent or starts (giving a
 * reason), or mark one whose booked service started a no-show; the owners and managers then refund what is due.
 */
class Sales extends BaseController
{
    public const ORDER_STATUSES       = ['open', 'in_progress', 'closed'];
    public const FINANCIAL_STATUSES   = ['pending', 'partially_paid', 'paid', 'partially_refunded', 'full_refunded'];
    public const FULFILLMENT_STATUSES = ['open', 'partially_fulfilled', 'fully_fulfilled'];

    /** Badge classes of each status; fulfillment "none" is an order with nothing to send */
    public const BADGES = [
        'order_status'       => ['open' => 'bg-primary', 'in_progress' => 'bg-info text-dark', 'closed' => 'bg-secondary',
            'cancelled' => 'bg-danger', 'no_show' => 'bg-dark'],
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
            } elseif ('needs_refund' === $filter['def']['name']) {
                $filters['needs_refund'] = 'Y' === (string) $filter['value'];
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
                // a cancelled order or a no-show is closed; the list says which
                'order_status'       => ! empty($order['cancelled_at']) ? 'cancelled' : (! empty($order['no_show_at']) ? 'no_show' : (string) ($order['order_status'] ?? '')),
                'refund_due'         => ! empty($order['cancelled_at']) && (float) ($order['refund_due_amount'] ?? 0) > 0
                    ? '<span class="badge bg-warning text-dark">' . esc(lang('Sales.list.refund-due', [formatMoney($order['refund_due_amount'], (string) ($order['currency_code'] ?? ''))])) . '</span>' : '',
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
     * A new order taken at the counter (a walk-in, a call-in): the page is run by scripts/sales_new_order through
     * counter(), which reaches LINK_INTERNAL_API v1/client/counter/*
     */
    public function newOrder(): string|RedirectResponse
    {
        if (null === currentBusinessSlug()) {
            return redirect()->to(base_url('admin/dashboard'));
        }
        return view('sales/new_order', [
            'locale'      => $this->request->getLocale(),
            'slug'        => 'sales',
            'page_title'  => esc(lang('Sales.new.title')),
            'breadcrumbs' => [base_url('admin/sales') => lang('System.page.sales')],
            'user'        => (array) session('user'),
        ]);
    }

    /**
     * POST admin/sales/counter/{stores|catalog|slots|sessions|quote|order}: the page's JSON body sent on to
     * LINK_INTERNAL_API v1/client/counter/<what> for the session business, and the API's answer as it is
     * ({ status, message, data, errors }), with its HTTP status and the new CSRF hash
     */
    public function counter(string $what): ResponseInterface
    {
        $body = $this->request->getJSON(true);
        $body = is_array($body) ? $body : [];
        [$code, $answer] = $this->callApi('v1/client/counter/' . $what, ['business_slug' => (string) currentBusinessSlug()] + $body);
        return $this->response->setStatusCode($code)->setJSON((array) $answer + ['csrf_hash' => csrf_hash()]);
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
     * Cancel the order through LINK_INTERNAL_API v1/client/order/cancel { public_id, reason, restock }
     */
    public function cancel(): ResponseInterface
    {
        return $this->relay('v1/client/order/cancel', [
            'public_id' => $this->input('public_id'),
            'reason'    => $this->input('reason'),
            'restock'   => ! in_array($this->input('restock'), ['0', 'N', 'false'], true),
        ]);
    }

    /**
     * Mark the order a no-show through LINK_INTERNAL_API v1/client/order/no-show { public_id }
     */
    public function noShow(): ResponseInterface
    {
        return $this->relay('v1/client/order/no-show', ['public_id' => $this->input('public_id')]);
    }

    /**
     * Settle a cancelled order's refund without refunding the rest through LINK_INTERNAL_API
     * v1/client/order/refund-close { public_id, reason }
     */
    public function closeRefund(): ResponseInterface
    {
        return $this->relay('v1/client/order/refund-close', ['public_id' => $this->input('public_id'), 'reason' => $this->input('reason')]);
    }

    /**
     * The free times an appointment of the order can move to, from LINK_INTERNAL_API v1/client/order/reschedule/slots
     * { public_id, line_number, date }: { success, data: {date, until, staff, slots}, message, csrf_hash }
     */
    public function rescheduleSlots(): ResponseInterface
    {
        [$code, $body] = $this->callApi('v1/client/order/reschedule/slots', [
            'public_id'   => $this->input('public_id'),
            'line_number' => (int) $this->input('line_number'),
            'date'        => $this->input('date'),
        ]);
        $ok = $code === 200 && ($body['status'] ?? '') === 'success';
        return $this->response->setStatusCode($ok ? 200 : ($code === 200 ? 502 : $code))->setJSON([
            'success'   => $ok,
            'data'      => $ok ? (array) ($body['data'] ?? []) : null,
            'message'   => $body['message'] ?? ($ok ? '' : lang('Sales.order.save-failed')),
            'csrf_hash' => csrf_hash(),
        ]);
    }

    /**
     * Move an appointment through LINK_INTERNAL_API v1/client/order/reschedule { public_id, line_number, starts_at, staff }
     */
    public function reschedule(): ResponseInterface
    {
        $staff = $this->request->getJsonVar('staff');
        return $this->relay('v1/client/order/reschedule', [
            'public_id'   => $this->input('public_id'),
            'line_number' => (int) $this->input('line_number'),
            'starts_at'   => $this->input('starts_at'),
            'staff'       => is_array($staff) ? array_values(array_filter($staff, 'is_string')) : [],
        ]);
    }

    /**
     * The slip a customer sent with a payment, from LINK_INTERNAL_API v1/client/order/payment-slip/{public_id}: shown
     * as it is (an image or a PDF), never cached
     * @param string $publicId order_payment.public_id
     */
    public function paymentSlip(string $publicId): ResponseInterface
    {
        if (! preg_match('/^[0-9A-Za-z_-]{12}$/', $publicId)) {
            throw PageNotFoundException::forPageNotFound(lang('Sales.order.no-slip'));
        }
        [$code, $body, $type] = $this->fetchApiPage('v1/client/order/payment-slip/' . $publicId);
        $type = strtolower(trim(explode(';', (string) $type)[0]));
        if (200 !== $code || ! in_array($type, ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'], true)) {
            throw PageNotFoundException::forPageNotFound(lang('Sales.order.no-slip'));
        }
        return $this->response
            ->removeHeader('Cache-Control')
            ->setHeader('Cache-Control', 'private, no-store')
            ->setHeader('Content-Disposition', 'inline')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setHeader('Content-Security-Policy', "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; object-src 'self'")
            ->setContentType($type, '')
            ->setBody($body);
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
            'urls'       => ['data' => base_url('admin/sales/data'), 'edit' => base_url('admin/sales/order/{public_id}'), 'create' => base_url('admin/sales/new')],
            'labels'     => ['add' => lang('Sales.list.new-order'), 'edit' => lang('Sales.list.view'), 'reset' => lang('Sales.list.reset'), 'all' => lang('Sales.list.all')],
            'messages'   => ['loadFailed' => lang('Sales.list.load-failed')],
            'columns'    => [
                $column('order_number', ['class' => 'text-nowrap fw-semibold']),
                $column('created_at', ['type' => 'datetime', 'class' => 'text-nowrap']),
                $column('customer', ['type' => 'html']),
                $column('line_count', ['class' => 'text-end']),
                $column('total_amount', ['class' => 'text-end text-nowrap']),
                $column('order_status', ['type' => 'badge', 'badges' => $badges('order_status', [...self::ORDER_STATUSES, 'cancelled', 'no_show'])]),
                $column('financial_status', ['type' => 'badge', 'badges' => $badges('financial_status', self::FINANCIAL_STATUSES)]),
                $column('fulfillment_status', ['type' => 'badge', 'badges' => $badges('fulfillment_status', [...self::FULFILLMENT_STATUSES, 'none'])]),
                $column('refund_due', ['type' => 'html', 'class' => 'text-nowrap']),
            ],
            'filters'    => [
                ['name' => 'search', 'type' => 'search', 'label' => lang('Sales.filter.search'), 'placeholder' => lang('Sales.filter.search-placeholder'), 'col' => 4],
                ['name' => 'date_from', 'type' => 'date', 'label' => lang('Sales.filter.date_from'), 'col' => 2],
                ['name' => 'date_to', 'type' => 'date', 'label' => lang('Sales.filter.date_to'), 'col' => 2],
                ['name' => 'order_status', 'type' => 'select', 'label' => lang('Sales.filter.order_status'), 'options' => $options('order_status', self::ORDER_STATUSES), 'col' => 2],
                ['name' => 'financial_status', 'type' => 'select', 'label' => lang('Sales.filter.financial_status'), 'options' => $options('financial_status', self::FINANCIAL_STATUSES), 'col' => 2],
                ['name' => 'fulfillment_status', 'type' => 'select', 'label' => lang('Sales.filter.fulfillment_status'),
                    'options' => $options('fulfillment_status', [...self::FULFILLMENT_STATUSES, 'none']), 'col' => 2],
                ['name' => 'needs_refund', 'type' => 'select', 'label' => lang('Sales.filter.needs_refund'), 'options' => ['Y' => lang('Sales.filter.needs-refund-yes')], 'col' => 2],
            ],
            'order'      => [],
            'dt_options' => ['searching' => false, 'ordering' => false],
        ];
    }
}
