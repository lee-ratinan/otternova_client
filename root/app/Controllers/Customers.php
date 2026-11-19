<?php

namespace App\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * The customers of the session business, from LINK_INTERNAL_API v1/client/customer*: anyone working in the business
 * sees them, read only, as the customers gave their details on their orders, and writes notes about them. A note is
 * deleted by whoever wrote it, or by an owner or manager, as the API allows.
 */
class Customers extends BaseController
{
    /**
     * The customers (generic_list), the one who ordered last first, searched by the API
     */
    public function index(): string|RedirectResponse
    {
        if (null === currentBusinessSlug()) {
            return redirect()->to(base_url('admin/dashboard'));
        }
        return view('generic_list', [
            'locale'     => $this->request->getLocale(),
            'slug'       => 'customers',
            'user'       => (array) session('user'),
            'listConfig' => $this->listConfig(),
        ]);
    }

    /**
     * DataTables data of index(): one page from LINK_INTERNAL_API v1/client/customers { business_slug, start, length,
     * search }, which searches and pages them itself
     */
    public function data(): ResponseInterface
    {
        $config = $this->listConfig();
        $params = lb_request($this->request, $config);
        $search = '';
        foreach ($params['filters'] as $filter) {
            if ('search' === $filter['def']['name']) {
                $search = trim((string) $filter['value']);
            }
        }
        [$code, $body] = $this->callApi('v1/client/customers', [
            'business_slug' => (string) currentBusinessSlug(),
            'start'         => $params['start'],
            'length'        => $params['length'],
            'search'        => $search,
        ]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->response->setJSON(lb_response($params['draw'], 0, 0, []) + [
                'error'     => $body['message'] ?? lang('Customers.list.load-failed'),
                'csrf_hash' => csrf_hash(),
            ]);
        }

        $rows = [];
        foreach ((array) ($body['data']['rows'] ?? []) as $customer) {
            $contact = array_filter([(string) ($customer['email_address'] ?? ''), (string) ($customer['telephone_number'] ?? '')]);
            $noShows = (int) ($customer['no_show_count'] ?? 0);
            $notes   = (int) ($customer['note_count'] ?? 0);
            $rows[]  = [
                'public_id'      => (string) ($customer['public_id'] ?? ''),
                'customer'       => '<div class="fw-semibold">' . esc((string) ($customer['customer_name'] ?? '')) . '</div>'
                    . ($contact ? '<div class="small text-muted">' . esc(implode(' · ', $contact)) . '</div>' : ''),
                'order_count'    => (int) ($customer['order_count'] ?? 0),
                'net_paid'       => formatMoney($customer['net_paid'] ?? null, (string) ($customer['currency_code'] ?? '')),
                'last_order_at'  => (string) ($customer['last_order_at'] ?? ''),
                'flags'          => ($noShows > 0 ? '<span class="badge bg-dark me-1">' . esc(lang('Customers.list.no-shows', [$noShows])) . '</span>' : '')
                    . ($notes > 0 ? '<span class="badge bg-light text-dark border"><i class="bi bi-sticky"></i> ' . $notes . '</span>' : ''),
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
     * One customer: their details and orders with the business, read only, and the notes about them, from
     * LINK_INTERNAL_API v1/client/customer { business_slug, public_id }
     * @param string $publicId customer_master.public_id
     */
    public function customer(string $publicId): string|RedirectResponse
    {
        if (null === currentBusinessSlug()) {
            return redirect()->to(base_url('admin/dashboard'));
        }
        [$code, $body] = $this->callApi('v1/client/customer', ['business_slug' => (string) currentBusinessSlug(), 'public_id' => $publicId]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            throw PageNotFoundException::forPageNotFound(lang('Customers.view.not-found'));
        }
        $data     = (array) $body['data'];
        $customer = (array) ($data['customer'] ?? []);
        return view('customers/view', [
            'locale'      => $this->request->getLocale(),
            'slug'        => 'customers',
            'page_title'  => esc((string) ($customer['customer_name'] ?? '')),
            'breadcrumbs' => [base_url('admin/customers') => lang('System.page.customers')],
            'user'        => (array) session('user'),
            'customer'    => $customer,
            'orders'      => (array) ($data['orders'] ?? []),
            'notes'       => (array) ($data['notes'] ?? []),
            'badges'      => Sales::BADGES,
        ]);
    }

    /**
     * Add a note through LINK_INTERNAL_API v1/client/customer-note/create { business_slug, customer_public_id, note_text }
     */
    public function addNote(): ResponseInterface
    {
        return $this->relay('v1/client/customer-note/create', [
            'business_slug'      => (string) currentBusinessSlug(),
            'customer_public_id' => $this->input('customer_public_id'),
            'note_text'          => $this->input('note_text'),
        ]);
    }

    /**
     * Delete a note through LINK_INTERNAL_API v1/client/customer-note/delete { public_id }
     */
    public function deleteNote(): ResponseInterface
    {
        return $this->relay('v1/client/customer-note/delete', ['public_id' => $this->input('public_id')]);
    }

    /**
     * A field of the JSON body or the form, as a trimmed string
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
            'message'   => $body['message'] ?? ($ok ? '' : lang('Customers.notes.save-failed')),
            'errors'    => (array) ($body['errors'] ?? []),
            'csrf_hash' => csrf_hash(),
        ]);
    }

    /**
     * Table Builder config of index(): the API searches and orders the customers, so DataTables' own search box and
     * column ordering are off; the search box is one of the filters. No Add button: customers come from their orders.
     */
    private function listConfig(): array
    {
        $column = static fn (string $data, array $more = []) => ['data' => $data, 'title' => lang('Customers.column.' . $data),
            'orderable' => false, 'searchable' => false] + $more;
        return [
            'table_id'   => 'customers-table',
            'title'      => lang('Customers.list.title'),
            'subtitle'   => lang('Customers.list.subtitle', [(string) currentBusinessName()]),
            'id_field'   => 'public_id',
            'urls'       => ['data' => base_url('admin/customers/data'), 'edit' => base_url('admin/customers/view/{public_id}')],
            'labels'     => ['edit' => lang('Customers.list.view'), 'reset' => lang('Customers.list.reset'), 'all' => lang('Customers.list.all')],
            'messages'   => ['loadFailed' => lang('Customers.list.load-failed')],
            'columns'    => [
                $column('customer', ['type' => 'html']),
                $column('order_count', ['class' => 'text-end']),
                $column('net_paid', ['class' => 'text-end text-nowrap']),
                $column('last_order_at', ['type' => 'datetime', 'class' => 'text-nowrap']),
                $column('flags', ['type' => 'html', 'class' => 'text-nowrap']),
            ],
            'filters'    => [
                ['name' => 'search', 'type' => 'search', 'label' => lang('Customers.filter.search'), 'placeholder' => lang('Customers.filter.search-placeholder'), 'col' => 6],
            ],
            'order'      => [],
            'dt_options' => ['searching' => false, 'ordering' => false],
        ];
    }
}
