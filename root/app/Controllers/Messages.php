<?php

namespace App\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * The messages customers send the session business through its contact form on otternova_mart, from
 * LINK_INTERNAL_API v1/client/messages* and v1/client/message*: anyone working in the business reads them, marks them
 * resolved (who clicked it is kept), unread again or spam, and comments on them for one another. The customer is
 * answered off the platform, by email or telephone; replying from here is a paid feature to come (can_reply).
 *
 * Unread is shared by the business: the first to open a message reads it for everyone. The header's and the
 * sidebar's badges show how many are unread (scripts/message_badge, through unreadCount()).
 */
class Messages extends BaseController
{
    /** Bootstrap classes of each message_status */
    public const STATUS_BADGES = ['N' => 'bg-danger', 'R' => 'bg-primary', 'D' => 'bg-success', 'S' => 'bg-secondary'];

    /**
     * The messages (generic_list), newest first, spam left out unless asked for, searched by the API
     */
    public function index(): string|RedirectResponse
    {
        if (null === currentBusinessSlug()) {
            return redirect()->to(base_url('admin/dashboard'));
        }
        return view('generic_list', [
            'locale'     => $this->request->getLocale(),
            'slug'       => 'messages',
            'user'       => (array) session('user'),
            'listConfig' => $this->listConfig(),
        ]);
    }

    /**
     * DataTables data of index(): one page from LINK_INTERNAL_API v1/client/messages { business_slug, start, length,
     * search, filters: {status} }
     */
    public function data(): ResponseInterface
    {
        $config  = $this->listConfig();
        $params  = lb_request($this->request, $config);
        $search  = '';
        $filters = [];
        foreach ($params['filters'] as $filter) {
            if ('search' === $filter['def']['name']) {
                $search = trim((string) $filter['value']);
            } elseif ('status' === $filter['def']['name'] && isset(self::STATUS_BADGES[(string) $filter['value']])) {
                $filters['status'] = (string) $filter['value'];
            }
        }
        [$code, $body] = $this->callApi('v1/client/messages', [
            'business_slug' => (string) currentBusinessSlug(),
            'start'         => $params['start'],
            'length'        => $params['length'],
            'search'        => $search,
            'filters'       => $filters,
        ]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            return $this->response->setJSON(lb_response($params['draw'], 0, 0, []) + [
                'error'     => $body['message'] ?? lang('Messages.list.load-failed'),
                'csrf_hash' => csrf_hash(),
            ]);
        }

        $rows = [];
        foreach ((array) ($body['data']['rows'] ?? []) as $message) {
            $unread  = ($message['message_status'] ?? '') === 'N';
            $contact = array_filter([(string) ($message['email_address'] ?? ''), (string) ($message['phone_number'] ?? '')]);
            $rows[]  = [
                'public_id'   => (string) ($message['public_id'] ?? ''),
                'sender'      => '<div class="' . ($unread ? 'fw-bold' : 'fw-semibold') . '">' . esc((string) ($message['sender_name'] ?? '')) . '</div>'
                    . ($contact ? '<div class="small text-muted">' . esc(implode(' · ', $contact)) . '</div>' : ''),
                'excerpt'     => '<div class="' . ($unread ? 'fw-semibold' : 'text-muted') . '">' . esc((string) ($message['excerpt'] ?? '')) . '</div>',
                'created_at'  => (string) ($message['created_at'] ?? ''),
                'status'      => (string) ($message['message_status'] ?? ''),
                'resolved_by' => (string) ($message['resolved_by_name'] ?? ''),
            ];
        }
        return $this->response->setJSON(lb_response(
            $params['draw'],
            (int) ($body['data']['records_total'] ?? 0),
            (int) ($body['data']['records_filtered'] ?? 0),
            lb_rows($rows, $config)
        ) + ['csrf_hash' => csrf_hash(), 'unread_count' => (int) ($body['data']['unread_count'] ?? 0)]);
    }

    /**
     * One message, with its timeline of comments and changes of status, from LINK_INTERNAL_API v1/client/message
     * { business_slug, public_id }, which also marks it read when nobody had opened it yet
     * @param string $publicId business_message.public_id
     */
    public function message(string $publicId): string|RedirectResponse
    {
        if (null === currentBusinessSlug()) {
            return redirect()->to(base_url('admin/dashboard'));
        }
        [$code, $body] = $this->callApi('v1/client/message', ['business_slug' => (string) currentBusinessSlug(), 'public_id' => $publicId]);
        if ($code !== 200 || ($body['status'] ?? '') !== 'success') {
            throw PageNotFoundException::forPageNotFound(lang('Messages.view.not-found'));
        }
        $data    = (array) $body['data'];
        $message = (array) ($data['message'] ?? []);
        return view('messages/view', [
            'locale'        => $this->request->getLocale(),
            'slug'          => 'messages',
            'page_title'    => esc((string) ($message['sender_name'] ?? '')),
            'breadcrumbs'   => [base_url('admin/messages') => lang('System.page.messages')],
            'user'          => (array) session('user'),
            'message'       => $message,
            'timeline'      => (array) ($data['timeline'] ?? []),
            'can_reply'     => (bool) ($data['can_reply'] ?? false),
            'business_name' => (string) currentBusinessName(),
            'badges'        => self::STATUS_BADGES,
        ]);
    }

    /**
     * The unread count of the badges: { success, data: {unread_count} }; 0 without a business
     */
    public function unreadCount(): ResponseInterface
    {
        $slug = currentBusinessSlug();
        if (null === $slug) {
            return $this->response->setJSON(['success' => true, 'data' => ['unread_count' => 0]]);
        }
        [$code, $body] = $this->callApi('v1/client/messages/unread-count', ['business_slug' => $slug]);
        $ok            = $code === 200 && ($body['status'] ?? '') === 'success';
        return $this->response->setStatusCode($ok ? 200 : ($code === 200 ? 502 : $code))->setJSON([
            'success' => $ok,
            'data'    => $ok ? ['unread_count' => (int) ($body['data']['unread_count'] ?? 0)] : null,
        ]);
    }

    /**
     * Change the status through LINK_INTERNAL_API v1/client/message/status { business_slug, public_id, status }:
     * N (unread again), R (reopen), D (resolve) or S (spam)
     */
    public function status(): ResponseInterface
    {
        return $this->relay('v1/client/message/status', ['public_id' => $this->input('public_id'), 'status' => $this->input('status')]);
    }

    /**
     * Add a comment through LINK_INTERNAL_API v1/client/message/comment { business_slug, public_id, text }
     */
    public function addComment(): ResponseInterface
    {
        return $this->relay('v1/client/message/comment', ['public_id' => $this->input('public_id'), 'text' => $this->input('text')]);
    }

    /**
     * Delete one's own comment through LINK_INTERNAL_API v1/client/message/comment/delete
     * { business_slug, public_id, comment_public_id }
     */
    public function deleteComment(): ResponseInterface
    {
        return $this->relay('v1/client/message/comment/delete', [
            'public_id'         => $this->input('public_id'),
            'comment_public_id' => $this->input('comment_public_id'),
        ]);
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
        [$code, $body] = $this->callApi($endpoint, ['business_slug' => (string) currentBusinessSlug()] + $payload);
        $ok = in_array($code, [200, 201], true) && ($body['status'] ?? '') === 'success';
        return $this->response->setStatusCode($ok ? 200 : ($code < 300 ? 502 : $code))->setJSON([
            'success'   => $ok,
            'message'   => $body['message'] ?? ($ok ? '' : lang('Messages.view.save-failed')),
            'errors'    => (array) ($body['errors'] ?? []),
            'csrf_hash' => csrf_hash(),
        ]);
    }

    /**
     * Table Builder config of index(): the API searches, filters and orders the messages, so DataTables' own search box
     * and column ordering are off. No Add button: messages come from the mart.
     */
    private function listConfig(): array
    {
        $column   = static fn (string $data, array $more = []) => ['data' => $data, 'title' => lang('Messages.column.' . $data),
            'orderable' => false, 'searchable' => false] + $more;
        $statuses = (array) lang('Messages.status');
        $badges   = [];
        foreach (self::STATUS_BADGES as $code => $class) {
            $badges[$code] = ['label' => (string) ($statuses[$code] ?? $code), 'class' => $class];
        }
        return [
            'table_id'   => 'messages-table',
            'title'      => lang('Messages.list.title'),
            'subtitle'   => lang('Messages.list.subtitle', [(string) currentBusinessName()]),
            'id_field'   => 'public_id',
            'urls'       => ['data' => base_url('admin/messages/data'), 'edit' => base_url('admin/messages/view/{public_id}')],
            'labels'     => ['edit' => lang('Messages.list.open'), 'reset' => lang('Messages.list.reset'), 'all' => lang('Messages.list.all')],
            'messages'   => ['loadFailed' => lang('Messages.list.load-failed')],
            'columns'    => [
                $column('sender', ['type' => 'html']),
                $column('excerpt', ['type' => 'html']),
                $column('created_at', ['type' => 'datetime', 'class' => 'text-nowrap']),
                $column('status', ['type' => 'badge', 'badges' => $badges, 'class' => 'text-nowrap']),
                $column('resolved_by'),
            ],
            'filters'    => [
                ['name' => 'status', 'type' => 'select', 'label' => lang('Messages.filter.status'), 'options' => $statuses,
                    'attrs' => ['placeholder' => lang('Messages.filter.all-but-spam')], 'col' => 3],
                ['name' => 'search', 'type' => 'search', 'label' => lang('Messages.filter.search'), 'placeholder' => lang('Messages.filter.search-placeholder'), 'col' => 6],
            ],
            'order'      => [],
            'dt_options' => ['searching' => false, 'ordering' => false],
        ];
    }
}
